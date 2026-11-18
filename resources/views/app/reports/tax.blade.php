@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.tax_report')">
        <x-slot:actions>
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <x-ui.select name="branch_id">
                    <option value="">{{ __('app.all_branches') }}</option>
                    @foreach ($branches as $option)
                        <option value="{{ $option->id }}" @selected($branch && (int) $branch->id === (int) $option->id)>{{ $option->name() }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.field type="date" name="from" value="{{ $from }}" />
                <x-ui.field type="date" name="to" value="{{ $to }}" />
                <x-ui.button type="submit" variant="secondary">{{ __('app.show') }}</x-ui.button>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    <dl class="grid gap-3 sm:grid-cols-3">
        <x-ui.metric :label="__('app.report_orders_count')" :value="$report['orders_count']" />
        <x-ui.metric :label="__('app.report_gross_total')" :value="$report['gross_total']" />
        <x-ui.metric :label="__('app.report_tax_total')" :value="$report['tax_total']" />
    </dl>

    <div class="ui-panel mt-8 overflow-hidden">
        <ul>
            @forelse ($report['rows'] as $row)
                <li class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3 text-sm last:border-b-0">
                    <span>{{ $row['business_date'] }} · <span class="tabular-nums">{{ $row['orders_count'] }}</span></span>
                    <span class="tabular-nums">{{ __('app.tax_total') }} {{ $row['tax_total'] }}</span>
                </li>
            @empty
                <li class="px-4 py-6 text-sm text-ink/70">{{ __('app.no_tax_rows') }}</li>
            @endforelse
        </ul>
    </div>
@endsection
