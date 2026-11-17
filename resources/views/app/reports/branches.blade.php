@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('app.branch_comparison') }}</h1>
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="date" name="from" value="{{ $from }}" class="min-h-11 rounded-lg border border-line bg-white px-3">
            <input type="date" name="to" value="{{ $to }}" class="min-h-11 rounded-lg border border-line bg-white px-3">
            <button class="min-h-11 rounded-lg border border-line px-3 text-sm">{{ __('app.show') }}</button>
        </form>
    </div>

    <ul class="mt-8 space-y-2 rounded-lg border border-line bg-white p-4">
        @forelse ($rows as $row)
            <li class="border-b border-line py-3 text-sm">
                <div class="font-semibold">{{ $row['branch_name'] }}</div>
                <p class="mt-1 text-ink/70 tabular-nums">
                    {{ __('app.report_orders_count') }} {{ $row['orders_count'] }}
                    · {{ __('app.report_gross_total') }} {{ $row['gross_total'] }}
                    · {{ __('app.report_tax_total') }} {{ $row['tax_total'] }}
                    · {{ __('app.report_net_total') }} {{ $row['net_total'] }}
                </p>
            </li>
        @empty
            <li class="text-sm text-ink/70">{{ __('app.no_branch_comparison') }}</li>
        @endforelse
    </ul>
@endsection
