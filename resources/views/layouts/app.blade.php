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
                    <p class="mt-1 font-semibold">{{ auth()->user()->tenant?->displayName() ?? __('app.platform_title') }}</p>
                </div>
                <nav class="flex gap-1 overflow-x-auto px-3 pb-4 md:flex-col">
                    @if (auth()->user()->is_platform_user)
                        <a href="{{ route('platform.tenants.index') }}" class="min-h-11 rounded-md px-3 py-2 text-sm hover:bg-canvas">{{ __('app.tenants') }}</a>
                    @else
                        <a href="{{ route('app.home') }}" class="min-h-11 rounded-md px-3 py-2 text-sm hover:bg-canvas">{{ __('app.branches') }}</a>
                        @if (auth()->user()->hasPermission('tenant.users.manage'))
                            <a href="{{ route('app.staff.index') }}" class="min-h-11 rounded-md px-3 py-2 text-sm hover:bg-canvas">{{ __('app.staff') }}</a>
                        @endif
                        <a href="{{ route('app.profile') }}" class="min-h-11 rounded-md px-3 py-2 text-sm hover:bg-canvas">{{ __('app.profile') }}</a>
                    @endif
                </nav>
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
                    @if (session('status'))
                        <p class="mb-4 rounded-md border border-line bg-white px-4 py-3 text-sm">{{ session('status') }}</p>
                    @endif
                    @yield('content')
                </main>
            </div>
        </div>
    </body>
</html>
