<?php

namespace App\Domain\Subscription;

use App\Models\Subscription;
use App\Support\Tenancy\TenantContext;

class IssueUpcomingInvoices
{
    public function __construct(private InvoiceIssuer $invoices) {}

    public function handle(): int
    {
        $issued = 0;
        $subscriptions = Subscription::withoutGlobalScope('tenant')
            ->whereIn('status', ['trialing', 'active', 'past_due', 'grace'])
            ->where('current_period_end', '<=', now()->addDays(3))
            ->get();

        foreach ($subscriptions as $subscription) {
            app(TenantContext::class)->set($subscription->tenant_id);
            $start = $subscription->current_period_end->copy();
            $end = $subscription->interval === 'yearly' ? $start->copy()->addYear() : $start->copy()->addMonth();

            if ($this->invoices->issue($subscription, $start, $end) !== null) {
                $issued++;
            }
        }

        app(TenantContext::class)->clear();

        return $issued;
    }
}
