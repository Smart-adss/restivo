<?php

namespace App\Domain\Menu\Actions;

use App\Domain\Menu\Support\MenuThemeCatalog;
use App\Domain\Menu\Support\PublicMenuCache;
use App\Models\MenuTheme;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Facades\DB;

class ActivateMenuTheme
{
    public function __construct(
        private FeatureGate $features,
        private EnsureTenantThemes $ensureTenantThemes,
    ) {}

    public function handle(string $presetKey): MenuTheme
    {
        $this->features->assertWritable();

        if (! $this->features->allows('module.digital_menu')) {
            abort(404);
        }

        if (! array_key_exists($presetKey, MenuThemeCatalog::all())) {
            abort(404);
        }

        $this->ensureTenantThemes->handle();

        return DB::transaction(function () use ($presetKey): MenuTheme {
            MenuTheme::query()->update(['is_active' => false]);

            $theme = MenuTheme::query()->where('preset_key', $presetKey)->firstOrFail();
            $preset = MenuThemeCatalog::all()[$presetKey];
            $theme->fill([
                'name' => $preset['name_ar'],
                'tokens' => MenuThemeCatalog::sanitize($preset['tokens']),
                'is_active' => true,
            ])->save();

            PublicMenuCache::forgetTenant();

            return $theme->refresh();
        });
    }
}
