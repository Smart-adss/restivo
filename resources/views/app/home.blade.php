@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('app.branches') }}</h1>
        @if (auth()->user()->hasPermission('tenant.branches.manage'))
            <a href="{{ route('app.branches.create') }}" class="inline-flex min-h-11 items-center rounded-md bg-brand px-4 text-sm font-semibold text-brand-ink">{{ __('app.create_branch') }}</a>
        @endif
    </div>

    @if ($branches->isEmpty())
        <div class="mx-auto mt-8 max-w-lg rounded-lg border border-dashed border-line bg-white px-6 py-12 text-center">
            <h2 class="text-xl font-semibold">{{ __('app.empty_title') }}</h2>
            <p class="mt-2 text-sm text-ink/70">{{ __('app.empty_body') }}</p>
        </div>
    @else
        <ul class="mt-6 grid gap-3 md:grid-cols-2">
            @foreach ($branches as $branch)
                <li>
                    <a href="{{ route('app.branches.show', $branch->public_id) }}" class="block rounded-lg border border-line bg-white px-4 py-4 hover:border-brand">
                        <p class="font-semibold">{{ $branch->name() }}</p>
                        <p class="mt-1 text-sm text-ink/70">{{ $branch->city }} — {{ $branch->code }}</p>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
