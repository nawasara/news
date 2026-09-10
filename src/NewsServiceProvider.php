<?php

namespace Nawasara\News;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Nawasara\News\Jobs\SyncNewsJob;
use Nawasara\News\Services\NewsSettings;
use Symfony\Component\Finder\Finder;

class NewsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nawasara-news.php', 'nawasara-news');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nawasara-news');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Guarded — Laravel's view:cache crashes on missing registered paths.
        if (is_dir(__DIR__.'/../resources/views/components')) {
            Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'nawasara-news');
        }

        $this->registerLivewire();
        $this->registerPublicApiRoutes();

        $this->app->booted(function () {
            if (! $this->app->runningInConsole()) {
                return;
            }

            if (! NewsSettings::schedulerEnabled()) {
                return;
            }

            $schedule = $this->app->make(Schedule::class);
            $interval = NewsSettings::syncInterval();

            // $schedule->call(), not $schedule->command() — see guide section
            // 7 / ProxmoxServiceProvider: console commands registered via
            // $this->commands() don't reliably surface in the Artisan kernel
            // at scheduler-boot time for packages that boot later.
            $schedule->call(fn () => SyncNewsJob::dispatch(triggerSource: 'scheduled'))
                ->name('nawasara-news:sync-articles')
                ->cron("*/{$interval} * * * *")
                ->withoutOverlapping(10);
        });
    }

    /**
     * Genuinely public, no-auth route — modeled on CctvServiceProvider's
     * stream-verify route (middleware ['api'] only, no token/scope check),
     * NOT on the citizen (api.citizen) or system-token (api.auth) paths.
     * Confirmed with PM: this endpoint needs zero login.
     *
     * Still guarded by class_exists() even though nawasara/api IS installed
     * in this monorepo (per root composer.json) — this package should keep
     * working if that ever changes, per every other package's convention.
     *
     * Only ONE thing is borrowed from nawasara-api: `route.prefix`, so the
     * news endpoints sit under the same /api/v1 as everything else. No
     * ScopeRegistry call — scopes are meaningless on a route with no token
     * check — and NOT its rate limit either; see the note below.
     */
    protected function registerPublicApiRoutes(): void
    {
        if (! class_exists(\Nawasara\Api\ApiServiceProvider::class)) {
            return;
        }

        $prefix = (string) config('nawasara-api.route.prefix', 'api/v1').'/news';

        // ⚠️ Batas laju SENDIRI, bukan `nawasara-api.rate_limit.per_minute`.
        //
        // Throttle Laravel menghitung per KUNCI, dan pada route tanpa
        // pemeriksaan token kuncinya adalah alamat IP. Ponsel di jaringan
        // seluler tidak punya IP publik sendiri — ratusan ribu pelanggan satu
        // operator keluar lewat segelintir alamat NAT, sehingga dari sisi
        // server mereka tampak sebagai SATU pengunjung dan berbagi satu jatah.
        //
        // Dengan 60 seperti API lainnya, beberapa puluh warga yang membuka
        // SuperApps bersamaan sudah cukup membuat sisanya menerima 429. Yang
        // mereka lihat hanya "gagal memuat", dan keluhannya berbunyi "kadang
        // bisa kadang tidak" — tidak dapat ditiru dari kantor, yang IP-nya
        // sendiri dan lengang.
        //
        // Angkanya dipisah, bukan menaikkan yang global, karena yang dilindungi
        // di sini hanya artikel yang memang boleh dibaca siapa saja. Endpoint
        // lain menulis data dan memegang token — keduanya pantas tetap ketat.
        //
        // Dibaca dari panel (halaman Pengaturan Berita), dengan config sebagai
        // cadangan. Angka ini perlu disetel oleh orang yang MELIHAT keluhannya
        // masuk, dan menunggu deploy untuk itu berarti aplikasi warga tetap
        // gagal memuat sepanjang penantian.
        $perMinute = NewsSettings::rateLimitPerMinute();

        Route::prefix($prefix)
            ->middleware(['api', "throttle:{$perMinute},1"])
            ->name('nawasara-api.news.')
            ->group(__DIR__.'/../routes/public.php');
    }

    protected function registerLivewire(): void
    {
        $namespace = 'Nawasara\\News\\Livewire';
        $basePath = __DIR__.'/Livewire';

        if (! is_dir($basePath)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($basePath)->name('*.php');

        foreach ($finder as $file) {
            $relativePath = str_replace('/', '\\', $file->getRelativePathname());
            $class = $namespace.'\\'.Str::beforeLast($relativePath, '.php');

            if (class_exists($class)) {
                $alias = 'nawasara-news.'.
                    Str::of($relativePath)
                        ->replace('.php', '')
                        ->replace('\\', '.')
                        ->replace('/', '.')
                        ->explode('.')
                        ->map(fn ($segment) => Str::kebab($segment))
                        ->join('.');

                Livewire::component($alias, $class);
            }
        }
    }
}
