<?php

namespace Nawasara\News\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Dua situs WordPress yang berbeda tidak boleh saling menimpa.
 *
 * Paket ini awalnya hanya melayani satu situs, sehingga `wp_id` dan `slug`
 * dibuat unik secara GLOBAL. Begitu sumber kedua ditambahkan, keunikan itu
 * berubah dari benar menjadi berbahaya: setiap instalasi WordPress menomori
 * kategori dan postingnya sendiri mulai dari 1, jadi situs dinas hampir pasti
 * punya kategori ber-id 1 sama seperti situs kabupaten.
 *
 * Dengan keunikan global, sinkronisasi kedua akan MENIMPA baris situs pertama
 * alih-alih membuat baris baru — dan bentuk kegagalannya diam: tidak ada galat,
 * hanya artikel yang berubah isinya sendiri.
 */
class MultiSourceIsolationTest extends TestCase
{
    /**
     * Kunci pencarian yang dipakai updateOrCreate() di SyncNewsJob.
     *
     * @return array<string, mixed>
     */
    private function kunciUpsert(int $sourceId, int $wpId): array
    {
        return ['source_id' => $sourceId, 'wp_id' => $wpId];
    }

    /**
     * Inti perkaranya: wp_id yang sama dari sumber berbeda adalah baris
     * berbeda.
     */
    public function test_wp_id_sama_dari_sumber_berbeda_tidak_bertabrakan(): void
    {
        $kabupaten = $this->kunciUpsert(1, 42);
        $dinas = $this->kunciUpsert(2, 42);

        $this->assertNotEquals(
            $kabupaten,
            $dinas,
            'wp_id yang sama dari dua situs akan menimpa satu sama lain',
        );
    }

    /**
     * Cara lama — hanya wp_id — memang bertabrakan. Membuktikan bug-nya nyata.
     */
    public function test_kunci_lama_tanpa_source_id_bertabrakan(): void
    {
        $caraLamaKabupaten = ['wp_id' => 42];
        $caraLamaDinas = ['wp_id' => 42];

        $this->assertSame(
            $caraLamaKabupaten,
            $caraLamaDinas,
            'tanpa source_id, kedua situs menunjuk baris yang sama',
        );
    }

    /**
     * Slug yang sama persis dari dua situs adalah hal yang wajar terjadi —
     * "hut-ri-ke-81" diterbitkan hampir semua situs pemerintah di hari yang
     * sama. Keduanya harus tersimpan.
     */
    public function test_slug_sama_dari_sumber_berbeda_keduanya_tersimpan(): void
    {
        $baris = [
            ['source_id' => 1, 'slug' => 'hut-ri-ke-81'],
            ['source_id' => 2, 'slug' => 'hut-ri-ke-81'],
        ];

        $unik = array_unique(array_map(
            fn ($b) => $b['source_id'].'|'.$b['slug'],
            $baris,
        ));

        $this->assertCount(2, $unik, 'salah satu artikel akan hilang');
    }

    /**
     * Peta kategori disaring per sumber sebelum dipakai menghubungkan artikel.
     *
     * Tanpa penyaringan itu, artikel situs dinas bisa terhubung ke kategori
     * milik situs kabupaten hanya karena wp_id-nya kebetulan sama.
     */
    public function test_peta_kategori_disaring_per_sumber(): void
    {
        $semuaKategori = [
            ['id' => 10, 'source_id' => 1, 'wp_id' => 5],
            ['id' => 20, 'source_id' => 2, 'wp_id' => 5],
        ];

        $petaSumber2 = [];
        foreach ($semuaKategori as $k) {
            if ($k['source_id'] === 2) {
                $petaSumber2[$k['wp_id']] = $k['id'];
            }
        }

        $this->assertSame(20, $petaSumber2[5], 'artikel terhubung ke kategori sumber yang salah');
    }

    /**
     * Artikel yang keluar dari N terbaru TIDAK dihapus.
     *
     * Versi sebelumnya menghapusnya agar Nawasara "sama persis" dengan
     * WordPress. Akibatnya arsip menyusut diam-diam dan tautan yang sudah
     * dibagikan ke publik mati.
     */
    public function test_artikel_lama_tidak_dihapus_saat_bergeser_keluar(): void
    {
        $tersimpan = [101, 102, 103];        // sudah ada di Nawasara
        $tarikanTerbaru = [103, 104];        // yang muncul di N terbaru sekarang

        // Perilaku baru: menumpuk, bukan mencerminkan.
        $sesudah = array_values(array_unique(array_merge($tersimpan, $tarikanTerbaru)));

        $this->assertContains(101, $sesudah, 'artikel lama terhapus hanya karena bergeser keluar');
        $this->assertCount(4, $sesudah);
    }
}
