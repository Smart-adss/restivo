@extends('layouts.app')

@section('content')
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('app.staff') }}</h1>
        <a href="{{ route('app.staff.create') }}" class="inline-flex min-h-11 items-center rounded-md bg-brand px-4 text-sm font-semibold text-brand-ink">{{ __('app.create_staff') }}</a>
    </div>
    <ul class="mt-6 divide-y divide-line rounded-lg border border-line bg-white">
        @foreach ($staff as $member)
            <li class="flex items-center justify-between gap-4 px-4 py-3">
                <div>
                    <p class="font-semibold">{{ $member->name }}</p>
                    <p class="text-sm text-ink/70">{{ $member->email }}</p>
                </div>
                <p class="text-sm">
                    @foreach ($roleNames->get($member->id, []) as $role)
                        {{ app()->getLocale() === 'ar' ? $role->name_ar : $role->name_en }}
                    @endforeach
                </p>
            </li>
        @endforeach
    </ul>
@endsection
