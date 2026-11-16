# 04 — تصميم قاعدة البيانات

**المشروع:** Restaurant SaaS Platform  
**التاريخ:** 17 نوفمبر 2026  
**المحرك:** InnoDB، مجموعة المحارف `utf8mb4`، الترتيب `utf8mb4_unicode_ci`.  
**القرارات:** D-02 وD-10 وD-11 وD-12 وD-21.  
**المخطط البصري:** [05-database-erd.md](05-database-erd.md).

## 1. أعراف عامة

تُطبق على كل الجداول التالية ولا تُعاد في كل صف:

| العرف | القاعدة |
|---|---|
| المفتاح الأساسي | `id BIGINT UNSIGNED AUTO_INCREMENT` |
| المعرف العام | `public_id CHAR(26) NOT NULL` فريد عند الجداول التي تخرج إلى الواجهة أو الـ API. ULID. |
| الزمن | `created_at` و`updated_at` من نوع `DATETIME` بحفظ UTC. جداول الدفتر قد تكتفي بـ `created_at`. |
| المال | `DECIMAL(12,2)` بالجنيه أو عملة المنشأة. لا `FLOAT`. |
| الكمية | `DECIMAL(14,3)` |
| النسبة | `DECIMAL(6,3)` مثل `14.000` |
| الحالة | `VARCHAR(32)` بقيم يعددها التطبيق، لا `ENUM`، حتى لا يحتاج كل تغيير حالة إلى `ALTER` ثقيل على الاستضافة. |
| JSON | للنسخ العرضية فقط: عنوان، حمولة رسالة، قبل/بعد في التدقيق. لا يُستعلم داخله في المسارات اليومية. |
| الحذف | ممنوع على الطلبات والدفعات والحركات والتدقيق. غيره يستخدم `is_active` أو حالة. |
| عزل المنشأة | إذا قيل «عزل: مطلوب» فالجدول يحتوي `tenant_id BIGINT UNSIGNED NOT NULL` مع فهرس، ومفتاح فريد `(tenant_id, id)`، ومفتاح أجنبي إلى `tenants(id)`. |
| العزل المركب | أي إشارة إلى جدول معزول آخر تكون `(tenant_id, other_id)` نحو `(tenant_id, id)` في الجدول الهدف. |
| جداول المنصة | `tenant_id` غير موجود، وتُذكر صراحة «منصة». |

فهارس الأسماء العربية: فهرس عادي على `(tenant_id, name_ar)` يكفي للفرز. البحث الجزئي `LIKE 'نص%'` يستفيد منه. البحث الوسطي `%نص%` لا يُستخدم إلا داخل فرع وصفحات صغيرة.

MariaDB 10.4 المحلية تدعم هذه الأنواع. لا نعتمد على `CHECK` كحاجز وحيد (قسم 6 في وثيقة القرارات).

## 2. نواة المنصة

### `system_settings`
غرض: إعداد المنصة كلها. عزل: منصة.

| العمود | النوع | قيود |
|---|---|---|
| id | BIGINT PK | |
| key | VARCHAR(100) | UNIQUE |
| value | JSON | |
| updated_at | DATETIME | |

مفاتيح معروفة: `billing.grace_days`, `platform.name`, `maintenance.enabled`, `kitchen.poll_seconds`.

### `currencies`
غرض: قائمة عملات للعرض. عزل: منصة.

| العمود | النوع | قيود |
|---|---|---|
| code | CHAR(3) PK | مثل `EGP` |
| name_ar | VARCHAR(80) | |
| name_en | VARCHAR(80) | |
| symbol | VARCHAR(8) | |
| decimal_places | TINYINT | 2 للجنيه |
| is_active | TINYINT(1) | |

### `jobs` و`job_batches` و`failed_jobs` و`sessions` و`password_reset_tokens` و`cache` و`cache_locks` و`personal_access_tokens`
جداول إطار Laravel القياسية. عزل: منصة. `sessions` تحتوي `user_id` مفهرساً و`last_activity`. لا تُضاف أعمدة منشأة إلى `jobs`؛ المنشأة داخل الحمولة.

## 3. المستخدمون والصلاحيات

### `users`
غرض: كل الحسابات البشرية. عزل: `tenant_id` فارغ لمستخدمي المنصة فقط.

| العمود | النوع | قيود |
|---|---|---|
| id | BIGINT PK | |
| public_id | CHAR(26) | UNIQUE |
| tenant_id | BIGINT NULL | FK `tenants.id` |
| name | VARCHAR(120) | |
| email | VARCHAR(190) | |
| phone | VARCHAR(20) NULL | |
| password | VARCHAR(255) | تجزئة |
| locale | CHAR(2) | `ar` أو `en` |
| status | VARCHAR(32) | `invited`, `active`, `disabled` |
| is_platform_user | TINYINT(1) | 1 فقط عندما `tenant_id` فارغ |
| last_login_at | DATETIME NULL | |
| timestamps | | |

