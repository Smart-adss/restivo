@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('app.branches') }}</h1>
        @if (auth()->user()->hasPermission('tenant.branches.manage'))
            <a href="{{ route('app.branches.create') }}" class="inline-flex min-h-11 items-center rounded-md bg-brand px-4 text-sm font-semibold text-brand-ink">{{ __('app.create_branch') }}</a>
        @endif
    </div>
    <ul class="mt-6 grid gap-3 md:grid-cols-2">
        @forelse ($branches as $branch)
            <li>
                <a href="{{ route('app.branches.show', $branch->public_id) }}" class="block rounded-lg border border-line bg-white px-4 py-4">
                    <p class="font-semibold">{{ $branch->name() }}</p>
                    <p class="mt-1 text-sm text-ink/70">{{ $branch->city }}</p>
                </a>
            </li>
        @empty
            <li class="text-sm text-ink/70">{{ __('app.empty_title') }}</li>
        @endforelse
    </ul>
@endsection
