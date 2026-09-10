<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_news_categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('source_id')
                ->constrained('nawasara_news_sources')
                ->cascadeOnDelete();

            // Id kategori milik WordPress sumbernya.
            $table->unsignedBigInteger('wp_id');

            $table->string('slug', 191);
            $table->string('name')->nullable();

            $table->timestamps();

            // ⚠️ Unik per SUMBER, bukan global. Dua situs WordPress yang
            // berbeda hampir pasti punya kategori "berita" dengan id 1 —
            // menjadikan wp_id atau slug unik secara global akan membuat
            // sumber kedua menimpa kategori sumber pertama.
            $table->unique(['source_id', 'wp_id']);
            $table->unique(['source_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_news_categories');
    }
};
