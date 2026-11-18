@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.workspace_home_title')" :description="__('app.workspace_home_hint')">
        <x-slot:actions>
            @if (auth()->user()->hasPermission('tenant.branches.manage'))
                <x-ui.button :href="route('app.branches.create')" variant="primary">
                    {{ __('app.create_branch') }}
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($subscription)
        <x-ui.panel class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm text-ink/60">{{ __('app.subscription') }}</p>
                <p class="mt-1 font-semibold">{{ $subscription->plan?->name() }} · {{ __('app.subscription_status_'.$subscription->status) }}</p>
            </div>
            <p class="text-sm tabular-nums text-ink/70">
                {{ __('app.period_end') }}:
                {{ $subscription->current_period_end?->timezone(auth()->user()->tenant?->timezone ?: 'Africa/Cairo')->toDateString() }}
            </p>
        </x-ui.panel>
    @endif

    @if ($shortcuts !== [])
        <section class="mb-8">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-[0.08em] text-ink/45">{{ __('app.home_quick_actions') }}</h2>
            <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($shortcuts as $shortcut)
                    <li>
                        <a href="{{ $shortcut['url'] }}" class="ui-panel block px-4 py-4 transition hover:border-brand">
                            <p class="font-semibold">{{ $shortcut['label'] }}</p>
                            <p class="mt-1 text-sm text-ink/65">{{ $shortcut['hint'] }}</p>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section>
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-[0.08em] text-ink/45">{{ __('app.branches') }}</h2>

        @if ($branches->isEmpty())
            <x-ui.empty-state :title="__('app.empty_title')" :body="__('app.empty_body')">
                <x-slot:action>
                    @if (auth()->user()->hasPermission('tenant.branches.manage'))
                        <x-ui.button :href="route('app.branches.create')" variant="primary">
                            {{ __('app.create_branch') }}
                        </x-ui.button>
                    @endif
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <ul class="grid gap-3 md:grid-cols-2">
                @foreach ($branches as $branch)
                    <li>
                        <a href="{{ route('app.branches.show', $branch->public_id) }}" class="ui-panel block px-4 py-4 transition hover:border-brand">
                            <p class="font-semibold">{{ $branch->name() }}</p>
                            <p class="mt-1 text-sm text-ink/70">{{ $branch->city }} — {{ $branch->code }}</p>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
