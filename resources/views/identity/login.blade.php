<div>
    <h1 class="text-3xl font-semibold tracking-tight">{{ __('app.login_title') }}</h1>
    <p class="mt-2 text-sm text-ink/70">{{ __('app.login_hint') }}</p>

    <form wire:submit="authenticate" class="mt-8 space-y-4">
        <div>
            <label for="email" class="mb-1 block text-sm">{{ __('app.email') }}</label>
            <input id="email" type="email" wire:model="email" autocomplete="username" required
                class="w-full rounded-lg border border-line bg-white px-3 py-3 text-start outline-none focus:border-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
            @error('email')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm">{{ __('app.password') }}</label>
            <input id="password" type="password" wire:model="password" autocomplete="current-password" required
                class="w-full rounded-lg border border-line bg-white px-3 py-3 text-start outline-none focus:border-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
            @error('password')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model="remember" class="size-4">
            <span>{{ __('app.remember') }}</span>
        </label>

        <button type="submit" wire:loading.attr="disabled"
            class="flex min-h-12 w-full items-center justify-center rounded-lg bg-brand px-4 font-semibold text-brand-ink hover:bg-ink disabled:opacity-70">
            {{ __('app.sign_in') }}
        </button>
    </form>
</div>
