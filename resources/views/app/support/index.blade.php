@extends('layouts.app')

@section('content')
    <x-ui.page-header :title="__('app.support')" :description="__('app.support_page_hint')" />

    <x-ui.panel class="max-w-xl px-4 py-5">
        <form method="POST" action="{{ route('app.support.store') }}" class="space-y-3">
            @csrf
            <x-ui.field name="subject" required placeholder="{{ __('app.subject') }}" />
            <x-ui.textarea name="body" required rows="5" placeholder="{{ __('app.message') }}"></x-ui.textarea>
            <x-ui.button type="submit" variant="primary">{{ __('app.save') }}</x-ui.button>
        </form>
    </x-ui.panel>

    <div class="ui-panel mt-6 max-w-xl overflow-hidden">
        <ul>
            @forelse ($tickets as $ticket)
                <li class="border-b border-line px-4 py-3 text-sm last:border-b-0">{{ $ticket->subject }}</li>
            @empty
                <li class="px-4 py-6 text-sm text-ink/70">{{ __('app.support_empty') }}</li>
            @endforelse
        </ul>
    </div>
@endsection
