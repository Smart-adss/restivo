@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.taxes')" :description="__('app.tax_hint')" />

    <div class="mt-2 grid gap-4 lg:grid-cols-2">
        @foreach ($rates as $rate)
            <form method="POST" action="{{ route('app.taxes.update', $rate->public_id) }}" class="ui-panel space-y-3 p-4">
                @csrf
                @method('PUT')
                <x-ui.section-title :title="$rate->name" />
                <div>
                    <x-ui.label for="name-{{ $rate->public_id }}">{{ __('app.name') }}</x-ui.label>
                    <x-ui.field id="name-{{ $rate->public_id }}" name="name" required maxlength="80" value="{{ $rate->name }}" />
                </div>
                <div>
                    <x-ui.label for="rate-{{ $rate->public_id }}">{{ __('app.tax_rate_percent') }}</x-ui.label>
                    <x-ui.field id="rate-{{ $rate->public_id }}" name="rate_percent" type="number" step="0.001" min="0" max="100" required value="{{ $rate->rate_percent }}" class="tabular-nums" />
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
                <x-ui.button type="submit" variant="primary">{{ __('app.save') }}</x-ui.button>
            </form>
        @endforeach

        <form method="POST" action="{{ route('app.taxes.store') }}" class="ui-panel space-y-3 p-4">
            @csrf
            <x-ui.section-title :title="__('app.add_tax_rate')" />
            <div>
                <x-ui.label for="new-name">{{ __('app.name') }}</x-ui.label>
                <x-ui.field id="new-name" name="name" required maxlength="80" />
            </div>
            <div>
                <x-ui.label for="new-rate">{{ __('app.tax_rate_percent') }}</x-ui.label>
                <x-ui.field id="new-rate" name="rate_percent" type="number" step="0.001" min="0" max="100" required value="0" class="tabular-nums" />
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
            <x-ui.button type="submit" variant="primary">{{ __('app.save') }}</x-ui.button>
        </form>
    </div>
@endsection
