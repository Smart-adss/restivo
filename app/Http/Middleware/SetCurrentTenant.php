<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->request->remove('tenant_id');
        $request->query->remove('tenant_id');

        if ($request->isJson()) {
            $payload = $request->json()->all();
            unset($payload['tenant_id']);
            $request->json()->replace($payload);
        }

        $context = app(TenantContext::class);
        $context->clear();

        $user = $request->user();

        if ($user && ! $user->is_platform_user && $user->tenant_id) {
            $tenant = Tenant::query()->find($user->tenant_id);

            if ($tenant) {
                $context->set($tenant->id);
            }
        }

        return $next($request);
    }
}
