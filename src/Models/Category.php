<?php

namespace Nawasara\News\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $table = 'nawasara_news_categories';

    protected $fillable = ['source_id', 'wp_id', 'slug', 'name'];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * Nama untuk ditampilkan.
     *
     * WordPress mengirim `name` yang sudah rapi ("Berita Daerah"), tetapi
     * tidak semua instalasi mengisinya. Slug dijadikan cadangan agar kolom
     * di panel tidak pernah kosong.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: str_replace('-', ' ', $this->slug);
    }
}
