# 06 — مواصفة الواجهة البرمجية

**المشروع:** Restaurant SaaS Platform  
**التاريخ:** 17 نوفمبر 2026  
**الأساس:** `/api/v1`  
**العقد:** JSON، UTF-8، معرفات ULID في كل الأجسام، لا `tenant_id` في الطلبات.

## 1. متى لا نستخدم API

أغلب لوحات الموظفين **طلبات خادم عادية عبر Livewire** لأنها تحتاج CSRF وجلسة ونماذج متعددة الحقول (إعدادات، خطط، وصفات، إغلاق وردية). بناء JSON موازٍ لكل شاشة يضاعف الصلاحيات بلا مستهلك.

| العملية | الآلية | السبب |
|---|---|---|
| دخول الموظف والمشرف | Blade + جلسة | CSRF وملفات تعريف الارتباط `HttpOnly` |
| إدارة الخطط والمنشآت والإعدادات | Livewire | نماذج إدارية بلا عميل خارجي |
| بناء المنتجات والوصفات وأوامر الشراء | Livewire | نماذج طويلة و`max_input_vars` يُضبط لها |
| شبكة نقطة البيع والسلة | Alpine محلياً ثم طلب واحد | تقليل الرحلات |
| تثبيت بيع الكاشير | `POST /api/v1/pos/sales` | منع التكرار وإعادة المحاولة من المتصفح أوضح من دورة Livewire |
| شاشة المطبخ | Livewire `wire:poll` أولاً | لا عميل منفصل. المسار البرمجي موجود إذا احتجنا عميلاً خفيفاً |
| قائمة الضيف | Blade للصفحة الأولى | SEO والعربية وسرعة أول رسم |
| إرسال طلب الضيف | `POST /api/v1/public/{slug}/orders` | إعادة المحاولة ومفتاح التكرار |
| ويب هوك البوابة | `POST /api/v1/webhooks/paymob` | طرف خارجي |
| تصدير CSV | طلب خادم يبث الملف | أسهل مع الجلسة من رمز API |

إذا تعارض مسار Livewire مع مسار API لنفس الكتابة، **إجراء التطبيق واحد** ويستدعيه الاثنان. لا قاعدتان للحساب.

## 2. الشكل العام

النجاح:

```json
{
  "data": {},
  "meta": { "request_id": "01J..." }
}
```

القوائم تضيف `meta.page`, `meta.per_page`, `meta.total`.

الخطأ:

```json
{
  "error": {
    "code": "limit_reached",
    "message": "بلغت المنشأة حد الطلبات لهذا الشهر.",
    "fields": {}
  }
}
```

| HTTP | code | متى |
|---|---|---|
| 401 | `unauthenticated` | بلا جلسة أو رمز |
| 403 | `forbidden` | صلاحية أو ميزة مغلقة |
| 404 | `not_found` | غائب أو لمنشأة أخرى |
| 409 | `conflict` | وردية مفتوحة، حد تزامن، حالة غير قانونية |
| 422 | `validation_error` | `fields` بمفاتيح الحقول |
| 429 | `rate_limited` | |

المصادقة: `Authorization: Bearer {token}` لمسارات الرمز، أو جلسة نفس النطاق مع `X-XSRF-TOKEN` لمسارات المتصفح. الإطلاق يصدر رمزاً شخصياً للكاشير فقط إذا احتجنا إعادة المحاولة خارج Livewire. الضيف بلا حساب يستخدم المسارات العامة بلا Bearer، مع CSRF عندما يفتح الصفحة من نفس النطاق، وحد معدل حسب IP.

الترويسة الاختيارية `Idempotency-Key: {ulid}` إلزامية على إنشاء الطلب والبيع والمرتجع.

## 3. المصادقة

| الطريقة | المسار | الغرض | الصلاحية |
|---|---|---|---|
| POST | `/api/v1/auth/login` | إصدار رمز لجهاز كاشير أو تكامل | عام مع حد معدل |
| POST | `/api/v1/auth/logout` | إبطال الرمز الحالي | مصادق |
| GET | `/api/v1/auth/me` | المستخدم والمنشأة والفروع والصلاحيات الفعالة | مصادق |

دخول:

```json
{ "email": "cashier@example.com", "password": "********", "device_name": "pos-tablet-1" }
```

تحقق: بريد صالح، كلمة 10 أحرف على الأقل، `device_name` حتى 60 حرفاً.

استجابة 200:

```json
{
  "data": {
    "token": "plain-text-once",
    "user": { "id": "01J...", "name": "منى", "locale": "ar" },
    "tenant": { "id": "01J...", "slug": "nour" },
    "branches": [{ "id": "01J...", "name": "مدينة نصر" }],
    "permissions": ["pos.sale.create"]
  }
}
```