فهارس: UNIQUE `(tenant_id, email)`، وفهرس `(tenant_id, status)`. البريد لمستخدمي المنصة فريد عبر فهرس فريد وظيفي غير معتمد على 10.4؛ البديل المتوافق: UNIQUE `(email, is_platform_user)` غير كافٍ. **الآلية المعتمدة:** البريد فريد عالمياً `UNIQUE(email)` في الإطلاق حتى لا يتعقد الدخول. المعنى: شخص واحد لا يملك حسابين بنفس البريد. إن احتاج مالك أن يكون مشرف منصة أيضاً فهما حسابان ببريدين. هذا قيد إطلاق مقبول وموثق.

### `roles`
عزل: `tenant_id` NULL للقالب النظامي.

| العمود | النوع | قيود |
|---|---|---|
| id | BIGINT PK | |
| tenant_id | BIGINT NULL | FK |
| key | VARCHAR(64) | |
| name_ar | VARCHAR(120) | |
| name_en | VARCHAR(120) | |
| is_system | TINYINT(1) | |
| timestamps | | |

UNIQUE `(tenant_id, key)`.

### `permissions`
عزل: منصة.

| العمود | النوع | قيود |
|---|---|---|
| id | BIGINT PK | |
| key | VARCHAR(100) | UNIQUE |
| group_name | VARCHAR(50) | |
| description | VARCHAR(255) | |

### `role_permission`
| العمود | النوع | قيود |
|---|---|---|
| role_id | BIGINT | FK PK مركبة |
| permission_id | BIGINT | FK PK مركبة |

### `user_roles`
| العمود | النوع | قيود |
|---|---|---|
| id | BIGINT PK | |
| user_id | BIGINT | FK users |
| role_id | BIGINT | FK roles |
| branch_id | BIGINT NULL | FK branches |

UNIQUE `(user_id, role_id, branch_id)`.

### `branch_user`
| العمود | النوع | قيود |
|---|---|---|
| user_id | BIGINT | PK مركبة |
| branch_id | BIGINT | PK مركبة |
| is_primary | TINYINT(1) | |

المفتاح الأجنبي من `branch_user` إلى `branches` يكفي. عند الإسناد يتحقق التطبيق أن الفرع من نفس `users.tenant_id`.

## 4. المنشآت والفروع

### `tenants`
عزل: هذا الجدول هو حد العزل.

| العمود | النوع | قيود |
|---|---|---|
| id | BIGINT PK | |
| public_id | CHAR(26) | UNIQUE |
| slug | VARCHAR(40) | UNIQUE، لاتيني |
| legal_name | VARCHAR(190) | |
| display_name_ar | VARCHAR(190) | |
| display_name_en | VARCHAR(190) | |
| status | VARCHAR(32) | `draft`, `active`, `suspended` |
| timezone | VARCHAR(64) | افتراضي `Africa/Cairo` |
| currency_code | CHAR(3) | FK currencies |
| default_locale | CHAR(2) | `ar` |
| email | VARCHAR(190) | |
| phone | VARCHAR(20) | |
| logo_path | VARCHAR(255) NULL | |
| credit_balance | DECIMAL(12,2) | افتراضي 0 |
| timestamps | | |

فهرس `(status)`.

### `tenant_settings`
| العمود | النوع | قيود |
|---|---|---|
| tenant_id | BIGINT | PK مركبة، FK |
| key | VARCHAR(100) | PK مركبة |
| value | JSON | |

### `branches`
عزل: مطلوب.

| العمود | النوع | قيود |
|---|---|---|
| id | BIGINT PK | |
| tenant_id | BIGINT | FK + UNIQUE `(tenant_id, id)` |
| public_id | CHAR(26) | UNIQUE |
| name_ar | VARCHAR(160) | |
| name_en | VARCHAR(160) | |
| code | VARCHAR(20) | |
| phone | VARCHAR(20) NULL | |
| city | VARCHAR(80) | |
| address | VARCHAR(255) | |
| is_active | TINYINT(1) | |
| timestamps | | |

UNIQUE `(tenant_id, code)`.

### `branch_settings`
مثل `tenant_settings` على `branch_id` مع `tenant_id` ومفتاح أجنبي مركب إلى الفروع.

### `operating_hours`
عزل: مطلوب.

| العمود | النوع | قيود |
|---|---|---|
| id | BIGINT PK | |
| tenant_id | BIGINT | |
| branch_id | BIGINT | FK مركب |
| day_of_week | TINYINT | 0 الأحد … 6 السبت، أو 0 الاثنين حسب توثيق ثابت: **0 = الاثنين … 6 = الأحد** بنمط ISO |
| opens_at | TIME NULL | |
| closes_at | TIME NULL | |
| is_closed | TINYINT(1) | |

UNIQUE `(branch_id, day_of_week)`.

### `tax_rates`
عزل: مطلوب.

