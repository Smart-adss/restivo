<?php

namespace App\Domain\Identity;

final class PermissionCatalog
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'platform.tenants.view' => 'عرض المنشآت',
            'platform.tenants.manage' => 'إنشاء المنشآت وتعليقها',
            'platform.plans.manage' => 'إدارة الخطط',
            'platform.billing.record_payment' => 'تسجيل دفعة',
            'platform.support.manage' => 'تذاكر الدعم',
            'platform.audit.view' => 'سجل التدقيق',
            'tenant.settings.manage' => 'إعدادات المنشأة',
            'tenant.branches.manage' => 'إدارة الفروع',
            'tenant.users.manage' => 'إدارة الموظفين',
            'tenant.roles.manage' => 'إدارة الأدوار',
            'menu.categories.manage' => 'تصنيفات القائمة',
            'menu.products.manage' => 'منتجات القائمة',
            'menu.theme.manage' => 'مظهر القائمة',
            'orders.orders.view' => 'عرض الطلبات',
            'orders.orders.create' => 'إنشاء طلب',
            'orders.orders.accept' => 'قبول طلب',
            'orders.orders.cancel' => 'إلغاء طلب',
            'orders.discounts.manual' => 'خصم يدوي',
            'pos.shift.open' => 'فتح وردية',
            'pos.shift.close' => 'إغلاق وردية',
            'pos.sale.create' => 'بيع نقطة البيع',
            'pos.refund.create' => 'مرتجع نقطة البيع',
            'tables.sessions.manage' => 'جلسات الطاولات',
            'tables.layout.manage' => 'مخطط الطاولات',
            'kitchen.tickets.view' => 'عرض تذاكر المطبخ',
            'kitchen.tickets.update' => 'تحديث تذاكر المطبخ',
            'reservations.reservations.manage' => 'الحجوزات',
            'inventory.items.manage' => 'أصناف المخزون',
            'inventory.movements.adjust' => 'تسوية المخزون',
            'inventory.counts.manage' => 'الجرد',
            'purchasing.orders.manage' => 'أوامر الشراء',
            'finance.reports.view' => 'التقارير',
            'finance.reports.advanced' => 'التقارير المتقدمة',
            'finance.expenses.manage' => 'المصروفات',
            'customers.customers.view' => 'عرض الزبائن',
            'customers.customers.manage' => 'إدارة الزبائن',
        ];
    }

    /**
     * @return list<string>
     */
    public static function platformKeys(): array
    {
        return array_values(array_filter(
            array_keys(self::all()),
            fn (string $key): bool => str_starts_with($key, 'platform.'),
        ));
    }

    /**
     * @return list<string>
     */
    public static function tenantKeys(): array
    {
        return array_values(array_filter(
            array_keys(self::all()),
            fn (string $key): bool => ! str_starts_with($key, 'platform.'),
        ));
    }
}
