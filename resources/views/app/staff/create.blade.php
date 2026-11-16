@extends('layouts.app')

@section('content')
    @php($field = 'w-full rounded-md border border-line bg-white px-3 py-3 text-start outline-none focus:border-brand')
    <h1 class="text-2xl font-semibold">{{ __('app.create_staff') }}</h1>
    <form method="POST" action="{{ route('app.staff.store') }}" class="mt-6 max-w-xl space-y-4">
        @csrf
        @foreach (['name', 'email', 'phone'] as $name)
            <div>
                <label for="{{ $name }}" class="mb-1 block text-sm">{{ __('app.'.$name) }}</label>
                <input id="{{ $name }}" name="{{ $name }}" type="{{ $name === 'email' ? 'email' : 'text' }}" value="{{ old($name) }}" @required($name !== 'phone') class="{{ $field }}">
                @error($name)
                    <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>
        @endforeach
        <div>
            <label for="password" class="mb-1 block text-sm">{{ __('app.password') }}</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" class="{{ $field }}">
            @error('password')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="role_key" class="mb-1 block text-sm">{{ __('app.role') }}</label>
            <select id="role_key" name="role_key" required class="{{ $field }}">
                @foreach ($roles as $role)
                    <option value="{{ $role->key }}" @selected(old('role_key') === $role->key)>{{ $role->name() }}</option>
                @endforeach
            </select>
            @error('role_key')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>
        <fieldset class="space-y-2">
            <legend class="mb-1 text-sm">{{ __('app.branches') }}</legend>
            @foreach ($branches as $branch)
                <label class="flex min-h-11 items-center gap-2 text-sm">
                    <input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" @checked(in_array($branch->id, old('branch_ids', [])))>
                    <span>{{ $branch->name() }}</span>
                </label>
            @endforeach
            @error('branch_ids')
                <p class="text-sm text-danger">{{ $message }}</p>
            @enderror
        </fieldset>
        <button type="submit" class="flex min-h-11 items-center justify-center rounded-md bg-brand px-4 font-semibold text-brand-ink">{{ __('app.save') }}</button>
    </form>
@endsection
