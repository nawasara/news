<?php

namespace Nawasara\News\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'nawasara_news_settings';

    protected $fillable = ['latest_post_limit', 'last_synced_at'];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    /**
     * This table only ever has one row. Fetch it, creating it with config
     * defaults on first access so callers never have to null-check.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'latest_post_limit' => config('nawasara-news.default_latest_post_limit', 100),
        ]);
    }
}
