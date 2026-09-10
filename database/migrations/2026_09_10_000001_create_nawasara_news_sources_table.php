<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Situs WordPress yang ditarik beritanya.
     *
     * Awalnya paket ini hanya melayani ponorogo.go.id lewat satu nilai di
     * config. Sumber berpindah ke tabel karena penambahannya adalah pekerjaan
     * staf — OPD baru menerbitkan berita, dan itu tidak boleh menunggu deploy.
     *
     * `latest_post_limit` ikut per baris, bukan satu angka global: situs
     * kabupaten menerbitkan jauh lebih sering daripada situs dinas, dan
     * menyamakan keduanya berarti salah satunya pasti keliru.
     */
    public function up(): void
    {
        Schema::create('nawasara_news_sources', function (Blueprint $table) {
            $table->id();

            // Dipakai di URL API publik (?source=disbudparpora) dan sebagai
            // kunci sinkronisasi yang stabil — nama boleh berubah, ini tidak.
            $table->string('slug', 100)->unique();

            $table->string('name');
            $table->string('base_url', 500);

            $table->unsignedInteger('latest_post_limit')->default(50);

            // Menonaktifkan sumber menghentikan sinkronisasi TANPA menghapus
            // artikel yang sudah ditarik — beda dengan menghapus barisnya.
            $table->boolean('is_active')->default(true)->index();

            $table->timestamp('last_synced_at')->nullable();

            // Diisi saat percobaan terakhir gagal, dikosongkan saat berhasil.
            // Tanpa ini, sumber yang mati hanya terlihat sebagai "tidak ada
            // artikel baru", yang mudah disangka sepi padahal rusak.
            $table->text('last_error')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_news_sources');
    }
};
