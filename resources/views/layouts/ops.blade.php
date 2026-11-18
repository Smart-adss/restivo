<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="min-h-dvh bg-canvas font-sans text-ink antialiased">
        <header class="sticky top-0 z-30 border-b border-line bg-surface/95 backdrop-blur-sm">
            <div class="flex items-center justify-between gap-3 px-3 py-2 md:px-5">
                <div class="flex min-w-0 items-center gap-3">
                    <a href="{{ route('app.home') }}" class="ui-btn ui-btn-ghost shrink-0 px-2.5">
                        {{ __('app.back_to_workspace') }}
                    </a>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold tracking-tight">
                            @hasSection('ops-title')
                                @yield('ops-title')
                            @else
                                {{ config('app.name') }}
                            @endif
                        </p>
                        @hasSection('ops-context')
                            <p class="truncate text-xs text-ink/60">@yield('ops-context')</p>
                        @endif
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    @include('layouts.partials.locale-switcher')
                    <span class="hidden text-sm font-semibold sm:inline">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-ui.button type="submit" variant="ghost">{{ __('app.logout') }}</x-ui.button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-[1600px] px-3 py-4 md:px-5 md:py-5">
            @if (session('status'))
                <x-ui.alert variant="success" class="mb-4">{{ session('status') }}</x-ui.alert>
            @endif
            @yield('content')
        </main>
    </body>
</html>