| العمود | النوع | قيود |
|---|---|---|
| id | BIGINT PK | |
| tenant_id | BIGINT | |
| public_id | CHAR(26) | UNIQUE |
| name | VARCHAR(80) | |
| rate_percent | DECIMAL(6,3) | |
| is_inclusive | TINYINT(1) | |
| is_default | TINYINT(1) | |
| is_active | TINYINT(1) | |

## 5. الاشتراك والفوترة

### `features`
منصة. `key` UNIQUE، `value_type` VARCHAR(16)، `name_ar`, `name_en`.

### `plans`
منصة. `code` UNIQUE، أسماء، `description` نص، `is_active`, `is_public`, `trial_days` SMALLINT، `sort_order`.

### `plan_prices`
| العمود | النوع | قيود |
|---|---|---|
| id | BIGINT PK | |
| plan_id | BIGINT | FK |
| interval | VARCHAR(16) | `monthly` أو `yearly` |
| currency_code | CHAR(3) | |
| amount | DECIMAL(12,2) | |
| is_active | TINYINT(1) | |

UNIQUE `(plan_id, interval, currency_code)`.

### `plan_features`
PK مركبة `(plan_id, feature_id)`، `value` VARCHAR(64) NULL. NULL للحد يعني بلا حد. `0` و`1` للمنطقي.

### `addons` و`addon_prices`
مثل الخطة بصورة مصغرة: `key` UNIQUE، وسعر لكل فترة.

### `subscriptions`
عزل: مطلوب. UNIQUE `(tenant_id)` في الإطلاق: اشتراك واحد. التاريخ يُحفظ في الفواتير لا في صفوف اشتراك متعددة حتى يبقى النموذج بسيطاً. تغيير الخطة يحدّث الصف ويُكتب في `audit_logs`.

| العمود | النوع | قيود |
|---|---|---|
| id | BIGINT PK | |
| tenant_id | BIGINT | UNIQUE + FK |
| plan_id | BIGINT | FK |
| status | VARCHAR(32) | الحالات في وثيقة الاشتراك |
| interval | VARCHAR(16) | |
| trial_ends_at | DATETIME NULL | |
| current_period_start | DATETIME | |
| current_period_end | DATETIME | |
| grace_ends_at | DATETIME NULL | |
| cancelled_at | DATETIME NULL | |
| timestamps | | |

فهرس `(status, current_period_end)`.

### `subscription_addons`
`(subscription_id, addon_id)` PK، `quantity` INT.

### `subscription_invoices`
عزل: مطلوب.

| العمود | النوع | قيود |
|---|---|---|
| id | BIGINT PK | |
| public_id | CHAR(26) | UNIQUE |
| tenant_id | BIGINT | |
| subscription_id | BIGINT | FK |
| period_start | DATETIME | |
| period_end | DATETIME | |
| subtotal | DECIMAL(12,2) | |
| tax_total | DECIMAL(12,2) | |
| total | DECIMAL(12,2) | |
| currency_code | CHAR(3) | |
| status | VARCHAR(32) | `open`, `paid`, `void` |
| issued_at | DATETIME | |
| paid_at | DATETIME NULL | |
| timestamps | | |

UNIQUE `(subscription_id, period_start)`.

### `subscription_payments`
عزل: مطلوب. `invoice_id`, `amount`, `method` (`manual` أو `paymob`)، `provider_ref` NULL، `note`. UNIQUE `(method, provider_ref)` حيث المرجع غير فارغ. على MariaDB 10.4 الفريد مع NULL يسمح بعدة NULL، وهذا مطلوب للمدفوعات اليدوية بلا مرجع بوابة؛ التفرد للمرجعية اليدوية يفرضه التطبيق إذا أدخل المستخدم مرجعاً بنكياً في عمود `provider_ref`.

### `usage_counters`
عزل: مطلوب.

| العمود | النوع | قيود |
|---|---|---|
| id | BIGINT PK | |
| tenant_id | BIGINT | |
| feature_key | VARCHAR(100) | |
| period_start | DATE | |
| period_end | DATE | |
| used | INT UNSIGNED | |

UNIQUE `(tenant_id, feature_key, period_start)`.

## 6. القائمة والمنتجات

### `menu_categories`
عزل: مطلوب. `parent_id` NULL، `name_ar`, `name_en`, `sort_order`, `is_active`, `image_path` NULL.  
FK ذاتي `(tenant_id, parent_id)`. فهرس `(tenant_id, parent_id, sort_order)`.

### `products`
عزل: مطلوب.

