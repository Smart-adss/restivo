<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body @class(['min-h-screen bg-canvas font-sans text-ink antialiased', 'platform-shell' => auth()->user()->is_platform_user])>
        <div class="min-h-screen md:flex">
            @include('layouts.partials.sidebar')

            <div class="min-w-0 flex-1">
                <header class="sticky top-0 z-20 flex items-center justify-between gap-4 border-b border-line bg-surface/95 px-4 py-2 backdrop-blur-sm md:px-8">
                    <div class="flex items-center gap-3">
                        @include('layouts.partials.locale-switcher')
                        @if (auth()->user()->is_platform_user)
                            <span class="hidden text-xs font-semibold uppercase tracking-[0.08em] text-ink/45 sm:inline">{{ __('app.platform_console') }}</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-semibold">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-ui.button type="submit" variant="ghost">{{ __('app.logout') }}</x-ui.button>
                        </form>
                    </div>
                </header>

                <main @class(['mx-auto w-full px-4 py-8 md:px-8', 'max-w-6xl' => auth()->user()->is_platform_user, 'max-w-5xl' => ! auth()->user()->is_platform_user])>
                    @unless (auth()->user()->is_platform_user)
                        @php($subscriptionStatus = auth()->user()->tenant?->subscription?->status)
                        @if (in_array($subscriptionStatus, ['past_due', 'grace', 'expired', 'suspended'], true))
                            <x-ui.alert
                                :variant="$subscriptionStatus === 'grace' ? 'danger' : 'warning'"
                                class="mb-6"
                            >
                                {{ __('app.subscription_status_'.$subscriptionStatus) }}
                            </x-ui.alert>
                        @endif
                    @endunless

                    @if (session('status'))
                        <x-ui.alert variant="success" class="mb-6">{{ session('status') }}</x-ui.alert>
                    @endif

                    @yield('content')
                </main>
            </div>
        </div>
    </body>
</html>
