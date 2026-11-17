@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('app.tax_report') }}</h1>
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <select name="branch_id" class="min-h-11 rounded-lg border border-line bg-white px-3">
                <option value="">{{ __('app.all_branches') }}</option>
                @foreach ($branches as $option)
                    <option value="{{ $option->id }}" @selected($branch && (int) $branch->id === (int) $option->id)>{{ $option->name() }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ $from }}" class="min-h-11 rounded-lg border border-line bg-white px-3">
            <input type="date" name="to" value="{{ $to }}" class="min-h-11 rounded-lg border border-line bg-white px-3">
            <button class="min-h-11 rounded-lg border border-line px-3 text-sm">{{ __('app.show') }}</button>
        </form>
    </div>

    <dl class="mt-8 grid gap-4 sm:grid-cols-3">
        <div class="rounded-lg border border-line bg-white px-4 py-4">
            <dt class="text-sm text-ink/70">{{ __('app.report_orders_count') }}</dt>
            <dd class="mt-2 text-xl font-semibold tabular-nums">{{ $report['orders_count'] }}</dd>
        </div>
        <div class="rounded-lg border border-line bg-white px-4 py-4">
            <dt class="text-sm text-ink/70">{{ __('app.report_gross_total') }}</dt>
            <dd class="mt-2 text-xl font-semibold tabular-nums">{{ $report['gross_total'] }}</dd>
        </div>
        <div class="rounded-lg border border-line bg-white px-4 py-4">
            <dt class="text-sm text-ink/70">{{ __('app.report_tax_total') }}</dt>
            <dd class="mt-2 text-xl font-semibold tabular-nums">{{ $report['tax_total'] }}</dd>
        </div>
    </dl>

    <ul class="mt-8 space-y-2 rounded-lg border border-line bg-white p-4">
        @forelse ($report['rows'] as $row)
            <li class="flex flex-wrap items-center justify-between gap-2 border-b border-line py-2 text-sm">
                <span>{{ $row['business_date'] }} · {{ $row['orders_count'] }}</span>
                <span class="tabular-nums">{{ __('app.tax_total') }} {{ $row['tax_total'] }}</span>
            </li>
        @empty
            <li class="text-sm text-ink/70">{{ __('app.no_tax_rows') }}</li>
        @endforelse
    </ul>
@endsection
