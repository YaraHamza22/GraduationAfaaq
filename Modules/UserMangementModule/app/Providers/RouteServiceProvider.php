<?php

namespace Modules\UserMangementModule\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'UserMangementModule';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     */
    public function boot(): void
    {
        RateLimiter::for('auth-sensitive', function (Request $request) {
            return [
                Limit::perMinute(10)->by((string) $request->ip()),
                Limit::perMinute(5)->by((string) $request->input('email')),
            ];
        });

        RateLimiter::for('platform-write', function (Request $request) {
            return Limit::perMinute(60)->by((string) optional($request->user())->id ?: (string) $request->ip());
        });

        RateLimiter::for('platform-sensitive-read', function (Request $request) {
            return Limit::perMinute(120)->by((string) optional($request->user())->id ?: (string) $request->ip());
        });

        parent::boot();
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     */
    protected function mapWebRoutes(): void
    {
        Route::middleware('web')->group(module_path($this->name, '/routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     */
    protected function mapApiRoutes(): void
    {
        Route::middleware('api')->prefix('api')->name('api.')->group(module_path($this->name, '/routes/api.php'));
    }
}
