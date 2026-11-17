<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="min-h-screen bg-canvas font-sans text-ink antialiased">
        @php
            $navClass = function (bool $active): string {
                return 'flex min-h-11 items-center rounded-lg border-s-2 px-3 text-sm '.($active
                    ? 'border-accent bg-white/10 font-semibold text-white'
                    : 'border-transparent text-white/70 hover:bg-white/5 hover:text-white');
            };
        @endphp
        <div class="min-h-screen md:grid md:grid-cols-[15.5rem_1fr]">
            <aside class="bg-rail text-white">
                <a href="{{ auth()->user()->is_platform_user ? route('platform.tenants.index') : route('app.home') }}" class="block px-4 py-6">
                    <span class="text-2xl font-semibold tracking-tight">{{ config('app.name') }}</span>
                    <span class="mt-1 block text-sm text-white/60">{{ auth()->user()->tenant?->displayName() ?? __('app.platform_title') }}</span>
                </a>
                <nav class="flex gap-1 overflow-x-auto px-3 pb-4 md:flex-col">
                    @if (auth()->user()->is_platform_user)
                        <a href="{{ route('platform.tenants.index') }}" class="{{ $navClass(request()->routeIs('platform.tenants.*')) }}">{{ __('app.tenants') }}</a>
                        @if (auth()->user()->hasPermission('platform.plans.manage'))
                            <a href="{{ route('platform.plans.index') }}" class="{{ $navClass(request()->routeIs('platform.plans.*')) }}">{{ __('app.plans') }}</a>
                        @endif
                        @if (auth()->user()->hasPermission('platform.support.manage'))
                            <a href="{{ route('platform.support.index') }}" class="{{ $navClass(request()->routeIs('platform.support.*')) }}">{{ __('app.support') }}</a>
                        @endif
                        @if (auth()->user()->hasPermission('platform.tenants.manage'))
                            <a href="{{ route('platform.settings.edit') }}" class="{{ $navClass(request()->routeIs('platform.settings.*')) }}">{{ __('app.settings') }}</a>
                        @endif
                        @if (auth()->user()->hasPermission('platform.audit.view'))
                            <a href="{{ route('platform.audit.index') }}" class="{{ $navClass(request()->routeIs('platform.audit.*')) }}">{{ __('app.audit') }}</a>
                            <a href="{{ route('platform.failed-jobs.index') }}" class="{{ $navClass(request()->routeIs('platform.failed-jobs.*')) }}">{{ __('app.failed_jobs') }}</a>
                        @endif
                    @else
                        <a href="{{ route('app.home') }}" class="{{ $navClass(request()->routeIs('app.home', 'app.branches.*')) }}">{{ __('app.branches') }}</a>
                        @if (auth()->user()->hasPermission('tenant.users.manage'))
                            <a href="{{ route('app.staff.index') }}" class="{{ $navClass(request()->routeIs('app.staff.*')) }}">{{ __('app.staff') }}</a>
                        @endif
                        @if (app(\App\Support\Features\FeatureGate::class)->allows('module.digital_menu'))
                            @if (auth()->user()->hasPermission('menu.categories.manage'))
                                <a href="{{ route('app.menu.categories.index') }}" class="{{ $navClass(request()->routeIs('app.menu.categories.*')) }}">{{ __('app.categories') }}</a>
                            @endif
                            @if (auth()->user()->hasPermission('menu.products.manage'))
                                <a href="{{ route('app.menu.products.index') }}" class="{{ $navClass(request()->routeIs('app.menu.products.*', 'app.menu.options.*')) }}">{{ __('app.products') }}</a>
                            @endif
                            @if (auth()->user()->hasPermission('menu.theme.manage'))
                                <a href="{{ route('app.menu.theme.edit') }}" class="{{ $navClass(request()->routeIs('app.menu.theme.*')) }}">{{ __('app.menu_theme') }}</a>
                            @endif
                        @endif
                        @if (auth()->user()->hasPermission('tenant.settings.manage'))
                            <a href="{{ route('app.subscription') }}" class="{{ $navClass(request()->routeIs('app.subscription')) }}">{{ __('app.subscription') }}</a>
                        @endif
                        <a href="{{ route('app.support.index') }}" class="{{ $navClass(request()->routeIs('app.support.*')) }}">{{ __('app.support') }}</a>
                        @if (auth()->user()->hasPermission('orders.orders.view') && app(\App\Support\Features\FeatureGate::class)->allows('module.online_ordering'))
                            <a href="{{ route('app.orders.index') }}" class="{{ $navClass(request()->routeIs('app.orders.*')) }}">{{ __('app.orders') }}</a>
                        @endif
                        @if (auth()->user()->hasPermission('reservations.reservations.manage') && app(\App\Support\Features\FeatureGate::class)->allows('module.reservations'))
                            <a href="{{ route('app.reservations.index') }}" class="{{ $navClass(request()->routeIs('app.reservations.*')) }}">{{ __('app.reservations') }}</a>
                        @endif
                        @if (app(\App\Support\Features\FeatureGate::class)->allows('module.pos'))
                            <a href="{{ route('app.pos') }}" class="{{ $navClass(request()->routeIs('app.pos')) }}">{{ __('app.pos') }}</a>
                        @endif
                        @if (auth()->user()->hasPermission('tables.sessions.manage') || auth()->user()->hasPermission('tables.layout.manage'))
                            <a href="{{ route('app.tables.index') }}" class="{{ $navClass(request()->routeIs('app.tables.*')) }}">{{ __('app.floor') }}</a>
                        @endif
                        @if (app(\App\Support\Features\FeatureGate::class)->allows('module.kitchen') && (auth()->user()->hasPermission('kitchen.tickets.view') || auth()->user()->hasPermission('tenant.settings.manage')))
                            <a href="{{ route('app.kitchen.index') }}" class="{{ $navClass(request()->routeIs('app.kitchen.*')) }}">{{ __('app.kitchen') }}</a>
                        @endif
                        @if (auth()->user()->hasPermission('finance.reports.view'))
                            <a href="{{ route('app.reports.day') }}" class="{{ $navClass(request()->routeIs('app.reports.day')) }}">{{ __('app.day_report') }}</a>
                            <a href="{{ route('app.reports.shifts') }}" class="{{ $navClass(request()->routeIs('app.reports.shifts')) }}">{{ __('app.shift_cash_report') }}</a>
                        @endif
                        @if (auth()->user()->hasPermission('finance.reports.advanced'))
                            <a href="{{ route('app.reports.tax') }}" class="{{ $navClass(request()->routeIs('app.reports.tax')) }}">{{ __('app.tax_report') }}</a>
                            @if (app(\App\Support\Features\FeatureGate::class)->allows('module.advanced_reports'))
                                <a href="{{ route('app.reports.profit') }}" class="{{ $navClass(request()->routeIs('app.reports.profit')) }}">{{ __('app.profit_report') }}</a>
                            @endif
                            @if (app(\App\Support\Features\FeatureGate::class)->allows('module.advanced_reports') && app(\App\Support\Features\FeatureGate::class)->allows('module.multi_branch'))
                                <a href="{{ route('app.reports.branches') }}" class="{{ $navClass(request()->routeIs('app.reports.branches')) }}">{{ __('app.branch_comparison') }}</a>
                            @endif
                        @endif
                        @if (auth()->user()->hasPermission('finance.expenses.manage'))
                            <a href="{{ route('app.expenses.index') }}" class="{{ $navClass(request()->routeIs('app.expenses.*')) }}">{{ __('app.expenses') }}</a>
                        @endif
                        @if (app(\App\Support\Features\FeatureGate::class)->allows('module.inventory') && (auth()->user()->hasPermission('inventory.items.manage') || auth()->user()->hasPermission('inventory.movements.adjust')))
                            <a href="{{ route('app.inventory.index') }}" class="{{ $navClass(request()->routeIs('app.inventory.*')) }}">{{ __('app.inventory') }}</a>
                        @endif
                        @if (app(\App\Support\Features\FeatureGate::class)->allows('module.inventory') && auth()->user()->hasPermission('purchasing.orders.manage'))
                            <a href="{{ route('app.purchasing.index') }}" class="{{ $navClass(request()->routeIs('app.purchasing.*')) }}">{{ __('app.purchasing') }}</a>
                        @endif
                        @if (app(\App\Support\Features\FeatureGate::class)->allows('module.inventory') && auth()->user()->hasPermission('inventory.counts.manage'))
                            <a href="{{ route('app.stock-counts.index') }}" class="{{ $navClass(request()->routeIs('app.stock-counts.*')) }}">{{ __('app.stock_counts') }}</a>
                        @endif
                        @if (app(\App\Support\Features\FeatureGate::class)->allows('module.inventory') && auth()->user()->hasPermission('inventory.movements.adjust'))
                            <a href="{{ route('app.stock-transfers.index') }}" class="{{ $navClass(request()->routeIs('app.stock-transfers.*')) }}">{{ __('app.stock_transfers') }}</a>
                        @endif
                        <a href="{{ route('app.profile') }}" class="{{ $navClass(request()->routeIs('app.profile')) }}">{{ __('app.profile') }}</a>
                    @endif
                </nav>
            </aside>
            <div class="min-w-0">
                <header class="flex items-center justify-between gap-4 border-b border-line bg-white px-4 py-2 md:px-8">
                    @include('layouts.partials.locale-switcher')
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-semibold">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="min-h-11 rounded-lg px-3 text-sm text-ink/70 hover:bg-canvas">{{ __('app.logout') }}</button>
                        </form>
                    </div>
                </header>
                <main class="mx-auto w-full max-w-5xl px-4 py-8 md:px-8">
                    @unless (auth()->user()->is_platform_user)
                        @php($subscriptionStatus = auth()->user()->tenant?->subscription?->status)
                        @if (in_array($subscriptionStatus, ['past_due', 'grace', 'expired', 'suspended'], true))
                            <p class="mb-6 rounded-lg border border-s-4 px-4 py-3 text-sm {{ $subscriptionStatus === 'grace' ? 'border-danger bg-white text-danger' : 'border-accent bg-white' }}">
                                {{ __('app.subscription_status_'.$subscriptionStatus) }}
                            </p>
                        @endif
                    @endunless
                    @if (session('status'))
                        <p class="mb-6 rounded-lg border border-s-4 border-brand bg-white px-4 py-3 text-sm">{{ session('status') }}</p>
                    @endif
                    @yield('content')
                </main>
            </div>
        </div>
    </body>
</html>
