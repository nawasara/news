<?php

namespace Nawasara\News\Services;

use Nawasara\Core\Models\Setting;

/**
 * Setelan berita yang dapat diubah staf lewat panel.
 *
 * Nilai disimpan di `nawasara_settings` (key-value milik nawasara/core, sudah
 * ber-cache) — bukan tabel sendiri, karena bentuknya memang segelintir angka
 * dan bendera, bukan entitas.
 *
 * ⚠️ Config TETAP menjadi cadangan, bukan dihapus. Pada pemasangan baru tabel
 * setelan masih kosong, dan pembacaan pertama terjadi saat ServiceProvider
 * mendaftarkan route — sebelum ada kesempatan siapa pun membuka panel. Tanpa
 * cadangan itu, batas laju akan bernilai nol dan seluruh API berita menolak
 * permintaan pertama yang datang.
 *
 * Urutannya: basis data → config → angka bawaan di sini.
 */
class NewsSettings
{
    /** Awalan kunci di `nawasara_settings`, agar tidak berbenturan antar paket. */
    protected const PREFIX = 'news.';

    /**
     * Batas permintaan per menit untuk API publik.
     *
     * Longgar dengan sengaja — lihat catatan di config dan README: route ini
     * tidak memeriksa token, sehingga kuota dihitung per ALAMAT IP, dan
     * pengguna seluler berbagi sedikit alamat NAT milik operator.
     */
    public static function rateLimitPerMinute(): int
    {
        $nilai = (int) static::baca(
            'rate_limit_per_minute',
            config('nawasara-news.rate_limit_per_minute', 300),
        );

        // Nol atau negatif akan membuat throttle menolak SEMUA permintaan.
        // Nilai seperti itu hanya bisa datang dari salah ketik, dan akibatnya
        // adalah API berita mati total — jadi ditolak di sini, bukan diteruskan.
        return $nilai > 0 ? $nilai : 300;
    }

    /** Menit antar sinkronisasi terjadwal. */
    public static function syncInterval(): int
    {
        $nilai = (int) static::baca(
            'sync_interval',
            config('nawasara-news.sync_interval', 60),
        );

        return max(1, $nilai);
    }

    /** Sinkronisasi terjadwal menyala. */
    public static function schedulerEnabled(): bool
    {
        return (bool) static::baca(
            'scheduler_enabled',
            config('nawasara-news.scheduler.enabled', true),
        );
    }

    /** Batas waktu permintaan HTTP ke situs WordPress, dalam detik. */
    public static function httpTimeout(): int
    {
        $nilai = (int) static::baca(
            'wp_http_timeout',
            config('nawasara-news.wp_http_timeout', 15),
        );

        return max(1, $nilai);
    }

    /** Zona waktu penampilan tanggal terbit. */
    public static function displayTimezone(): string
    {
        return (string) static::baca(
            'display_timezone',
            config('nawasara-news.display_timezone', 'Asia/Jakarta'),
        );
    }

    /**
     * Simpan satu setelan.
     *
     * @param  string  $kunci  tanpa awalan `news.`
     */
    public static function simpan(string $kunci, mixed $nilai): void
    {
        Setting::set(static::PREFIX.$kunci, $nilai);
    }

    /**
     * Baca dari basis data, jatuh ke cadangan bila belum pernah disimpan.
     *
     * Dibungkus try/catch karena pembacaan pertama dapat terjadi SEBELUM
     * migrasi dijalankan — saat `php artisan migrate` sendiri mem-boot
     * aplikasi. Tanpa ini, memasang paket pada basis data kosong menggagalkan
     * perintah migrate-nya sendiri, dan pesannya tidak menunjuk ke sini sama
     * sekali.
     */
    protected static function baca(string $kunci, mixed $cadangan): mixed
    {
        try {
            return Setting::get(static::PREFIX.$kunci, $cadangan);
        } catch (\Throwable) {
            return $cadangan;
        }
    }
}
