<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="min-h-screen bg-canvas font-sans text-ink antialiased">
        <div class="min-h-screen md:grid md:grid-cols-2">
            <section class="flex flex-col justify-between bg-rail px-6 py-8 text-white md:px-12 md:py-12">
                <p class="text-4xl font-semibold tracking-tight">{{ config('app.name') }}</p>
                <p class="mt-4 max-w-sm text-lg text-white/75">{{ __('app.login_lead') }}</p>
            </section>
            <section class="flex flex-col justify-center px-6 py-10 md:px-12">
                <div class="mb-8">
                    @include('layouts.partials.locale-switcher')
                </div>
                <div class="w-full max-w-md">
                    {{ $slot }}
                </div>
            </section>
        </div>
        @livewireScripts
    </body>
</html>
