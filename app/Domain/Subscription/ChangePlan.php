<?php

namespace App\Domain\Subscription;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\UsageCounter;
use App\Models\User;
use App\Support\Features\PlanChangeRejected;
use Illuminate\Support\Facades\DB;

class ChangePlan
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Subscription $subscription, Plan $plan): void
    {
        if ($subscription->plan_id === $plan->id) {
            return;
        }

        $this->assertFits($subscription, $plan);

        DB::transaction(function () use ($subscription, $plan): void {
            $subscription = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            $oldPlan = Plan::query()->findOrFail($subscription->plan_id);
            $before = ['plan_id' => $subscription->plan_id, 'plan_code' => $oldPlan->code];

            $oldPrice = (float) $oldPlan->priceFor($subscription->interval);
            $newPrice = (float) $plan->priceFor($subscription->interval);
            $periodSeconds = max(1, $subscription->current_period_end->getTimestamp() - $subscription->current_period_start->getTimestamp());
            $remaining = max(0, $subscription->current_period_end->getTimestamp() - now()->getTimestamp());
            $net = round(($newPrice * $remaining / $periodSeconds) - ($oldPrice * $remaining / $periodSeconds), 2);

            $subscription->update(['plan_id' => $plan->id]);

            $tenant = Tenant::query()->findOrFail($subscription->tenant_id);

            if ($net > 0) {
                SubscriptionInvoice::query()->create([
                    'subscription_id' => $subscription->id,
                    'period_start' => now(),
                    'period_end' => $subscription->current_period_end,
                    'subtotal' => $net,
                    'tax_total' => 0,
                    'total' => $net,
                    'currency_code' => 'EGP',
                    'status' => 'open',
                    'issued_at' => now(),
                ]);
            } elseif ($net < 0) {
                $tenant->credit_balance = round((float) $tenant->credit_balance + abs($net), 2);
                $tenant->save();
            }

            $this->audit->log('subscription.plan_changed', $subscription, $before, [
                'plan_id' => $plan->id,
                'plan_code' => $plan->code,
            ]);
        });
    }

    private function assertFits(Subscription $subscription, Plan $plan): void
    {
        $values = [];

        foreach ($plan->features()->get() as $feature) {
            $values[$feature->key] = $feature->pivot->value;
        }

        $branches = DB::table('branches')->where('tenant_id', $subscription->tenant_id)->where('is_active', true)->count();
        $users = User::query()->where('tenant_id', $subscription->tenant_id)->where('status', 'active')->count();
        $branchLimit = $values['branches.max'] ?? null;
        $userLimit = $values['users.max'] ?? null;
        $orderLimit = $values['orders.monthly'] ?? null;

        $messages = [];

        if ($branchLimit !== null && $branches > (int) $branchLimit) {
            $messages[] = __('app.plan_change_excess_branches', [
                'used' => $branches,
                'limit' => (int) $branchLimit,
            ]);
        }

        if ($branches > 1 && ($values['module.multi_branch'] ?? '0') !== '1') {
            $messages[] = __('app.plan_change_excess_branches', [
                'used' => $branches,
                'limit' => 1,
            ]);
        }

        if ($userLimit !== null && $users > (int) $userLimit) {
            $messages[] = __('app.plan_change_excess_users', [
                'used' => $users,
                'limit' => (int) $userLimit,
            ]);
        }

        $used = (int) UsageCounter::query()
            ->where('feature_key', 'orders.monthly')
            ->whereDate('period_start', now($this->timezone($subscription))->startOfMonth()->toDateString())
            ->value('used');

        if ($orderLimit !== null && $used > (int) $orderLimit) {
            $messages[] = __('app.plan_change_excess_orders', [
                'used' => $used,
                'limit' => (int) $orderLimit,
            ]);
        }

        if ($messages !== []) {
            throw new PlanChangeRejected($messages);
        }
    }

    private function timezone(Subscription $subscription): string
    {
        return Tenant::query()->whereKey($subscription->tenant_id)->value('timezone') ?: 'Africa/Cairo';
    }
}
