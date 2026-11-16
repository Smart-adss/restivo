<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="min-h-screen bg-canvas font-sans text-ink antialiased">
        <div class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-4 py-10">
            <div class="mb-6 flex items-center justify-between gap-4">
                <p class="text-sm text-ink/70">{{ config('app.name') }}</p>
                @include('layouts.partials.locale-switcher')
            </div>
            {{ $slot }}
        </div>
        @livewireScripts
    </body>
</html>
