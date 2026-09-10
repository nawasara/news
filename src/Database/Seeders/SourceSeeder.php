<?php

namespace Nawasara\News\Database\Seeders;

use Illuminate\Database\Seeder;
use Nawasara\News\Models\Source;

/**
 * Sumber berita awal.
 *
 * Hanya situs kabupaten yang diisikan — situs dinas ditambahkan staf lewat
 * panel sesuai kebutuhan, dan menuliskannya di sini justru akan menghidupkan
 * kembali baris yang sengaja mereka hapus setiap kali seeder dijalankan.
 *
 * `firstOrCreate` pada slug: menjalankan ulang seeder tidak menimpa batas
 * jumlah artikel atau status aktif yang sudah disetel staf.
 */
class SourceSeeder extends Seeder
{
    public function run(): void
    {
        Source::firstOrCreate(
            ['slug' => 'ponorogo'],
            [
                'name' => 'Pemerintah Kabupaten Ponorogo',
                'base_url' => 'https://ponorogo.go.id',
                'latest_post_limit' => 100,
                'is_active' => true,
            ],
        );
    }
}
