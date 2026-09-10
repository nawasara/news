<?php

namespace Nawasara\News\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    protected $table = 'nawasara_news_articles';

    protected $fillable = [
        'source_id',
        'wp_id',
        'category_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'link',
        'cover_image',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        // Objek ukuran: {thumbnail, medium, medium_large, full}. Setiap kunci
        // dijamin ada dan berisi URL yang benar-benar bekerja selama nilai
        // keseluruhannya tidak null — lihat WordpressClient::resolveCoverImages().
        'cover_image' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
