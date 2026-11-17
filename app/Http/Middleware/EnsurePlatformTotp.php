<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformTotp
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('restivo.require_platform_totp')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user?->is_platform_user) {
            return $next($request);
        }

        if ($request->routeIs('platform.totp.*', 'logout')) {
            return $next($request);
        }

        if (! $user->totp_confirmed_at) {
            return redirect()->route('platform.totp.setup');
        }

        if (! $request->session()->get('platform_totp_ok')) {
            return redirect()->route('platform.totp.challenge');
        }

        return $next($request);
    }
}