مستخدم المنصة لا يستعمل هذا المسار لإدارة الخطط في الإطلاق؛ دخوله من النموذج. إن استُعمل المسار لا يحتوي `tenant`.

## 4. المنشآت والفروع والمستخدمون

هذه المسارات للتكامل ولوحة مستقبلية. شاشات المرحلة 1 Livewire وتستدعي نفس الإجراءات.

| الطريقة | المسار | الغرض | الصلاحية |
|---|---|---|---|
| GET | `/api/v1/tenant` | ملف المنشأة الحالية | مستخدم منشأة |
| PATCH | `/api/v1/tenant` | تحديث الأسماء المعروضة والهاتف | `tenant.settings.manage` |
| GET | `/api/v1/branches` | فروع المستخدم | مستخدم منشأة |
| POST | `/api/v1/branches` | إنشاء فرع | `tenant.branches.manage` + حد الفروع |
| GET | `/api/v1/branches/{id}` | فرع | وصول للفرع |
| PATCH | `/api/v1/branches/{id}` | تعديل فرع | `tenant.branches.manage` |
| GET | `/api/v1/branches/{id}/hours` | ساعات العمل | وصول للفرع |
| PUT | `/api/v1/branches/{id}/hours` | استبدال السبعة أيام | `tenant.settings.manage` |
| GET | `/api/v1/users` | موظفون | `tenant.users.manage` |
| POST | `/api/v1/users` | دعوة أو إنشاء | `tenant.users.manage` + حد المستخدمين |
| PATCH | `/api/v1/users/{id}` | حالة أو فروع أو أدوار | `tenant.users.manage` |

إنشاء فرع — الحقول: `name_ar` مطلوب حتى 160، `name_en` مطلوب، `code` حتى 20 بحروف لاتينية وأرقام، `phone` اختياري، `city` مطلوب، `address` مطلوب.  
استجابة 201 تعيد الفرع بـ `id` العام.

ساعات العمل: مصفوفة من 7 عناصر `day_of_week` 0–6، `is_closed`، `opens_at` و`closes_at` بصيغة `HH:MM` إذا لم يكن مغلقاً، والإغلاق بعد الفتح في نفس اليوم. الورديات العابرة لمنتصف الليل مؤجلة (P1) وتُرفض في الإطلاق برسالة واضحة.

إنشاء مستخدم: `name`, `email`, `branch_ids[]` ULID، `role_key` من أدوار المنشأة. لا يُرسل `role` نصي حر خارج الأدوار.

## 5. القائمة والمنتجات

| الطريقة | المسار | الغرض | الصلاحية |
|---|---|---|---|
| GET | `/api/v1/categories` | شجرة التصنيفات | `menu.products.manage` أو عرض القائمة |
| POST | `/api/v1/categories` | إنشاء | `menu.categories.manage` |
| PATCH | `/api/v1/categories/{id}` | تعديل | `menu.categories.manage` |
| GET | `/api/v1/products` | قائمة مع تصفية `branch_id` و`q` | عرض |
| POST | `/api/v1/products` | إنشاء | `menu.products.manage` + `products.max` |
| GET | `/api/v1/products/{id}` | منتج مع مجموعات الخيارات | عرض |
| PATCH | `/api/v1/products/{id}` | تعديل بيانات وسعر أساس | `menu.products.manage` |
| PUT | `/api/v1/products/{id}/branches/{branchId}` | إتاحة وسعر فرع | `menu.products.manage` |
| POST | `/api/v1/products/{id}/option-groups` | مجموعة خيارات | `menu.products.manage` |
| POST | `/api/v1/option-groups/{id}/options` | خيار | `menu.products.manage` |
| POST | `/api/v1/products/{id}/images` | رفع صورة متعدد الأجزاء | `menu.products.manage` |
| GET | `/api/v1/public/{slug}/branches/{branchId}/menu` | قائمة الضيف | عام + `module.digital_menu` |

منتج — التحقق: `name_ar` مطلوب، `name_en` مطلوب، `category_id` ULID من نفس المنشأة، `base_price` رقم بين 0 و 999999.99، `description_*` حتى 500.  
مجموعة: `kind` ضمن القائمة المغلقة، `selection` `single|multiple`، `min_select` و`max_select` أعداد، وإذا `single` فـ `max_select=1`.  
خيار: `price_delta >= 0`.  
صورة: ملف واحد، 4 ميغابايت، JPEG/PNG/WebP.  
قائمة الضيف لا تعيد منتجات `is_available=false` ولا أسعار فروع أخرى ولا `sku`.

