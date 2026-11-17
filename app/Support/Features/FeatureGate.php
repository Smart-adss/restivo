<?php

namespace App\Support\Features;

use App\Models\Subscription;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class FeatureGate
{
    /** @var array<string, ?string>|null */
    private ?array $values = null;

    private ?Subscription $subscription = null;

    private ?int $resolvedTenantId = null;

    public function allows(string $key): bool
    {
        return $this->raw($key) === '1';
    }

    public function limit(string $key): ?int
    {
        $value = $this->raw($key);

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function subscription(): ?Subscription
    {
        $this->resolved();

        return $this->subscription;
    }

    public function isReadOnly(): bool
    {
        $status = $this->subscription()?->status;

        return in_array($status, ['expired', 'suspended'], true);
    }

    public function assertWritable(): void
    {
        if ($this->isReadOnly()) {
            throw new SubscriptionReadOnly;
        }
    }

    public function assertUnderCeiling(string $key, int $current): void
    {
        $this->assertWritable();
        $limit = $this->limit($key);

        if ($limit !== null && $current >= $limit) {
            throw new LimitExceeded($key);
        }
    }

    public function flush(): void
    {
        $this->values = null;
        $this->subscription = null;
        $this->resolvedTenantId = null;
    }

    private function raw(string $key): ?string
    {
        $values = $this->resolved();

        if (! array_key_exists($key, $values) || $values[$key] === null) {
            return null;
        }

        return (string) $values[$key];
    }

    /**
     * @return array<string, ?string>
     */
    private function resolved(): array
    {
        $tenantId = app(TenantContext::class)->id();

        if ($this->values !== null && $this->resolvedTenantId === $tenantId) {
            return $this->values;
        }

        $this->values = null;
        $this->subscription = null;
        $this->resolvedTenantId = $tenantId;

        if ($tenantId === null) {
            return $this->values = [];
        }

        $subscription = Subscription::query()->first();

        if ($subscription === null) {
            return $this->values = [];
        }

        $this->subscription = $subscription;
        $values = [];
        $rows = DB::table('plan_features')
            ->join('features', 'features.id', '=', 'plan_features.feature_id')
            ->where('plan_features.plan_id', $subscription->plan_id)
            ->get(['features.key', 'plan_features.value']);

        foreach ($rows as $row) {
            $values[$row->key] = $row->value;
        }

        $addons = DB::table('subscription_addons')
            ->join('addons', 'addons.id', '=', 'subscription_addons.addon_id')
            ->where('subscription_addons.subscription_id', $subscription->id)
            ->get(['addons.key', 'subscription_addons.quantity']);

        $increases = [
            'addon.extra_branch' => 'branches.max',
            'addon.extra_user' => 'users.max',
        ];

        foreach ($addons as $addon) {
            $feature = $increases[$addon->key] ?? null;

            if ($feature === null || ! array_key_exists($feature, $values) || $values[$feature] === null) {
                continue;
            }

            $values[$feature] = (string) ((int) $values[$feature] + (int) $addon->quantity);
        }

        return $this->values = $values;
    }
}
