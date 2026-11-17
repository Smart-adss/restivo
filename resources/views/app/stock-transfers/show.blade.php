@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('app.stock_transfer') }} #{{ $transfer->public_id }}</h1>
        <a href="{{ route('app.stock-transfers.index') }}" class="min-h-11 rounded-lg px-3 text-sm text-ink/70 hover:bg-canvas">{{ __('app.stock_transfers') }}</a>
    </div>

    @if (session('status'))
        <p class="mt-4 rounded-lg border border-line bg-white px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif

    <p class="mt-4 text-sm text-ink/70">
        {{ $transfer->fromBranch?->name() }} → {{ $transfer->toBranch?->name() }} · {{ __('app.stock_transfer_status_'.$transfer->status) }}
    </p>

    <ul class="mt-6 space-y-2 rounded-lg border border-line bg-white p-4">
        @foreach ($transfer->lines as $line)
            <li class="flex flex-wrap items-center justify-between gap-2 border-b border-line py-2 text-sm">
                <span>{{ $line->stockItem?->name() }}</span>
                <span>{{ $line->quantity }}</span>
            </li>
        @endforeach
    </ul>

    <div class="mt-6 flex flex-wrap gap-3">
        @if ($transfer->status === 'draft')
            <form method="POST" action="{{ route('app.stock-transfers.send', $transfer->public_id) }}">
                @csrf
                <button class="min-h-11 rounded-lg bg-brand px-4 text-sm font-semibold text-brand-ink">{{ __('app.send_transfer') }}</button>
            </form>
        @endif
        @if ($transfer->status === 'sent')
            <form method="POST" action="{{ route('app.stock-transfers.receive', $transfer->public_id) }}">
                @csrf
                <button class="min-h-11 rounded-lg bg-brand px-4 text-sm font-semibold text-brand-ink">{{ __('app.receive_transfer') }}</button>
            </form>
        @endif
    </div>
@endsection
