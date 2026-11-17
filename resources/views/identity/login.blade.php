@extends('layouts.guest')

@section('content')
    <h1 class="text-3xl font-semibold tracking-tight">{{ __('app.login_title') }}</h1>
    <p class="mt-2 text-sm text-ink/70">{{ __('app.login_hint') }}</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-4">
        @csrf
        <div>
            <x-ui.label for="email">{{ __('app.email') }}</x-ui.label>
            <x-ui.field id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required />
            @error('email')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <x-ui.label for="password">{{ __('app.password') }}</x-ui.label>
            <x-ui.field id="password" name="password" type="password" autocomplete="current-password" required />
            @error('password')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" value="1" class="size-4 rounded border-line" @checked(old('remember'))>
            <span>{{ __('app.remember') }}</span>
        </label>

        <x-ui.button type="submit" variant="primary" class="w-full min-h-12">
            {{ __('app.sign_in') }}
        </x-ui.button>
    </form>
@endsection
