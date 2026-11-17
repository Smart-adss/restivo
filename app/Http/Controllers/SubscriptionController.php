<?php

namespace App\Http\Controllers;

use App\Support\Features\FeatureGate;
use App\Support\Features\TenantUsageSnapshot;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function show(FeatureGate $gate, TenantUsageSnapshot $usage): View
    {
        abort_unless(auth()->user()->hasPermission('tenant.settings.manage'), 403);

        $subscription = $gate->subscription()?->load('plan', 'invoices');
        $snapshot = $usage->forCurrentTenant(auth()->user()->tenant);

        return view('app.subscription', [
            'subscription' => $subscription,
            'branchCount' => $snapshot['branch_count'],
            'branchLimit' => $snapshot['branch_limit'],
            'userCount' => $snapshot['user_count'],
            'userLimit' => $snapshot['user_limit'],
            'orderCount' => $snapshot['order_count'],
            'orderLimit' => $snapshot['order_limit'],
        ]);
    }
}
