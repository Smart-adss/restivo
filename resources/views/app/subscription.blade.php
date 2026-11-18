@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.subscription')" :description="__('app.subscription_page_hint')" />

    @if ($subscription)
        <dl class="ui-panel max-w-xl space-y-3 px-4 py-4 text-sm">
            <div class="flex justify-between gap-4"><dt class="text-ink/65">{{ __('app.plan') }}</dt><dd class="font-semibold">{{ $subscription->plan->name() }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-ink/65">{{ __('app.status') }}</dt><dd>{{ __('app.subscription_status_'.$subscription->status) }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-ink/65">{{ __('app.period_end') }}</dt><dd class="tabular-nums">{{ $subscription->current_period_end->timezone('Africa/Cairo')->toDateString() }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-ink/65">{{ __('app.branches') }}</dt><dd class="tabular-nums">{{ $branchCount }} / {{ $branchLimit ?? __('app.unlimited') }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-ink/65">{{ __('app.staff') }}</dt><dd class="tabular-nums">{{ $userCount }} / {{ $userLimit ?? __('app.unlimited') }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-ink/65">{{ __('app.orders') }}</dt><dd class="tabular-nums">{{ $orderCount }} / {{ $orderLimit ?? __('app.unlimited') }}</dd></div>
        </dl>

        <div class="ui-panel mt-6 max-w-xl overflow-hidden">
            <ul>
                @forelse ($subscription->invoices as $invoice)
                    <li class="flex justify-between border-b border-line px-4 py-3 text-sm last:border-b-0">
                        <span>{{ $invoice->status }}</span>
                        <span class="tabular-nums font-semibold">{{ $invoice->total }} {{ $invoice->currency_code }}</span>
                    </li>
                @empty
                    <li class="px-4 py-6 text-sm text-ink/70">{{ __('app.no_invoices') }}</li>
                @endforelse
            </ul>
        </div>
    @endif
@endsection
