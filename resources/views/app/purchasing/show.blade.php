@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('app.purchase_order') }} #{{ $order->public_id }}</h1>
        <a href="{{ route('app.purchasing.index') }}" class="min-h-11 rounded-lg px-3 text-sm text-ink/70 hover:bg-canvas">{{ __('app.purchasing') }}</a>
    </div>

    @if (session('status'))
        <p class="mt-4 rounded-lg border border-line bg-white px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif

    <p class="mt-4 text-sm text-ink/70">
        {{ $order->supplier?->name }} · {{ $order->branch?->name() }} · {{ __('app.purchase_status_'.$order->status) }} · {{ $order->total }}
    </p>

    <ul class="mt-6 space-y-2 rounded-lg border border-line bg-white p-4">
        @foreach ($order->lines as $line)
            <li class="flex flex-wrap items-center justify-between gap-2 border-b border-line py-2 text-sm">
                <span>{{ $line->stockItem?->name() }}</span>
                <span>{{ $line->quantity }} @ {{ $line->unit_cost }} · {{ __('app.received') }} {{ $line->received_qty }}</span>
            </li>
        @endforeach
    </ul>

    @if (in_array($order->status, ['draft', 'sent', 'partial'], true))
        <form method="POST" action="{{ route('app.purchasing.receive', $order->public_id) }}" class="mt-6 space-y-3 rounded-lg border border-line bg-white p-4">
            @csrf
            <h2 class="font-semibold">{{ __('app.receive_purchase') }}</h2>
            @foreach ($order->lines as $index => $line)
                @if (bccomp($line->remainingQty(), '0', 3) === 1)
                    <input type="hidden" name="lines[{{ $index }}][line_id]" value="{{ $line->id }}">
                    <label class="block text-sm">
                        {{ $line->stockItem?->name() }} ({{ __('app.remaining') }} {{ $line->remainingQty() }})
                        <input name="lines[{{ $index }}][quantity]" type="number" step="0.001" min="0.001" max="{{ $line->remainingQty() }}" value="{{ $line->remainingQty() }}" required class="mt-1 min-h-11 w-full rounded-lg border border-line px-3">
                    </label>
                @endif
            @endforeach
            <button class="min-h-11 rounded-lg bg-brand px-4 text-sm font-semibold text-brand-ink">{{ __('app.receive') }}</button>
        </form>
    @endif
@endsection
