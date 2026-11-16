<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Subscription\ChangePlan;
use App\Domain\Subscription\InvoiceIssuer;
use App\Domain\Subscription\RecordPayment;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Support\Features\DuplicatePayment;
use App\Support\Features\PlanChangeRejected;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BillingController extends Controller
{
    public function pay(Request $request, Tenant $tenant, RecordPayment $payments): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('platform.billing.record_payment'), 403);
        app(TenantContext::class)->set($tenant->id);

        $data = $request->validate([
            'invoice_id' => ['required', 'integer'],
            'provider_ref' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $invoice = SubscriptionInvoice::query()->whereKey($data['invoice_id'])->firstOrFail();

        try {
            $payments->handle($invoice, 'manual', $data['provider_ref'], $data['note'] ?? null);
        } catch (DuplicatePayment) {
            throw ValidationException::withMessages([
                'provider_ref' => __('app.duplicate_payment'),
            ]);
        }

        return redirect()->route('platform.tenants.show', $tenant->public_id)->with('status', __('app.payment_recorded'));
    }

    public function changePlan(Request $request, Tenant $tenant, ChangePlan $changePlan): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('platform.plans.manage'), 403);
        app(TenantContext::class)->set($tenant->id);

        $data = $request->validate([
            'plan_code' => ['required', Rule::exists('plans', 'code')->where('is_public', 1)],
        ]);

        $subscription = $tenant->subscription;
        $plan = Plan::query()->where('code', $data['plan_code'])->firstOrFail();

        try {
            $changePlan->handle($subscription, $plan);
        } catch (PlanChangeRejected) {
            throw ValidationException::withMessages([
                'plan_code' => __('app.plan_change_rejected'),
            ]);
        }

        return redirect()->route('platform.tenants.show', $tenant->public_id)->with('status', __('app.plan_changed'));
    }

    public function issueRenewal(Tenant $tenant, InvoiceIssuer $invoices): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('platform.billing.record_payment'), 403);
        app(TenantContext::class)->set($tenant->id);
        $subscription = $tenant->subscription;
        $start = $subscription->current_period_end->copy();
        $end = $subscription->interval === 'yearly' ? $start->copy()->addYear() : $start->copy()->addMonth();
        $invoices->issue($subscription, $start, $end);

        return redirect()->route('platform.tenants.show', $tenant->public_id);
    }
}
