<?php

namespace Nawasara\News\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Situs WordPress yang ditarik beritanya.
 *
 * Satu baris per situs — ponorogo.go.id, disbudparpora.ponorogo.go.id, dan
 * seterusnya. Menambah sumber adalah pekerjaan staf lewat panel, bukan
 * pekerjaan pengembang lewat deploy.
 */
class Source extends Model
{
    protected $table = 'nawasara_news_sources';

    protected $fillable = [
        'slug',
        'name',
        'base_url',
        'latest_post_limit',
        'is_active',
        'last_synced_at',
        'last_error',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latest_post_limit' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Sumber yang ikut dalam sinkronisasi terjadwal.
     *
     * `is_active` false berarti "berhenti menarik", BUKAN "buang yang sudah
     * ada" — artikel yang terlanjur ditarik tetap tampil. Yang membuang
     * adalah menghapus barisnya (cascade).
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Sinkronisasi terakhir gagal dan belum pulih. */
    public function isFailing(): bool
    {
        return $this->last_error !== null;
    }
}
