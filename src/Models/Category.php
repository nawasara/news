<?php

namespace Nawasara\News\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $table = 'nawasara_news_categories';

    protected $fillable = ['wp_id', 'slug'];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
