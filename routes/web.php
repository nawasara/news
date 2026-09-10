<?php

use Illuminate\Support\Facades\Route;
use Nawasara\News\Livewire\Article\Index as ArticleIndex;
use Nawasara\News\Livewire\Setting\Index as SettingIndex;
use Nawasara\News\Livewire\Source\Index as SourceIndex;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::middleware(['web', 'auth'])->prefix('nawasara-news')->group(function () {
    Route::get('articles', ArticleIndex::class)
        ->middleware(PermissionMiddleware::using('news.article.view'))
        ->name('nawasara-news.articles.index');

    Route::get('sources', SourceIndex::class)
        ->middleware(PermissionMiddleware::using('news.source.view'))
        ->name('nawasara-news.sources.index');

    Route::get('settings', SettingIndex::class)
        ->middleware(PermissionMiddleware::using('news.setting.view'))
        ->name('nawasara-news.settings.index');
});
