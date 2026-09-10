<?php

use Illuminate\Support\Facades\Route;
use Nawasara\News\Http\Api\NewsController;

// Dipasang oleh NewsServiceProvider::registerPublicApiRoutes() di
// {prefix nawasara-api}/news dengan middleware ['api', 'throttle:N,1'] —
// tanpa token, tanpa scope, tanpa login. Mengikuti pola route stream-verify
// milik CCTV, satu-satunya preseden route benar-benar terbuka di basis kode
// ini, BUKAN jalur citizen atau system-token.
Route::get('/articles', [NewsController::class, 'index'])->name('articles.index');
Route::get('/articles/{slug}', [NewsController::class, 'show'])->name('articles.show');

// Daftar sumber, agar klien tidak perlu memasang daftar situs secara tetap.
Route::get('/sources', [NewsController::class, 'sources'])->name('sources.index');
