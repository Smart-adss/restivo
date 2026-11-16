<?php

namespace App\Domain\Identity;

final class RoleCatalog
{
    /**
     * @return array<string, array{name_ar: string, name_en: string, grants: list<string>}>
     */
    public static function definitions(): array
    {
        $tenant = PermissionCatalog::tenantKeys();
        $withoutBranchAdmin = array_values(array_filter(
            $tenant,
            fn (string $key): bool => ! in_array($key, ['tenant.branches.manage', 'tenant.roles.manage'], true),
        ));

        return [
            'platform_super_admin' => [
                'name_ar' => 'مشرف المنصة',
                'name_en' => 'Platform super admin',
                'grants' => PermissionCatalog::platformKeys(),
            ],
            'platform_support' => [
                'name_ar' => 'دعم المنصة',
                'name_en' => 'Platform support',
                'grants' => ['platform.tenants.view', 'platform.support.manage'],
            ],
            'business_owner' => [
                'name_ar' => 'مالك المنشأة',
                'name_en' => 'Business owner',
                'grants' => $tenant,
            ],
            'branch_manager' => [
                'name_ar' => 'مدير الفرع',
                'name_en' => 'Branch manager',
                'grants' => $withoutBranchAdmin,
            ],
            'cashier' => [
                'name_ar' => 'كاشير',
                'name_en' => 'Cashier',
                'grants' => [
                    'orders.orders.view',
                    'orders.orders.create',
                    'pos.shift.open',
                    'pos.shift.close',
                    'pos.sale.create',
                ],
            ],
            'kitchen_staff' => [
                'name_ar' => 'موظف المطبخ',
                'name_en' => 'Kitchen staff',
                'grants' => ['kitchen.tickets.view', 'kitchen.tickets.update'],
            ],
            'waiter' => [
                'name_ar' => 'ويتر',
                'name_en' => 'Waiter',
                'grants' => [
                    'tables.sessions.manage',
                    'orders.orders.view',
                    'orders.orders.create',
                    'kitchen.tickets.view',
                ],
            ],
            'inventory_clerk' => [
                'name_ar' => 'أمين المخزن',
                'name_en' => 'Inventory clerk',
                'grants' => [
                    'inventory.items.manage',
                    'inventory.movements.adjust',
                    'inventory.counts.manage',
                    'purchasing.orders.manage',
                ],
            ],
            'accountant' => [
                'name_ar' => 'محاسب',
                'name_en' => 'Accountant',
                'grants' => [
                    'finance.reports.view',
                    'finance.reports.advanced',
                    'finance.expenses.manage',
                    'orders.orders.view',
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function tenantTemplateKeys(): array
    {
        return array_values(array_filter(
            array_keys(self::definitions()),
            fn (string $key): bool => ! str_starts_with($key, 'platform_'),
        ));
    }

    /**
     * @return list<string>
     */
    public static function assignableStaffKeys(): array
    {
        return array_values(array_filter(
            self::tenantTemplateKeys(),
            fn (string $key): bool => $key !== 'business_owner',
        ));
    }
}
