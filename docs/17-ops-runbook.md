# 17 — تشغيل Cron والطابور والبريد (P7-S17-03)

## Cron على cPanel (كل دقيقة)

```text
* * * * * cd /home/{account}/restivo && /usr/local/bin/php artisan schedule:run >> /home/{account}/logs/schedule.log 2>&1
```

```text
* * * * * cd /home/{account}/restivo && /usr/local/bin/php artisan queue:work database --stop-when-empty --max-time=50 --tries=3 --backoff=30 >> /home/{account}/logs/queue.log 2>&1
```

`--stop-when-empty` إلزامي حتى لا تتراكم عمليات PHP كل دقيقة.

## المجدول داخل التطبيق

| الأمر | التوقيت |
|---|---|
| `billing:advance-states` | كل ساعة |
| `billing:issue-invoices` | 02:10 Africa/Cairo |
| `reports:fill-daily-summaries` | كل 15 دقيقة |

## اختبار بريد خلال دقيقتين

1. ضبط SMTP في `.env` على بيئة التجريب.
2. `php artisan mail:send-test you@example.com`
3. خلال دقيقتين: إما وصول الرسالة إن كان العامل يعمل، أو ظهور الصف في `jobs` ثم اختفاؤه بعد `queue:work --stop-when-empty`.
4. عند الفشل راجع `failed_jobs` و`storage/logs`.

على الاختبار المحلي يستخدم `MAIL_MAILER=array`؛ الأمر يضع المهمة في الطابور ويؤكد أن المسار يعمل.

## جاهزية الإنتاج

بعد ضبط `.env` على الخادم:

```text
php artisan ops:production-ready
php artisan ops:production-ready --strict
```

الأول يفرض DEBUG=false وHTTPS وTOTP الإلزامي والطابور على database.  
`--strict` يفشل أيضاً إن لم يُكمل أي مشرف تسجيل TOTP بعد.  
الترتيب الكامل: `docs/19-production-golive.md`.
