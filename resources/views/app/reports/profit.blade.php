@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.profit_report')">
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

    @if ($report['is_estimated'])
        <x-ui.alert variant="warning" class="mb-4">{{ __('app.profit_estimated_tag') }}</x-ui.alert>
    @endif

    <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            'net_sales' => $report['net_sales'],
            'confirmed_sales' => $report['confirmed_sales'],
            'recipe_cogs' => $report['recipe_cogs'],
            'unlinked_purchases' => $report['unlinked_purchases'],
            'expenses_total' => $report['expenses_total'],
            'estimated_profit' => $report['estimated_profit'],
        ] as $key => $value)
            <x-ui.metric :label="__('app.profit_'.$key)" :value="$value" />
        @endforeach
    </dl>

    @if ($report['missing_recipes'] !== [])
        <section class="mt-8">
            <x-ui.section-title :title="__('app.missing_recipes')" />
            <div class="ui-panel overflow-hidden">
                <ul>
                    @foreach ($report['missing_recipes'] as $name)
                        <li class="border-b border-line px-4 py-2 text-sm last:border-b-0">{{ $name }}</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection
