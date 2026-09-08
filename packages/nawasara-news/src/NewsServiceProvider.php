<?php

namespace Nawasara\News;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Nawasara\News\Jobs\SyncNewsFromPonorogoJob;
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

            if (! config('nawasara-news.scheduler.enabled', true)) {
                return;
            }

            $schedule = $this->app->make(Schedule::class);
            $interval = max(1, (int) config('nawasara-news.sync_interval', 15));

            // $schedule->call(), not $schedule->command() — see guide section
            // 7 / ProxmoxServiceProvider: console commands registered via
            // $this->commands() don't reliably surface in the Artisan kernel
            // at scheduler-boot time for packages that boot later.
            $schedule->call(fn () => SyncNewsFromPonorogoJob::dispatch(triggerSource: 'scheduled'))
                ->name('nawasara-news:sync-ponorogo')
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
     * We borrow two things from nawasara-api's config, nothing else (no
     * ScopeRegistry call — scopes are meaningless on a route with no token
     * check):
     *   - route.prefix — confirmed against the real nawasara-api.php config.
     *   - rate_limit.per_minute — reused for OUR throttle instead of a
     *     hardcoded number, so tuning NAWASARA_API_RATE_PER_MINUTE affects
     *     this route too, consistent with every other API surface in the
     *     app, even though this route bypasses api.auth/api.citizen
     *     entirely and isn't actually subject to nawasara-api's per-token
     *     limiting — it's just borrowing the same config value as a sane
     *     shared default.
     */
    protected function registerPublicApiRoutes(): void
    {
        if (! class_exists(\Nawasara\Api\ApiServiceProvider::class)) {
            return;
        }

        $prefix = (string) config('nawasara-api.route.prefix', 'api/v1').'/news';
        $perMinute = (int) config('nawasara-api.rate_limit.per_minute', 60);

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
