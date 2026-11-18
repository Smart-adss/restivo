@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.branch_comparison')">
        <x-slot:actions>
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <x-ui.field type="date" name="from" value="{{ $from }}" />
                <x-ui.field type="date" name="to" value="{{ $to }}" />
                <x-ui.button type="submit" variant="secondary">{{ __('app.show') }}</x-ui.button>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="ui-panel overflow-hidden">
        <ul>
            @forelse ($rows as $row)
                <li class="border-b border-line px-4 py-3 text-sm last:border-b-0">
                    <div class="font-semibold">{{ $row['branch_name'] }}</div>
                    <p class="mt-1 text-ink/70 tabular-nums">
                        {{ __('app.report_orders_count') }} {{ $row['orders_count'] }}
                        · {{ __('app.report_gross_total') }} {{ $row['gross_total'] }}
                        · {{ __('app.report_tax_total') }} {{ $row['tax_total'] }}
                        · {{ __('app.report_net_total') }} {{ $row['net_total'] }}
                    </p>
                </li>
            @empty
                <li class="px-4 py-6 text-sm text-ink/70">{{ __('app.no_branch_comparison') }}</li>
            @endforelse
        </ul>
    </div>
@endsection
