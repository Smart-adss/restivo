<?php

namespace App\Http\Middleware;

use App\Support\Features\FeatureGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionWritable
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app(FeatureGate::class)->isReadOnly()) {
            abort(403);
        }

        return $next($request);
    }
}