استجابة منتج مختصرة:

```json
{
  "data": {
    "id": "01J...",
    "name_ar": "لاتيه",
    "name_en": "Latte",
    "base_price": "45.00",
    "option_groups": [
      {
        "id": "01J...",
        "kind": "size",
        "selection": "single",
        "is_required": true,
        "options": [
          { "id": "01J...", "name_ar": "وسط", "price_delta": "0.00" }
        ]
      }
    ]
  }
}
```

المبالغ سلاسل عشرية حتى لا يقرّب JSON الأعداد ذات الفاصلة.

## 6. الطلبات العامة والإدارية

| الطريقة | المسار | الغرض | الصلاحية |
|---|---|---|---|
| POST | `/api/v1/public/{slug}/orders` | طلب ضيف | عام + طلب أونلاين + حد شهري |
| GET | `/api/v1/public/{slug}/orders/{id}` | متابعة حالة برقم وهاتف | عام |
| GET | `/api/v1/orders` | قائمة الفرع | `orders.orders.view` |
| GET | `/api/v1/orders/{id}` | التفاصيل التاريخية | `orders.orders.view` |
| POST | `/api/v1/orders/{id}/accept` | قبول | `orders.orders.accept` |
| POST | `/api/v1/orders/{id}/status` | نقل حالة مسموح | حسب الحالة |
| POST | `/api/v1/orders/{id}/cancel` | إلغاء | `orders.orders.cancel` |

جسم طلب الضيف:

```json
{
  "branch_id": "01J...",
  "type": "delivery",
  "table_id": null,
  "customer_name": "أحمد",
  "customer_phone": "+201012345678",
  "zone_id": "01J...",
  "address_line": "شارع النصر، مبنى 4",
  "notes": "",
  "coupon_code": "WELCOME",
  "items": [
    {
      "product_id": "01J...",
      "quantity": 2,
      "option_ids": ["01J..."],
      "notes": "بدون سكر"
    }
  ]
}
```

تحقق: `type` مغلق، الهاتف E.164، كمية بين 1 و 99، على الأكثر 30 بنداً، `option_ids` تنتمي للمنتج، المجموعات الإجبارية مكتملة، لا خيارات زائدة عن `max_select`، المنطقة تابعة لفرع الطلب عند التوصيل، الطاولة تابعة للفرع عند `dine_in` من QR. السعر غير مقبول من العميل.

متابعة الضيف تطلب أيضاً `phone` كمعامل. عدم التطابق 404 حتى لا تُستعرض الطلبات بالتخمين.

`POST status` الجسم: `{ "to": "ready" }`. الانتقالات المسموحة فقط من مصفوفة الخادم: placed→accepted→preparing→ready→(out_for_delivery)→completed، وplaced→cancelled، وaccepted→cancelled. القفز يرفض 409.

## 7. نقطة البيع

| الطريقة | المسار | الغرض | الصلاحية |
|---|---|---|---|
| POST | `/api/v1/pos/shifts` | فتح وردية | `pos.shift.open` |
| POST | `/api/v1/pos/shifts/{id}/close` | إغلاق | `pos.shift.close` |
| POST | `/api/v1/pos/shifts/{id}/movements` | إدخال أو سحب | `pos.shift.open` |
| POST | `/api/v1/pos/sales` | بيع مع تحصيل | `pos.sale.create` |
| POST | `/api/v1/pos/orders/{id}/refunds` | مرتجع | `pos.refund.create` |

فتح وردية: `register_id`, `opening_float >= 0`.  
إغلاق: `counted_cash >= 0`.  
بيع: نفس بنود الطلب بالإضافة إلى `payments[]` كل عنصر `method` و`amount`. المجموع يساوي `total` الذي يحسبه الخادم. اختلاف المجموع 422 مع الإجمالي الصحيح في `error.fields.total` حتى تحدّث الواجهة الأرقام.  
مرتجع: `amount` أصغر من أو يساوي القابل للرد، `reason` مطلوب حتى 255، `idempotency` إلزامي.

استجابة البيع 201:

```json
{
  "data": {
    "id": "01J...",
    "number": 184,
    "total": "128.00",
    "status": "completed",
    "payments": [{ "method": "cash", "amount": "150.00" }]
  }
}
```

البيع من الكاشير يُنشأ `completed` إذا دُفع بالكامل، وتُنشأ تذاكر المطبخ في نفس الوقت. طلب الضيف يبقى `placed`.

## 8. الطاولات

