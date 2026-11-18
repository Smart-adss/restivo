@php
    $age = (int) $ticket->created_at->diffInMinutes(now());
    $isLate = $age >= $late_minutes;
    $ticketClass = 'ops-ticket';
    if ($isLate) {
        $ticketClass .= ' ops-ticket-late';
    } elseif ($ticket->status === 'ready') {
        $ticketClass .= ' ops-ticket-ready';
    }
@endphp
<article class="{{ $ticketClass }}" data-ticket-id="{{ $ticket->public_id }}">
    <div class="flex items-start justify-between gap-2">
        <div>
            <p class="text-xl font-semibold tracking-tight tabular-nums">#{{ $ticket->order->number }}</p>
            <p class="mt-1 text-sm text-ink/70">
                {{ __('app.kitchen_status_'.$ticket->status) }}
                · <span class="tabular-nums">{{ $age }}</span> {{ __('app.minutes') }}
            </p>
        </div>
        <span class="rounded-md bg-canvas px-2 py-1 text-xs font-semibold uppercase tracking-wide text-ink/70">
            {{ __('app.kitchen_status_'.$ticket->status) }}
        </span>
    </div>
    @if ($isLate)
        <p class="mt-3 text-sm font-semibold text-danger">{{ __('app.kitchen_ticket_late') }}</p>
    @endif
    <ul class="mt-3 space-y-1.5 text-sm">
        @foreach ($ticket->items as $item)
            <li class="flex gap-2">
                <span class="font-semibold tabular-nums">{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }}</span>
                <span>×</span>
                <span>{{ app()->getLocale() === 'ar' ? $item->name_ar : $item->name_en }}@if ($item->notes) — {{ $item->notes }}@endif</span>
            </li>
        @endforeach
    </ul>
    <div class="mt-4 flex flex-wrap gap-2">
        @if (auth()->user()->hasPermission('kitchen.tickets.update'))
            @if ($ticket->status === 'queued')
                <form method="POST" action="{{ route('app.kitchen.tickets.start', $ticket->public_id) }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary">{{ __('app.kitchen_start') }}</x-ui.button>
                </form>
            @endif
            @if ($ticket->status !== 'ready')
                <form method="POST" action="{{ route('app.kitchen.tickets.ready', $ticket->public_id) }}">
                    @csrf
                    <x-ui.button type="submit" variant="primary">{{ __('app.kitchen_ready') }}</x-ui.button>
                </form>
            @endif
        @endif
    </div>
</article>
