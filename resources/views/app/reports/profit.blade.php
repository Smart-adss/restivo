@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('app.profit_report') }}</h1>
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

    @if ($report['is_estimated'])
        <p class="mt-4 rounded-lg border border-line bg-white px-4 py-3 text-sm text-ink/80">{{ __('app.profit_estimated_tag') }}</p>
    @endif

    <dl class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            'net_sales' => $report['net_sales'],
            'confirmed_sales' => $report['confirmed_sales'],
            'recipe_cogs' => $report['recipe_cogs'],
            'unlinked_purchases' => $report['unlinked_purchases'],
            'expenses_total' => $report['expenses_total'],
            'estimated_profit' => $report['estimated_profit'],
        ] as $key => $value)
            <div class="rounded-lg border border-line bg-white px-4 py-4">
                <dt class="text-sm text-ink/70">{{ __('app.profit_'.$key) }}</dt>
                <dd class="mt-2 text-xl font-semibold tabular-nums">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    @if ($report['missing_recipes'] !== [])
        <section class="mt-8">
            <h2 class="mb-2 text-lg font-semibold">{{ __('app.missing_recipes') }}</h2>
            <ul class="rounded-lg border border-line bg-white p-4 text-sm">
                @foreach ($report['missing_recipes'] as $name)
                    <li class="border-b border-line py-2">{{ $name }}</li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
