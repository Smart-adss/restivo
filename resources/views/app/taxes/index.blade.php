@extends('layouts.app')

@section('content')
    <div>
        <h1 class="text-2xl font-semibold">{{ __('app.taxes') }}</h1>
        <p class="mt-1 max-w-2xl text-sm text-ink/70">{{ __('app.tax_hint') }}</p>
    </div>

    @if (session('status'))
        <p class="mt-4 rounded-lg border border-line bg-white px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        @foreach ($rates as $rate)
            <form method="POST" action="{{ route('app.taxes.update', $rate->public_id) }}" class="space-y-3 rounded-lg border border-line bg-white p-4">
                @csrf
                @method('PUT')
                <h2 class="font-semibold">{{ $rate->name }}</h2>
                <div>
                    <label for="name-{{ $rate->public_id }}" class="mb-1 block text-sm">{{ __('app.name') }}</label>
                    <input id="name-{{ $rate->public_id }}" name="name" required maxlength="80" value="{{ $rate->name }}" class="min-h-11 w-full rounded-lg border border-line px-3">
                </div>
                <div>
                    <label for="rate-{{ $rate->public_id }}" class="mb-1 block text-sm">{{ __('app.tax_rate_percent') }}</label>
                    <input id="rate-{{ $rate->public_id }}" name="rate_percent" type="number" step="0.001" min="0" max="100" required value="{{ $rate->rate_percent }}" class="min-h-11 w-full rounded-lg border border-line px-3">
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="is_inclusive" value="0">
                    <input type="checkbox" name="is_inclusive" value="1" @checked($rate->is_inclusive)>
                    {{ __('app.prices_include_tax') }}
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" name="is_default" value="1" @checked($rate->is_default)>
                    {{ __('app.tax_default') }}
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked($rate->is_active)>
                    {{ __('app.is_active') }}
                </label>
                @error('is_default')<p class="text-sm text-danger">{{ $message }}</p>@enderror
                @error('is_active')<p class="text-sm text-danger">{{ $message }}</p>@enderror
                <button class="min-h-11 rounded-lg bg-brand px-4 text-sm font-semibold text-brand-ink hover:bg-ink">{{ __('app.save') }}</button>
            </form>
        @endforeach

        <form method="POST" action="{{ route('app.taxes.store') }}" class="space-y-3 rounded-lg border border-line bg-white p-4">
            @csrf
            <h2 class="font-semibold">{{ __('app.add_tax_rate') }}</h2>
            <div>
                <label for="new-name" class="mb-1 block text-sm">{{ __('app.name') }}</label>
                <input id="new-name" name="name" required maxlength="80" class="min-h-11 w-full rounded-lg border border-line px-3">
            </div>
            <div>
                <label for="new-rate" class="mb-1 block text-sm">{{ __('app.tax_rate_percent') }}</label>
                <input id="new-rate" name="rate_percent" type="number" step="0.001" min="0" max="100" required value="0" class="min-h-11 w-full rounded-lg border border-line px-3">
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="is_inclusive" value="0">
                <input type="checkbox" name="is_inclusive" value="1">
                {{ __('app.prices_include_tax') }}
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="is_default" value="0">
                <input type="checkbox" name="is_default" value="1">
                {{ __('app.tax_default') }}
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" checked>
                {{ __('app.is_active') }}
            </label>
            <button class="min-h-11 rounded-lg bg-brand px-4 text-sm font-semibold text-brand-ink hover:bg-ink">{{ __('app.save') }}</button>
        </form>
    </div>
@endsection
