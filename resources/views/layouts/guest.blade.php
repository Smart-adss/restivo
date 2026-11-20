<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="min-h-screen bg-canvas font-sans text-ink antialiased">
        <div class="min-h-screen md:grid md:grid-cols-2">
            <section class="ui-shell-aside relative flex flex-col justify-between overflow-hidden px-6 py-8 text-white md:px-12 md:py-12">
                <div class="pointer-events-none absolute inset-0 opacity-30" style="background:
                    radial-gradient(circle at 20% 20%, color-mix(in srgb, var(--color-accent) 35%, transparent), transparent 42%),
                    radial-gradient(circle at 80% 80%, color-mix(in srgb, var(--color-brand) 45%, transparent), transparent 46%);"></div>
                <div class="relative">
                    <p class="text-4xl font-semibold tracking-tight">{{ config('app.name') }}</p>
                    <p class="mt-4 max-w-sm text-lg text-white/75">
                        @hasSection('guest-lead')
                            @yield('guest-lead')
                        @else
                            {{ __('app.login_lead') }}
                        @endif
                    </p>
                </div>
                <p class="relative text-sm text-white/45">{{ __('app.shell_title') }}</p>
            </section>
            <section class="flex flex-col justify-center px-6 py-10 md:px-12">
                <div class="mb-8">
                    @include('layouts.partials.locale-switcher')
                </div>
                <div class="w-full max-w-md">
                    @yield('content')
                </div>
            </section>
        </div>
    </body>
</html>
