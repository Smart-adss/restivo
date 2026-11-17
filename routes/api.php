<?php

use App\Http\Controllers\Api\BillingWebhookController;
use App\Http\Controllers\Api\PublicOrderController;
use App\Http\Controllers\Api\PublicReservationController;
use App\Http\Controllers\Api\PublicTableOrderController;
use App\Http\Middleware\ResolvePublicTenant;
use Illuminate\Support\Facades\Route;

Route::post('/v1/webhooks/billing', BillingWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('api.webhooks.billing');

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

        Route::post('/tables/{tablePublicId}/orders/quote', [PublicTableOrderController::class, 'quote'])
            ->name('api.public.table.orders.quote');

        Route::post('/tables/{tablePublicId}/orders', [PublicTableOrderController::class, 'store'])
            ->middleware('throttle:public-orders')
            ->name('api.public.table.orders.store');

        Route::get('/branches/{branchPublicId}/reservation-slots', [PublicReservationController::class, 'slots'])
            ->name('api.public.reservations.slots');

        Route::post('/reservations', [PublicReservationController::class, 'store'])
            ->middleware('throttle:public-reservations')
            ->name('api.public.reservations.store');
    });
