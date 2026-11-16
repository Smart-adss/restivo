# 05 — مخطط الكيانات والعلاقات

**المشروع:** Restaurant SaaS Platform  
**التاريخ:** 17 نوفمبر 2026  
**المصدر النصي للأعمدة:** [04-database-design.md](04-database-design.md).

مخطط واحد لكل الجداول غير قابل للقراءة. المخططات التالية مقسمة حسب حدود المعاملات. العلاقات المعروضة هي روابط العمل الأساسية. كل جدول تشغيلي ما زال يحمل `tenant_id` حتى لو لم يُرسم السهم إلى `tenants` في المخطط الفرعي لتجنب تكرار الخطوط.

```mermaid
erDiagram
  TENANTS ||--o{ BRANCHES : has
  TENANTS ||--o| SUBSCRIPTIONS : holds
  TENANTS ||--o{ USERS : employs
  PLANS ||--o{ SUBSCRIPTIONS : defines
  PLANS ||--o{ PLAN_PRICES : priced
  PLANS ||--o{ PLAN_FEATURES : grants
  FEATURES ||--o{ PLAN_FEATURES : catalog
  SUBSCRIPTIONS ||--o{ SUBSCRIPTION_INVOICES : billed
  SUBSCRIPTION_INVOICES ||--o{ SUBSCRIPTION_PAYMENTS : paid_by
  SUBSCRIPTIONS ||--o{ SUBSCRIPTION_ADDONS : extras
  ADDONS ||--o{ SUBSCRIPTION_ADDONS : selected
  TENANTS ||--o{ USAGE_COUNTERS : consumes
  USERS ||--o{ USER_ROLES : assigned
  ROLES ||--o{ USER_ROLES : named
  ROLES ||--o{ ROLE_PERMISSION : includes
  PERMISSIONS ||--o{ ROLE_PERMISSION : named
  BRANCHES ||--o{ BRANCH_USER : staffed
  USERS ||--o{ BRANCH_USER : accesses
```

## 1. الاشتراك والهوية

```mermaid
erDiagram
  TENANTS {
    bigint id PK
    char public_id UK
    varchar slug UK
    varchar status
    char currency_code
  }
  BRANCHES {
    bigint id PK
    bigint tenant_id FK
    char public_id UK
    varchar code
  }
  USERS {
    bigint id PK
    bigint tenant_id FK
    varchar email UK
    varchar status
  }
  ROLES {
    bigint id PK
    bigint tenant_id FK
    varchar key
  }
  PERMISSIONS {
    bigint id PK
    varchar key UK
  }
  SUBSCRIPTIONS {
    bigint id PK
    bigint tenant_id FK
    bigint plan_id FK
    varchar status
    datetime current_period_end
  }
  PLANS {
    bigint id PK
    varchar code UK
  }
  FEATURES {
    bigint id PK
    varchar key UK
    varchar value_type
  }
  PLAN_FEATURES {
    bigint plan_id PK
    bigint feature_id PK
    varchar value
  }
  SUBSCRIPTION_INVOICES {
    bigint id PK
    bigint subscription_id FK
    decimal total
    varchar status
  }
  USAGE_COUNTERS {
    bigint id PK
    bigint tenant_id FK
    varchar feature_key
    int used
  }

  TENANTS ||--o{ BRANCHES : has
  TENANTS ||--o{ USERS : has
  TENANTS ||--o| SUBSCRIPTIONS : has
  PLANS ||--o{ SUBSCRIPTIONS : applied
  PLANS ||--o{ PLAN_FEATURES : sets
  FEATURES ||--o{ PLAN_FEATURES : keyed
  SUBSCRIPTIONS ||--o{ SUBSCRIPTION_INVOICES : issues
  TENANTS ||--o{ USAGE_COUNTERS : counts
  USERS }o--o{ ROLES : via_user_roles
  ROLES }o--o{ PERMISSIONS : via_role_permission
```

## 2. القائمة

```mermaid
erDiagram
  BRANCHES ||--o{ PRODUCT_BRANCH_SETTINGS : overrides
  MENU_CATEGORIES ||--o{ MENU_CATEGORIES : parent
  MENU_CATEGORIES ||--o{ PRODUCTS : groups
  PRODUCTS ||--o{ PRODUCT_BRANCH_SETTINGS : availability
  PRODUCTS ||--o{ PRODUCT_IMAGES : shows
  PRODUCTS ||--o{ PRODUCT_OPTION_GROUPS : configures
  PRODUCT_OPTION_GROUPS ||--o{ PRODUCT_OPTIONS : contains
  PRODUCT_OPTIONS ||--o{ PRODUCT_OPTION_BRANCH_SETTINGS : branch_price
  TAX_RATES ||--o{ PRODUCTS : taxes
  PREP_STATIONS ||--o{ PRODUCTS : cooks
  BRANCHES ||--o{ QR_CODES : prints
```

## 3. الطلب والدفع والصندوق

