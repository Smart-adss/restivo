@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('app.tenants') }}</h1>
        @if (auth()->user()->hasPermission('platform.tenants.manage'))
            <a href="{{ route('platform.tenants.create') }}" class="inline-flex min-h-11 items-center rounded-lg bg-brand hover:bg-ink px-4 text-sm font-semibold text-brand-ink">{{ __('app.create_tenant') }}</a>
        @endif
    </div>

    @if ($tenants->isEmpty())
        <p class="mt-6 text-sm text-ink/70">{{ __('app.no_tenants') }}</p>
    @else
        <ul class="mt-6 divide-y divide-line rounded-lg border border-line bg-white">
            @foreach ($tenants as $tenant)
                <li>
                    <a href="{{ route('platform.tenants.show', $tenant->public_id) }}" class="flex items-center justify-between gap-4 px-4 py-3">
                        <span>
                            <span class="block font-semibold">{{ $tenant->displayName() }}</span>
                            <span class="text-sm text-ink/70">{{ $tenant->slug }}</span>
                        </span>
                        <span class="text-sm">{{ $tenant->status === 'active' ? __('app.status_active') : __('app.status_suspended') }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