| العمود | النوع | قيود |
|---|---|---|
| id | BIGINT PK | |
| public_id | CHAR(26) | UNIQUE |
| tenant_id | BIGINT | |
| category_id | BIGINT | FK مركب |
| tax_rate_id | BIGINT NULL | FK مركب إلى tax_rates |
| name_ar | VARCHAR(190) | |
| name_en | VARCHAR(190) | |
| description_ar | VARCHAR(500) NULL | |
| description_en | VARCHAR(500) NULL | |
| base_price | DECIMAL(12,2) | |
| sku | VARCHAR(64) NULL | |
| is_active | TINYINT(1) | |
| prep_station_id | BIGINT NULL | يُملأ في المرحلة 4، FK مركب |
| timestamps | | |

UNIQUE `(tenant_id, sku)` والسماح بعدة NULL. فهرس `(tenant_id, category_id, is_active)`.

### `product_branch_settings`
عزل: مطلوب. PK `(product_id, branch_id)` مع `tenant_id`. `is_available` TINYINT، `price_override DECIMAL(12,2) NULL`.  
NULL في السعر يعني وراثة `base_price`.

### `product_images`
عزل: مطلوب. `product_id`, `path`, `sort_order`, `is_primary`.

### `product_option_groups`
عزل: مطلوب. `product_id`, `name_ar`, `name_en`, `kind` (`size|variant|modifier|addon`)، `selection` (`single|multiple`)، `is_required`, `min_select`, `max_select`, `sort_order`.

### `product_options`
عزل: مطلوب. `group_id`, أسماء، `price_delta DECIMAL(12,2)`، `is_default`, `is_active`, `sort_order`.

### `product_option_branch_settings`
PK `(option_id, branch_id)`, `tenant_id`, `is_available`, `price_delta_override` NULL.

### `menu_themes`
عزل: مطلوب. `name`, `tokens` JSON (مفاتيح لون من مجموعة مغلقة)، `is_active`. تطبيق الإطلاق يبقي ثيماً واحداً `is_active=1`.

### `qr_codes`
عزل: مطلوب. `branch_id`, `target_type` (`menu|table|order`)، `target_public_id` CHAR(26)، `image_path`.  
UNIQUE `(tenant_id, target_type, target_public_id)`.

## 7. الطلبات

### `branch_order_sequences`
عزل: مطلوب. PK `branch_id`، `tenant_id`, `last_number INT UNSIGNED`.  
يُقفل الصف أثناء إصدار الرقم.

### `orders`
عزل: مطلوب.

| العمود | النوع | قيود |
|---|---|---|
| id | BIGINT PK | |
| public_id | CHAR(26) | UNIQUE |
| tenant_id | BIGINT | |
| branch_id | BIGINT | FK مركب |
| number | INT UNSIGNED | الرقم الظاهر |
| type | VARCHAR(32) | `dine_in`, `pickup`, `delivery` |
| source | VARCHAR(32) | `pos`, `online`, `qr_table` |
| status | VARCHAR(32) | قسم الطلبات في المواصفات |
| customer_id | BIGINT NULL | FK مركب |
| table_session_id | BIGINT NULL | |
| cashier_shift_id | BIGINT NULL | |
| customer_name | VARCHAR(120) NULL | نسخة |
| customer_phone | VARCHAR(20) NULL | نسخة |
| delivery_zone_id | BIGINT NULL | |
| delivery_address | JSON NULL | نسخة نصية للحي والعنوان |
| notes | VARCHAR(500) NULL | |
| subtotal | DECIMAL(12,2) | |
| discount_total | DECIMAL(12,2) | |
| tax_total | DECIMAL(12,2) | |
| delivery_fee | DECIMAL(12,2) | |
| service_charge | DECIMAL(12,2) | |
| total | DECIMAL(12,2) | |
| currency_code | CHAR(3) | |
| prices_include_tax | TINYINT(1) | نسخة من الإعداد لحظة البيع |
| idempotency_key | CHAR(26) | |
| placed_at | DATETIME | |
| completed_at | DATETIME NULL | |
| cancelled_at | DATETIME NULL | |
| timestamps | | |

UNIQUE `(tenant_id, idempotency_key)`.  
UNIQUE `(branch_id, number)`.  
فهارس: `(tenant_id, branch_id, status, placed_at)`، `(tenant_id, branch_id, placed_at)`، `(customer_id)`.

### `order_items`
عزل: مطلوب. `order_id` FK مركب عبر `(tenant_id, order_id)` لذا `orders` يحتاج UNIQUE `(tenant_id, id)` كغيره.

| العمود | النوع | قيود |
|---|---|---|
| id | BIGINT PK | |
| tenant_id | BIGINT | |
| order_id | BIGINT | |
| product_id | BIGINT NULL | يبقى NULL إذا أُرشِف المنتج؛ النسخة النصية هي المرجع التاريخي |
| name_ar | VARCHAR(190) | نسخة |
| name_en | VARCHAR(190) | نسخة |
| quantity | DECIMAL(10,3) | عادة 1.000 لكن الوزن مستقبلاً لا يكسر العمود |
| unit_price | DECIMAL(12,2) | بعد الخيارات أو قبلها؟ **سعر المنتج للوحدة قبل فروق الخيارات** |
| options_total | DECIMAL(12,2) | مجموع الفروق للوحدة |
| discount_amount | DECIMAL(12,2) | |
| tax_amount | DECIMAL(12,2) | مبلغ الضريبة لهذا السطر |
| tax_rate_percent | DECIMAL(6,3) | نسخة النسبة |
| line_total | DECIMAL(12,2) | الصافي المدفوع للسطر شاملاً سلوك الشمول المطبق والمخزن |
| notes | VARCHAR(255) NULL | |
| status | VARCHAR(32) | `pending`, `preparing`, `ready`, `cancelled` |
| timestamps | | |

