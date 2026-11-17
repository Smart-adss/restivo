<?php

namespace App\Http\Controllers\Menu;

use App\Domain\Menu\Actions\ActivateMenuTheme;
use App\Domain\Menu\Actions\EnsureTenantThemes;
use App\Domain\Menu\Support\MenuThemeCatalog;
use App\Http\Controllers\Controller;
use App\Models\MenuTheme;
use App\Support\Features\FeatureGate;
use App\Support\Features\SubscriptionReadOnly;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ThemeController extends Controller
{
    public function edit(FeatureGate $features, EnsureTenantThemes $ensureTenantThemes): View
    {
        $this->authorizeTheme($features);
        $ensureTenantThemes->handle();

        return view('app.menu.theme.edit', [
            'presets' => MenuThemeCatalog::all(),
            'active' => MenuTheme::query()->where('is_active', true)->value('preset_key') ?? 'green',
        ]);
    }

    public function update(Request $request, ActivateMenuTheme $activateMenuTheme, FeatureGate $features): RedirectResponse
    {
        $this->authorizeTheme($features);

        $data = $request->validate([
            'preset_key' => ['required', 'string', 'in:'.implode(',', array_keys(MenuThemeCatalog::all()))],
        ], [], [
            'preset_key' => __('app.menu_theme'),
        ]);

        try {
            $activateMenuTheme->handle($data['preset_key']);
        } catch (SubscriptionReadOnly) {
            abort(403);
        }

        return back()->with('status', __('app.theme_saved'));
    }

    private function authorizeTheme(FeatureGate $features): void
    {
        abort_unless(auth()->user()->hasPermission('menu.theme.manage'), 403);

        if (! $features->allows('module.digital_menu')) {
            abort(404);
        }
    }
}
