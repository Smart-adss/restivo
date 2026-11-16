<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="min-h-screen bg-canvas font-sans text-ink antialiased">
        <div class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-4 py-10">
            <div class="rounded-lg border border-line bg-white px-5 py-6">
                <h1 class="text-2xl font-semibold">{{ __('app.suspended_title') }}</h1>
                <p class="mt-2 text-sm text-ink/70">{{ __('app.suspended_body') }}</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-6">
                    @csrf
                    <button type="submit" class="flex min-h-11 w-full items-center justify-center rounded-md bg-brand px-4 font-semibold text-brand-ink">{{ __('app.logout') }}</button>
                </form>
            </div>
        </div>
    </body>
</html>
