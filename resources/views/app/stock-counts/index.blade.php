@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('app.stock_counts') }}</h1>
    </div>

    @if (session('status'))
        <p class="mt-4 rounded-lg border border-line bg-white px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('app.stock-counts.store') }}" class="mt-6 max-w-xl space-y-3 rounded-lg border border-line bg-white p-4">
        @csrf
        <h2 class="font-semibold">{{ __('app.add_stock_count') }}</h2>
        <select name="branch_id" required class="min-h-11 w-full rounded-lg border border-line px-3">
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}">{{ $branch->name() }}</option>
            @endforeach
        </select>
        <select name="lines[0][stock_item_id]" required class="min-h-11 w-full rounded-lg border border-line px-3">
            @foreach ($items as $item)
                <option value="{{ $item->id }}">{{ $item->name() }}</option>
            @endforeach
        </select>
        <input name="lines[0][counted_qty]" type="number" step="0.001" min="0" required placeholder="{{ __('app.counted_qty') }}" class="min-h-11 w-full rounded-lg border border-line px-3">
        <button class="min-h-11 rounded-lg bg-brand px-4 text-sm font-semibold text-brand-ink">{{ __('app.save') }}</button>
    </form>

    <section class="mt-8">
        <ul class="space-y-2 rounded-lg border border-line bg-white p-4">
            @forelse ($counts as $count)
                <li class="flex flex-wrap items-center justify-between gap-2 border-b border-line py-2 text-sm">
                    <a href="{{ route('app.stock-counts.show', $count->public_id) }}" class="font-medium text-brand hover:underline">#{{ $count->public_id }}</a>
                    <span>{{ $count->branch?->name() }} · {{ __('app.stock_count_status_'.$count->status) }}</span>
                </li>
            @empty
                <li class="text-sm text-ink/70">{{ __('app.no_stock_counts') }}</li>
            @endforelse
        </ul>
    </section>
@endsection
