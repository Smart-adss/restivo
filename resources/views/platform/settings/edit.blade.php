@extends('layouts.app')

@section('content')
    @php($field = 'w-full rounded-md border border-line bg-white px-3 py-3 text-start outline-none focus:border-brand')
    <h1 class="text-2xl font-semibold">{{ __('app.settings') }}</h1>
    <form method="POST" action="{{ route('platform.settings.update') }}" class="mt-6 max-w-xl space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label class="mb-1 block text-sm" for="platform_name">{{ __('app.platform_name') }}</label>
            <input id="platform_name" name="platform_name" value="{{ old('platform_name', $platformName) }}" class="{{ $field }}">
        </div>
        <div>
            <label class="mb-1 block text-sm" for="billing_grace_days">{{ __('app.grace_days') }}</label>
            <input id="billing_grace_days" name="billing_grace_days" value="{{ old('billing_grace_days', $graceDays) }}" class="{{ $field }}">
        </div>
        <div>
            <label class="mb-1 block text-sm" for="kitchen_poll_seconds">{{ __('app.poll_seconds') }}</label>
            <input id="kitchen_poll_seconds" name="kitchen_poll_seconds" value="{{ old('kitchen_poll_seconds', $pollSeconds) }}" class="{{ $field }}">
        </div>
        <input type="hidden" name="maintenance_enabled" value="{{ $maintenance ? 1 : 0 }}">
        <button class="inline-flex min-h-11 items-center rounded-md bg-brand px-4 font-semibold text-brand-ink">{{ __('app.save') }}</button>
    </form>
@endsection
