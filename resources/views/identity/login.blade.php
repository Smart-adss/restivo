@extends('layouts.guest')

@section('content')
    <h1 class="text-3xl font-semibold tracking-tight">{{ __('app.login_title') }}</h1>
    <p class="mt-2 text-sm text-ink/70">{{ __('app.login_hint') }}</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-4">
        @csrf
        <div>
            <label for="email" class="mb-1 block text-sm">{{ __('app.email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required
                class="w-full rounded-lg border border-line bg-white px-3 py-3 text-start outline-none focus:border-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
            @error('email')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm">{{ __('app.password') }}</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required
                class="w-full rounded-lg border border-line bg-white px-3 py-3 text-start outline-none focus:border-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
            @error('password')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1" class="size-4" @checked(old('remember'))>
            <span>{{ __('app.remember') }}</span>
        </label>

        <button type="submit"
            class="flex min-h-12 w-full items-center justify-center rounded-lg bg-brand px-4 font-semibold text-brand-ink hover:bg-ink">
            {{ __('app.sign_in') }}
        </button>
    </form>
@endsection
