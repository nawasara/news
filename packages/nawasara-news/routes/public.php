<?php

use Illuminate\Support\Facades\Route;
use Nawasara\News\Http\Api\NewsController;

// Mounted by NewsServiceProvider::registerPublicApiRoutes() at
// {nawasara-api prefix}/news, middleware ['api', 'throttle:60,1'] — no
// token, no scope, no login. Modeled structurally on CCTV's stream-verify
// route (the codebase's one genuinely-open precedent), NOT on the citizen
// or system-token paths — see /areas/nawasara.md for why.
Route::get('/articles', [NewsController::class, 'index'])->name('articles.index');
Route::get('/articles/{slug}', [NewsController::class, 'show'])->name('articles.show');
