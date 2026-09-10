<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_news_articles', function (Blueprint $table) {
            // bigint: id ini tidak pernah keluar lewat API publik — pencarian
            // memakai `slug`. Lihat panduan §10a.
            $table->id();

            $table->foreignId('source_id')
                ->constrained('nawasara_news_sources')
                ->cascadeOnDelete();

            // Id post milik WordPress sumbernya.
            $table->unsignedBigInteger('wp_id');

            $table->foreignId('category_id')
                ->nullable()
                ->constrained('nawasara_news_categories')
                ->nullOnDelete();

            $table->string('title', 500);
            $table->string('slug', 191);

            $table->text('excerpt')->nullable();
            $table->longText('content');

            $table->string('link', 500);

            // Objek berisi beberapa ukuran (thumbnail/medium/medium_large/
            // full), bukan satu URL — lihat WordpressClient::resolveCoverImages().
            $table->json('cover_image')->nullable();

            // Kolom `date` WordPress memuat jam, bukan hanya tanggal.
            $table->dateTime('published_at')->nullable();

            $table->timestamps();

            // ⚠️ Sama seperti kategori: unik per sumber. Dua situs berbeda
            // boleh punya slug yang sama persis ("hut-ri-ke-81"), dan itu
            // wajar — yang tidak boleh adalah salah satunya hilang.
            $table->unique(['source_id', 'wp_id']);
            $table->unique(['source_id', 'slug']);

            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_news_articles');
    }
};
