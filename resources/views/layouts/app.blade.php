<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="min-h-screen bg-canvas font-sans text-ink antialiased">
        <div class="min-h-screen md:grid md:grid-cols-[16rem_1fr]">
            <aside class="border-b border-line bg-white md:border-e md:border-b-0">
                <div class="px-4 py-5">
                    <p class="text-sm text-ink/60">{{ config('app.name') }}</p>
                    <p class="mt-1 font-semibold">{{ __('app.shell_title') }}</p>
                </div>
            </aside>
            <div class="min-w-0">
                <header class="flex items-center justify-between gap-4 border-b border-line bg-white px-4 py-3">
                    @include('layouts.partials.locale-switcher')
                    <div class="flex items-center gap-4">
                        <span class="text-sm">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="min-h-11 text-sm text-ink/70">{{ __('app.logout') }}</button>
                        </form>
                    </div>
                </header>
                <main class="px-4 py-8">
                    @yield('content')
                </main>
            </div>
        </div>
    </body>
</html>
