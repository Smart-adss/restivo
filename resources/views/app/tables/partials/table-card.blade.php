@php
    $session = $sessions->get($table->id);
    $allTables = $areas->flatMap->tables->merge($orphan_tables);
@endphp
<li class="rounded-lg border border-line bg-white p-4">
    <div class="flex items-start justify-between gap-2">
        <div>
            <p class="font-semibold">{{ $table->name }}</p>
            <p class="mt-1 text-sm text-ink/70">{{ __('app.seats') }}: {{ $table->seats }} · {{ __('app.table_status_'.$table->status) }}</p>
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
                    <button class="min-h-11 rounded-lg border border-line px-3 text-sm">{{ __('app.open_session') }}</button>
                </form>
            @else
                <form method="POST" action="{{ route('app.tables.sessions.close', $session->public_id) }}">
                    @csrf
                    <button class="min-h-11 rounded-lg border border-line px-3 text-sm">{{ __('app.close_session') }}</button>
                </form>
                <form method="POST" action="{{ route('app.tables.sessions.transfer', $session->public_id) }}" class="flex flex-wrap items-center gap-2">
                    @csrf
                    <select name="to_table_id" class="min-h-11 rounded-lg border border-line px-2 text-sm">
                        @foreach ($allTables as $candidate)
                            @if ((int) $candidate->id !== (int) $table->id && $candidate->status === 'available')
                                <option value="{{ $candidate->public_id }}">{{ $candidate->name }}</option>
                            @endif
                        @endforeach
                    </select>
                    <button class="min-h-11 rounded-lg border border-line px-3 text-sm">{{ __('app.transfer_session') }}</button>
                </form>
            @endif
        @endif

        @if (auth()->user()->hasPermission('tables.layout.manage') && app(\App\Support\Features\FeatureGate::class)->allows('module.online_ordering'))
            <form method="POST" action="{{ route('app.tables.qr.store', $table->public_id) }}">
                @csrf
                <button class="min-h-11 rounded-lg border border-line px-3 text-sm">{{ __('app.generate_table_qr') }}</button>
            </form>
        @endif
    </div>

    @if ($session && $session->orders->isNotEmpty())
        <ul class="mt-3 space-y-1 text-sm text-ink/80">
            @foreach ($session->orders as $order)
                <li>#{{ $order->number }} · {{ $order->total }}</li>
            @endforeach
        </ul>
    @endif
</li>
