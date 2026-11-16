<?php

namespace App\Domain\Subscription;

use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Support\Features\DuplicatePayment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class RecordPayment
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(SubscriptionInvoice $invoice, string $method, string $providerRef, ?string $note = null): void
    {
        DB::transaction(function () use ($invoice, $method, $providerRef, $note): void {
            $locked = SubscriptionInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'paid' || $this->referenceExists($method, $providerRef)) {
                throw new DuplicatePayment;
            }

            $subscription = Subscription::query()->whereKey($locked->subscription_id)->lockForUpdate()->firstOrFail();
            $before = [
                'status' => $subscription->status,
                'current_period_end' => $subscription->current_period_end->toDateTimeString(),
            ];

            try {
                SubscriptionPayment::query()->create([
                    'invoice_id' => $locked->id,
                    'amount' => $locked->total,
                    'method' => $method,
                    'provider_ref' => $providerRef,
                    'note' => $note,
                ]);
            } catch (QueryException) {
                throw new DuplicatePayment;
            }

            $locked->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $subscription->update([
                'status' => 'active',
                'current_period_start' => $locked->period_start,
                'current_period_end' => $locked->period_end,
                'grace_ends_at' => null,
            ]);

            $this->audit->log('billing.payment_recorded', $subscription, $before, [
                'status' => 'active',
                'current_period_end' => $subscription->current_period_end->toDateTimeString(),
                'provider_ref' => $providerRef,
            ]);
        });
    }

    private function referenceExists(string $method, string $providerRef): bool
    {
        return SubscriptionPayment::query()
            ->where('method', $method)
            ->where('provider_ref', $providerRef)
            ->exists();
    }
}