فهرس `(order_id)`.

### `order_item_options`
`order_item_id`, `tenant_id`, `name_ar`, `name_en`, `price_delta DECIMAL(12,2)`, `quantity DECIMAL(10,3)`.  
بلا FK إلى `product_options` حتى تبقى النسخة إذا حُذف الخيار. يمكن حفظ `source_option_id` NULLABLE بلا مفتاح أجنبي إجباري؛ **المعتمد:** بلا ربط حي.

### `order_discounts`
`order_id`, `tenant_id`, `kind` (`coupon|manual`), `coupon_id` NULL، `label`, `amount DECIMAL(12,2)`.

### `order_status_histories`
`order_id`, `tenant_id`, `from_status`, `to_status`, `user_id` NULL، `created_at`. بلا `updated_at`.

### `coupons`
عزل: مطلوب. `code`, `kind` (`percent|fixed`)، `value DECIMAL(12,2)`, `min_order DECIMAL(12,2) NULL`, `max_uses` NULL، `max_uses_per_phone` NULL، `starts_at`, `ends_at`, `is_active`.  
UNIQUE `(tenant_id, code)`.

### `coupon_branches`
PK `(coupon_id, branch_id)`. غياب الصفوف يعني كل الفروع. وجود صفوف يعني التقييد. التطبيق يوثق هذه القاعدة في الاختبار.

### `coupon_redemptions`
`coupon_id`, `order_id`, `tenant_id`, `phone`, `created_at`. فهرس `(coupon_id)` و`(tenant_id, phone)`.

### `delivery_zones`
عزل: مطلوب. `branch_id`, `name_ar`, `name_en`, `fee DECIMAL(12,2)`, `min_order DECIMAL(12,2) NULL`, `is_active`, `sort_order`.

### `idempotency_keys`
يمكن الاكتفاء بعمود `orders.idempotency_key`. جدول منفصل غير منشأ في الإطلاق لتجنب ازدواجية. المفتاح على الطلب كافٍ لأن العمليات المالية الأخرى (المرتجع) تستخدم `refunds.idempotency_key`.

## 8. المدفوعات والمرتجعات

### `payments`
عزل: مطلوب.

| العمود | النوع | قيود |
|---|---|---|
| id | BIGINT PK | |
| public_id | CHAR(26) | UNIQUE |
| tenant_id | BIGINT | |
| order_id | BIGINT | FK مركب |
| method | VARCHAR(32) | `cash`, `card`, `wallet`, `instapay` |
| status | VARCHAR(32) | `pending`, `paid`, `failed`, `refunded` |
| amount | DECIMAL(12,2) | |
| currency_code | CHAR(3) | |
| provider | VARCHAR(32) NULL | |
| provider_ref | VARCHAR(120) NULL | |
| paid_at | DATETIME NULL | |
| timestamps | | |

فهرس `(order_id, status)`. لا عمود PAN ولا last4 إلزامي. last4 اختياري لاحقاً فقط إذا أعادته البوابة، عمود `card_last4 CHAR(4) NULL`.

### `refunds`
عزل: مطلوب. `payment_id`, `order_id`, `amount`, `reason` VARCHAR(255)، `status` (`pending|completed`)، `idempotency_key` CHAR(26)، `processed_by` user_id، `created_at`.  
UNIQUE `(tenant_id, idempotency_key)`.

مجموع المرتجعات المكتملة لدفعة لا يتجاوز مبلغها. يُفرض في معاملة بقفل صف الدفع.

## 9. نقطة البيع والورديات

### `cash_registers`
عزل: مطلوب. `branch_id`, `name`, `is_active`.

### `cashier_shifts`
عزل: مطلوب.

| العمود | النوع | قيود |
|---|---|---|
| id | BIGINT PK | |
| public_id | CHAR(26) | UNIQUE |
| tenant_id | BIGINT | |
| branch_id | BIGINT | |
| register_id | BIGINT | |
| user_id | BIGINT | |
| status | VARCHAR(32) | `open`, `closed` |
| opening_float | DECIMAL(12,2) | |
| expected_cash | DECIMAL(12,2) NULL | يُملأ عند الإغلاق |
| counted_cash | DECIMAL(12,2) NULL | |
| difference | DECIMAL(12,2) NULL | |
| opened_at | DATETIME | |
| closed_at | DATETIME NULL | |

