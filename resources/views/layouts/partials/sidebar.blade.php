@php
    $groups = \App\Support\Ui\SidebarNav::groups();
@endphp

<aside class="ui-shell-aside text-white md:sticky md:top-0 md:flex md:h-dvh md:min-h-screen md:w-[15.5rem] md:shrink-0 md:flex-col">
    <div class="flex items-center justify-between gap-3 px-4 py-5 md:block">
        <a href="{{ auth()->user()->is_platform_user ? route('platform.tenants.index') : route('app.home') }}" class="block min-w-0">
            <span class="block text-2xl font-semibold tracking-tight">{{ config('app.name') }}</span>
            <span class="mt-1 block truncate text-sm text-white/55">
                {{ auth()->user()->tenant?->displayName() ?? __('app.platform_title') }}
            </span>
        </a>
        <label for="shell-nav-toggle" class="ui-btn ui-btn-ghost border border-white/15 text-white md:hidden">
            {{ __('app.menu') }}
        </label>
    </div>

    <input id="shell-nav-toggle" type="checkbox" class="peer sr-only md:hidden">

    <nav class="hidden max-h-[70vh] overflow-y-auto px-3 pb-5 peer-checked:block md:block md:max-h-none md:flex-1 md:overflow-y-auto">
        @foreach ($groups as $group)
            <div @class(['mt-5' => ! $loop->first, 'mt-1' => $loop->first])>
                <p class="mb-2 px-3 text-[0.7rem] font-semibold uppercase tracking-[0.08em] text-white/40">
                    {{ $group['label'] }}
                </p>
                <div class="space-y-0.5">
                    @foreach ($group['items'] as $item)
                        <a
                            href="{{ $item['url'] }}"
                            @class(['ui-nav-link', 'ui-nav-link-active' => $item['active']])
                        >
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>
</aside>
