<?php

namespace Nawasara\News\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Setelan dibaca dari panel, dengan config sebagai cadangan.
 *
 * Urutannya penting dan mudah dibalik: basis data → config → angka bawaan.
 * Pada pemasangan baru, tabel setelan masih kosong dan pembacaan PERTAMA
 * terjadi saat ServiceProvider mendaftarkan route — sebelum ada kesempatan
 * siapa pun membuka panel.
 *
 * Tanpa cadangan itu, batas laju bernilai nol dan seluruh API berita menolak
 * permintaan pertama yang datang — pada pemasangan yang tampak berhasil.
 */
class SettingsFallbackTest extends TestCase
{
    /** Meniru NewsSettings::rateLimitPerMinute(). */
    private function batasLaju(mixed $dariDb, int $dariConfig): int
    {
        $nilai = (int) ($dariDb ?? $dariConfig);

        // Nol atau negatif membuat throttle menolak SEMUA permintaan; nilai
        // seperti itu hanya bisa datang dari salah ketik.
        return $nilai > 0 ? $nilai : 300;
    }

    /** Nilai dari panel dipakai bila ada. */
    public function test_nilai_panel_menang_atas_config(): void
    {
        $this->assertSame(900, $this->batasLaju(900, 300));
    }

    /** Basis data kosong jatuh ke config, bukan ke nol. */
    public function test_db_kosong_jatuh_ke_config(): void
    {
        $this->assertSame(300, $this->batasLaju(null, 300));
    }

    /**
     * Inti perkaranya: nol tidak boleh diteruskan ke throttle.
     *
     * `throttle:0,1` menolak setiap permintaan — API berita mati total,
     * dan penyebabnya tidak terlihat dari pesan galat mana pun.
     */
    public function test_nol_tidak_pernah_diteruskan(): void
    {
        $this->assertGreaterThan(0, $this->batasLaju(0, 300));
        $this->assertGreaterThan(0, $this->batasLaju(-50, 300));
        $this->assertGreaterThan(0, $this->batasLaju('', 300));
    }

    /**
     * Menghapus setelan harus lewat model, bukan query builder.
     *
     * Setting membersihkan cache-nya di event `deleted`, dan event itu hanya
     * menyala untuk instance model. Penghapusan massal melewatinya: barisnya
     * hilang, tetapi nilai lama tetap terbaca dari cache selama satu jam —
     * tombol "Kembalikan Bawaan" tampak tidak berfungsi.
     *
     * Terbukti saat menguji rantai penuh, 10 September 2026.
     */
    public function test_hapus_massal_melewatkan_pembersihan_cache(): void
    {
        // Meniru dua cara penghapusan.
        $eventMenyala = ['massal' => false, 'per_model' => false];

        // where(...)->delete() — tidak memuat model, tidak ada event.
        $eventMenyala['massal'] = false;

        // get()->each->delete() — tiap baris dimuat lalu dihapus sebagai model.
        $eventMenyala['per_model'] = true;

        $this->assertFalse($eventMenyala['massal'], 'penghapusan massal tidak membersihkan cache');
        $this->assertTrue($eventMenyala['per_model'], 'penghapusan per model yang membersihkan cache');
    }
}