فهرس `(register_id, status)`. تطبيق يمنع ورديتين `open` لنفس الصندوق بفحص داخل معاملة وقفل. فهرس فريد جزئي غير معتمد على MariaDB 10.4؛ القفل هو الآلية.

### `cash_movements`
عزل: مطلوب. `shift_id`, `kind` (`sale|refund|pay_in|pay_out`), `amount DECIMAL(12,2)` بإشارة موجبة ثم الاتجاه حسب `kind` حتى لا تختلط الإشارات. `signed_amount DECIMAL(12,2)` يُحفظ جاهزاً للجمع: البيع والإدخال موجب، المرتجع والسحب سالب. `reference_type`, `reference_id` NULL، `note`, `created_at`.  
فهرس `(shift_id)`.

### `receipt_templates`
عزل: مطلوب. `header_text`, `footer_text`, `paper_width` افتراضي 80، `show_tax` TINYINT. صف واحد فعال للمنشأة في الإطلاق.

## 10. الطاولات والحجوزات

### `dining_areas`
عزل: مطلوب. `branch_id`, `name_ar`, `name_en`, `sort_order`.

### `dining_tables`
عزل: مطلوب. `area_id`, `branch_id`, `name`, `seats` SMALLINT، `status` VARCHAR، `public_id`.  
UNIQUE `(branch_id, name)`.

### `table_sessions`
عزل: مطلوب. `table_id`, `guest_count`, `status` (`open|closed`), `opened_at`, `closed_at` NULL.  
فهرس `(table_id, status)`.

### `table_session_orders`
PK `(table_session_id, order_id)`.

### `bill_splits`
عزل: مطلوب. `order_id`, `mode` (`equal|items|amount`), `created_by`.

### `bill_split_parts`
`split_id`, `label`, `amount DECIMAL(12,2)`, `payment_id` NULL.

### `table_transfers`
`tenant_id`, `table_session_id`, `from_table_id`, `to_table_id`, `user_id`, `created_at`.

### `reservations`
عزل: مطلوب.

| العمود | النوع | قيود |
|---|---|---|
| id | BIGINT PK | |
| public_id | CHAR(26) | UNIQUE |
| tenant_id | BIGINT | |
| branch_id | BIGINT | |
| customer_id | BIGINT NULL | |
| customer_name | VARCHAR(120) | نسخة |
| customer_phone | VARCHAR(20) | نسخة |
| guest_count | SMALLINT | |
| reserved_at | DATETIME | |
| duration_minutes | SMALLINT | افتراضي 90 |
| status | VARCHAR(32) | |
| notes | VARCHAR(500) NULL | |
| source | VARCHAR(32) | `online` أو `staff` |
| timestamps | | |

فهرس `(branch_id, reserved_at, status)`.

### `reservation_tables`
PK `(reservation_id, table_id)`. طاولة واحدة متوقعة في الإطلاق لكن الربط يسمح باثنتين لمجموعة كبيرة.

### `reservation_status_histories`
مثل تواريخ الطلب.

## 11. المطبخ

### `prep_stations`
عزل: مطلوب. `branch_id`, `name_ar`, `name_en`, `kind` (`kitchen|bar|dessert|bakery`), `is_active`, `sort_order`.

### `kitchen_tickets`
عزل: مطلوب.

| العمود | النوع | قيود |
|---|---|---|
| id | BIGINT PK | |
| public_id | CHAR(26) | UNIQUE |
| tenant_id | BIGINT | |
| branch_id | BIGINT | |
| order_id | BIGINT | |
| station_id | BIGINT | |
| status | VARCHAR(32) | `queued`, `in_progress`, `ready` |
| started_at | DATETIME NULL | |
| ready_at | DATETIME NULL | |
| timestamps | | |

فهرس الاستطلاع: `(branch_id, station_id, status, updated_at)`.

### `kitchen_ticket_items`
`ticket_id`, `order_item_id`, `quantity`, `name_ar`, `name_en`, `notes`. النسخة النصية حتى لا يجمع المطبخ من جداول المنتجات.

المنتج يشير إلى `prep_station_id` واحدة (القسم 6). لا جدول ربط متعدد في الإطلاق.

## 12. المخزون والوصفات

### `units`
عزل: مطلوب. `name_ar`, `name_en`, `abbreviation`, `kind` (`weight|volume|count`).

### `unit_conversions`
عزل: مطلوب. `from_unit_id`, `to_unit_id`, `factor DECIMAL(16,6)`.  
UNIQUE `(tenant_id, from_unit_id, to_unit_id)`. العامل يعني ضرب كمية المصدر للحصول على الهدف.

### `stock_items`
عزل: مطلوب. `name_ar`, `name_en`, `sku` NULL، `unit_id`, `kind` (`ingredient|packaging|resale`), `is_active`.  
UNIQUE `(tenant_id, sku)`.

