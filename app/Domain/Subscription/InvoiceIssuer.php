<?php

namespace App\Domain\Subscription;

use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceIssuer
{
    public function __construct(private AuditLogger $audit) {}

    public function issue(Subscription $subscription, Carbon $start, Carbon $end): ?SubscriptionInvoice
    {
        return DB::transaction(function () use ($subscription, $start, $end): ?SubscriptionInvoice {
            $existing = SubscriptionInvoice::query()
                ->where('subscription_id', $subscription->id)
                ->where('period_start', $start)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return null;
            }

            $amount = (float) $subscription->plan()->firstOrFail()->priceFor($subscription->interval);
            $tenant = Tenant::query()->find(app(TenantContext::class)->id() ?? $subscription->tenant_id);
            $credit = min((float) $tenant->credit_balance, $amount);

            try {
                $invoice = SubscriptionInvoice::query()->create([
                    'subscription_id' => $subscription->id,
                    'period_start' => $start,
                    'period_end' => $end,
                    'subtotal' => round($amount, 2),
                    'tax_total' => 0,
                    'total' => round($amount - $credit, 2),
                    'currency_code' => 'EGP',
                    'status' => 'open',
                    'issued_at' => now(),
                ]);
            } catch (QueryException) {
                return null;
            }

            if ($credit > 0) {
                $tenant->credit_balance = round((float) $tenant->credit_balance - $credit, 2);
                $tenant->save();
            }

            $this->audit->log('billing.invoice_issued', $invoice, null, [
                'total' => $invoice->total,
                'period_start' => $start->toDateTimeString(),
                'period_end' => $end->toDateTimeString(),
            ]);

            return $invoice;
        });
    }
}
