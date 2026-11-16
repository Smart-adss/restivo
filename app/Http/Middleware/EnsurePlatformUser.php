<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->is_platform_user || ! $user->hasPermission('platform.tenants.view')) {
            abort(403);
        }

        return $next($request);
    }
}