| الطريقة | المسار | الغرض | الصلاحية |
|---|---|---|---|
| GET | `/api/v1/branches/{id}/areas` | المناطق والطاولات | `tables.sessions.manage` أو عرض |
| POST | `/api/v1/branches/{id}/areas` | إنشاء منطقة | `tables.layout.manage` |
| POST | `/api/v1/areas/{id}/tables` | طاولة: اسم، مقاعد 1–30 | `tables.layout.manage` |
| POST | `/api/v1/tables/{id}/sessions` | فتح جلسة | `tables.sessions.manage` |
| POST | `/api/v1/table-sessions/{id}/close` | إغلاق | `tables.sessions.manage` |
| POST | `/api/v1/table-sessions/{id}/transfer` | `{ "to_table_id": "..." }` | `tables.sessions.manage` |
| POST | `/api/v1/orders/{id}/splits` | تقسيم | `tables.sessions.manage` |

التقسيم: `mode` و`parts[]` بمبالغ مجموعها يساوي إجمالي الطلب بهامش صفر. التقسيم بالبنود يرسل `order_item_id` لكل جزء. الطاولة الهدف في النقل حالتها متاحة ونفس الفرع.

## 9. الحجوزات

| الطريقة | المسار | الغرض | الصلاحية |
|---|---|---|---|
| GET | `/api/v1/public/{slug}/branches/{id}/reservation-slots` | أوقات متاحة ليوم | عام + مزية الحجوزات |
| POST | `/api/v1/public/{slug}/reservations` | طلب حجز | عام |
| GET | `/api/v1/reservations` | قائمة يوم `date` | `reservations.reservations.manage` |
| POST | `/api/v1/reservations/{id}/confirm` | تأكيد مع `table_ids` اختيارية | نفس الصلاحية |
| POST | `/api/v1/reservations/{id}/cancel` | إلغاء | نفس الصلاحية |
| POST | `/api/v1/reservations/{id}/no-show` | لم يحضر | نفس الصلاحية |
| POST | `/api/v1/reservations/{id}/seat` | تحويل إلى جلسة إن وُجدت طاولة | نفس الصلاحية + طاولات |

التحقق: `guest_count` 1–30، `reserved_at` في المستقبل بساعة على الأقل للضيف، داخل ساعات الفرع، الهاتف E.164، الاسم حتى 120. التعارض مع حجز مؤكد على نفس الطاولة 409.

## 10. المطبخ

| الطريقة | المسار | الغرض | الصلاحية |
|---|---|---|---|
| GET | `/api/v1/kitchen/tickets` | المفتوحة منذ `updated_after` | `kitchen.tickets.view` |
| POST | `/api/v1/kitchen/tickets/{id}/start` | بدء | `kitchen.tickets.update` |
| POST | `/api/v1/kitchen/tickets/{id}/ready` | جاهز | `kitchen.tickets.update` |
| GET | `/api/v1/prep-stations` | محطات الفرع الحالي | عرض |
| POST | `/api/v1/prep-stations` | إنشاء | `tenant.settings.manage` |

الاستعلام: `branch_id` اختياري ويُتجاهل إن لم يكن للمستخدم حقه، ويُستخدم فرع الجلسة. `station_id` اختياري. `updated_after` تاريخ ISO-8601. الاستجابة لا تحتوي أسعاراً ولا هواتف.

## 11. المخزون والموردون

المرحلة 5. الصلاحيات من مجموعة `inventory.*` و`purchasing.orders.manage`.

| الطريقة | المسار | الغرض |
|---|---|---|
| GET/POST | `/api/v1/stock-items` | عرض وإنشاء صنف |
| GET | `/api/v1/branches/{id}/stocks` | الأرصدة |
| POST | `/api/v1/stock-movements` | تسوية أو هدر. الجسم: `stock_item_id`, `kind` ضمن `adjustment|waste`, `quantity` بإشارة للتسوية وموجبة للهدر ثم تُخزن سالبة، `note` مطلوب |
| POST | `/api/v1/recipes` | `{ "product_id", "lines": [{ "stock_item_id", "quantity", "unit_id" }] }` |
| GET/POST | `/api/v1/suppliers` | مورد: اسم مطلوب، هاتف مطلوب |
| POST | `/api/v1/purchase-orders` | أمر مسودة |
| POST | `/api/v1/purchase-orders/{id}/receive` | استلام جزئي `lines[{line_id, quantity}]` لا تتجاوز المتبقي |

التحويل بين الفروع: `POST /api/v1/stock-transfers` ثم `POST /api/v1/stock-transfers/{id}/receive`. يحتاج `module.multi_branch` و`module.inventory`.

الجرد: `POST /api/v1/stock-counts` ثم بنود ثم `POST /api/v1/stock-counts/{id}/post`.

