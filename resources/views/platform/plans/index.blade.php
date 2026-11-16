@extends('layouts.app')

@section('content')
    <h1 class="text-2xl font-semibold">{{ __('app.plans') }}</h1>
    <ul class="mt-6 divide-y divide-line rounded-lg border border-line bg-white">
        @foreach ($plans as $plan)
            <li>
                <a href="{{ route('platform.plans.edit', $plan->code) }}" class="flex items-center justify-between px-4 py-3">
                    <span>
                        <span class="block font-semibold">{{ $plan->name() }}</span>
                        <span class="text-sm text-ink/70">{{ $plan->code }}</span>
                    </span>
                    <span class="text-sm">{{ $plan->priceFor('monthly') }}</span>
                </a>
            </li>
        @endforeach
    </ul>
@endsection
