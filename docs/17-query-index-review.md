# 17 — مراجعة أثقل الاستعلامات والفهارس (P7-S17-04)

تاريخ المراجعة: 2026-11-17.

## الاستعلامات العشرة

| # | المسار | الاستعلام | الفهرس المعتمد | ملاحظات |
|---|---|---|---|---|
| 1 | تقرير يوم الفرع | `orders` حسب `tenant_id, branch_id, placed_at, status` | `(tenant_id, branch_id, placed_at)` و`(tenant_id, branch_id, status, placed_at)` | لا `SELECT *` كامل بلا مدى تاريخ |
| 2 | مدفوعات التقرير | `payments` بـ `order_id` | `(order_id, status)` | |
| 3 | مرتجعات التقرير | `refunds` بـ `order_id` | موجود عبر FK/فهارس الطلب | |
| 4 | لوحة الطلبات | `orders` فرع+حالة | نفس فهارس الطلبات | |
| 5 | مطبخ | `kitchen_tickets` فرع+محطة+حالة | `(branch_id, station_id, status, updated_at)` | |
| 6 | حجوزات | `reservations` فرع+زمن | `(branch_id, reserved_at, status)` | |
| 7 | دفتر المخزون | `stock_movements` | `(branch_id, stock_item_id, occurred_at)` | |
| 8 | ملخص يومي | `daily_branch_summaries` UNIQUE فرع+تاريخ | PK منطقي | مقارنة الفروع من الملخص لا من مسح الطلبات |
| 9 | قائمة عامة | `products` تصنيف+نشط | `(tenant_id, category_id, is_active)` | قياس 200 منتج في RELEASE_NOTES |
| 10 | جلسات POS | `cashier_shifts` | `(register_id, status)` | |

## إضافة Sprint 17

- فهارس سابقة: `(tenant_id, branch_id, placed_at)` و`(tenant_id, branch_id, status, placed_at)`.
- تهجير `000017` يضيف `(tenant_id, branch_id, placed_at, status)` لدعم نافذة التاريخ ثم الحالة.
- `BuildDailyBranchReport` يختار أعمدة محددة ويقيد بـ `branch_id` + `placed_at` (لا `SELECT *`).

**قبول:** تقرير اليوم يقيّد بـ `branch_id` + نافذة `placed_at`؛ لا يُسمح بتجميع بلا تاريخ في الواجهة.
