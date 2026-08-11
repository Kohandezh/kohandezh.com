# P3 — Setup Guides

این فایل راهنمای تنظیمات دستی است که بعد از deploy باید انجام بشن.

---

## 1. فعال‌سازی LiteSpeed Cache (تأثیر: TTFB از ۸۷۰ms به <۲۰۰ms)

سایت روی LiteSpeed Server هست، پس **LiteSpeed Cache plugin** بهترین گزینه‌ست.

### نصب:
1. wp-admin ← Plugins ← Add New
2. جستجو کنید: `LiteSpeed Cache`
3. Install + Activate (نویسنده: LiteSpeed Technologies)

### تنظیمات اولیه (بعد از Activate):
1. wp-admin ← LiteSpeed Cache ← Settings
2. تب **General**:
   - `Enable Cache` = **ON**
   - `Cache Logged-in Users` = **OFF** (مهم — امنیت)
3. تب **TTL**:
   - `Default Public TTL` = **86400** (۲۴ ساعت — pages کم تغییر می‌کنن)
   - `Front Page TTL` = **3600** (۱ ساعت)
4. تب **Purge**:
   - `Auto Purge Rules`:
     - ✓ Update post/page on publish/slug change
     - ✓ Update plugin/theme

### Image Optimization (تبش جدا):
1. wp-admin ← LiteSpeed Cache ← Image Optimization
2. `Send Optimization Request` = **ON**
3. این به‌صورت خودکار WebP/AVIF تولید می‌کنه و به مرورگر مناسب serve می‌کنه.

### CCSS (Critical CSS):
1. wp-admin ← LiteSpeed Cache ← CSS Optimization
2. `Generate Critical CSS` = **ON**
3. این FCP را به‌طور میانگین **۳۰-۵۰٪ بهتر** می‌کنه.

### تست بعد از فعال‌سازی:
```bash
curl -sI https://kohandezh.com/ | grep -iE "x-litespeed-cache|x-lscache|cache-control"
# انتظار: x-litespeed-cache: hit (بعد از اولین request)
```

---

## 2. ساخت Wikidata Entity (تأثیر: Knowledge Graph panel در Google)

Wikidata یک entity مستقل برای شما می‌سازه که در Google Knowledge Graph و Wikipedia استفاده می‌شه.

### پیش‌نیازها:
- ✅ Account روی https://www.wikidata.org (با Wikipedia account می‌تونی login کنی)
- ✅ حداقل ۱۵ روز account age (برای autoconfirmed)
- ✅ حداقل ۵۰ ویرایش (برای برخی عملیات)

### مراحل ساخت entity:

