@php
    $session = $sessions->get($table->id);
    $allTables = $areas->flatMap->tables->merge($orphan_tables);
    $statusClass = match ($table->status) {
        'occupied' => 'border-brand',
        'reserved' => 'border-accent',
        default => 'border-line',
    };
@endphp
<li class="ui-panel p-4 {{ $statusClass }}">
    <div class="flex items-start justify-between gap-2">
        <div>
            <p class="text-lg font-semibold tracking-tight">{{ $table->name }}</p>
            <p class="mt-1 text-sm text-ink/70">
                {{ __('app.seats') }}: <span class="tabular-nums">{{ $table->seats }}</span>
                · {{ __('app.table_status_'.$table->status) }}
            </p>
        </div>
        @if ($session)
            <span class="text-xs font-semibold uppercase tracking-wide text-brand">{{ __('app.session_open') }}</span>
        @endif
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
        @if (auth()->user()->hasPermission('tables.sessions.manage'))
            @if (! $session)
                <form method="POST" action="{{ route('app.tables.sessions.open', $table->public_id) }}">
                    @csrf
                    <input type="hidden" name="guest_count" value="2">
                    <x-ui.button type="submit" variant="primary">{{ __('app.open_session') }}</x-ui.button>
                </form>
            @else
                <form method="POST" action="{{ route('app.tables.sessions.close', $session->public_id) }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary">{{ __('app.close_session') }}</x-ui.button>
                </form>
                <form method="POST" action="{{ route('app.tables.sessions.transfer', $session->public_id) }}" class="flex flex-wrap items-center gap-2">
                    @csrf
                    <x-ui.select name="to_table_id" class="!min-h-11 w-auto">
                        @foreach ($allTables as $candidate)
                            @if ((int) $candidate->id !== (int) $table->id && $candidate->status === 'available')
                                <option value="{{ $candidate->public_id }}">{{ $candidate->name }}</option>
                            @endif
                        @endforeach
                    </x-ui.select>
                    <x-ui.button type="submit" variant="ghost">{{ __('app.transfer_session') }}</x-ui.button>
                </form>
            @endif
        @endif

        @if (auth()->user()->hasPermission('tables.layout.manage') && app(\App\Support\Features\FeatureGate::class)->allows('module.online_ordering'))
            <form method="POST" action="{{ route('app.tables.qr.store', $table->public_id) }}">
                @csrf
                <x-ui.button type="submit" variant="ghost">{{ __('app.generate_table_qr') }}</x-ui.button>
            </form>
        @endif
    </div>

    @if ($session && $session->orders->isNotEmpty())
        <ul class="mt-3 space-y-1 border-t border-line pt-3 text-sm text-ink/80">
            @foreach ($session->orders as $order)
                <li>#{{ $order->number }} · <span class="tabular-nums">{{ $order->total }}</span></li>
            @endforeach
        </ul>
    @endif
</li>
