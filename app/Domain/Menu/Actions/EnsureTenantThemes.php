<?php

namespace App\Domain\Menu\Actions;

use App\Domain\Menu\Support\MenuThemeCatalog;
use App\Models\MenuTheme;
use Illuminate\Support\Facades\DB;

class EnsureTenantThemes
{
    public function handle(): void
    {
        DB::transaction(function (): void {
            $existing = MenuTheme::query()->get()->keyBy('preset_key');

            foreach (MenuThemeCatalog::all() as $key => $preset) {
                if ($existing->has($key)) {
                    continue;
                }

                MenuTheme::query()->create([
                    'name' => $preset['name_ar'],
                    'preset_key' => $key,
                    'tokens' => $preset['tokens'],
                    'is_active' => $key === 'green' && $existing->isEmpty(),
                ]);
            }

            if (! MenuTheme::query()->where('is_active', true)->exists()) {
                MenuTheme::query()->where('preset_key', 'green')->update(['is_active' => true]);
            }
        });
    }
}
