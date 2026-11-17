@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.branches')" :description="__('app.branches_home_hint')">
        <x-slot:actions>
            @if (auth()->user()->hasPermission('tenant.branches.manage'))
                <x-ui.button :href="route('app.branches.create')" variant="primary">
                    {{ __('app.create_branch') }}
                </x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

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
@endsection
