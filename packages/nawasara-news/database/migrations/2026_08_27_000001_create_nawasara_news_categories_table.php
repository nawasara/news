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

            // Sync key — WordPress's own category id. Unique so upsert-by-wp_id
            // is a simple updateOrCreate(['wp_id' => ...]).
            $table->unsignedBigInteger('wp_id')->unique();

            $table->string('slug', 191)->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_news_categories');
    }
};
