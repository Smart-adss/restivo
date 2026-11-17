@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('app.stock_transfers') }}</h1>
    </div>

    @if (session('status'))
        <p class="mt-4 rounded-lg border border-line bg-white px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif

    @if ($multiBranch)
        <form method="POST" action="{{ route('app.stock-transfers.store') }}" class="mt-6 max-w-xl space-y-3 rounded-lg border border-line bg-white p-4">
            @csrf
            <h2 class="font-semibold">{{ __('app.add_stock_transfer') }}</h2>
            <select name="from_branch_id" required class="min-h-11 w-full rounded-lg border border-line px-3">
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}">{{ __('app.from') }}: {{ $branch->name() }}</option>
                @endforeach
            </select>
            <select name="to_branch_id" required class="min-h-11 w-full rounded-lg border border-line px-3">
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}">{{ __('app.to') }}: {{ $branch->name() }}</option>
                @endforeach
            </select>
            <select name="lines[0][stock_item_id]" required class="min-h-11 w-full rounded-lg border border-line px-3">
                @foreach ($items as $item)
                    <option value="{{ $item->id }}">{{ $item->name() }}</option>
                @endforeach
            </select>
            <input name="lines[0][quantity]" type="number" step="0.001" min="0.001" required placeholder="{{ __('app.quantity') }}" class="min-h-11 w-full rounded-lg border border-line px-3">
            <button class="min-h-11 rounded-lg bg-brand px-4 text-sm font-semibold text-brand-ink">{{ __('app.save') }}</button>
        </form>
    @else
        <p class="mt-6 text-sm text-ink/70">{{ __('app.multi_branch_required') }}</p>
    @endif

    <section class="mt-8">
        <ul class="space-y-2 rounded-lg border border-line bg-white p-4">
            @forelse ($transfers as $transfer)
                <li class="flex flex-wrap items-center justify-between gap-2 border-b border-line py-2 text-sm">
                    <a href="{{ route('app.stock-transfers.show', $transfer->public_id) }}" class="font-medium text-brand hover:underline">#{{ $transfer->public_id }}</a>
                    <span>{{ $transfer->fromBranch?->name() }} → {{ $transfer->toBranch?->name() }} · {{ __('app.stock_transfer_status_'.$transfer->status) }}</span>
                </li>
            @empty
                <li class="text-sm text-ink/70">{{ __('app.no_stock_transfers') }}</li>
            @endforelse
        </ul>
    </section>
@endsection
