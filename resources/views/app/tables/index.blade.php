@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ __('app.floor') }}</h1>
        <form method="GET" class="flex items-center gap-2">
            <label class="text-sm" for="branch_id">{{ __('app.branch') }}</label>
            <select id="branch_id" name="branch_id" class="min-h-11 rounded-lg border border-line bg-white px-3" onchange="this.form.submit()">
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((int) $branch_id === (int) $branch->id)>{{ $branch->name() }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if (session('status'))
        <p class="mt-4 rounded-lg border border-line bg-white px-4 py-3 text-sm">{{ session('status') }}</p>
    @endif

    @if (auth()->user()->hasPermission('tables.layout.manage'))
        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <form method="POST" action="{{ route('app.tables.areas.store') }}" class="space-y-3 rounded-lg border border-line bg-white p-4">
                @csrf
                <h2 class="font-semibold">{{ __('app.add_dining_area') }}</h2>
                <input type="hidden" name="branch_id" value="{{ $branch_id }}">
                <input name="name_ar" required maxlength="120" placeholder="{{ __('app.name_ar') }}" class="min-h-11 w-full rounded-lg border border-line px-3">
                <input name="name_en" required maxlength="120" placeholder="{{ __('app.name_en') }}" class="min-h-11 w-full rounded-lg border border-line px-3">
                <button class="min-h-11 rounded-lg bg-brand px-4 text-sm font-semibold text-brand-ink">{{ __('app.save') }}</button>
            </form>

            <form method="POST" action="{{ route('app.tables.tables.store') }}" class="space-y-3 rounded-lg border border-line bg-white p-4">
                @csrf
                <h2 class="font-semibold">{{ __('app.add_dining_table') }}</h2>
                <input type="hidden" name="branch_id" value="{{ $branch_id }}">
                <select name="area_id" class="min-h-11 w-full rounded-lg border border-line px-3">
                    <option value="">{{ __('app.no_area') }}</option>
                    @foreach ($areas as $area)
                        <option value="{{ $area->id }}">{{ app()->getLocale() === 'ar' ? $area->name_ar : $area->name_en }}</option>
                    @endforeach
                </select>
                <input name="name" required maxlength="80" placeholder="{{ __('app.table_name') }}" class="min-h-11 w-full rounded-lg border border-line px-3" value="{{ old('name') }}">
                @error('name')
                    <p class="text-sm text-red-700">{{ $message }}</p>
                @enderror
                <input name="seats" type="number" min="1" max="30" value="2" required class="min-h-11 w-full rounded-lg border border-line px-3">
                <button class="min-h-11 rounded-lg bg-brand px-4 text-sm font-semibold text-brand-ink">{{ __('app.save') }}</button>
            </form>
        </div>
    @endif

    <div class="mt-8 space-y-8">
        @foreach ($areas as $area)
            <section>
                <h2 class="mb-3 text-lg font-semibold">{{ app()->getLocale() === 'ar' ? $area->name_ar : $area->name_en }}</h2>
                <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($area->tables as $table)
                        @include('app.tables.partials.table-card', ['table' => $table, 'sessions' => $sessions, 'qrs' => $qrs, 'orphan_tables' => $orphan_tables, 'areas' => $areas])
                    @endforeach
                </ul>
            </section>
        @endforeach

        @if ($orphan_tables->isNotEmpty())
            <section>
                <h2 class="mb-3 text-lg font-semibold">{{ __('app.no_area') }}</h2>
                <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($orphan_tables as $table)
                        @include('app.tables.partials.table-card', ['table' => $table, 'sessions' => $sessions, 'qrs' => $qrs, 'orphan_tables' => $orphan_tables, 'areas' => $areas])
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection
