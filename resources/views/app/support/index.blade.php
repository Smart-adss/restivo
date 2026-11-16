@extends('layouts.app')

@section('content')
    <h1 class="text-2xl font-semibold">{{ __('app.support') }}</h1>
    <form method="POST" action="{{ route('app.support.store') }}" class="mt-6 max-w-xl space-y-3">
        @csrf
        <input name="subject" required placeholder="{{ __('app.subject') }}" class="w-full rounded-md border border-line bg-white px-3 py-3">
        <textarea name="body" required class="w-full rounded-md border border-line bg-white px-3 py-3"></textarea>
        <button class="inline-flex min-h-11 items-center rounded-lg bg-brand hover:bg-ink px-4 font-semibold text-brand-ink">{{ __('app.save') }}</button>
    </form>
    <ul class="mt-6 max-w-xl divide-y divide-line rounded-lg border border-line bg-white">
        @foreach ($tickets as $ticket)
            <li class="px-4 py-3 text-sm">{{ $ticket->subject }}</li>
        @endforeach
    </ul>
@endsection
