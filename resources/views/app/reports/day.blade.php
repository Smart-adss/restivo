@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.day_report')" :description="__('app.day_report_hint')">
        <x-slot:actions>
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <div>
                    <x-ui.label for="day-branch">{{ __('app.branch') }}</x-ui.label>
                    <x-ui.select id="day-branch" name="branch_id">
                        @foreach ($branches as $option)
                            <option value="{{ $option->id }}" @selected((int) $branch->id === (int) $option->id)>{{ $option->name() }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div>
                    <x-ui.label for="day-date">{{ __('app.date') }}</x-ui.label>
                    <x-ui.field id="day-date" type="date" name="date" value="{{ $date }}" />
                </div>
                <x-ui.button type="submit" variant="secondary">{{ __('app.show') }}</x-ui.button>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            'orders_count' => $live['orders_count'],
            'gross_total' => $live['gross_total'],
            'discount_total' => $live['discount_total'],
            'tax_total' => $live['tax_total'],
            'net_total' => $live['net_total'],
            'cash_total' => $live['cash_total'],
            'electronic_total' => $live['electronic_total'],
            'refund_total' => $live['refund_total'],
            'delivery_fee_total' => $live['delivery_fee_total'],
        ] as $key => $value)
            <x-ui.metric :label="__('app.report_'.$key)" :value="$value" />
        @endforeach
    </dl>

    @if ($stored)
        <p class="mt-6 text-sm text-ink/70">{{ __('app.summary_calculated_at') }}: <span class="tabular-nums">{{ $stored->calculated_at }}</span></p>
    @endif

    <div class="mt-8 flex flex-wrap gap-3 text-sm">
        <a href="{{ route('app.reports.tax', ['branch_id' => $branch->id, 'from' => $date, 'to' => $date]) }}" class="font-semibold text-brand hover:underline">{{ __('app.tax_report') }}</a>
        <a href="{{ route('app.reports.shifts', ['branch_id' => $branch->id]) }}" class="font-semibold text-brand hover:underline">{{ __('app.shift_cash_report') }}</a>
        <a href="{{ route('app.reports.export.orders', ['branch_id' => $branch->id, 'from' => $date, 'to' => $date]) }}" class="font-semibold text-brand hover:underline">{{ __('app.export_orders_csv') }}</a>
    </div>
@endsection