## 12. العملاء والمدفوعات والتقارير

| الطريقة | المسار | الغرض | الصلاحية |
|---|---|---|---|
| GET | `/api/v1/customers` | بحث بالهاتف أو الاسم | `customers.customers.view` |
| GET | `/api/v1/customers/{id}` | ملف وعناوين وآخر الطلبات | `customers.customers.view` |
| GET | `/api/v1/orders/{id}/payments` | دفعات الطلب | `orders.orders.view` |
| GET | `/api/v1/reports/daily` | ملخص فرع `from` و`to` بحد 31 يوماً للتجميع الحي و366 من جدول الملخص | `finance.reports.view` |
| GET | `/api/v1/reports/tax` | ضريبة الفترة من المبالغ المخزنة | `finance.reports.advanced` |
| GET | `/api/v1/reports/branches` | مقارنة | `module.multi_branch` + تقارير متقدمة |
| POST | `/api/v1/expenses` | مصروف | `finance.expenses.manage` |

مدى التاريخ إلزامي في التقارير. غيابه 422.

## 13. الاشتراك من جهة المالك

| الطريقة | المسار | الغرض | الصلاحية |
|---|---|---|---|
| GET | `/api/v1/subscription` | الحالة والاستخدام والفاتورة المفتوحة | مالك |
| GET | `/api/v1/subscription/plans` | الخطط العامة فقط | مالك |
| POST | `/api/v1/subscription/checkout` | بدء دفع الفاتورة المفتوحة | مالك |
| POST | `/api/v1/webhooks/paymob` | تثبيت الدفع | توقيع البوابة |

`checkout` يعيد `{ "redirect_url": "https://..." }` ولا يعيد مفاتيح سرية.  
ويب هوك: جسم البوابة كما تسلمه، والتحقق من التوقيع قبل منطق العمل. الاستجابة 200 فارغة عند التكرار السليم.

## 14. مشرف المنصة

تحت `/api/v1/platform`. كلها تتطلب `is_platform_user` وصلاحية المنصة المطابقة. لا نطاق منشأة صامت.

| الطريقة | المسار | الغرض | الصلاحية |
|---|---|---|---|
| GET | `/api/v1/platform/tenants` | قائمة | `platform.tenants.view` |
| POST | `/api/v1/platform/tenants` | إنشاء | `platform.tenants.manage` |
| PATCH | `/api/v1/platform/tenants/{id}` | حالة أو slug | `platform.tenants.manage` |
| GET | `/api/v1/platform/plans` | الخطط | `platform.plans.manage` |
| POST | `/api/v1/platform/plans` | إنشاء خطة | `platform.plans.manage` |
| PUT | `/api/v1/platform/plans/{id}/features` | استبدال القيم | `platform.plans.manage` |
| PUT | `/api/v1/platform/plans/{id}/prices` | أسعار | `platform.plans.manage` |
| POST | `/api/v1/platform/tenants/{id}/subscription` | إسناد خطة | `platform.billing.record_payment` أو إدارة خطط حسب الإجراء |
| POST | `/api/v1/platform/invoices/{id}/payments` | دفعة يدوية | `platform.billing.record_payment` |
| GET | `/api/v1/platform/audit-logs` | سجل | `platform.audit.view` |
| GET | `/api/v1/platform/tickets` | دعم | `platform.support.manage` |
| POST | `/api/v1/platform/tickets/{id}/messages` | رد | `platform.support.manage` |

إنشاء منشأة: `legal_name`, `display_name_ar`, `display_name_en`, `slug` يطابق `^[a-z0-9-]{3,40}$`, `email`, `phone`, `timezone`, `currency_code`, `owner_name`, `owner_email`. ينشئ منشأة ومالكاً مدعواً وفرعاً أول إن طُلب `first_branch`.

دفعة يدوية: `amount` يساوي إجمالي الفاتورة في الإطلاق (دفع جزئي لاشتراك المنصة غير معتمد)، `reference` حتى 120، `note` حتى 255.

`PUT features` الجسم: `{ "values": { "module.pos": "1", "branches.max": "5", "orders.monthly": null } }`. المفاتيح خارج الكتالوج 422. المنطقي يجب أن يكون `0` أو `1`.

## 15. ترقيعات لاحقة للواجهة

- ترقيم الصفحات `page` و`per_page` بحد أقصى 100.
- التصفية الحرة غير مفتوحة. كل قائمة لها معاملات موثقة فقط.
- الإصدار `/api/v1` يبقى عند كسر حقل. الحقول الجديدة اختيارية في الاستجابة.
- لا GraphQL في هذا المشروع.
