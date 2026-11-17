@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('app.expenses') }}</h1>
        <form method="GET" class="flex items-center gap-2">
            <select name="branch_id" class="min-h-11 rounded-lg border border-line bg-white px-3" onchange="this.form.submit()">
                @foreach ($branches as $option)
                    <option value="{{ $option->id }}" @selected($branch && (int) $branch->id === (int) $option->id)>{{ $option->name() }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if (session('status'))
        <p class="mt-4 rounded-lg border border-line bg-white px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <form method="POST" action="{{ route('app.expenses.categories.store') }}" class="space-y-3 rounded-lg border border-line bg-white p-4">
            @csrf
            <h2 class="font-semibold">{{ __('app.add_expense_category') }}</h2>
            <input name="name_ar" required maxlength="120" placeholder="{{ __('app.name_ar') }}" class="min-h-11 w-full rounded-lg border border-line px-3">
            <input name="name_en" required maxlength="120" placeholder="{{ __('app.name_en') }}" class="min-h-11 w-full rounded-lg border border-line px-3">
            <button class="min-h-11 rounded-lg bg-brand px-4 text-sm font-semibold text-brand-ink">{{ __('app.save') }}</button>
        </form>

        <form method="POST" action="{{ route('app.expenses.store') }}" class="space-y-3 rounded-lg border border-line bg-white p-4">
            @csrf
            <h2 class="font-semibold">{{ __('app.add_expense') }}</h2>
            <select name="branch_id" required class="min-h-11 w-full rounded-lg border border-line px-3">
                @foreach ($branches as $option)
                    <option value="{{ $option->id }}" @selected($branch && (int) $branch->id === (int) $option->id)>{{ $option->name() }}</option>
                @endforeach
            </select>
            <select name="category_id" required class="min-h-11 w-full rounded-lg border border-line px-3">
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name() }}</option>
                @endforeach
            </select>
            <input name="amount" type="number" step="0.01" min="0.01" required placeholder="{{ __('app.amount') }}" class="min-h-11 w-full rounded-lg border border-line px-3">
            <input name="tax_amount" type="number" step="0.01" min="0" placeholder="{{ __('app.tax_amount') }}" class="min-h-11 w-full rounded-lg border border-line px-3">
            <input name="incurred_on" type="date" required value="{{ now()->toDateString() }}" class="min-h-11 w-full rounded-lg border border-line px-3">
            <input name="note" maxlength="255" placeholder="{{ __('app.notes') }}" class="min-h-11 w-full rounded-lg border border-line px-3">
            <button class="min-h-11 rounded-lg bg-brand px-4 text-sm font-semibold text-brand-ink">{{ __('app.save') }}</button>
        </form>
    </div>

    <section class="mt-8">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-semibold">{{ __('app.expenses') }}</h2>
            @if ($branch)
                <a href="{{ route('app.reports.export.expenses', ['branch_id' => $branch->id, 'from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()]) }}" class="text-sm text-brand hover:underline">{{ __('app.export_expenses_csv') }}</a>
            @endif
        </div>
        <ul class="space-y-2 rounded-lg border border-line bg-white p-4">
            @forelse ($expenses as $expense)
                <li class="flex flex-wrap items-center justify-between gap-2 border-b border-line py-2 text-sm">
                    <span>{{ $expense->category?->name() }} · {{ $expense->incurred_on?->toDateString() }}</span>
                    <span class="font-semibold tabular-nums">{{ $expense->amount }}</span>
                </li>
            @empty
                <li class="text-sm text-ink/70">{{ __('app.no_expenses') }}</li>
            @endforelse
        </ul>
    </section>
@endsection
