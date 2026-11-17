# 19 — مسار الإطلاق على الإنتاج

**الغرض:** ترتيب ما تبقّى بعد توقيع القائمة محلياً (`docs/16-commercial-launch-checklist.md`) حتى أول عميل دافع.  
**لا ميزات جديدة هنا** — إعداد وتشغيل فقط.

## ترتيب التنفيذ

| # | البند | وثيقة / أمر | معيار الإغلاق |
|---|---|---|---|
| 1 | اختيار مزوّد يجيب CLI وCron وInnoDB و≥256M | `docs/17-hosting-provider-answers.md` عمود الإنتاج | كل الحرج = نعم |
| 2 | حجز نطاق وتفعيل HTTPS وإعادة توجيه HTTP | لوحة المزوّد + `APP_URL=https://…` | شهادة صالحة ومسار الدخول يعمل على HTTPS |
| 3 | نشر بالترتيب المعتمد | `docs/09-shared-hosting-deployment.md` + أرشيف `docs/18-rollback-plan.md` | دخول المشرف يعمل؛ السجل بلا 500 |
| 4 | تهجير MySQL الحي | `php artisan migrate --force` | آخر تهجير مطبّق بلا خطأ |
| 5 | جاهزية الإعداد | `php artisan ops:production-ready` | خروج 0؛ لا فشل حرج |
| 6 | Cron + طابور | `docs/17-ops-runbook.md` | `mail:send-test` يفرّغ خلال دقيقتين |
| 7 | كلمة بذرة المشرف + TOTP | `/platform/totp/setup` | `ops:production-ready` يرى مشرفاً مؤكَّد TOTP |
| 8 | استعادة نسخة على حساب اختبار | `docs/17-backup-restore-drill.md` | دخول + فتح طلب قديم |
| 9 | منشأة تجريبية 3 أيام | `docs/18-pilot-runbook.md` + مراقبة يومية | سيناريو القبول أخضر كل يوم |
| 10 | أول عميل دافع | تحصيل يدوي `docs/17-billing-collection.md` | فاتورة مفتوحة → دفعة `manual` بمرجع فريد |

## أوامر بعد كل نشر

```text
cd /home/{account}/restivo
/usr/local/bin/php artisan migrate --force
/usr/local/bin/php artisan config:cache
/usr/local/bin/php artisan route:cache
/usr/local/bin/php artisan view:cache
/usr/local/bin/php artisan ops:production-ready
/usr/local/bin/php artisan ops:health-check
```

## إيقاف فوري

- أي «لا» حرج من المزوّد → لا نشر.
- `ops:production-ready` يفشل على `APP_DEBUG=true` أو غياب `APP_KEY` أو `REQUIRE_PLATFORM_TOTP=false` في الإنتاج → لا فتح للعملاء.
- فشل استعادة النسخة على الاختبار → لا عميل دافع.

## سجل الإغلاق (يُملأ على الإنتاج)

| البند | التاريخ (Africa/Cairo) | من نفّذ | ملاحظات |
|---|---|---|---|
| مزوّد مجاب | | | |
| HTTPS | | | |
| migrate حي | | | |
| production-ready = 0 | | | |
| TOTP مشرف | | | |
| استعادة نسخة | | | |
| يوم 1 تجريب | | | |
| يوم 2 تجريب | | | |
| يوم 3 تجريب | | | |
