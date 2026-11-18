@extends('layouts.guest')

@section('guest-lead', __('auth.totp_setup_hint'))

@section('content')
    <h1 class="text-3xl font-semibold tracking-tight">{{ __('auth.totp_setup_title') }}</h1>
    <p class="mt-2 text-sm text-ink/70">{{ __('auth.totp_setup_body') }}</p>

    <div class="ui-panel mt-6 px-4 py-4 text-sm">
        <p class="font-medium">{{ __('auth.totp_secret_label') }}</p>
        <p class="mt-2 break-all font-mono text-base tracking-wider" dir="ltr">{{ $secret }}</p>
        <p class="mt-3 break-all text-ink/60" dir="ltr">{{ $uri }}</p>
    </div>

    <form method="post" action="{{ route('platform.totp.setup.confirm') }}" class="mt-8 space-y-4">
        @csrf
        <div>
            <x-ui.label for="code">{{ __('auth.totp_code') }}</x-ui.label>
            <x-ui.field id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required class="tabular-nums tracking-widest" />
            @error('code')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>
        <x-ui.button type="submit" variant="primary" class="w-full min-h-12">
            {{ __('auth.totp_confirm') }}
        </x-ui.button>
    </form>
@endsection
