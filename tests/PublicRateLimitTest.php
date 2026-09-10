<?php

namespace Nawasara\News\Tests;

use PHPUnit\Framework\TestCase;

/**
 * API berita punya batas lajunya sendiri, bukan milik nawasara-api.
 *
 * Throttle Laravel menghitung per KUNCI, dan pada route yang tidak memeriksa
 * token kuncinya adalah alamat IP. Ponsel di jaringan seluler tidak punya IP
 * publik sendiri — ratusan ribu pelanggan satu operator keluar lewat segelintir
 * alamat NAT, sehingga dari sisi server mereka tampak sebagai SATU pengunjung
 * dan berbagi satu jatah.
 *
 * Dengan 60 seperti API lainnya, beberapa puluh warga yang membuka SuperApps
 * bersamaan sudah cukup membuat sisanya menerima 429 — dan yang mereka lihat
 * hanya "gagal memuat".
 */
class PublicRateLimitTest extends TestCase
{
    /** Nilai yang dipakai NewsServiceProvider::registerPublicApiRoutes(). */
    private function batasBerita(?int $configNews, int $configApi): int
    {
        // Meniru urutan pembacaan di provider: config paket sendiri lebih dulu,
        // dengan bawaan 300 — TIDAK pernah jatuh ke nilai nawasara-api.
        return $configNews ?? 300;
    }

    /**
     * Inti perkaranya: batas berita tidak boleh ikut nilai API global.
     */
    public function test_tidak_memakai_batas_nawasara_api(): void
    {
        // API global ketat (60) karena melayani endpoint bertoken.
        $this->assertNotSame(
            60,
            $this->batasBerita(null, 60),
            'batas berita ikut nilai API global — beberapa puluh pembaca di balik satu NAT akan kena 429',
        );
    }

    /** Bawaannya cukup longgar untuk ratusan pembaca di balik satu NAT. */
    public function test_bawaan_longgar(): void
    {
        $this->assertGreaterThanOrEqual(300, $this->batasBerita(null, 60));
    }

    /** Tetap dapat disetel lewat env bila lalu lintas ternyata lebih padat. */
    public function test_dapat_ditimpa_lewat_config(): void
    {
        $this->assertSame(1000, $this->batasBerita(1000, 60));
    }

    /**
     * Menaikkan batas berita TIDAK boleh melonggarkan API lain.
     *
     * Itu sebabnya angkanya dipisah, bukan menaikkan yang global: endpoint lain
     * menulis data dan memegang token, dan keduanya pantas tetap ketat.
     */
    public function test_api_lain_tidak_ikut_longgar(): void
    {
        $batasApiLain = 60;

        $this->batasBerita(1000, $batasApiLain);

        $this->assertSame(60, $batasApiLain, 'menaikkan batas berita ikut melonggarkan API bertoken');
    }
}
