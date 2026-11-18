@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.tenants')" :description="__('app.tenants_index_hint')">
        <x-slot:actions>
            @if (auth()->user()->hasPermission('platform.tenants.manage'))
                <x-ui.button :href="route('platform.tenants.create')" variant="primary">
                    {{ __('app.create_tenant') }}
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($tenants->isEmpty())
        <x-ui.empty-state :title="__('app.no_tenants')" />
    @else
        <ul class="ui-panel divide-y divide-line overflow-hidden !p-0">
            @foreach ($tenants as $tenant)
                <li>
                    <a href="{{ route('platform.tenants.show', $tenant->public_id) }}" class="flex items-center justify-between gap-4 px-4 py-3 transition hover:bg-canvas/60">
                        <span class="min-w-0">
                            <span class="block font-semibold">{{ $tenant->displayName() }}</span>
                            <span class="text-sm text-ink/70">{{ $tenant->slug }}</span>
                            @if ($tenant->subscription)
                                <span class="mt-1 block text-xs text-ink/55">
                                    {{ $tenant->subscription->plan?->name() }}
                                    · {{ $tenant->subscription->current_period_end?->timezone('Africa/Cairo')->toDateString() }}
                                </span>
                            @endif
                        </span>
                        <span @class([
                            'shrink-0 rounded-md px-2 py-1 text-xs font-semibold',
                            'bg-canvas text-brand' => $tenant->status === 'active',
                            'bg-danger/10 text-danger' => $tenant->status !== 'active',
                        ])>
                            {{ $tenant->status === 'active' ? __('app.status_active') : __('app.status_suspended') }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
