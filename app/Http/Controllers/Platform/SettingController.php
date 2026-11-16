<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        abort_unless(auth()->user()->hasPermission('platform.tenants.manage'), 403);

        return view('platform.settings.edit', [
            'graceDays' => SystemSetting::getValue('billing.grace_days', 7),
            'platformName' => SystemSetting::getValue('platform.name', config('app.name')),
            'maintenance' => (bool) SystemSetting::getValue('maintenance.enabled', false),
            'pollSeconds' => SystemSetting::getValue('kitchen.poll_seconds', 10),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('platform.tenants.manage'), 403);

        $data = $request->validate([
            'billing_grace_days' => ['required', 'integer', 'min:0', 'max:60'],
            'platform_name' => ['required', 'string', 'max:120'],
            'maintenance_enabled' => ['required', 'boolean'],
            'kitchen_poll_seconds' => ['required', 'integer', 'min:8', 'max:60'],
        ]);

        foreach ([
            'billing.grace_days' => (int) $data['billing_grace_days'],
            'platform.name' => $data['platform_name'],
            'maintenance.enabled' => (bool) $data['maintenance_enabled'],
            'kitchen.poll_seconds' => (int) $data['kitchen_poll_seconds'],
        ] as $key => $value) {
            SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => ['value' => $value]]);
        }

        return redirect()->route('platform.settings.edit')->with('status', __('app.settings_saved'));
    }
}
