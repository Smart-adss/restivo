@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">{{ $tenant->displayName() }}</h1>
            <p class="mt-1 text-sm text-ink/70">{{ $tenant->slug }}</p>
        </div>
        <p class="text-sm">{{ $tenant->status === 'active' ? __('app.status_active') : __('app.status_suspended') }}</p>
    </div>

    @if (session('temporary_password'))
        <div class="mt-6 max-w-xl rounded-lg border border-accent bg-white px-4 py-4">
            <p class="text-sm font-semibold">{{ __('app.temporary_password') }}</p>
            <p class="mt-2 font-mono text-lg">{{ session('temporary_password') }}</p>
            <p class="mt-2 text-sm text-ink/70">{{ session('owner_email') }} — {{ __('app.temporary_password_hint') }}</p>
        </div>
    @endif

    @if (auth()->user()->hasPermission('platform.tenants.manage'))
        <form method="POST" action="{{ route($tenant->status === 'active' ? 'platform.tenants.suspend' : 'platform.tenants.activate', $tenant->public_id) }}" class="mt-6">
            @csrf
            <button type="submit" class="inline-flex min-h-11 items-center rounded-md border border-line bg-white px-4 text-sm">
                {{ $tenant->status === 'active' ? __('app.suspend') : __('app.activate') }}
            </button>
        </form>
    @endif
@endsection
