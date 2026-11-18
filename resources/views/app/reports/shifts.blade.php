@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.shift_cash_report')">
        <x-slot:actions>
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <x-ui.select name="branch_id">
                    @foreach ($branches as $option)
                        <option value="{{ $option->id }}" @selected((int) $branch->id === (int) $option->id)>{{ $option->name() }}</option>
                    @endforeach
                </x-ui.select>
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
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-semibold">#{{ $row['public_id'] }} · {{ __('app.shift_status_'.$row['status']) }}</span>
                        <span class="tabular-nums">{{ __('app.expected_cash') }} {{ $row['expected_cash'] }}</span>
                    </div>
                    <p class="mt-1 text-ink/70">
                        {{ __('app.opening_float') }} <span class="tabular-nums">{{ $row['opening_float'] }}</span>
                        · {{ __('app.movements_total') }} <span class="tabular-nums">{{ $row['movements_total'] }}</span>
                        @if ($row['counted_cash'] !== null)
                            · {{ __('app.counted_cash') }} <span class="tabular-nums">{{ $row['counted_cash'] }}</span>
                            · {{ __('app.difference') }} <span class="tabular-nums">{{ $row['difference'] }}</span>
                        @endif
                        · {{ $row['matches_movements'] ? __('app.matches_movements') : __('app.mismatch_movements') }}
                    </p>
                </li>
            @empty
                <li class="px-4 py-6 text-sm text-ink/70">{{ __('app.no_shifts') }}</li>
            @endforelse
        </ul>
    </div>
@endsection
