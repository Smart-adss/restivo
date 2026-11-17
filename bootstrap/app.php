<?php

use App\Http\Middleware\EnsurePlatformUser;
use App\Http\Middleware\EnsureSubscriptionWritable;
use App\Http\Middleware\EnsureTenantOperational;
use App\Http\Middleware\ResolvePublicTenant;
use App\Http\Middleware\SetCurrentTenant;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Public order APIs need session CSRF for same-origin guest pages.
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            SetCurrentTenant::class,
        ]);

        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            SetLocale::class,
        ]);

        $middleware->alias([
            'platform' => EnsurePlatformUser::class,
            'tenant.operational' => EnsureTenantOperational::class,
            'subscription.writable' => EnsureSubscriptionWritable::class,
            'public.tenant' => ResolvePublicTenant::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(function () {
            $user = auth()->user();

            return route($user?->is_platform_user ? 'platform.tenants.index' : 'app.home');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
