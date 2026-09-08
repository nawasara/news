<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nawasara_news_articles', function (Blueprint $table) {
            // Was a single URL string; now an object of sizes
            // (thumbnail/medium/medium_large/full — see WordpressClient's
            // resolveCoverImages()). Existing rows hold plain URL strings,
            // which aren't valid JSON — Eloquent's array cast will decode
            // those to null rather than error (json_decode() failure is
            // silent, not thrown). That's an accepted, self-healing
            // transient: every article gets re-upserted with the new
            // object shape on the very next sync run, so this is a gap of
            // at most one sync interval, not a data-loss concern. Requires
            // doctrine/dbal for ->change() on MySQL if not already present
            // elsewhere in this app.
            $table->json('gambar_sampul')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('nawasara_news_articles', function (Blueprint $table) {
            $table->string('gambar_sampul', 500)->nullable()->change();
        });
    }
};
