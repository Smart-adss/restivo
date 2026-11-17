<?php

namespace App\Jobs;

use App\Domain\Inventory\Actions\ConsumeOrderRecipes;
use App\Models\Order;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ConsumeOrderRecipesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $orderId,
        public int $tenantId,
    ) {}

    public function handle(ConsumeOrderRecipes $consumeOrderRecipes, TenantContext $tenantContext, FeatureGate $features): void
    {
        $tenantContext->set($this->tenantId);
        $features->flush();

        try {
            $order = Order::query()->find($this->orderId);

            if ($order === null || $order->status === 'cancelled') {
                return;
            }

            $consumeOrderRecipes->handle($order);
        } catch (Throwable $exception) {
            Log::warning('Recipe consumption failed for order '.$this->orderId, [
                'tenant_id' => $this->tenantId,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * Record a failed sync dispatch into failed_jobs (queue workers already log via WorkCommand).
     */
    public static function recordSyncFailure(int $orderId, int $tenantId, Throwable $exception): void
    {
        $uuid = (string) Str::uuid();

        app('queue.failer')->log(
            (string) config('queue.default'),
            'default',
            json_encode([
                'uuid' => $uuid,
                'displayName' => static::class,
                'job' => 'Illuminate\Queue\CallQueuedHandler@call',
                'maxTries' => 3,
                'data' => [
                    'commandName' => static::class,
                    'command' => serialize(new static($orderId, $tenantId)),
                ],
            ]),
            $exception,
        );
    }
}