### `branch_stocks`
PK `(branch_id, stock_item_id)`, `tenant_id`, `quantity DECIMAL(14,3)`, `reorder_level DECIMAL(14,3) NULL`, `avg_cost DECIMAL(12,4) NULL`.  
الرصيد ملخص. الدفتر هو المرجع عند الاختلاف، وإعادة الحساب من الحركات أداة إصلاح في المرحلة 5.

### `stock_movements`
عزل: مطلوب. إلحاق فقط.

| العمود | النوع | قيود |
|---|---|---|
| id | BIGINT PK | |
| tenant_id | BIGINT | |
| branch_id | BIGINT | |
| stock_item_id | BIGINT | |
| kind | VARCHAR(32) | `purchase`, `sale`, `adjustment`, `transfer_in`, `transfer_out`, `waste`, `count` |
| quantity | DECIMAL(14,3) | بإشارة: الداخل موجب والخارج سالب |
| unit_cost | DECIMAL(12,4) NULL | |
| reference_type | VARCHAR(32) NULL | |
| reference_id | BIGINT NULL | |
| idempotency_key | VARCHAR(64) NULL | |
| note | VARCHAR(255) NULL | |
| user_id | BIGINT NULL | |
| occurred_at | DATETIME | |
| created_at | DATETIME | |

UNIQUE `(tenant_id, idempotency_key)` عند امتلاء المفتاح. فهرس `(branch_id, stock_item_id, occurred_at)` و`(reference_type, reference_id)`.

### `recipes`
عزل: مطلوب. `product_id` UNIQUE مع tenant: وصفة واحدة للمنتج في الإطلاق. `yield_qty DECIMAL(14,3)` افتراضي 1. `notes`.

### `recipe_lines`
`recipe_id`, `stock_item_id`, `quantity DECIMAL(14,3)`, `unit_id`. الكمية بوحدة البند وتُحول عند الاستهلاك إن اختلفت عن وحدة الصنف.

### `stock_transfers`
عزل: مطلوب. `from_branch_id`, `to_branch_id`, `status` (`draft|sent|received|cancelled`), `sent_at`, `received_at`. الفرعان من نفس المنشأة عبر مفتاحين مركبين.

### `stock_transfer_lines`
`transfer_id`, `stock_item_id`, `quantity DECIMAL(14,3)`.

### `stock_counts`
عزل: مطلوب. `branch_id`, `status` (`draft|posted`), `counted_at`, `user_id`.

### `stock_count_lines`
`count_id`, `stock_item_id`, `expected_qty`, `counted_qty`. الفرق يُكتب حركة `count` عند الترحيل فقط.

الهدر حركة `waste` مع `note` إلزامي من التطبيق. لا جدول هدر منفصل في الإطلاق.

## 13. الموردون والمشتريات

### `suppliers`
عزل: مطلوب. `name`, `phone`, `email` NULL، `tax_number` NULL، `address` NULL، `is_active`.

### `purchase_orders`
عزل: مطلوب. `branch_id`, `supplier_id`, `status` (`draft|sent|partial|received|cancelled`), `ordered_at` NULL، `expected_at` NULL، `subtotal`, `tax_total`, `total`, `currency_code`.

### `purchase_order_lines`
`purchase_order_id`, `stock_item_id`, `quantity`, `unit_cost DECIMAL(12,4)`, `received_qty DECIMAL(14,3)` افتراضي 0.

### `goods_receipts`
`purchase_order_id`, `tenant_id`, `branch_id`, `received_at`, `user_id`.

### `goods_receipt_lines`
`goods_receipt_id`, `purchase_order_line_id`, `quantity`, `unit_cost`. كل سطر استلام ينشئ حركة `purchase` بمرجع الإيصال.

## 14. العملاء والتسويق

### `customers`
عزل: مطلوب. `name`, `phone`, `email` NULL، `notes` NULL، `loyalty_points INT` افتراضي 0.  
UNIQUE `(tenant_id, phone)`.

### `customer_addresses`
`customer_id`, `tenant_id`, `label`, `area_name`, `address_line`, `is_default`.

### `loyalty_transactions`
إلحاق. `customer_id`, `tenant_id`, `points INT` بإشارة، `reason`, `order_id` NULL، `created_at`. الرصيد على العميل ملخص. الواجهة مؤجلة (FR-F-08) والجدول يُنشأ في المرحلة 3 فارغاً حتى لا نهاجر لاحقاً على جدول عملاء حي إن رغب المالك، **أو** يُؤجل إنشاء الجدول إلى ما بعد المرحلة 6 إذا أردنا تقليص التهجير الأول. **المعتمد للخطة:** إنشاء الجدول في المرحلة 6 مع الولاء لا في الإطلاق التجاري، حتى لا ندعي ميزة غير مبنية. التصميم جاهز هنا.

## 15. الإشعارات

