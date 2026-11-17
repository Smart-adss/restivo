<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $branch->name() }} · {{ $tenant->displayName() }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            :root {
                --color-brand: {{ $theme['brand'] ?? '#145c40' }};
                --color-canvas: {{ $theme['canvas'] ?? '#e7eeea' }};
                --color-ink: {{ $theme['ink'] ?? '#12201b' }};
                --color-accent: {{ $theme['accent'] ?? '#f2c14e' }};
                --color-line: {{ $theme['line'] ?? '#d5e0da' }};
                --color-brand-ink: {{ $theme['brand-ink'] ?? '#f3faf6' }};
            }
        </style>
    </head>
    <body class="min-h-screen bg-canvas font-sans text-ink antialiased">
        <header class="border-b border-line bg-white">
            <div class="mx-auto flex w-full max-w-xl items-center justify-between gap-3 px-4 py-4">
                <div>
                    <p class="text-sm text-ink/60">{{ $tenant->displayName() }}</p>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ $branch->name() }}</h1>
                </div>
                <form method="POST" action="{{ route('locale.update') }}">
                    @csrf
                    <input type="hidden" name="locale" value="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}">
                    <button type="submit" class="min-h-11 rounded-lg border border-line px-3 text-sm">{{ app()->getLocale() === 'ar' ? 'EN' : 'عربي' }}</button>
                </form>
            </div>
        </header>

        <main class="mx-auto w-full max-w-xl px-4 py-6">
            @if ($closed_today || ! $is_open)
                <p class="mb-6 rounded-lg border border-s-4 border-accent bg-white px-4 py-3 text-sm">
                    {{ __('app.branch_closed_now') }}
                    @if (! $closed_today && $opens_at && $closes_at)
                        · {{ substr($opens_at, 0, 5) }} – {{ substr($closes_at, 0, 5) }}
                    @endif
                </p>
            @endif

            @forelse ($categories as $category)
                <section class="mb-8">
                    <h2 class="mb-3 text-lg font-semibold">{{ app()->getLocale() === 'ar' ? $category['name_ar'] : $category['name_en'] }}</h2>
                    <ul class="space-y-3">
                        @foreach ($category['products'] as $product)
                            <li class="border-b border-line pb-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-semibold">{{ app()->getLocale() === 'ar' ? $product['name_ar'] : $product['name_en'] }}</p>
                                        @php($desc = app()->getLocale() === 'ar' ? $product['description_ar'] : $product['description_en'])
                                        @if ($desc)
                                            <p class="mt-1 line-clamp-2 text-sm text-ink/70">{{ $desc }}</p>
                                        @endif
                                    </div>
                                    <p class="shrink-0 font-semibold tabular-nums">{{ $product['price'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    @foreach ($category['children'] as $child)
                        <div class="mt-5">
                            <h3 class="mb-2 text-base font-semibold">{{ app()->getLocale() === 'ar' ? $child['name_ar'] : $child['name_en'] }}</h3>
                            <ul class="space-y-3">
                                @foreach ($child['products'] as $product)
                                    <li class="border-b border-line pb-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="font-semibold">{{ app()->getLocale() === 'ar' ? $product['name_ar'] : $product['name_en'] }}</p>
                                            </div>
                                            <p class="shrink-0 font-semibold tabular-nums">{{ $product['price'] }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </section>
            @empty
                <p class="text-sm text-ink/70">{{ __('app.public_menu_empty') }}</p>
            @endforelse
        </main>
    </body>
</html>
