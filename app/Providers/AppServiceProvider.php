<?php

namespace App\Providers;

use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('public-orders', function (Request $request) {
            return Limit::perHour(10)->by($request->ip().'|'.$request->route('slug'));
        });

        RateLimiter::for('public-reservations', function (Request $request) {
            return Limit::perHour(5)->by($request->ip().'|'.$request->route('slug'));
        });
    }
}
