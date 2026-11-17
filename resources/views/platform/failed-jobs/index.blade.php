@extends('layouts.app')

@section('content')
    <h1 class="text-2xl font-semibold">{{ __('app.failed_jobs') }}</h1>
    <p class="mt-2 text-sm text-ink/70">{{ __('app.failed_jobs_hint') }}</p>

    @if ($jobs->isEmpty())
        <p class="mt-8 text-ink/70">{{ __('app.failed_jobs_empty') }}</p>
    @else
        <ul class="mt-6 space-y-3">
            @foreach ($jobs as $job)
                <li class="rounded-lg border border-line bg-white px-4 py-3 text-sm">
                    <p class="font-semibold" dir="ltr">{{ $job->uuid }}</p>
                    <p class="mt-1 text-ink/60">{{ $job->failed_at }} · {{ $job->queue }}</p>
                    <p class="mt-2 break-words font-mono text-xs text-danger" dir="ltr">{{ $job->exception_preview }}</p>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
