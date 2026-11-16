<?php

namespace App\Support\Features;

final class FeatureCatalog
{
    /**
     * @return array<string, array{type: string, name_ar: string, name_en: string}>
     */
    public static function all(): array
    {
        return [
            'branches.max' => ['type' => 'integer', 'name_ar' => 'حد الفروع', 'name_en' => 'Branch limit'],
            'users.max' => ['type' => 'integer', 'name_ar' => 'حد المستخدمين', 'name_en' => 'User limit'],
            'orders.monthly' => ['type' => 'integer', 'name_ar' => 'طلبات الشهر', 'name_en' => 'Monthly orders'],
            'module.digital_menu' => ['type' => 'boolean', 'name_ar' => 'القائمة الرقمية', 'name_en' => 'Digital menu'],
            'module.online_ordering' => ['type' => 'boolean', 'name_ar' => 'الطلب أونلاين', 'name_en' => 'Online ordering'],
            'module.pos' => ['type' => 'boolean', 'name_ar' => 'نقطة البيع', 'name_en' => 'Point of sale'],
            'module.inventory' => ['type' => 'boolean', 'name_ar' => 'المخزون', 'name_en' => 'Inventory'],
            'module.reservations' => ['type' => 'boolean', 'name_ar' => 'الحجوزات', 'name_en' => 'Reservations'],
            'module.kitchen' => ['type' => 'boolean', 'name_ar' => 'المطبخ', 'name_en' => 'Kitchen'],
            'module.delivery' => ['type' => 'boolean', 'name_ar' => 'التوصيل', 'name_en' => 'Delivery'],
            'module.advanced_reports' => ['type' => 'boolean', 'name_ar' => 'التقارير المتقدمة', 'name_en' => 'Advanced reports'],
            'module.multi_branch' => ['type' => 'boolean', 'name_ar' => 'تعدد الفروع', 'name_en' => 'Multiple branches'],
            'module.loyalty' => ['type' => 'boolean', 'name_ar' => 'الولاء', 'name_en' => 'Loyalty'],
            'products.max' => ['type' => 'integer', 'name_ar' => 'حد المنتجات', 'name_en' => 'Product limit'],
            'images.per_product' => ['type' => 'integer', 'name_ar' => 'صور المنتج', 'name_en' => 'Images per product'],
        ];
    }
}
