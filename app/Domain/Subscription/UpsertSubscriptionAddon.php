<?php

namespace App\Domain\Subscription;

use App\Models\Addon;
use App\Models\Subscription;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpsertSubscriptionAddon
{
    public function __construct(
        private AuditLogger $audit,
        private FeatureGate $features,
    ) {}

    public function handle(Subscription $subscription, string $addonKey, int $quantity): void
    {
        abort_unless(auth()->user()?->hasPermission('platform.plans.manage'), 403);

        if ($quantity < 0) {
            throw ValidationException::withMessages([
                'quantity' => __('app.addon_quantity_invalid'),
            ]);
        }

        $addon = Addon::query()->where('key', $addonKey)->where('is_active', true)->first();

        if ($addon === null) {
            throw ValidationException::withMessages([
                'addon_key' => __('app.addon_unavailable'),
            ]);
        }

        if (! in_array($addonKey, ['addon.extra_branch', 'addon.extra_user', 'addon.whatsapp'], true)) {
            throw ValidationException::withMessages([
                'addon_key' => __('app.addon_unavailable'),
            ]);
        }

        DB::transaction(function () use ($subscription, $addon, $quantity): void {
            $before = DB::table('subscription_addons')
                ->where('subscription_id', $subscription->id)
                ->where('addon_id', $addon->id)
                ->value('quantity');

            if ($quantity === 0) {
                DB::table('subscription_addons')
                    ->where('subscription_id', $subscription->id)
                    ->where('addon_id', $addon->id)
                    ->delete();
            } else {
                DB::table('subscription_addons')->updateOrInsert(
                    [
                        'subscription_id' => $subscription->id,
                        'addon_id' => $addon->id,
                    ],
                    ['quantity' => $quantity],
                );
            }

            $this->audit->log('subscription.addon_updated', $subscription, [
                'addon_key' => $addon->key,
                'quantity' => $before,
            ], [
                'addon_key' => $addon->key,
                'quantity' => $quantity,
            ]);
        });

        $this->features->flush();
    }
}
