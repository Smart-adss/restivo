<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="min-h-screen bg-canvas font-sans text-ink antialiased">
        <div class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-6 py-10">
            <p class="text-3xl font-semibold tracking-tight">{{ config('app.name') }}</p>
            <div class="mt-8">
                <h1 class="text-2xl font-semibold">{{ __('app.suspended_title') }}</h1>
                <p class="mt-2 text-sm text-ink/70">{{ __('app.suspended_body') }}</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-6">
                    @csrf
                    <button type="submit" class="mt-2 flex min-h-12 w-full items-center justify-center rounded-lg bg-brand px-4 font-semibold text-brand-ink hover:bg-ink">{{ __('app.logout') }}</button>
                </form>
            </div>
        </div>
    </body>
</html>
