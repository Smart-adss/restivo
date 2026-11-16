<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\UsageCounter;
use App\Models\User;
use App\Support\Features\FeatureGate;
use App\Support\Tenancy\TenantContext;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function show(FeatureGate $gate): View
    {
        abort_unless(auth()->user()->hasPermission('tenant.settings.manage'), 403);

        $subscription = $gate->subscription()?->load('plan', 'invoices');
        $tenantId = app(TenantContext::class)->id();

        return view('app.subscription', [
            'subscription' => $subscription,
            'branchCount' => Branch::query()->where('is_active', true)->count(),
            'branchLimit' => $gate->limit('branches.max'),
            'userCount' => User::query()->where('tenant_id', $tenantId)->where('status', 'active')->count(),
            'userLimit' => $gate->limit('users.max'),
            'orderCount' => (int) UsageCounter::query()->where('feature_key', 'orders.monthly')->whereDate('period_start', now(auth()->user()->tenant->timezone ?: 'Africa/Cairo')->startOfMonth()->toDateString())->value('used'),
            'orderLimit' => $gate->limit('orders.monthly'),
        ]);
    }
}
