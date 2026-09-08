<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_news_articles', function (Blueprint $table) {
            // bigint PK: never exposed by the public API — lookups go through
            // `slug` (WordPress's own post slug). See guide section 10a.
            $table->id();

            // Sync key — WordPress's own post id.
            $table->unsignedBigInteger('wp_id')->unique();

            $table->foreignId('category_id')
                ->nullable()
                ->constrained('nawasara_news_categories')
                ->nullOnDelete();

            $table->string('judul', 500);
            $table->string('slug', 191)->unique();

            $table->text('ringkasan')->nullable();
            $table->longText('isi_lengkap');

            $table->string('link', 500);
            $table->string('gambar_sampul', 500)->nullable();

            // WP's `date` field includes a time component, not just a date.
            $table->dateTime('tanggal_terbit')->nullable();

            $table->timestamps();

            $table->index('tanggal_terbit');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_news_articles');
    }
};
