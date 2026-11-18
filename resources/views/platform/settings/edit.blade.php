@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.settings')" :description="__('app.platform_settings_hint')" />

    <form method="POST" action="{{ route('platform.settings.update') }}" class="ui-panel max-w-xl space-y-4 px-4 py-5">
        @csrf
        @method('PUT')
        <div>
            <x-ui.label for="platform_name">{{ __('app.platform_name') }}</x-ui.label>
            <x-ui.field id="platform_name" name="platform_name" value="{{ old('platform_name', $platformName) }}" />
        </div>
        <div>
            <x-ui.label for="billing_grace_days">{{ __('app.grace_days') }}</x-ui.label>
            <x-ui.field id="billing_grace_days" name="billing_grace_days" value="{{ old('billing_grace_days', $graceDays) }}" class="tabular-nums" />
        </div>
        <div>
            <x-ui.label for="kitchen_poll_seconds">{{ __('app.poll_seconds') }}</x-ui.label>
            <x-ui.field id="kitchen_poll_seconds" name="kitchen_poll_seconds" value="{{ old('kitchen_poll_seconds', $pollSeconds) }}" class="tabular-nums" />
        </div>
        <input type="hidden" name="maintenance_enabled" value="{{ $maintenance ? 1 : 0 }}">
        <x-ui.button type="submit" variant="primary">{{ __('app.save') }}</x-ui.button>
    </form>
@endsection
