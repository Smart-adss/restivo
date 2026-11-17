@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('app.reservations') }}</h1>
            <p class="mt-1 text-sm text-ink/70">{{ __('app.reservations_day_hint') }}</p>
        </div>
        <div class="flex flex-wrap items-end gap-3">
            <a href="{{ $whatsapp_url }}" target="_blank" rel="noopener" class="min-h-11 rounded-lg border border-line px-3 text-sm leading-[2.75rem]">{{ __('app.whatsapp_guest') }}</a>
            <form method="GET" action="{{ route('app.reservations.index') }}" class="flex flex-wrap items-end gap-2">
                <label class="text-sm">{{ __('app.branch') }}
                    <select name="branch_id" class="mt-1 min-h-11 rounded-lg border border-line px-3">
                        @foreach ($branches as $option)
                            <option value="{{ $option->id }}" @selected($option->id === $branch->id)>{{ $option->name() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm">{{ __('app.date') }}
                    <input type="date" name="date" value="{{ $date }}" class="mt-1 min-h-11 rounded-lg border border-line px-3">
                </label>
                <button type="submit" class="min-h-11 rounded-lg bg-brand px-4 text-sm font-semibold text-brand-ink">{{ __('app.show') }}</button>
            </form>
        </div>
    </div>

    @error('status')
        <p class="mb-4 rounded-lg border border-s-4 border-danger bg-white px-4 py-3 text-sm text-danger">{{ $message }}</p>
    @enderror

    <section class="mb-8 rounded-lg border border-line bg-white px-4 py-4">
        <h2 class="font-semibold">{{ __('app.add_dining_table') }}</h2>
        <form method="POST" action="{{ route('app.dining-tables.store') }}" class="mt-3 grid gap-3 sm:grid-cols-4">
            @csrf
            <input type="hidden" name="branch_id" value="{{ $branch->id }}">
            <input type="hidden" name="date" value="{{ $date }}">
            <label class="text-sm sm:col-span-2">{{ __('app.table_name') }}
                <input name="name" required maxlength="80" class="mt-1 w-full min-h-11 rounded-lg border border-line px-3">
            </label>
            <label class="text-sm">{{ __('app.seats') }}
                <input name="seats" type="number" min="1" max="30" value="4" required class="mt-1 w-full min-h-11 rounded-lg border border-line px-3">
            </label>
            <button type="submit" class="min-h-11 self-end rounded-lg border border-line px-3 text-sm">{{ __('app.save') }}</button>
        </form>
    </section>

    @if ($reservations->isEmpty())
        <p class="rounded-lg border border-line bg-white px-4 py-6 text-sm text-ink/70">{{ __('app.reservations_empty') }}</p>
    @else
        <ul class="space-y-4">
            @foreach ($reservations as $reservation)
                <li class="rounded-lg border border-line bg-white px-4 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold">
                                {{ $reservation->reserved_at->format('H:i') }}
                                · {{ __('app.reservation_status_'.$reservation->status) }}
                                · {{ $reservation->guest_count }} {{ __('app.guests') }}
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
                                        <label class="text-sm">{{ __('app.assign_table') }}
                                            <select name="table_ids[]" class="mt-1 min-h-11 rounded-lg border border-line px-3">
                                                <option value="">{{ __('app.no_table') }}</option>
                                                @foreach ($tables as $table)
                                                    <option value="{{ $table->id }}">{{ $table->name }} ({{ $table->seats }})</option>
                                                @endforeach
                                            </select>
                                        </label>
                                    @endif
                                    <button type="submit" class="min-h-11 rounded-lg border border-line px-3 text-sm">{{ __('app.confirm') }}</button>
                                </form>
                            @endif
                            @if (! in_array($reservation->status, ['cancelled', 'completed', 'no_show'], true))
                                <form method="POST" action="{{ route('app.reservations.cancel', $reservation->public_id) }}">
                                    @csrf
                                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                    <input type="hidden" name="date" value="{{ $date }}">
                                    <button type="submit" class="min-h-11 rounded-lg border border-danger px-3 text-sm text-danger">{{ __('app.cancel') }}</button>
                                </form>
                            @endif
                            @if ($reservation->status === 'confirmed')
                                <form method="POST" action="{{ route('app.reservations.no-show', $reservation->public_id) }}">
                                    @csrf
                                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                    <input type="hidden" name="date" value="{{ $date }}">
                                    <button type="submit" class="min-h-11 rounded-lg border border-line px-3 text-sm">{{ __('app.mark_no_show') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