```mermaid
erDiagram
  BRANCHES ||--o{ ORDERS : receives
  BRANCH_ORDER_SEQUENCES ||--|| BRANCHES : numbers
  CUSTOMERS ||--o{ ORDERS : places
  ORDERS ||--o{ ORDER_ITEMS : contains
  ORDER_ITEMS ||--o{ ORDER_ITEM_OPTIONS : customized
  ORDERS ||--o{ ORDER_DISCOUNTS : reduced
  ORDERS ||--o{ ORDER_STATUS_HISTORIES : tracked
  COUPONS ||--o{ ORDER_DISCOUNTS : applied
  COUPONS ||--o{ COUPON_REDEMPTIONS : redeemed
  DELIVERY_ZONES ||--o{ ORDERS : fees
  ORDERS ||--o{ PAYMENTS : settled
  PAYMENTS ||--o{ REFUNDS : reversed
  CASHIER_SHIFTS ||--o{ ORDERS : sold_during
  CASH_REGISTERS ||--o{ CASHIER_SHIFTS : opens
  CASHIER_SHIFTS ||--o{ CASH_MOVEMENTS : moves
  USERS ||--o{ CASHIER_SHIFTS : cashier
```

## 4. الصالة والحجز والمطبخ

```mermaid
erDiagram
  BRANCHES ||--o{ DINING_AREAS : divides
  DINING_AREAS ||--o{ DINING_TABLES : holds
  DINING_TABLES ||--o{ TABLE_SESSIONS : seats
  TABLE_SESSIONS ||--o{ TABLE_SESSION_ORDERS : includes
  ORDERS ||--o{ TABLE_SESSION_ORDERS : linked
  ORDERS ||--o{ BILL_SPLITS : splits
  BILL_SPLITS ||--o{ BILL_SPLIT_PARTS : parts
  TABLE_SESSIONS ||--o{ TABLE_TRANSFERS : moved
  BRANCHES ||--o{ RESERVATIONS : books
  RESERVATIONS ||--o{ RESERVATION_TABLES : assigns
  DINING_TABLES ||--o{ RESERVATION_TABLES : reserved
  CUSTOMERS ||--o{ RESERVATIONS : requests
  ORDERS ||--o{ KITCHEN_TICKETS : fires
  PREP_STATIONS ||--o{ KITCHEN_TICKETS : works
  KITCHEN_TICKETS ||--o{ KITCHEN_TICKET_ITEMS : shows
  ORDER_ITEMS ||--o{ KITCHEN_TICKET_ITEMS : copied
```

## 5. المخزون والمشتريات والمالية

```mermaid
erDiagram
  UNITS ||--o{ STOCK_ITEMS : measured
  UNITS ||--o{ UNIT_CONVERSIONS : converts
  STOCK_ITEMS ||--o{ BRANCH_STOCKS : balanced
  BRANCHES ||--o{ BRANCH_STOCKS : stores
  STOCK_ITEMS ||--o{ STOCK_MOVEMENTS : ledger
  BRANCHES ||--o{ STOCK_MOVEMENTS : at
  PRODUCTS ||--o| RECIPES : cooked_from
  RECIPES ||--o{ RECIPE_LINES : uses
  STOCK_ITEMS ||--o{ RECIPE_LINES : ingredient
  BRANCHES ||--o{ STOCK_TRANSFERS : sends
  STOCK_TRANSFERS ||--o{ STOCK_TRANSFER_LINES : lines
  BRANCHES ||--o{ STOCK_COUNTS : counts
  STOCK_COUNTS ||--o{ STOCK_COUNT_LINES : lines
  SUPPLIERS ||--o{ PURCHASE_ORDERS : supplies
  BRANCHES ||--o{ PURCHASE_ORDERS : receives_at
  PURCHASE_ORDERS ||--o{ PURCHASE_ORDER_LINES : lines
  PURCHASE_ORDERS ||--o{ GOODS_RECEIPTS : receipts
  GOODS_RECEIPTS ||--o{ GOODS_RECEIPT_LINES : lines
  BRANCHES ||--o{ EXPENSES : spends
  EXPENSE_CATEGORIES ||--o{ EXPENSES : classifies
  BRANCHES ||--o{ DAILY_BRANCH_SUMMARIES : rolls_up
  CUSTOMERS ||--o{ CUSTOMER_ADDRESSES : lives
  CUSTOMERS ||--o{ LOYALTY_TRANSACTIONS : earns
```

## 6. الإشعار والتدقيق

```mermaid
erDiagram
  TENANTS ||--o{ OUTBOUND_MESSAGES : sends
  NOTIFICATION_TEMPLATES ||--o{ OUTBOUND_MESSAGES : renders
  TENANTS ||--o{ AUDIT_LOGS : audits
  USERS ||--o{ AUDIT_LOGS : acts
  TENANTS ||--o{ SUPPORT_TICKETS : opens
  SUPPORT_TICKETS ||--o{ SUPPORT_MESSAGES : thread
  USERS ||--o{ SUPPORT_MESSAGES : writes
```

## 7. قواعد لا تظهر في الخطوط

- منع الربط عبر منشأتين يتم بمفتاح أجنبي مركب `(tenant_id, id)` لا بسهم إضافي في الرسم.
- `order_items.product_id` قابل للإفراغ حتى يبقى السطر بعد أرشفة المنتج. النسخة النصية والسعر على السطر هما سجل البيع.
- `stock_movements` لا يُحذف. `branch_stocks.quantity` ملخص.
- `daily_branch_summaries` مشتق من الطلبات والدفعات والمرتجعات ويمكن إعادة بناؤه.
- `loyalty_transactions` مؤجل الإنشاء إلى المرحلة 6 رغم ظهوره في المخطط حتى يبقى شكل البيانات النهائي معروفاً.
