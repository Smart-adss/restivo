@php
    $age = (int) $ticket->created_at->diffInMinutes(now());
    $isLate = $age >= $late_minutes;
@endphp
<article class="rounded-lg border border-line bg-white p-4" data-ticket-id="{{ $ticket->public_id }}">
    <div class="flex items-start justify-between gap-2">
        <div>
            <p class="font-semibold">#{{ $ticket->order->number }}</p>
            <p class="mt-1 text-sm text-ink/70">{{ __('app.kitchen_status_'.$ticket->status) }} · {{ $age }} {{ __('app.minutes') }}</p>
        </div>
    </div>
    @if ($isLate)
        <p class="mt-2 text-sm font-semibold">{{ __('app.kitchen_ticket_late') }}</p>
    @endif
    <ul class="mt-3 space-y-1 text-sm">
        @foreach ($ticket->items as $item)
            <li>{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }} × {{ app()->getLocale() === 'ar' ? $item->name_ar : $item->name_en }}@if ($item->notes) — {{ $item->notes }}@endif</li>
        @endforeach
    </ul>
    <div class="mt-4 flex flex-wrap gap-2">
        @if (auth()->user()->hasPermission('kitchen.tickets.update'))
            @if ($ticket->status === 'queued')
                <form method="POST" action="{{ route('app.kitchen.tickets.start', $ticket->public_id) }}">
                    @csrf
                    <button class="min-h-11 rounded-lg border border-line px-3 text-sm">{{ __('app.kitchen_start') }}</button>
                </form>
            @endif
            @if ($ticket->status !== 'ready')
                <form method="POST" action="{{ route('app.kitchen.tickets.ready', $ticket->public_id) }}">
                    @csrf
                    <button class="min-h-11 rounded-lg border border-line px-3 text-sm">{{ __('app.kitchen_ready') }}</button>
                </form>
            @endif
        @endif
    </div>
</article>
