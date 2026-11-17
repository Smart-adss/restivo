<?php

namespace App\Support\Features;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\UsageCounter;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * Live usage vs plan limits for a tenant (FR-P-05 / owner subscription screen).
 */
class TenantUsageSnapshot
{
    public function __construct(private FeatureGate $gate) {}

    /**
     * @return array{
     *   branch_count: int,
     *   branch_limit: ?int,
     *   user_count: int,
     *   user_limit: ?int,
     *   order_count: int,
     *   order_limit: ?int
     * }
     */
    public function forCurrentTenant(?Tenant $tenant = null): array
    {
        $tenantId = app(TenantContext::class)->id();
        $tenant ??= Tenant::query()->find($tenantId);
        $timezone = $tenant?->timezone ?: 'Africa/Cairo';

        return [
            'branch_count' => Branch::query()->where('is_active', true)->count(),
            'branch_limit' => $this->gate->limit('branches.max'),
            'user_count' => User::query()->where('tenant_id', $tenantId)->where('status', 'active')->count(),
            'user_limit' => $this->gate->limit('users.max'),
            'order_count' => (int) UsageCounter::query()
                ->where('feature_key', 'orders.monthly')
                ->whereDate('period_start', now($timezone)->startOfMonth()->toDateString())
                ->value('used'),
            'order_limit' => $this->gate->limit('orders.monthly'),
        ];
    }
}
