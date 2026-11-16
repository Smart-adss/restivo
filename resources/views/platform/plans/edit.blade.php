@extends('layouts.app')

@section('content')
    @php($field = 'w-full rounded-md border border-line bg-white px-3 py-3 text-start outline-none focus:border-brand')
    <h1 class="text-2xl font-semibold">{{ $plan->name() }}</h1>
    <p class="mt-1 text-sm text-ink/70">{{ $plan->code }}</p>
    <form method="POST" action="{{ route('platform.plans.update', $plan->code) }}" class="mt-6 max-w-xl space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label class="mb-1 block text-sm" for="name_ar">{{ __('app.name_ar') }}</label>
            <input id="name_ar" name="name_ar" value="{{ old('name_ar', $plan->name_ar) }}" class="{{ $field }}">
        </div>
        <div>
            <label class="mb-1 block text-sm" for="name_en">{{ __('app.name_en') }}</label>
            <input id="name_en" name="name_en" value="{{ old('name_en', $plan->name_en) }}" class="{{ $field }}">
        </div>
        <div>
            <label class="mb-1 block text-sm" for="description">{{ __('app.description') }}</label>
            <textarea id="description" name="description" class="{{ $field }}">{{ old('description', $plan->description) }}</textarea>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm" for="monthly_amount">{{ __('app.monthly') }}</label>
                <input id="monthly_amount" name="monthly_amount" value="{{ old('monthly_amount', $plan->priceFor('monthly')) }}" class="{{ $field }}">
            </div>
            <div>
                <label class="mb-1 block text-sm" for="yearly_amount">{{ __('app.yearly') }}</label>
                <input id="yearly_amount" name="yearly_amount" value="{{ old('yearly_amount', $plan->priceFor('yearly')) }}" class="{{ $field }}">
            </div>
        </div>
        <input type="hidden" name="is_active" value="{{ $plan->is_active ? 1 : 0 }}">
        <input type="hidden" name="is_public" value="{{ $plan->is_public ? 1 : 0 }}">
        <input type="hidden" name="trial_days" value="{{ $plan->trial_days }}">
        @foreach ($catalog as $key => $definition)
            @php($current = $plan->features->firstWhere('key', $key)?->pivot->value)
            <div>
                <label class="mb-1 block text-sm" for="feature-{{ $key }}">{{ $definition['name_ar'] }} <span class="text-ink/50">{{ $key }}</span></label>
                <input id="feature-{{ $key }}" name="features[{{ $key }}]" value="{{ old('features.'.$key, $current) }}" class="{{ $field }}">
            </div>
        @endforeach
        <button class="inline-flex min-h-11 items-center rounded-lg bg-brand hover:bg-ink px-4 font-semibold text-brand-ink">{{ __('app.save') }}</button>
    </form>
@endsection
