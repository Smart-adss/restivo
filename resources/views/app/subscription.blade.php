@extends('layouts.app')

@section('content')
    <h1 class="text-2xl font-semibold">{{ __('app.subscription') }}</h1>
    @if ($subscription)
        <dl class="mt-6 max-w-xl space-y-3 rounded-lg border border-line bg-white px-4 py-4 text-sm">
            <div class="flex justify-between gap-4"><dt>{{ __('app.plan') }}</dt><dd>{{ $subscription->plan->name() }}</dd></div>
            <div class="flex justify-between gap-4"><dt>{{ __('app.status') }}</dt><dd>{{ __('app.subscription_status_'.$subscription->status) }}</dd></div>
            <div class="flex justify-between gap-4"><dt>{{ __('app.period_end') }}</dt><dd>{{ $subscription->current_period_end->timezone('Africa/Cairo')->toDateString() }}</dd></div>
            <div class="flex justify-between gap-4"><dt>{{ __('app.branches') }}</dt><dd>{{ $branchCount }} / {{ $branchLimit ?? __('app.unlimited') }}</dd></div>
            <div class="flex justify-between gap-4"><dt>{{ __('app.staff') }}</dt><dd>{{ $userCount }} / {{ $userLimit ?? __('app.unlimited') }}</dd></div>
            <div class="flex justify-between gap-4"><dt>{{ __('app.orders') }}</dt><dd>{{ $orderCount }} / {{ $orderLimit ?? __('app.unlimited') }}</dd></div>
        </dl>
        <ul class="mt-6 max-w-xl divide-y divide-line rounded-lg border border-line bg-white text-sm">
            @foreach ($subscription->invoices as $invoice)
                <li class="flex justify-between px-4 py-3">
                    <span>{{ $invoice->status }}</span>
                    <span>{{ $invoice->total }} {{ $invoice->currency_code }}</span>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
