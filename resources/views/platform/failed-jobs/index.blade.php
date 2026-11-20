@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.failed_jobs')" :description="__('app.failed_jobs_hint')" />

    @if ($jobs->isEmpty())
        <x-ui.empty-state :title="__('app.failed_jobs_empty')" />
    @else
        <ul class="space-y-3">
            @foreach ($jobs as $job)
                <li class="ui-panel px-4 py-3 text-sm">
                    <p class="font-semibold" dir="ltr">{{ $job->uuid }}</p>
                    <p class="mt-1 text-ink/60">{{ $job->failed_at }} · {{ $job->queue }}</p>
                    <p class="mt-2 break-words font-mono text-xs text-danger" dir="ltr">{{ $job->exception_preview }}</p>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
