@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('app.shift_cash_report') }}</h1>
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <select name="branch_id" class="min-h-11 rounded-lg border border-line bg-white px-3">
                @foreach ($branches as $option)
                    <option value="{{ $option->id }}" @selected((int) $branch->id === (int) $option->id)>{{ $option->name() }}</option>
                @endforeach
            </select>
            <input type="date" name="from" value="{{ $from }}" class="min-h-11 rounded-lg border border-line bg-white px-3">
            <input type="date" name="to" value="{{ $to }}" class="min-h-11 rounded-lg border border-line bg-white px-3">
            <button class="min-h-11 rounded-lg border border-line px-3 text-sm">{{ __('app.show') }}</button>
        </form>
    </div>

    <ul class="mt-8 space-y-2 rounded-lg border border-line bg-white p-4">
        @forelse ($rows as $row)
            <li class="border-b border-line py-3 text-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-medium">#{{ $row['public_id'] }} · {{ __('app.shift_status_'.$row['status']) }}</span>
                    <span class="tabular-nums">{{ __('app.expected_cash') }} {{ $row['expected_cash'] }}</span>
                </div>
                <p class="mt-1 text-ink/70">
                    {{ __('app.opening_float') }} {{ $row['opening_float'] }}
                    · {{ __('app.movements_total') }} {{ $row['movements_total'] }}
                    @if ($row['counted_cash'] !== null)
                        · {{ __('app.counted_cash') }} {{ $row['counted_cash'] }}
                        · {{ __('app.difference') }} {{ $row['difference'] }}
                    @endif
                    · {{ $row['matches_movements'] ? __('app.matches_movements') : __('app.mismatch_movements') }}
                </p>
            </li>
        @empty
            <li class="text-sm text-ink/70">{{ __('app.no_shifts') }}</li>
        @endforelse
    </ul>
@endsection
