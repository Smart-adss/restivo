<?php

namespace App\Domain\Subscription;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

class StartSubscription
{
    public function __construct(private InvoiceIssuer $invoices) {}

    public function handle(Tenant $tenant, string $planCode, string $interval, bool $trial): Subscription
    {
        $plan = Plan::query()->where('code', $planCode)->where('is_active', true)->firstOrFail();
        $start = Carbon::now();
        $trialing = $trial && $plan->trial_days > 0;
        $end = $trialing
            ? $start->copy()->addDays($plan->trial_days)
            : ($interval === 'yearly' ? $start->copy()->addYear() : $start->copy()->addMonth());

        $subscription = Subscription::query()->create([
            'plan_id' => $plan->id,
            'status' => $trialing ? 'trialing' : 'past_due',
            'interval' => $interval,
            'trial_ends_at' => $trialing ? $end : null,
            'current_period_start' => $start,
            'current_period_end' => $end,
        ]);

        if (! $trialing) {
            $this->invoices->issue($subscription, $start->copy(), $end->copy());
        }

        return $subscription;
    }
}
