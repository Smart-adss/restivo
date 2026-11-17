@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('app.stock_count') }} #{{ $count->public_id }}</h1>
        <a href="{{ route('app.stock-counts.index') }}" class="min-h-11 rounded-lg px-3 text-sm text-ink/70 hover:bg-canvas">{{ __('app.stock_counts') }}</a>
    </div>

    @if (session('status'))
        <p class="mt-4 rounded-lg border border-line bg-white px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif

    <p class="mt-4 text-sm text-ink/70">{{ $count->branch?->name() }} · {{ __('app.stock_count_status_'.$count->status) }}</p>

    <ul class="mt-6 space-y-2 rounded-lg border border-line bg-white p-4">
        @foreach ($count->lines as $line)
            <li class="flex flex-wrap items-center justify-between gap-2 border-b border-line py-2 text-sm">
                <span>{{ $line->stockItem?->name() }}</span>
                <span>{{ __('app.expected') }} {{ $line->expected_qty }} → {{ __('app.counted') }} {{ $line->counted_qty }}</span>
            </li>
        @endforeach
    </ul>

    @if ($count->status === 'draft')
        <form method="POST" action="{{ route('app.stock-counts.post', $count->public_id) }}" class="mt-6">
            @csrf
            <button class="min-h-11 rounded-lg bg-brand px-4 text-sm font-semibold text-brand-ink">{{ __('app.post_stock_count') }}</button>
        </form>
    @endif
@endsection
