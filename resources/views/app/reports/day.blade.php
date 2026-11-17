@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('app.day_report') }}</h1>
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <select name="branch_id" class="min-h-11 rounded-lg border border-line bg-white px-3">
                @foreach ($branches as $option)
                    <option value="{{ $option->id }}" @selected((int) $branch->id === (int) $option->id)>{{ $option->name() }}</option>
                @endforeach
            </select>
            <input type="date" name="date" value="{{ $date }}" class="min-h-11 rounded-lg border border-line bg-white px-3">
            <button class="min-h-11 rounded-lg border border-line px-3 text-sm">{{ __('app.show') }}</button>
        </form>
    </div>

    <dl class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
            <div class="rounded-lg border border-line bg-white px-4 py-4">
                <dt class="text-sm text-ink/70">{{ __('app.report_'.$key) }}</dt>
                <dd class="mt-2 text-xl font-semibold tabular-nums">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    @if ($stored)
        <p class="mt-6 text-sm text-ink/70">{{ __('app.summary_calculated_at') }}: {{ $stored->calculated_at }}</p>
    @endif
@endsection
