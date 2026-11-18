@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.audit')" :description="__('app.audit_page_hint')" />

    @if ($logs->isEmpty())
        <x-ui.empty-state :title="__('app.audit_empty')" />
    @else
        <ul class="space-y-3">
            @foreach ($logs as $log)
                <li class="ui-panel px-4 py-3 text-sm">
                    <p class="font-semibold">{{ $log->action }}</p>
                    <p class="mt-2 break-words text-ink/70" dir="ltr">{{ json_encode($log->before, JSON_UNESCAPED_UNICODE) }}</p>
                    <p class="break-words text-ink/70" dir="ltr">{{ json_encode($log->after, JSON_UNESCAPED_UNICODE) }}</p>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
