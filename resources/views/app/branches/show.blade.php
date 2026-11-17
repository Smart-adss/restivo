@extends('layouts.app')

@section('content')
    @php($field = 'w-full rounded-md border border-line bg-white px-3 py-3 text-start outline-none focus:border-brand')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ $branch->name() }}</h1>
        <div class="flex flex-wrap gap-2">
            @if (app(\App\Support\Features\FeatureGate::class)->allows('module.digital_menu'))
                <a href="{{ route('public.menu.show', ['slug' => auth()->user()->tenant->slug, 'branchPublicId' => $branch->public_id]) }}" class="min-h-11 rounded-lg border border-line px-3 text-sm hover:bg-canvas" target="_blank" rel="noopener">{{ __('app.open_public_menu') }}</a>
            @endif
            @if (app(\App\Support\Features\FeatureGate::class)->allows('module.reservations'))
                <a href="{{ route('public.reservations.show', ['slug' => auth()->user()->tenant->slug, 'branchPublicId' => $branch->public_id]) }}" class="min-h-11 rounded-lg border border-line px-3 text-sm hover:bg-canvas" target="_blank" rel="noopener">{{ __('app.book_table') }}</a>
            @endif
        </div>
    </div>
    <dl class="mt-6 max-w-xl space-y-3 rounded-lg border border-line bg-white px-4 py-4 text-sm">
        <div class="flex justify-between gap-4"><dt class="text-ink/60">{{ __('app.branch_code') }}</dt><dd>{{ $branch->code }}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-ink/60">{{ __('app.city') }}</dt><dd>{{ $branch->city }}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-ink/60">{{ __('app.address') }}</dt><dd>{{ $branch->address }}</dd></div>
    </dl>

    @if (app(\App\Support\Features\FeatureGate::class)->allows('module.digital_menu'))
        <section class="mt-8 max-w-xl rounded-lg border border-line bg-white px-4 py-5">
            <h2 class="text-lg font-semibold">{{ __('app.menu_qr') }}</h2>
            <p class="mt-1 text-sm text-ink/70">{{ __('app.menu_qr_hint') }}</p>
            @if ($qr)
                <img src="{{ route('public.menu.qr', ['slug' => auth()->user()->tenant->slug, 'branchPublicId' => $branch->public_id]) }}" alt="QR" class="mt-4 h-40 w-40 border border-line bg-white p-2">
            @endif
            <form method="POST" action="{{ route('app.branches.qr.store', $branch->public_id) }}" class="mt-4">
                @csrf
                <button type="submit" class="min-h-11 rounded-lg bg-brand hover:bg-ink px-4 text-sm font-semibold text-brand-ink">{{ __('app.generate_qr') }}</button>
            </form>
        </section>
    @endif

    @if (auth()->user()->hasPermission('tenant.branches.manage'))
        <section class="mt-8 max-w-2xl rounded-lg border border-line bg-white px-4 py-5">
            <h2 class="text-lg font-semibold">{{ __('app.operating_hours') }}</h2>
            <form method="POST" action="{{ route('app.branches.hours.update', $branch->public_id) }}" class="mt-4 space-y-3">
                @csrf
                @method('PUT')
                @foreach ($hours as $index => $hour)
                    <div class="grid gap-2 rounded-lg border border-line px-3 py-3 md:grid-cols-[7rem_auto_1fr_1fr]">
                        <input type="hidden" name="days[{{ $index }}][day_of_week]" value="{{ $hour->day_of_week }}">
                        <p class="self-center text-sm font-semibold">{{ __('app.weekday_'.$hour->day_of_week) }}</p>
                        <label class="flex min-h-11 items-center gap-2 text-sm">
                            <input type="checkbox" name="days[{{ $index }}][is_closed]" value="1" @checked($hour->is_closed)>
                            {{ __('app.day_closed') }}
                        </label>
                        <input type="time" name="days[{{ $index }}][opens_at]" value="{{ $hour->opens_at ? substr($hour->opens_at, 0, 5) : '09:00' }}" class="{{ $field }}">
                        <input type="time" name="days[{{ $index }}][closes_at]" value="{{ $hour->closes_at ? substr($hour->closes_at, 0, 5) : '23:00' }}" class="{{ $field }}">
                    </div>
                @endforeach
                @error('days')
                    <p class="text-sm text-danger">{{ $message }}</p>
                @enderror
                <button type="submit" class="min-h-11 rounded-lg bg-brand hover:bg-ink px-4 text-sm font-semibold text-brand-ink">{{ __('app.save') }}</button>
            </form>
        </section>
    @endif
@endsection
