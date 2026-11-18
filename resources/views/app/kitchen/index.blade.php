@extends('layouts.ops')

@section('ops-title', __('app.kitchen'))
@section('ops-context', $branch->name())

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div>
                <x-ui.label for="kitchen-branch">{{ __('app.branch') }}</x-ui.label>
                <x-ui.select id="kitchen-branch" name="branch_id" onchange="this.form.submit()">
                    @foreach ($branches as $option)
                        <option value="{{ $option->id }}" @selected((int) $branch->id === (int) $option->id)>{{ $option->name() }}</option>
                    @endforeach
                </x-ui.select>
            </div>
            @if ($stations->isNotEmpty())
                <div>
                    <x-ui.label for="kitchen-station">{{ __('app.prep_station') }}</x-ui.label>
                    <x-ui.select id="kitchen-station" name="station_id" onchange="this.form.submit()">
                        @foreach ($stations as $station)
                            <option value="{{ $station->public_id }}" @selected($selected_station?->id === $station->id)>{{ $station->name() }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
            @endif
        </form>
    </div>

    @if (auth()->user()->hasPermission('tenant.settings.manage'))
        <form method="POST" action="{{ route('app.kitchen.stations.store') }}" class="ui-panel mt-5 grid gap-3 p-4 md:grid-cols-5">
            @csrf
            <input type="hidden" name="branch_id" value="{{ $branch->id }}">
            <x-ui.field name="name_ar" required maxlength="120" placeholder="{{ __('app.name_ar') }}" />
            <x-ui.field name="name_en" required maxlength="120" placeholder="{{ __('app.name_en') }}" />
            <x-ui.select name="kind">
                <option value="kitchen">{{ __('app.prep_kind_kitchen') }}</option>
                <option value="bar">{{ __('app.prep_kind_bar') }}</option>
                <option value="dessert">{{ __('app.prep_kind_dessert') }}</option>
                <option value="bakery">{{ __('app.prep_kind_bakery') }}</option>
            </x-ui.select>
            <x-ui.button type="submit" variant="primary" class="md:col-span-2">{{ __('app.add_prep_station') }}</x-ui.button>
        </form>
    @endif

    @if ($selected_station === null)
        <x-ui.empty-state class="mt-8" :title="__('app.no_prep_stations')" />
    @else
        <div
            id="kitchen-board"
            class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            data-poll-url="{{ $poll_url }}"
            data-poll-seconds="{{ $poll_seconds }}"
            data-late-minutes="{{ $late_minutes }}"
            data-start-template="{{ url('/app/kitchen/tickets/__ID__/start') }}"
            data-ready-template="{{ url('/app/kitchen/tickets/__ID__/ready') }}"
            data-csrf="{{ csrf_token() }}"
            data-label-minutes="{{ __('app.minutes') }}"
            data-label-late="{{ __('app.kitchen_ticket_late') }}"
            data-label-start="{{ __('app.kitchen_start') }}"
            data-label-ready="{{ __('app.kitchen_ready') }}"
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
            const csrf = root.dataset.csrf;
            const labelMinutes = root.dataset.labelMinutes || 'min';
            const labelLate = root.dataset.labelLate || 'Late';
            const labelStart = root.dataset.labelStart || 'Start';
            const labelReady = root.dataset.labelReady || 'Ready';
            let updatedAfter = null;

            function ticketClass(ticket) {
                if (ticket.is_late) return 'ops-ticket ops-ticket-late';
                if (ticket.status === 'ready') return 'ops-ticket ops-ticket-ready';
                return 'ops-ticket';
            }

            function renderTickets(tickets) {
                root.innerHTML = tickets.map((ticket) => {
                    const late = ticket.is_late
                        ? `<p class="mt-3 text-sm font-semibold text-danger">${ticket.late_label || labelLate}</p>`
                        : '';
                    const items = (ticket.items || []).map((item) =>
                        `<li class="flex gap-2"><span class="font-semibold tabular-nums">${item.quantity}</span><span>×</span><span>${item.name_ar}${item.notes ? ' — ' + item.notes : ''}</span></li>`
                    ).join('');
                    const startBtn = ticket.status === 'queued'
                        ? `<button data-action="start" data-id="${ticket.id}" class="ui-btn ui-btn-secondary">${labelStart}</button>`
                        : '';
                    const readyBtn = ticket.status !== 'ready'
                        ? `<button data-action="ready" data-id="${ticket.id}" class="ui-btn ui-btn-primary">${labelReady}</button>`
                        : '';
                    return `<article class="${ticketClass(ticket)}" data-ticket-id="${ticket.id}">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-xl font-semibold tracking-tight tabular-nums">#${ticket.order_number}</p>
                                <p class="mt-1 text-sm text-ink/70">${ticket.status} · <span class="tabular-nums">${ticket.age_minutes}</span> ${labelMinutes}</p>
                            </div>
                            <span class="rounded-md bg-canvas px-2 py-1 text-xs font-semibold uppercase tracking-wide text-ink/70">${ticket.status}</span>
                        </div>
                        ${late}
                        <ul class="mt-3 space-y-1.5 text-sm">${items}</ul>
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
