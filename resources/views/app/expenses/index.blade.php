@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.expenses')">
        <x-slot:actions>
            <form method="GET" class="flex items-end gap-2">
                <x-ui.select name="branch_id" onchange="this.form.submit()">
                    @foreach ($branches as $option)
                        <option value="{{ $option->id }}" @selected($branch && (int) $branch->id === (int) $option->id)>{{ $option->name() }}</option>
                    @endforeach
                </x-ui.select>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 lg:grid-cols-2">
        <form method="POST" action="{{ route('app.expenses.categories.store') }}" class="ui-panel space-y-3 p-4">
            @csrf
            <x-ui.section-title :title="__('app.add_expense_category')" />
            <x-ui.field name="name_ar" required maxlength="120" placeholder="{{ __('app.name_ar') }}" />
            <x-ui.field name="name_en" required maxlength="120" placeholder="{{ __('app.name_en') }}" />
            <x-ui.button type="submit" variant="primary">{{ __('app.save') }}</x-ui.button>
        </form>

        <form method="POST" action="{{ route('app.expenses.store') }}" class="ui-panel space-y-3 p-4">
            @csrf
            <x-ui.section-title :title="__('app.add_expense')" />
            <x-ui.select name="branch_id" required>
                @foreach ($branches as $option)
                    <option value="{{ $option->id }}" @selected($branch && (int) $branch->id === (int) $option->id)>{{ $option->name() }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select name="category_id" required>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name() }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.field name="amount" type="number" step="0.01" min="0.01" required placeholder="{{ __('app.amount') }}" class="tabular-nums" />
            <x-ui.field name="tax_amount" type="number" step="0.01" min="0" placeholder="{{ __('app.tax_amount') }}" class="tabular-nums" />
            <x-ui.field name="incurred_on" type="date" required value="{{ now()->toDateString() }}" />
            <x-ui.field name="note" maxlength="255" placeholder="{{ __('app.notes') }}" />
            <x-ui.button type="submit" variant="primary">{{ __('app.save') }}</x-ui.button>
        </form>
    </div>

    <section class="mt-8">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <x-ui.section-title :title="__('app.expenses')" class="mb-0" />
            @if ($branch)
                <a href="{{ route('app.reports.export.expenses', ['branch_id' => $branch->id, 'from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()]) }}" class="text-sm font-semibold text-brand hover:underline">{{ __('app.export_expenses_csv') }}</a>
            @endif
        </div>
        <div class="ui-panel overflow-hidden">
            <ul>
                @forelse ($expenses as $expense)
                    <li class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3 text-sm last:border-b-0">
                        <span>{{ $expense->category?->name() }} · {{ $expense->incurred_on?->toDateString() }}</span>
                        <span class="font-semibold tabular-nums">{{ $expense->amount }}</span>
                    </li>
                @empty
                    <li class="px-4 py-6 text-sm text-ink/70">{{ __('app.no_expenses') }}</li>
                @endforelse
            </ul>
        </div>
    </section>
@endsection
