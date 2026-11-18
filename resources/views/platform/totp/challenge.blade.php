@extends('layouts.guest')

@section('guest-lead', __('auth.totp_challenge_hint'))

@section('content')
    <h1 class="text-3xl font-semibold tracking-tight">{{ __('auth.totp_challenge_title') }}</h1>
    <p class="mt-2 text-sm text-ink/70">{{ __('auth.totp_challenge_body') }}</p>

    <form method="post" action="{{ route('platform.totp.verify') }}" class="mt-8 space-y-4">
        @csrf
        <div>
            <x-ui.label for="code">{{ __('auth.totp_code') }}</x-ui.label>
            <x-ui.field id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus class="tabular-nums tracking-widest" />
            @error('code')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>
        <x-ui.button type="submit" variant="primary" class="w-full min-h-12">
            {{ __('auth.totp_verify') }}
        </x-ui.button>
    </form>
@endsection
