<?php

namespace Nawasara\News\Tests;

use Nawasara\News\Services\WordpressClient;
use PHPUnit\Framework\TestCase;

/**
 * Panjang slug dari WordPress.
 *
 * Menjaga perbaikan 20 September 2026. Kolom `slug` selebar varchar(191),
 * sedangkan WordPress tidak membatasi panjang slug sama sekali: tiga artikel
 * di ponorogo.go.id punya slug 199 sampai 200 karakter.
 *
 * Yang membuatnya mahal bukan satu artikel yang gagal disimpan, melainkan
 * bahwa penulisan berjalan dalam satu transaksi per sumber. Satu baris yang
 * terlalu panjang menggagalkan seluruh sumber, dan sejak 17 September tidak
 * ada satu pun berita baru yang masuk selama tiga hari.
 */
class SlugLengthTest extends TestCase
{
    /** Mirip slug sungguhan dari ponorogo.go.id, yang panjangnya 199 sampai 200 karakter. */
    private const SLUG_NYATA = 'nota-kesepakatan-antara-pemerintah-kabupaten-ponorogo-'
        .'dengan-dewan-perwakilan-rakyat-daerah-kabupaten-ponorogo-tentang-kebijakan-'
        .'umum-perubahan-anggaran-pendapatan-dan-belanja-daerah-serta-prioritas-dan-'
        .'plafon-anggaran-sementara-tahun-2026';

    public function test_slug_panjang_dipotong_ke_batas_kolom(): void
    {
        $this->assertGreaterThan(191, mb_strlen(self::SLUG_NYATA), 'contohnya harus melebihi batas');

        $hasil = WordpressClient::trimSlug(self::SLUG_NYATA);

        $this->assertSame(191, mb_strlen($hasil));
        $this->assertStringStartsWith('nota-kesepakatan-antara-pemerintah', $hasil);
    }

    public function test_slug_pendek_tidak_diubah(): void
    {
        $this->assertSame('berita-biasa', WordpressClient::trimSlug('berita-biasa'));
    }

    public function test_tepat_di_batas_tidak_dipotong(): void
    {
        $tepat = str_repeat('a', 191);

        $this->assertSame($tepat, WordpressClient::trimSlug($tepat));
    }

    /**
     * Dipotong per KARAKTER, bukan per byte.
     *
     * `substr` biasa akan membelah karakter multibyte di tengah dan
     * menghasilkan UTF-8 yang tidak sah, yang justru ditolak MySQL dengan
     * galat berbeda. Judul berita Indonesia memang jarang memuatnya, tetapi
     * kutip tipografis dan tanda panjang sesekali muncul.
     */
    public function test_multibyte_tidak_terbelah(): void
    {
        $hasil = WordpressClient::trimSlug(str_repeat("\u{00e4}", 200));

        $this->assertSame(191, mb_strlen($hasil));
        $this->assertTrue(mb_check_encoding($hasil, 'UTF-8'));
    }
}
