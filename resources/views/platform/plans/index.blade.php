@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.plans')" :description="__('app.plans_index_hint')" />

    <ul class="ui-panel divide-y divide-line overflow-hidden !p-0">
        @foreach ($plans as $plan)
            <li>
                <a href="{{ route('platform.plans.edit', $plan->code) }}" class="flex items-center justify-between gap-4 px-4 py-3 transition hover:bg-canvas/60">
                    <span>
                        <span class="block font-semibold">{{ $plan->name() }}</span>
                        <span class="text-sm text-ink/70">{{ $plan->code }}</span>
                    </span>
                    <span class="tabular-nums text-sm font-semibold">{{ $plan->priceFor('monthly') }}</span>
                </a>
            </li>
        @endforeach
    </ul>
@endsection
