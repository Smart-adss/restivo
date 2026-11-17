<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolvePublicTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = (string) $request->route('slug');
        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null || $tenant->status !== 'active') {
            abort(404);
        }

        app(TenantContext::class)->set($tenant->id);
        app(FeatureGate::class)->flush();

        $request->attributes->set('public_tenant', $tenant);

        return $next($request);
    }
}
