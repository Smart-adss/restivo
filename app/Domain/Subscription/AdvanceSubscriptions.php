<?php

namespace App\Domain\Subscription;

use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

class AdvanceSubscriptions
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(): int
    {
        $graceDays = (int) SystemSetting::getValue('billing.grace_days', 7);
        $changed = 0;

        $subscriptions = Subscription::withoutGlobalScope('tenant')->get();

        foreach ($subscriptions as $subscription) {
            app(TenantContext::class)->set($subscription->tenant_id);
            $next = $this->nextStatus($subscription, $graceDays);

            if ($next === null) {
                continue;
            }

            $before = $subscription->status;
            $subscription->status = $next['status'];
            $subscription->grace_ends_at = $next['grace_ends_at'];
            $subscription->save();
            $changed++;

            $this->audit->log('subscription.status_advanced', $subscription, [
                'status' => $before,
            ], [
                'status' => $subscription->status,
            ]);
        }

        app(TenantContext::class)->clear();

        return $changed;
    }

    /**
     * @return array{status: string, grace_ends_at: Carbon}|null
     */
    private function nextStatus(Subscription $subscription, int $graceDays): ?array
    {
        if (in_array($subscription->status, ['expired', 'suspended'], true)) {
            return null;
        }

        $end = $subscription->status === 'trialing'
            ? $subscription->trial_ends_at
            : $subscription->current_period_end;

        if ($end === null || now()->lessThanOrEqualTo($end)) {
            return null;
        }

        $graceEnds = $end->copy()->addDays($graceDays);
        $status = now()->greaterThan($graceEnds)
            ? 'expired'
            : (now()->lessThanOrEqualTo($end->copy()->addDay()) ? 'past_due' : 'grace');

        $sameGrace = $subscription->grace_ends_at?->toDateTimeString() === $graceEnds->toDateTimeString();

        if ($subscription->status === $status && $sameGrace) {
            return null;
        }

        return [
            'status' => $status,
            'grace_ends_at' => $graceEnds,
        ];
    }
}
