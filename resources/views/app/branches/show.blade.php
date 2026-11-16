@extends('layouts.app')

@section('content')
    <h1 class="text-2xl font-semibold">{{ $branch->name() }}</h1>
    <dl class="mt-6 max-w-xl space-y-3 rounded-lg border border-line bg-white px-4 py-4 text-sm">
        <div class="flex justify-between gap-4"><dt class="text-ink/60">{{ __('app.branch_code') }}</dt><dd>{{ $branch->code }}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-ink/60">{{ __('app.city') }}</dt><dd>{{ $branch->city }}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-ink/60">{{ __('app.address') }}</dt><dd>{{ $branch->address }}</dd></div>
    </dl>
@endsection
