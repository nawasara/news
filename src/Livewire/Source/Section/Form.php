<?php

namespace Nawasara\News\Livewire\Source\Section;

use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Nawasara\News\Models\Source;
use Nawasara\News\Services\WordpressClient;

/**
 * Modal tambah / ubah sumber berita.
 */
class Form extends Component
{
    public ?int $sourceId = null;

    public string $name = '';

    public string $slug = '';

    public string $baseUrl = '';

    public int $latestPostLimit = 50;

    public bool $isActive = true;

    /** Hasil uji koneksi, ditampilkan di dalam modal. */
    public ?string $testResult = null;

    public ?bool $testOk = null;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('nawasara_news_sources', 'slug')->ignore($this->sourceId),
            ],
            'baseUrl' => ['required', 'url', 'max:500'],
            'latestPostLimit' => ['required', 'integer', 'min:1', 'max:1000'],
            'isActive' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung.',
            'baseUrl.url' => 'Alamat harus lengkap dengan https://, contoh: https://ponorogo.go.id',
        ];
    }

    #[On('news-source-create')]
    public function create(): void
    {
        $this->resetForm();
        $this->dispatch('modal-open:news-source-form');
    }

    #[On('news-source-edit')]
    public function edit(int $sourceId): void
    {
        $source = Source::findOrFail($sourceId);

        $this->sourceId = $source->id;
        $this->name = $source->name;
        $this->slug = $source->slug;
        $this->baseUrl = $source->base_url;
        $this->latestPostLimit = $source->latest_post_limit;
        $this->isActive = $source->is_active;

        $this->testResult = null;
        $this->testOk = null;
        $this->resetErrorBag();

        $this->dispatch('modal-open:news-source-form');
    }

    /**
     * Uji apakah alamat yang diketik benar-benar situs WordPress ber-wp-json.
     *
     * Salah ketik alamat adalah kekeliruan yang paling mungkin terjadi di form
     * ini, dan tanpa uji ini akibatnya baru terlihat satu jam kemudian sebagai
     * sinkronisasi gagal — jauh dari tempat kesalahannya dibuat.
     */
    public function testConnection(): void
    {
        $this->validateOnly('baseUrl');

        try {
            $client = new WordpressClient(
                $this->baseUrl,
                (int) config('nawasara-news.wp_http_timeout', 15),
            );

            $kategori = $client->getCategories();

            $this->testOk = true;
            $this->testResult = 'Terhubung — '.count($kategori).' kategori terbaca.';
        } catch (\Throwable $e) {
            $this->testOk = false;
            $this->testResult = 'Gagal: '.$e->getMessage();
        }
    }

    public function save(): void
    {
        $this->authorize($this->sourceId ? 'news.source.update' : 'news.source.create');

        $data = $this->validate();

        Source::updateOrCreate(
            ['id' => $this->sourceId],
            [
                'name' => $data['name'],
                'slug' => $data['slug'],
                'base_url' => rtrim($data['baseUrl'], '/'),
                'latest_post_limit' => $data['latestPostLimit'],
                'is_active' => $data['isActive'],
            ],
        );

        $this->dispatch('news-source-saved');
        $this->dispatch('modal-close:news-source-form');
        $this->dispatch('toast', type: 'success', message: "Sumber {$data['name']} disimpan.");

        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->sourceId = null;
        $this->name = '';
        $this->slug = '';
        $this->baseUrl = '';
        $this->latestPostLimit = 50;
        $this->isActive = true;
        $this->testResult = null;
        $this->testOk = null;
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('nawasara-news::livewire.pages.source.section.form');
    }
}
