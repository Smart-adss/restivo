<?php

use App\Http\Controllers\Api\PublicOrderController;
use App\Http\Middleware\ResolvePublicTenant;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/public/{slug}')
    ->where(['slug' => '[a-z0-9-]{3,40}'])
    ->middleware([ResolvePublicTenant::class])
    ->group(function () {
        Route::post('/orders/quote', [PublicOrderController::class, 'quote'])
            ->name('api.public.orders.quote');

        Route::post('/orders', [PublicOrderController::class, 'store'])
            ->middleware('throttle:public-orders')
            ->name('api.public.orders.store');

        Route::get('/orders/{orderPublicId}', [PublicOrderController::class, 'show'])
            ->name('api.public.orders.show');
    });
