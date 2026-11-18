@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.reservations')" :description="__('app.reservations_day_hint')">
        <x-slot:actions>
            <div class="flex flex-wrap items-end gap-3">
                <x-ui.button :href="$whatsapp_url" variant="secondary" target="_blank" rel="noopener">{{ __('app.whatsapp_guest') }}</x-ui.button>
                <form method="GET" action="{{ route('app.reservations.index') }}" class="flex flex-wrap items-end gap-2">
                    <div>
                        <x-ui.label for="res-branch">{{ __('app.branch') }}</x-ui.label>
                        <x-ui.select id="res-branch" name="branch_id">
                            @foreach ($branches as $option)
                                <option value="{{ $option->id }}" @selected($option->id === $branch->id)>{{ $option->name() }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    <div>
                        <x-ui.label for="res-date">{{ __('app.date') }}</x-ui.label>
                        <x-ui.field id="res-date" type="date" name="date" value="{{ $date }}" />
                    </div>
                    <x-ui.button type="submit" variant="primary">{{ __('app.show') }}</x-ui.button>
                </form>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    @error('status')
        <x-ui.alert variant="danger" class="mb-4">{{ $message }}</x-ui.alert>
    @enderror

    <section class="ui-panel mb-8 px-4 py-4">
        <x-ui.section-title :title="__('app.add_dining_table')" />
        <form method="POST" action="{{ route('app.dining-tables.store') }}" class="mt-3 grid gap-3 sm:grid-cols-4">
            @csrf
            <input type="hidden" name="branch_id" value="{{ $branch->id }}">
            <input type="hidden" name="date" value="{{ $date }}">
            <div class="sm:col-span-2">
                <x-ui.label for="table_name">{{ __('app.table_name') }}</x-ui.label>
                <x-ui.field id="table_name" name="name" required maxlength="80" />
            </div>
            <div>
                <x-ui.label for="table_seats">{{ __('app.seats') }}</x-ui.label>
                <x-ui.field id="table_seats" name="seats" type="number" min="1" max="30" value="4" required class="tabular-nums" />
            </div>
            <x-ui.button type="submit" variant="secondary" class="self-end">{{ __('app.save') }}</x-ui.button>
        </form>
    </section>

    @if ($reservations->isEmpty())
        <x-ui.empty-state :title="__('app.reservations_empty')" />
    @else
        <ul class="space-y-3">
            @foreach ($reservations as $reservation)
                <li class="ui-panel px-4 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold">
                                <span class="tabular-nums">{{ $reservation->reserved_at->format('H:i') }}</span>
                                · {{ __('app.reservation_status_'.$reservation->status) }}
                                · <span class="tabular-nums">{{ $reservation->guest_count }}</span> {{ __('app.guests') }}
                            </p>
                            <p class="mt-1 text-sm text-ink/70">
                                {{ $reservation->customer_name }} · <span dir="ltr">{{ $reservation->customer_phone }}</span>
                            </p>
                            @if ($reservation->tables->isNotEmpty())
                                <p class="mt-1 text-sm">{{ __('app.tables') }}: {{ $reservation->tables->pluck('name')->join(', ') }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if (in_array($reservation->status, ['pending', 'confirmed'], true))
                                <form method="POST" action="{{ route('app.reservations.confirm', $reservation->public_id) }}" class="flex flex-wrap items-end gap-2">
                                    @csrf
                                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                    <input type="hidden" name="date" value="{{ $date }}">
                                    @if ($tables->isNotEmpty())
                                        <div>
                                            <x-ui.label>{{ __('app.assign_table') }}</x-ui.label>
                                            <x-ui.select name="table_ids[]">
                                                <option value="">{{ __('app.no_table') }}</option>
                                                @foreach ($tables as $table)
                                                    <option value="{{ $table->id }}">{{ $table->name }} ({{ $table->seats }})</option>
                                                @endforeach
                                            </x-ui.select>
                                        </div>
                                    @endif
                                    <x-ui.button type="submit" variant="secondary">{{ __('app.confirm') }}</x-ui.button>
                                </form>
                            @endif
                            @if (! in_array($reservation->status, ['cancelled', 'completed', 'no_show'], true))
                                <form method="POST" action="{{ route('app.reservations.cancel', $reservation->public_id) }}">
                                    @csrf
                                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                    <input type="hidden" name="date" value="{{ $date }}">
                                    <x-ui.button type="submit" variant="danger">{{ __('app.cancel') }}</x-ui.button>
                                </form>
                            @endif
                            @if ($reservation->status === 'confirmed')
                                <form method="POST" action="{{ route('app.reservations.no-show', $reservation->public_id) }}">
                                    @csrf
                                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                    <input type="hidden" name="date" value="{{ $date }}">
                                    <x-ui.button type="submit" variant="ghost">{{ __('app.mark_no_show') }}</x-ui.button>
                                </form>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
