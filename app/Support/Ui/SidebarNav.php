<?php

namespace App\Support\Ui;

use App\Models\User;
use App\Support\Features\FeatureGate;
use Illuminate\Support\Facades\Route;

class SidebarNav
{
    /**
     * @return list<array{label: string, items: list<array{label: string, url: string, active: bool}>}>
     */
    public static function groups(?User $user = null): array
    {
        $user ??= auth()->user();

        if ($user === null) {
            return [];
        }

        return $user->is_platform_user
            ? self::platformGroups($user)
            : self::tenantGroups($user);
    }

    /**
     * @return list<array{label: string, items: list<array{label: string, url: string, active: bool}>}>
     */
    private static function platformGroups(User $user): array
    {
        $items = [
            self::item(__('app.tenants'), 'platform.tenants.index', ['platform.tenants.*']),
        ];

        if ($user->hasPermission('platform.plans.manage')) {
            $items[] = self::item(__('app.plans'), 'platform.plans.index', ['platform.plans.*']);
        }

        if ($user->hasPermission('platform.support.manage')) {
            $items[] = self::item(__('app.support'), 'platform.support.index', ['platform.support.*']);
        }

        if ($user->hasPermission('platform.tenants.manage')) {
            $items[] = self::item(__('app.settings'), 'platform.settings.edit', ['platform.settings.*']);
        }

        if ($user->hasPermission('platform.audit.view')) {
            $items[] = self::item(__('app.audit'), 'platform.audit.index', ['platform.audit.*']);
            $items[] = self::item(__('app.failed_jobs'), 'platform.failed-jobs.index', ['platform.failed-jobs.*']);
        }

        return [
            [
                'label' => __('app.platform_title'),
                'items' => array_values(array_filter($items)),
            ],
        ];
    }

    /**
     * @return list<array{label: string, items: list<array{label: string, url: string, active: bool}>}>
     */
    private static function tenantGroups(User $user): array
    {
        $gate = app(FeatureGate::class);
        $groups = [];

        $operations = [];
        if ($user->hasPermission('orders.orders.view') && $gate->allows('module.online_ordering')) {
            $operations[] = self::item(__('app.orders'), 'app.orders.index', ['app.orders.*']);
        }
        if ($user->hasPermission('reservations.reservations.manage') && $gate->allows('module.reservations')) {
            $operations[] = self::item(__('app.reservations'), 'app.reservations.index', ['app.reservations.*']);
        }
        if ($gate->allows('module.pos')) {
            $operations[] = self::item(__('app.pos'), 'app.pos', ['app.pos', 'app.pos.*']);
        }
        if ($user->hasPermission('tables.sessions.manage') || $user->hasPermission('tables.layout.manage')) {
            $operations[] = self::item(__('app.floor'), 'app.tables.index', ['app.tables.*']);
        }
        if ($gate->allows('module.kitchen') && ($user->hasPermission('kitchen.tickets.view') || $user->hasPermission('tenant.settings.manage'))) {
            $operations[] = self::item(__('app.kitchen'), 'app.kitchen.index', ['app.kitchen.*']);
        }
        if ($operations !== []) {
            $groups[] = ['label' => __('app.nav_operations'), 'items' => $operations];
        }

        $menu = [];
        if ($gate->allows('module.digital_menu')) {
            if ($user->hasPermission('menu.categories.manage')) {
                $menu[] = self::item(__('app.categories'), 'app.menu.categories.index', ['app.menu.categories.*']);
            }
            if ($user->hasPermission('menu.products.manage')) {
                $menu[] = self::item(__('app.products'), 'app.menu.products.index', ['app.menu.products.*', 'app.menu.options.*']);
            }
            if ($user->hasPermission('menu.theme.manage')) {
                $menu[] = self::item(__('app.menu_theme'), 'app.menu.theme.edit', ['app.menu.theme.*']);
            }
        }
        if ($menu !== []) {
            $groups[] = ['label' => __('app.nav_menu'), 'items' => $menu];
        }

        $inventory = [];
        if ($gate->allows('module.inventory')) {
            if ($user->hasPermission('inventory.items.manage') || $user->hasPermission('inventory.movements.adjust')) {
                $inventory[] = self::item(__('app.inventory'), 'app.inventory.index', ['app.inventory.*']);
            }
            if ($user->hasPermission('purchasing.orders.manage')) {
                $inventory[] = self::item(__('app.purchasing'), 'app.purchasing.index', ['app.purchasing.*']);
            }
            if ($user->hasPermission('inventory.counts.manage')) {
                $inventory[] = self::item(__('app.stock_counts'), 'app.stock-counts.index', ['app.stock-counts.*']);
            }
            if ($user->hasPermission('inventory.movements.adjust')) {
                $inventory[] = self::item(__('app.stock_transfers'), 'app.stock-transfers.index', ['app.stock-transfers.*']);
            }
        }
        if ($inventory !== []) {
            $groups[] = ['label' => __('app.nav_inventory'), 'items' => $inventory];
        }

        $finance = [];
        if ($user->hasPermission('finance.reports.view')) {
            $finance[] = self::item(__('app.day_report'), 'app.reports.day', ['app.reports.day']);
            $finance[] = self::item(__('app.shift_cash_report'), 'app.reports.shifts', ['app.reports.shifts']);
        }
        if ($user->hasPermission('finance.reports.advanced')) {
            $finance[] = self::item(__('app.tax_report'), 'app.reports.tax', ['app.reports.tax']);
            if ($gate->allows('module.advanced_reports')) {
                $finance[] = self::item(__('app.profit_report'), 'app.reports.profit', ['app.reports.profit']);
            }
            if ($gate->allows('module.advanced_reports') && $gate->allows('module.multi_branch')) {
                $finance[] = self::item(__('app.branch_comparison'), 'app.reports.branches', ['app.reports.branches']);
            }
        }
        if ($user->hasPermission('finance.expenses.manage')) {
            $finance[] = self::item(__('app.expenses'), 'app.expenses.index', ['app.expenses.*']);
        }
        if ($finance !== []) {
            $groups[] = ['label' => __('app.nav_finance'), 'items' => $finance];
        }

        $workspace = [
            self::item(__('app.branches'), 'app.home', ['app.home', 'app.branches.*']),
        ];
        if ($user->hasPermission('tenant.users.manage')) {
            $workspace[] = self::item(__('app.staff'), 'app.staff.index', ['app.staff.*']);
        }
        if ($user->hasPermission('tenant.settings.manage')) {
            $workspace[] = self::item(__('app.subscription'), 'app.subscription', ['app.subscription']);
            $workspace[] = self::item(__('app.taxes'), 'app.taxes.index', ['app.taxes.*']);
        }
        $workspace[] = self::item(__('app.support'), 'app.support.index', ['app.support.*']);
        $workspace[] = self::item(__('app.profile'), 'app.profile', ['app.profile']);

        $groups[] = ['label' => __('app.nav_workspace'), 'items' => $workspace];

        return $groups;
    }

    /**
     * @param  list<string>  $activePatterns
     * @return array{label: string, url: string, active: bool}|null
     */
    private static function item(string $label, string $routeName, array $activePatterns): ?array
    {
        if (! Route::has($routeName)) {
            return null;
        }

        return [
            'label' => $label,
            'url' => route($routeName),
            'active' => request()->routeIs(...$activePatterns),
        ];
    }
}
