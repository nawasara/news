<?php

namespace Nawasara\News\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    protected $table = 'nawasara_news_articles';

    protected $fillable = [
        'wp_id',
        'category_id',
        'judul',
        'slug',
        'ringkasan',
        'isi_lengkap',
        'link',
        'gambar_sampul',
        'tanggal_terbit',
    ];

    protected $casts = [
        'tanggal_terbit' => 'datetime',
        // Object of sizes: {thumbnail, medium, medium_large, full}. Every
        // key is guaranteed present with a real URL whenever this whole
        // value is non-null — see WordpressClient::resolveCoverImages().
        'gambar_sampul' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
