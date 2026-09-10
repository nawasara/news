<?php

namespace Nawasara\News\Livewire\Article\Section;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\News\Jobs\SyncNewsJob;
use Nawasara\News\Models\Article;
use Nawasara\News\Models\Category;
use Nawasara\News\Models\Source;

/**
 * Daftar artikel — hanya-baca. Sumber datanya WordPress; yang bisa diubah di
 * sini cuma cara melihatnya.
 *
 * Pengaturan sumber TIDAK ada di sini, melainkan di halaman Sumber Berita
 * tersendiri: keduanya punya bentuk daftar sendiri-sendiri dan aksi yang
 * berbeda, jadi menggabungkannya membuat satu komponen mengurus dua hal.
 */
class Table extends Component
{
    use WithPagination;

    /** Saringan memakai #[Url] agar tautan hasil penyaringan dapat dibagikan. */
    #[Url]
    public string $search = '';

    /** @var array<int, int> */
    #[Url]
    public array $categoryId = [];

    /** @var array<int, int> */
    #[Url]
    public array $sourceId = [];

    #[Url]
    public string $sort = 'newest';

    /**
     * Id saja, bukan modelnya. `detail` di bawah adalah #[Computed], sehingga
     * artikel utuh — termasuk `content` yang panjang — tidak pernah ikut
     * dalam snapshot Livewire; ia dibaca ulang di server hanya saat modal
     * benar-benar dirender.
     */
    public ?int $detailId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryId(): void
    {
        $this->resetPage();
    }

    public function updatedSourceId(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    /**
     * Hanya kategori yang benar-benar punya artikel.
     *
     * WordPress mengirim seluruh daftar kategorinya, termasuk yang tidak
     * dipakai satu pun artikel yang tertarik — menawarkannya sebagai saringan
     * hanya menghasilkan hasil kosong.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function categoryOptions(): array
    {
        return Category::query()
            ->whereHas('articles')
            ->orderBy('slug')
            ->get()
            ->mapWithKeys(fn (Category $c) => [$c->id => $c->display_name])
            ->all();
    }

    /** @return array<int, string> */
    #[Computed]
    public function sourceOptions(): array
    {
        return Source::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<string, string> */
    #[Computed]
    public function sortOptions(): array
    {
        return [
            'newest' => 'Terbaru terbit',
            'oldest' => 'Terlama terbit',
            'title' => 'Judul (A-Z)',
        ];
    }

    /**
     * Waktu sinkronisasi terakhir dari sumber mana pun, untuk sync-info-bar.
     */
    #[Computed]
    public function lastSyncedAt(): ?string
    {
        $terakhir = Source::query()->max('last_synced_at');

        return $terakhir
            ? \Illuminate\Support\Carbon::parse($terakhir)->diffForHumans()
            : null;
    }

    public function openDetail(int $articleId): void
    {
        $this->detailId = $articleId;
        $this->dispatch('modal-open:news-article-detail');
    }

    #[Computed]
    public function detail(): ?Article
    {
        return $this->detailId
            ? Article::query()->with(['category', 'source'])->find($this->detailId)
            : null;
    }

    public function syncNow(): void
    {
        $this->authorize('news.source.sync');

        SyncNewsJob::dispatch(triggerSource: 'manual');

        $this->dispatch('toast', type: 'success', message: 'Sinkronisasi dijadwalkan — cek kembali beberapa saat lagi.');
    }

    /** Ada saringan aktif — menentukan empty state mana yang ditampilkan. */
    public function hasFilter(): bool
    {
        return $this->search !== '' || $this->categoryId !== [] || $this->sourceId !== [];
    }

    public function render()
    {
        $articles = Article::query()
            ->with(['category', 'source'])
            ->when($this->search, function ($query) {
                $query->where(function ($sub) {
                    $sub->where('title', 'like', "%{$this->search}%")
                        ->orWhereHas('category', fn ($q) => $q->where('slug', 'like', "%{$this->search}%")
                            ->orWhere('name', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->categoryId, fn ($q) => $q->whereIn('category_id', $this->categoryId))
            ->when($this->sourceId, fn ($q) => $q->whereIn('source_id', $this->sourceId))
            ->when($this->sort === 'oldest', fn ($q) => $q->orderBy('published_at'))
            ->when($this->sort === 'title', fn ($q) => $q->orderBy('title'))
            ->when($this->sort === 'newest', fn ($q) => $q->orderByDesc('published_at'))
            ->paginate(20);

        return view('nawasara-news::livewire.pages.article.section.table', [
            'articles' => $articles,
            'totalArticles' => Article::query()->count(),
        ]);
    }
}
