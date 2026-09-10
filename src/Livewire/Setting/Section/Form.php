<?php

namespace Nawasara\News\Livewire\Setting\Section;

use Livewire\Component;
use Nawasara\News\Models\Article;
use Nawasara\News\Models\Source;
use Nawasara\News\Services\NewsSettings;

/**
 * Pengaturan paket berita.
 *
 * Yang ada di sini adalah setelan yang wajar diubah staf tanpa deploy: seberapa
 * sering menarik, seberapa longgar API publik, berapa lama menunggu situs yang
 * lambat. Yang TIDAK ada di sini adalah daftar sumber — itu entitas dengan
 * halaman sendiri, bukan setelan.
 */
class Form extends Component
{
    public int $rateLimitPerMinute = 300;

    public int $syncInterval = 60;

    public bool $schedulerEnabled = true;

    public int $httpTimeout = 15;

    public string $displayTimezone = 'Asia/Jakarta';

    public function mount(): void
    {
        $this->muatUlang();
    }

    protected function rules(): array
    {
        return [
            // Batas bawah 10, bukan 1: pada route tanpa token kuota dihitung
            // per ALAMAT IP, dan satu warga membuka daftar lalu satu artikel
            // sudah memakai beberapa permintaan. Angka satuan berarti aplikasi
            // gagal memuat bagi hampir semua orang.
            'rateLimitPerMinute' => ['required', 'integer', 'min:10', 'max:10000'],
            'syncInterval' => ['required', 'integer', 'min:5', 'max:1440'],
            'schedulerEnabled' => ['boolean'],
            'httpTimeout' => ['required', 'integer', 'min:5', 'max:120'],
            'displayTimezone' => ['required', 'string', 'timezone'],
        ];
    }

    protected function messages(): array
    {
        return [
            'rateLimitPerMinute.min' => 'Minimal 10 — di bawah itu aplikasi warga akan gagal memuat berita.',
            'syncInterval.min' => 'Minimal 5 menit, agar situs sumber tidak terbebani.',
            'httpTimeout.min' => 'Minimal 5 detik — situs yang lambat butuh waktu menjawab.',
            'displayTimezone.timezone' => 'Zona waktu tidak dikenali, contoh yang benar: Asia/Jakarta',
        ];
    }

    /** @return array<string, string> */
    public function getTimezoneOptionsProperty(): array
    {
        return [
            'Asia/Jakarta' => 'WIB — Asia/Jakarta',
            'Asia/Makassar' => 'WITA — Asia/Makassar',
            'Asia/Jayapura' => 'WIT — Asia/Jayapura',
        ];
    }

    public function save(): void
    {
        $this->authorize('news.setting.update');

        $data = $this->validate();

        NewsSettings::simpan('rate_limit_per_minute', $data['rateLimitPerMinute']);
        NewsSettings::simpan('sync_interval', $data['syncInterval']);
        NewsSettings::simpan('scheduler_enabled', $data['schedulerEnabled']);
        NewsSettings::simpan('wp_http_timeout', $data['httpTimeout']);
        NewsSettings::simpan('display_timezone', $data['displayTimezone']);

        $this->dispatch('toast', type: 'success', message: 'Pengaturan disimpan.');
    }

    /** Kembalikan ke nilai config — jalan keluar bila setelan terlanjur keliru. */
    public function resetToDefault(): void
    {
        $this->authorize('news.setting.update');

        // ⚠️ Dihapus per MODEL, bukan lewat query builder.
        //
        // Setting membersihkan cache-nya di event `deleted`, dan event itu
        // hanya menyala bila yang dihapus adalah instance model. Penghapusan
        // massal (`where(...)->delete()`) melewatinya, sehingga barisnya hilang
        // dari basis data tetapi nilai lamanya tetap terbaca dari cache selama
        // satu jam — tombol ini akan tampak tidak berfungsi sama sekali.
        $kunci = ['rate_limit_per_minute', 'sync_interval', 'scheduler_enabled', 'wp_http_timeout', 'display_timezone'];

        \Nawasara\Core\Models\Setting::whereIn('key', array_map(fn ($k) => 'news.'.$k, $kunci))
            ->get()
            ->each->delete();

        $this->muatUlang();

        $this->dispatch('toast', type: 'success', message: 'Pengaturan dikembalikan ke bawaan.');
    }

    protected function muatUlang(): void
    {
        $this->rateLimitPerMinute = NewsSettings::rateLimitPerMinute();
        $this->syncInterval = NewsSettings::syncInterval();
        $this->schedulerEnabled = NewsSettings::schedulerEnabled();
        $this->httpTimeout = NewsSettings::httpTimeout();
        $this->displayTimezone = NewsSettings::displayTimezone();
    }

    public function render()
    {
        return view('nawasara-news::livewire.pages.setting.section.form', [
            'jumlahSumber' => Source::count(),
            'jumlahSumberAktif' => Source::where('is_active', true)->count(),
            'jumlahArtikel' => Article::count(),
        ]);
    }
}
