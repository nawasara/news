<?php

namespace Nawasara\News\Livewire\Source\Section;

use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Nawasara\News\Jobs\SyncNewsJob;
use Nawasara\News\Models\Source;

/**
 * Daftar situs WordPress yang ditarik beritanya.
 *
 * Menambah sumber adalah pekerjaan staf, bukan pekerjaan deploy — itu alasan
 * halaman ini ada.
 */
class Table extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    public function hasFilter(): bool
    {
        return $this->search !== '' || $this->statusFilter !== '';
    }

    /** @return array<string, string> */
    public function getStatusOptionsProperty(): array
    {
        return [
            'active' => 'Aktif',
            'inactive' => 'Nonaktif',
            'failing' => 'Bermasalah',
        ];
    }

    #[On('news-source-saved')]
    public function refreshList(): void
    {
        // Cukup memicu render ulang — datanya dibaca ulang di render().
    }

    public function toggleActive(int $sourceId): void
    {
        $this->authorize('news.source.update');

        $source = Source::findOrFail($sourceId);
        $source->update(['is_active' => ! $source->is_active]);

        $this->dispatch(
            'toast',
            type: 'success',
            message: $source->is_active
                ? "Sumber {$source->name} diaktifkan."
                : "Sumber {$source->name} dinonaktifkan — artikel yang sudah ditarik tetap tersimpan.",
        );
    }

    public function syncOne(int $sourceId): void
    {
        $this->authorize('news.source.sync');

        $source = Source::findOrFail($sourceId);

        SyncNewsJob::dispatch(payload: ['source_id' => $source->id], triggerSource: 'manual');

        $this->dispatch('toast', type: 'success', message: "Sinkronisasi {$source->name} dijadwalkan.");
    }

    public function delete(int $sourceId): void
    {
        $this->authorize('news.source.delete');

        $source = Source::withCount('articles')->findOrFail($sourceId);
        $nama = $source->name;
        $jumlah = $source->articles_count;

        // Menghapus sumber ikut membuang artikelnya (cascade) — itu sebabnya
        // jumlahnya disebutkan di pesan, bukan sekadar "berhasil dihapus".
        $source->delete();

        $this->dispatch(
            'toast',
            type: 'success',
            message: "Sumber {$nama} dihapus beserta {$jumlah} artikelnya.",
        );
    }

    public function render()
    {
        $sources = Source::query()
            ->withCount('articles')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('base_url', 'like', "%{$this->search}%"))
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($this->statusFilter === 'failing', fn ($q) => $q->whereNotNull('last_error'))
            ->orderBy('name')
            ->get();

        return view('nawasara-news::livewire.pages.source.section.table', [
            'sources' => $sources,
        ]);
    }
}
