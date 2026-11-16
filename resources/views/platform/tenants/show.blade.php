@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">{{ $tenant->displayName() }}</h1>
            <p class="mt-1 text-sm text-ink/70">{{ $tenant->slug }}</p>
        </div>
        <p class="text-sm">{{ $tenant->status === 'active' ? __('app.status_active') : __('app.status_suspended') }}</p>
    </div>

    @if (session('temporary_password'))
        <div class="mt-6 max-w-xl rounded-lg border border-accent bg-white px-4 py-4">
            <p class="text-sm font-semibold">{{ __('app.temporary_password') }}</p>
            <p class="mt-2 font-mono text-lg">{{ session('temporary_password') }}</p>
            <p class="mt-2 text-sm text-ink/70">{{ session('owner_email') }} — {{ __('app.temporary_password_hint') }}</p>
        </div>
    @endif

    @if ($subscription)
        <section class="mt-6 max-w-xl rounded-lg border border-line bg-white px-4 py-4 text-sm">
            <p class="font-semibold">{{ $subscription->plan->name() }}</p>
            <p class="mt-1">{{ __('app.subscription_status_'.$subscription->status) }}</p>
            <p class="mt-1 text-ink/70">{{ $subscription->current_period_end->timezone('Africa/Cairo')->toDateString() }}</p>
            @foreach ($subscription->invoices as $invoice)
                <p class="mt-2">{{ $invoice->status }} — {{ $invoice->total }} {{ $invoice->currency_code }}</p>
            @endforeach
        </section>
        @if (auth()->user()->hasPermission('platform.billing.record_payment'))
            <form method="POST" action="{{ route('platform.tenants.payments.store', $tenant->public_id) }}" class="mt-4 max-w-xl space-y-3">
                @csrf
                <select name="invoice_id" class="w-full rounded-md border border-line bg-white px-3 py-3">
                    @foreach ($subscription->invoices->where('status', 'open') as $invoice)
                        <option value="{{ $invoice->id }}">{{ $invoice->total }} — {{ $invoice->public_id }}</option>
                    @endforeach
                </select>
                <input name="provider_ref" placeholder="{{ __('app.provider_ref') }}" class="w-full rounded-md border border-line bg-white px-3 py-3">
                @error('provider_ref')<p class="text-sm text-danger">{{ $message }}</p>@enderror
                <button class="inline-flex min-h-11 items-center rounded-lg bg-brand hover:bg-ink px-4 text-sm font-semibold text-brand-ink">{{ __('app.record_payment') }}</button>
            </form>
        @endif
        @if (auth()->user()->hasPermission('platform.plans.manage'))
            <form method="POST" action="{{ route('platform.tenants.plan.update', $tenant->public_id) }}" class="mt-4 flex max-w-xl gap-2">
                @csrf
                <select name="plan_code" class="w-full rounded-md border border-line bg-white px-3 py-3">
                    @foreach ($plans as $plan)
                        <option value="{{ $plan->code }}" @selected($subscription->plan->code === $plan->code)>{{ $plan->name() }}</option>
                    @endforeach
                </select>
                <button class="inline-flex min-h-11 items-center rounded-md border border-line bg-white px-4 text-sm">{{ __('app.change_plan') }}</button>
            </form>
            @error('plan_code')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
        @endif
    @endif

    @if (auth()->user()->hasPermission('platform.tenants.manage'))
        <form method="POST" action="{{ route($tenant->status === 'active' ? 'platform.tenants.suspend' : 'platform.tenants.activate', $tenant->public_id) }}" class="mt-6">
            @csrf
            <button type="submit" class="inline-flex min-h-11 items-center rounded-md border border-line bg-white px-4 text-sm">
                {{ $tenant->status === 'active' ? __('app.suspend') : __('app.activate') }}
            </button>
        </form>
    @endif
@endsection
