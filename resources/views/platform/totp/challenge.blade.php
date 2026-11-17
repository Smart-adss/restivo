<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="min-h-screen bg-canvas font-sans text-ink antialiased">
        <div class="min-h-screen md:grid md:grid-cols-2">
            <section class="flex flex-col justify-between bg-rail px-6 py-8 text-white md:px-12 md:py-12">
                <p class="text-4xl font-semibold tracking-tight">{{ config('app.name') }}</p>
                <p class="mt-4 max-w-sm text-lg text-white/75">{{ __('auth.totp_challenge_hint') }}</p>
            </section>
            <section class="flex flex-col justify-center px-6 py-10 md:px-12">
                <div class="w-full max-w-md">
                    <h1 class="text-3xl font-semibold tracking-tight">{{ __('auth.totp_challenge_title') }}</h1>
                    <p class="mt-2 text-sm text-ink/70">{{ __('auth.totp_challenge_body') }}</p>

                    <form method="post" action="{{ route('platform.totp.verify') }}" class="mt-8 space-y-4">
                        @csrf
                        <div>
                            <label for="code" class="mb-1 block text-sm">{{ __('auth.totp_code') }}</label>
                            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus
                                class="w-full rounded-lg border border-line bg-white px-3 py-3 text-start outline-none focus:border-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                            @error('code')
                                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit"
                            class="flex min-h-12 w-full items-center justify-center rounded-lg bg-brand px-4 font-semibold text-brand-ink hover:bg-ink">
                            {{ __('auth.totp_verify') }}
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </body>
</html>
