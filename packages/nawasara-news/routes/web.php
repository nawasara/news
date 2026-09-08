<?php

use Illuminate\Support\Facades\Route;
use Nawasara\News\Livewire\News\Index as NewsIndex;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::middleware(['web', 'auth'])->prefix('nawasara-news')->group(function () {
    Route::get('articles', NewsIndex::class)
        ->middleware(PermissionMiddleware::using('news.article.view'))
        ->name('nawasara-news.articles.index');
});
