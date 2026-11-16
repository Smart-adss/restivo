<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantOperational
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->is_platform_user) {
            return redirect()->route('platform.tenants.index');
        }

        $tenant = $user?->tenant_id ? Tenant::query()->find($user->tenant_id) : null;

        if (! $tenant || $tenant->status !== 'active') {
            return response()->view('app.suspended', [], 403);
        }

        return $next($request);
    }
}