#### مرحله ۱: آماده‌سازی منابع معتبر (نقل‌قول‌ها)
Wikidata به **منابع مستقل** نیاز داره. این منابع را آماده کن:
1. صفحه‌ی لینکدین شما (https://www.linkedin.com/in/kohandezh)
2. صفحه‌ی KSF (https://ksf.ir)
3. مقاله‌ی روزنامه/رسانه در مورد شما (اگر هست)
4. صفحه‌ی پارک فناوری پردیس (معرفی spinoff ها)
5. صفحه‌ی University of Tehran (در صورت وجود)

#### مرحله ۲: ساخت Item جدید
1. به https://www.wikidata.org/wiki/Special:NewItem برید
2. **Language** = `en`
3. **Label** = `Mohammad Ali Kohandezh`
4. **Description** = `Iranian IT manager and CEO of Kohan System Farda (KSF)`
5. **Aliases** = `Mohammad Kohandezh, محمدعلی کهن‌دژ`
6. Submit

#### مرحله ۳: افزودن Statements
بعد از ساخت، این statement ها را اضافه کن (با منبع/Reference):

| Property | Value | Source |
|---|---|---|
| instance of (P31) | human (Q5) | — |
| sex or gender (P21) | male (Q6581097) | — |
| country of citizenship (P27) | Iran (Q794) | — |
| occupation (P106) | businessperson (Q43845), computer scientist (Q82594), cybersecurity professional | LinkedIn |
| employer (P108) | Kohan System Farda (create as new entity first) | ksf.ir |
| academic degree (P512) | PhD in information technology management | LinkedIn |
| educated at (P69) | University of Tehran (Q1377970) | LinkedIn |
| official website (P856) | https://kohandezh.com/ | — |
| LinkedIn personal profile ID (P6634) | kohandezh | LinkedIn URL |
| X username (P2002) | Konandehh | X URL |
| place of work (P937) | Tehran (Q3692), Pardis Technology Park | Address |
| startup accelerator (P9886) | Nokhbegan Technology Growth Center (Masir 21) | ksf.ir |
| languages spoken, written or signed (P1412) | Persian (Q9168), English (Q1860) | — |
| field of work (P101) | artificial intelligence (Q11660), cybersecurity (Q129165), virtualization (Q1417638) | — |

#### مرحله ۴: ساخت entity برای KSF
1. یک entity جدید برای `Kohan System Farda` بسازید
2. Properties:
   - instance of: company (Q783793)
   - country: Iran
   - headquarters: Tehran, Pardis Technology Park
   - official website: https://ksf.ir
   - CEO: (link to your entity)
   - founded: 2007

#### مرحله ۵: بعد از تأیید Wikidata
وقتی entity شما ساخته و تأیید شد (معمولاً ۱-۳ هفته)، این تغییر را در repo اعمال کن:

در فایل `index.html` (و ۸ زبان دیگر)، در بخش `Person` schema، این خط را به `sameAs` array اضافه کن:
```json
"https://www.wikidata.org/wiki/QXXXXXXX"  // Q number شما را از آدرس entity بردار
```

بعد از sync و deploy، Google در ۲-۴ هفته بعد از crawl، احتمالاً **Knowledge Graph panel** نشون می‌ده وقتی کسی اسم شما را سرچ کنه.

### تست پیشرفت:
- https://www.google.com/search?q=Mohammad+Ali+Kohandezh
- به دنبال panel سمت راست باشه (info box با عکس و توضیحات)

---

## 3. فعال‌سازی Cloudflare (اختیاری ولی قویاً پیشنهاد می‌شه)

اگر Cloudflare را در front بذارید:

### تأثیرات:
- TTFB از ۸۷۰ms به **<۱۰۰ms** (CDN جهانی + edge cache)
- **Geo-IP رایگان** (هدر `CF-IPCountry` خودکار ست می‌شه — کد شما به‌صورت خودکار استفاده می‌کنه)
- DDoS protection
- بهینه‌سازی تصاویر (Polish)
- بهینه‌سازی JS/CSS (Auto Minify — البته شما خودتون minify می‌کنید، پس این بخش را خاموش کنید)

### مراحل:
1. ثبت‌نام رایگان در https://cloudflare.com
2. افزودن domain `kohandezh.com`
3. تغییر nameserver های domain به Cloudflare NS ها
4. صبر ۲۴-۴۸ ساعت برای propagation
5. در تب **Speed** ← Optimization:
   - Auto Minify: **OFF** (شما خودتون می‌کنید)
   - Brotli: **ON**
   - 0-RTT Connection Resumption: **ON**
6. در تب **Caching** ← Configuration:
   - Caching Level: **Standard**
   - Browser Cache TTL: **4 hours**
7. در تب **Caching** ← Cache Rules:
   - Cache everything (assets, HTML, etc.)
   - Bypass cache برای `/wp-admin/*` و `/wp-login.php`

### تست:
```bash
curl -sI https://kohandezh.com/ | grep -iE "cf-ray|cf-cache|server"
# انتظار: cf-ray header + server: cloudflare
```

بعد از فعال‌سازی Cloudflare، Geo-IP routing شما به‌صورت خودکار از هدر `HTTP_CF_IPCOUNTRY` استفاده می‌کنه (کد قبلاً نوشته شده) و **هیچ API call ای لازم نیست**.

---

## 4. حذف jQuery (P3 — deferred)

jQuery در ۷ فایل JS استفاده شده:
- `assets/js/404-games.js` (۱ استفاده)
- `assets/js/animation-change-text.js` (۱)
- `assets/js/carousel.js` (۱)
- `assets/js/countto.js` (۷ استفاده)
- `assets/js/gsapAnimation.js` (۱۱ استفاده)
- `assets/js/lazy-bundle.js` (۱)
- `assets/js/linkedin-content.js` (۱)

### تأثیر:
- ۳۹KB savings (jquery.min.js)
- ~۱۲۰KB savings اگر با bootstrap.min.js هم replace بشه (که به jQuery وابسته‌ست)

### چرا تأخیر افتاد:
این یک refactor بزرگ با ریسک بالاست. هر فایل باید به vanilla JS rewrite بشه و تست بشه که animation/counter/carousel بدون مشکل کار کنن. پیشنهاد:
- به‌جای حذف کامل jQuery، اول **Defer** و **Async** اش کن (تا render-block نشه)
- بعد یکی‌یکی فایل‌ها را rewrite کن (با test بعد از هر کدوم)
- در نهایت، jQuery را فقط در صفحاتی که نیاز دارن load کن (نه همه‌جا)

### شروع سریع (بدون rewrite):
در `index.html` (و ۸ زبان دیگر)، jQuery script tag را پیدا کن و این تغییر را بده:

```html
<!-- قبل -->
<script src="assets/js/jquery.min.js"></script>

<!-- بعد -->
<script src="assets/js/jquery.min.js" defer></script>
```

این کار jQuery را از render-blocking خارج می‌کنه و FCP را ~۲۰۰-۴۰۰ms بهتر می‌کنه، بدون نیاز به rewrite.

برای اجرای کامل (با rewrite)، یک session جداگانه لازم است.
