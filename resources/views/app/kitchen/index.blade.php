@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('app.kitchen') }}</h1>
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <select name="branch_id" class="min-h-11 rounded-lg border border-line bg-white px-3" onchange="this.form.submit()">
                @foreach ($branches as $option)
                    <option value="{{ $option->id }}" @selected((int) $branch->id === (int) $option->id)>{{ $option->name() }}</option>
                @endforeach
            </select>
            @if ($stations->isNotEmpty())
                <select name="station_id" class="min-h-11 rounded-lg border border-line bg-white px-3" onchange="this.form.submit()">
                    @foreach ($stations as $station)
                        <option value="{{ $station->public_id }}" @selected($selected_station?->id === $station->id)>{{ $station->name() }}</option>
                    @endforeach
                </select>
            @endif
        </form>
    </div>

    @if (session('status'))
        <p class="mt-4 rounded-lg border border-line bg-white px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif

    @if (auth()->user()->hasPermission('tenant.settings.manage'))
        <form method="POST" action="{{ route('app.kitchen.stations.store') }}" class="mt-6 grid gap-3 rounded-lg border border-line bg-white p-4 md:grid-cols-5">
            @csrf
            <input type="hidden" name="branch_id" value="{{ $branch->id }}">
            <input name="name_ar" required maxlength="120" placeholder="{{ __('app.name_ar') }}" class="min-h-11 rounded-lg border border-line px-3">
            <input name="name_en" required maxlength="120" placeholder="{{ __('app.name_en') }}" class="min-h-11 rounded-lg border border-line px-3">
            <select name="kind" class="min-h-11 rounded-lg border border-line px-3">
                <option value="kitchen">{{ __('app.prep_kind_kitchen') }}</option>
                <option value="bar">{{ __('app.prep_kind_bar') }}</option>
                <option value="dessert">{{ __('app.prep_kind_dessert') }}</option>
                <option value="bakery">{{ __('app.prep_kind_bakery') }}</option>
            </select>
            <button class="min-h-11 rounded-lg bg-brand px-4 text-sm font-semibold text-brand-ink">{{ __('app.add_prep_station') }}</button>
        </form>
    @endif

    @if ($selected_station === null)
        <p class="mt-8 text-sm text-ink/70">{{ __('app.no_prep_stations') }}</p>
    @else
        <div
            id="kitchen-board"
            class="mt-8 grid gap-4 md:grid-cols-2"
            data-poll-url="{{ $poll_url }}"
            data-poll-seconds="{{ $poll_seconds }}"
            data-late-minutes="{{ $late_minutes }}"
            data-start-template="{{ url('/app/kitchen/tickets/__ID__/start') }}"
            data-ready-template="{{ url('/app/kitchen/tickets/__ID__/ready') }}"
            data-csrf="{{ csrf_token() }}"
        >
            @foreach ($tickets as $ticket)
                @include('app.kitchen.partials.ticket', ['ticket' => $ticket, 'late_minutes' => $late_minutes])
            @endforeach
        </div>
        <p class="mt-4 text-xs text-ink/50">{{ __('app.kitchen_poll_hint', ['seconds' => $poll_seconds]) }}</p>
    @endif

    <script>
        (() => {
            const root = document.getElementById('kitchen-board');
            if (! root) return;
            const url = root.dataset.pollUrl;
            const seconds = Number(root.dataset.pollSeconds || 8) * 1000;
            const lateMinutes = Number(root.dataset.lateMinutes || 15);
            const csrf = root.dataset.csrf;
            let updatedAfter = null;

            function renderTickets(tickets) {
                root.innerHTML = tickets.map((ticket) => {
                    const late = ticket.is_late
                        ? `<p class="mt-2 text-sm font-semibold">${ticket.late_label || 'Late'}</p>`
                        : '';
                    const items = (ticket.items || []).map((item) =>
                        `<li>${item.quantity} × ${item.name_ar}${item.notes ? ' — ' + item.notes : ''}</li>`
                    ).join('');
                    const startBtn = ticket.status === 'queued'
                        ? `<button data-action="start" data-id="${ticket.id}" class="min-h-11 rounded-lg border border-line px-3 text-sm">{{ __('app.kitchen_start') }}</button>`
                        : '';
                    const readyBtn = ticket.status !== 'ready'
                        ? `<button data-action="ready" data-id="${ticket.id}" class="min-h-11 rounded-lg border border-line px-3 text-sm">{{ __('app.kitchen_ready') }}</button>`
                        : '';
                    return `<article class="rounded-lg border border-line bg-white p-4" data-ticket-id="${ticket.id}">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold">#${ticket.order_number}</p>
                                <p class="mt-1 text-sm text-ink/70">${ticket.status} · ${ticket.age_minutes} {{ __('app.minutes') }}</p>
                            </div>
                        </div>
                        ${late}
                        <ul class="mt-3 space-y-1 text-sm">${items}</ul>
                        <div class="mt-4 flex flex-wrap gap-2">${startBtn}${readyBtn}</div>
                    </article>`;
                }).join('');
            }

            async function poll() {
                const endpoint = updatedAfter ? `${url}${url.includes('?') ? '&' : '?'}updated_after=${encodeURIComponent(updatedAfter)}` : url;
                const response = await fetch(endpoint, { headers: { 'Accept': 'application/json' } });
                if (! response.ok) return;
                const payload = await response.json();
                if (payload.data?.server_time) {
                    updatedAfter = payload.data.server_time;
                }
                // Full refresh of open tickets keeps board correct without phones/prices.
                const full = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (! full.ok) return;
                const fullPayload = await full.json();
                renderTickets(fullPayload.data.tickets || []);
            }

            root.addEventListener('click', async (event) => {
                const button = event.target.closest('button[data-action]');
                if (! button) return;
                const id = button.dataset.id;
                const action = button.dataset.action;
                const template = action === 'start' ? root.dataset.startTemplate : root.dataset.readyTemplate;
                const target = template.replace('__ID__', id);
                await fetch(target, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                });
                poll();
            });

            setInterval(poll, seconds);
        })();
    </script>
@endsection
