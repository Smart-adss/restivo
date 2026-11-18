<?php

namespace App\Support\Ui;

use App\Models\User;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Facades\Route;

class WorkspaceShortcuts
{
    /**
     * Real operational shortcuts for the tenant home (no invented metrics).
     *
     * @return list<array{label: string, url: string, hint: string}>
     */
    public static function for(?User $user = null): array
    {
        $user ??= auth()->user();
        if ($user === null || $user->is_platform_user) {
            return [];
        }

        $gate = app(FeatureGate::class);
        $items = [];

        if ($gate->allows('module.pos') && Route::has('app.pos')) {
            $items[] = [
                'label' => __('app.pos'),
                'url' => route('app.pos'),
                'hint' => __('app.home_shortcut_pos'),
            ];
        }

        if ($gate->allows('module.kitchen') && ($user->hasPermission('kitchen.tickets.view') || $user->hasPermission('tenant.settings.manage')) && Route::has('app.kitchen.index')) {
            $items[] = [
                'label' => __('app.kitchen'),
                'url' => route('app.kitchen.index'),
                'hint' => __('app.home_shortcut_kitchen'),
            ];
        }

        if ($user->hasPermission('orders.orders.view') && $gate->allows('module.online_ordering') && Route::has('app.orders.index')) {
            $items[] = [
                'label' => __('app.orders'),
                'url' => route('app.orders.index'),
                'hint' => __('app.home_shortcut_orders'),
            ];
        }

        if (($user->hasPermission('tables.sessions.manage') || $user->hasPermission('tables.layout.manage')) && Route::has('app.tables.index')) {
            $items[] = [
                'label' => __('app.floor'),
                'url' => route('app.tables.index'),
                'hint' => __('app.home_shortcut_floor'),
            ];
        }

        if ($user->hasPermission('finance.reports.view') && Route::has('app.reports.day')) {
            $items[] = [
                'label' => __('app.day_report'),
                'url' => route('app.reports.day'),
                'hint' => __('app.home_shortcut_day_report'),
            ];
        }

        return $items;
    }
}