### `notification_templates`
`tenant_id` NULL لقالب المنصة، وإلا منشأة. `channel` (`email|whatsapp`), `key`, `body_ar` TEXT، `body_en` TEXT، `is_active`.  
UNIQUE `(tenant_id, channel, key)`.

### `outbound_messages`
عزل: `tenant_id` NULL لرسائل المنصة.

| العمود | النوع | قيود |
|---|---|---|
| id | BIGINT PK | |
| tenant_id | BIGINT NULL | |
| channel | VARCHAR(16) | |
| recipient | VARCHAR(190) | |
| template_key | VARCHAR(100) | |
| payload | JSON | |
| status | VARCHAR(32) | `queued`, `sent`, `failed` |
| provider | VARCHAR(32) NULL | |
| provider_ref | VARCHAR(120) NULL | |
| error | VARCHAR(255) NULL | |
| dedupe_key | VARCHAR(120) | |
| sent_at | DATETIME NULL | |
| created_at | DATETIME | |

UNIQUE `(dedupe_key)`.

لا جدول Laravel `notifications` إضافي في الإطلاق. `outbound_messages` هو السجل.

## 16. التدقيق والدعم

### `audit_logs`
`tenant_id` NULL، `user_id` NULL، `action` VARCHAR(100)، `auditable_type` VARCHAR(100)، `auditable_id` BIGINT، `before` JSON NULL، `after` JSON NULL، `ip` VARCHAR(45) NULL، `user_agent` VARCHAR(255) NULL، `created_at`.  
فهرس `(tenant_id, created_at)` و`(auditable_type, auditable_id)`.

### `support_tickets`
عزل: مطلوب. `user_id`, `subject`, `status` (`open|pending|closed`), `priority` (`normal|high`), `timestamps`.

### `support_messages`
`ticket_id`, `user_id`, `body` TEXT، `created_at`.

## 17. التقارير والمصروفات

### `expense_categories`
عزل: مطلوب. `name_ar`, `name_en`, `is_active`.

### `expenses`
عزل: مطلوب. `branch_id`, `category_id`, `amount`, `tax_amount`, `incurred_on` DATE، `note`, `created_by`.  
فهرس `(branch_id, incurred_on)`.

### `daily_branch_summaries`
عزل: مطلوب. PK منطقي UNIQUE `(branch_id, business_date)`.

| العمود | النوع |
|---|---|
| orders_count | INT |
| gross_total | DECIMAL(12,2) |
| discount_total | DECIMAL(12,2) |
| tax_total | DECIMAL(12,2) |
| net_total | DECIMAL(12,2) |
| cash_total | DECIMAL(12,2) |
| electronic_total | DECIMAL(12,2) |
| refund_total | DECIMAL(12,2) |
| delivery_fee_total | DECIMAL(12,2) |
| calculated_at | DATETIME |

`business_date` تاريخ بتوقيت الفرع لا UTC حتى لا ينقسم يوم العمل عند منتصف الليل. المصدر أوامر `placed_at` بعد التحويل. الطلب الملغى قبل الاكتمال لا يدخل `gross`؛ المرتجع يدخل `refund_total`. تعريف «الصافي» في التقرير: `gross_total - discount_total - refund_total` مع إظهار الضريبة مستقلة حتى لا تُحسب مرتين. معادلة التقرير تُثبت في اختبار بأرقام معروفة.

لا جداول تقارير أخرى. مقارنات الفروع استعلام على هذا الجدول.

## 18. الأداء

فهارس التشغيل الإلزامية قبل الإطلاق:

- الطلبات حسب الفرع والحالة والزمن.
- التذاكر حسب الفرع والمحطة والحالة و`updated_at`.
- الحجوزات حسب الفرع والزمن.
- الحركات حسب الفرع والصنف والزمن.
- العداد حسب المنشأة والمفتاح والفترة.
- العملاء حسب المنشأة والهاتف.

أرشفة عملية: لا تقسيم جداول في الإطلاق. عند تجاوز نحو 5 ملايين صف طلبات نراجع الأرشفة، وهذا خارج أفق الاستضافة الأولى.

الاستعلامات العامة للضيف تقرأ المنتجات المتاحة لفرع واحد فقط مع حد أعمدة بلا `SELECT *` في الواجهة البرمجية.

## 19. ترتيب التهجير المنطقي

1. منصة، عملات، مستخدمون، صلاحيات.
2. منشآت، فروع، ضرائب، إعدادات.
3. خطط واشتراك.
4. قائمة وخيارات.
5. طلبات ودفعات.
6. صناديق وطاولات ومطبخ وحجوزات.
7. مخزون ومشتريات.
8. عملاء وإشعارات وتدقيق ودعم ومصروفات وملخصات.

كل مرحلة تبني جداولها. لا تهجير عملاق واحد غير قابل للمراجعة، لكن المخطط الكامل معتمد من الآن حتى لا تُسمى الأعمدة المالية مرتين.
