# چک‌لیست دیپلوی kohandezh.com

> **P0 release 2026-08-02** — اصلاحات بحرانی SEO/GEO/LLM:
> - FAQ آپدیت شد ("9+ years" → "Over 18 years" در ۹ زبان)
> - locale-router v2: bot/SEO-safe، تشخیص زبان مرورگر، English به‌عنوان canonical پیش‌فرض
> - ۶۰+ صفحه‌ی قدیمی ۲۰۱۶ (shop, cart, affiliate-marketing, …) ۳۰۱ می‌شوند به homepage
> - canonical تکراری Yoast حذف شد
> - robots.txt قوانین allow برای ۱۲ AI crawler اضافه شد (GPTBot, ClaudeBot, PerplexityBot, …)
> - صفحات legacy از wp-sitemap.xml حذف می‌شن
>
> **P1 release 2026-08-02** — Geo-IP routing + Polish:
> - **Geo-IP language routing**: IP ایران → /fa/، آلمان → /de/، فرانسه → /fr/، ژاپن → /ja/، ... به‌صورت server-side (PHP)
> - Fallback chain کامل: `?lang` > cookie > CF-IPCountry > ip-api.com (cached) > browser-language > English
> - Cookie-based override: کاربر با کلیک روی "English" می‌تونه از geo-redirect فرار کنه (۱ سال cookie)
> - Bot-safe: Googlebot/GPTBot/ClaudeBot هرگز geo-redirect نمی‌شن (canonical English می‌مونن)
> - tap targetها 33px → 44px (Apple HIG minimum) در mobile nav
> - build.sh اجرا شد: **۱۳۳KB saving** روی JS/CSS (۲۱٪ کمتر)
> - cache-bust همه‌ی ?v=N ها در ۹ HTML file
>
> **P2 release 2026-08-02** — Semantic structure + Rich entity schemas:
> - **Heading hierarchy اصلاح شد**: ۱ H2 → **۷ H2**، ۱ H3 → **۸+ H3**، H4 از ۱۴ به ۰
> - **Organization schema** برای KSF + ۳ **SoftwareApplication** (پادیار، همایار، نت‌یار)
> - **ProfessionalService** schema با OfferCatalog، **WebSite** schema
> - همه‌ی schema ها با `@id` به هم متصل شدن (entity graph)
>
> **P3 release 2026-08-02** — Performance + GEO Polish:
> - تمام ۶۸ HTML img tag ها `loading` + `alt` دارن (از ۲۰ تا بی‌loading → ۰)
> - alt های توصیفی برای brand logos: Parsian Bank، Sarmayeh Bank، Mehr Eghtesad، Tehran Chamber، Noor Hospital
> - **fa-llms.txt** ساخته شد (نسخه‌ی فارسی llms.txt برای LLMهای فارسی‌زبان)
> - **WP rewrite rule** برای سرو `/llms.txt` و `/fa-llms.txt` — دیگه نیازی به FTP upload نیست
> - **Cache-Control headers**: versioned assets → ۱ سال immutable، HTML → ۱ ساعت + stale-while-revalidate
> - **SETUP-GUIDES.md**: راهنمای کامل LiteSpeed Cache + Wikidata entity creation + Cloudflare setup
> - schema version bump 1.3.0 → 1.4.0 (rewrite rules auto-flush)
>
> **دست‌کاهش شده (deferred):**
> - حذف jQuery: ۷ فایل نیاز به vanilla JS rewrite دارن — session جداگانه لازمه
> - Review schema: testimonialها ناشناس هستن و با policy گوگل قابل استفاده نیست (خطر penalty)
>
> SHA-256 آخر: `43b77445749da77e35f2ba613d79adaf3877260b12e864b305c9d997bccbdcaf`

پوشه قابل آپلود: ریشه‌ی پروژه = خود سایت استاتیک (اکنون flat). فایل‌های قابل آپلود: `index.html`, `*.html`, `assets/`, `blog/`, `portfolio/`, `.htaccess`, `robots.txt`, `sitemap.xml`, `llms.txt`. **نباید** آپلود شوند: `_tooling/`, `.claude/`, `.gstack/`, `.tooling-state/`, `dev-server.php`, `run-dev.sh`, `build.sh`, `Agent.md`.

## یکپارچه‌سازی با قالب وردپرس

ریشه‌ی پروژه (سایت استاتیک، اکنون flat) منبع اصلی (source of truth) است. قالب وردپرس در `_tooling/wp-theme/kohandezhcv/` از روی همین ریشه **تولید** می‌شود، نه دستی ویرایش.

بعد از هر تغییری در سایت استاتیک که باید به وردپرس هم برسه (محتوا، CSS/JS، فونت، عکس، امنیت):

```
python3 _tooling/wp-theme/sync-from-static.py
```

برای پیش‌نمایش تغییرات بدون نوشتن/حذف، اول `--dry-run` بزن:

```
python3 _tooling/wp-theme/sync-from-static.py --dry-run
```

این اسکریپت assets/ رو کامل mirror می‌کنه (rsync --delete) و هر ۹ تمپلیت CV + PSN + Certificates رو از HTML استاتیک بازسازی می‌کنه، بعد zip رو دوباره می‌سازه. فایل‌های `home.php`, `single.php`, `functions.php` دستی می‌مونن (چون معادل استاتیک ندارن). با `--no-zip` می‌تونی فقط sync رو بدون rebuild zip اجرا کنی.

**چرا این مهمه**: قبلاً یه بار یه فیکس امنیتی (آپدیت کتابخونه‌های آسیب‌پذیر) فقط توی قالب وردپرس اعمال شده بود و سایت استاتیک هفته‌ها بدون اون فیکس مونده بود، چون sync دستی بود و یادمون رفت. این اسکریپت دیگه اجازه نمی‌ده این اتفاق بیفته.

### نصب قالب روی وردپرس

- فایل نصبی: `_tooling/wp-theme/kohandezhcv.zip` → wp-admin ← Appearance ← Themes ← Add New ← Upload Theme.
  (خودِ zip روی `wp-content/themes/kohandezhcv/` باز می‌شود؛ فایل‌های استاتیک را دستی داخل `wp-content` کپی نکنید.)
- اگر آپلود از wp-admin با خطا خورد، یعنی `upload_max_filesize` هاست کوچک‌تر از حجم zip است:
  همان پوشه‌ی `kohandezhcv/` را مستقیم با FTP/File Manager داخل `wp-content/themes/` بگذارید (محدودیت PHP دور زده می‌شود).
- صفحات لازم (`fa`, `ar`, …, `psn`, `certificates`, `blog`, `portfolio`) موقع فعال‌سازی قالب **خودکار** ساخته می‌شوند
  (`kdcv_ensure_required_pages()`); دستی نسازید.
- **`videos.html`**: این صفحه تمپلیت وردپرس ندارد چون ~۴۰ مگابایت مدیای آن (`assets/media/`) عمداً داخل قالب نمی‌رود.
  لینک ویدیوها در قالب به `home_url('/videos.html')` اشاره می‌کند، پس برای کار کردن آن باید `videos.html` و پوشه‌ی
  `assets/media/` با FTP کنار `wp-config.php` (ریشه‌ی هاست) آپلود شوند. وردپرس فایل‌های واقعی را مستقیم سرو می‌کند و روت نمی‌کند.

## قبل از آپلود

- [ ] **کلید Web3Forms**: مقدار `YOUR_ACCESS_KEY_HERE` باید در هر ۸ فایل زبانی جایگزین شده باشد.
      بررسی: `grep -l "YOUR_ACCESS_KEY_HERE" *.html` نباید چیزی برگرداند.
- [ ] **کلید z.ai (AI Pet)**: کلید API هرگز در repo ذخیره نمی‌شود. در `wp-config.php` روی سرور (بالای `/* That's all, stop editing! */`) این خط اضافه شود:
      ```php
      define( 'ZAI_API_KEY', 'YOUR_ZAI_KEY_HERE' );
      ```
      بدون این کلید، ویدژت هوش مصنوعی به‌طور خودکار به keyword-search فعلی برمی‌گردد و صفحه‌ی admin پیام «not configured» نشان می‌دهد.
      مدل پیش‌فرض `glm-4.6` است؛ برای تغییر: `define( 'KDCV_ASK_MODEL_OVERRIDE', 'glm-4.5' );`
- [ ] تست محلی: `./run-dev.sh` (سرور روی http://localhost:8735/ با PHP) و چک صفحه اصلی + فرم + بلاگ. (محیط Docker روی پورت ۸۸۸۸ حذف شده است.)

## پس از اولین activation وردپرس

- [ ] منوی **AI Chat** در wp-admin باز شود؛ جدول `{wp_prefix}_kdcv_pet_chats` باید به‌صورت خودکار توسط `kdcv_ask_install()` ساخته شود (در اولین بار `admin_init` اگر schema option نباشد).
- [ ] یک سؤال نمونه از همان صفحه بپرسید؛ باید در جدول history با status=`ok` ظاهر شود.
- [ ] دکمه‌های **Export CSV** و **Export JSON** یک دانلود واقعی تولید کنند.
- [ ] دکمه‌ی **Clear All** با تأیید، جدول را TRUNCATE کند.

## مدیریت Blog در صفحه اصلی

- منبع اصلی نوشته‌ها `blog/index.html` (در ریشه‌ی پروژه) است؛ هر کارت جدیدی که به آرشیو اضافه شود، در Home هم خوانده می‌شود.
- برای سنجاق‌کردن نوشته‌های منتخب، slug آن‌ها را در `assets/data/home-blog.json` داخل `pinned` قرار دهید.
- اگر فقط نوشته‌های منتخب نمایش داده شوند، مقدار `fillWithLatest` را `false` کنید.
- برای نوشتهٔ تازه، ترجمه‌های عنوان، تاریخ، خلاصه و برچسب را در همان JSON اضافه کنید؛ در غیر این صورت متن منبع فارسی نمایش داده می‌شود.

## SEO/GEO — فایل‌های ریشه که با sync-from-static.py کپی نمی‌شوند

اسکریپت sync فقط `assets/` و تمپلیت‌های صفحه رو mirror می‌کنه. این فایل‌ها روی ریشه‌ی پروژه (سایت استاتیک) هستند و **خودکار به قالب وردپرس منتقل نمی‌شوند**:

- `llms.txt` — روی سایت استاتیک همینجوری آپلود بشه (`https://kohandezh.com/llms.txt`)؛ برای مسیر وردپرس باید دستی روی ریشه‌ی هاست (کنار `wp-config.php`، نه داخل تم) گذاشته بشه.
- `robots.txt`, `sitemap.xml`, `.htaccess` — همینطور؛ اگه پلاگین SEO (Yoast/RankMath) روی وردپرس فعاله ممکنه sitemap خودش رو بسازه، در اون صورت لازم نیست دستی بذاری.
- **`manifest.json`, `sw.js`, `offline.html`** (لایه‌ی PWA) — حتماً باید روی **ریشه‌ی هاست** باشند، نه داخل تم.
  `sw.js` فقط می‌تواند صفحاتی را کنترل کند که در مسیر خودش یا زیرمجموعه‌ی آن باشند؛ اگر داخل `wp-content/themes/...` گذاشته شود
  scope آن به همان پوشه محدود می‌شود و کل سایت آفلاین کار نمی‌کند.
- **`feed.xml`** — خروجی `python3 _tooling/build-feed.py`. روی وردپرس اگر فید داخلی خود WP (`/feed/`) فعال است این فایل لازم نیست؛
  برای سایت استاتیک باید روی ریشه آپلود شود. بعد از افزودن هر نوشته‌ی جدید، اسکریپت را دوباره اجرا کنید.
- **`privacy.html`, `terms.html`** — روی سایت استاتیک مستقیم آپلود می‌شوند. روی وردپرس بهتر است به‌جای فایل، دو برگه (Page)
  با همان محتوا ساخته شود تا از منوی وردپرس قابل ویرایش باشد؛ لینک‌های فوتر در قالب به `privacy.html` / `terms.html` اشاره می‌کنند
  و در آن حالت باید به آدرس برگه‌ها تغییر کنند.

### افزودن نوشته‌ی جدید به وبلاگ (چک‌لیست)

1. فایل `blog/<slug>.html` را بسازید (از یک پست موجود کپی بگیرید تا متاتگ‌ها کامل بمانند).
2. کارت آن را در `blog/index.html` اضافه کنید و شمارنده‌ی hero (`01—NN`) را به‌روز کنید.
3. ترجمه‌ی عنوان/تاریخ/خلاصه/برچسب را در `assets/data/home-blog.json` برای **هر ۹ زبان** اضافه کنید
   (اگر زبانی جا بیفتد، آن زبان بی‌صدا به فارسی برمی‌گردد).
4. آدرس را به `sitemap.xml` اضافه کنید.
5. `python3 _tooling/build-feed.py` را اجرا کنید تا `feed.xml` بازتولید شود.

## چیزهایی که نباید آپلود شوند

- `.gstack/` و `.swarm/` (state ابزارهای توسعه)
- `.DS_Store` (اگر دوباره ساخته شد)
- هیچ فایلی از `_archive/` — مخصوصاً `wp-config.php` که پسورد دیتابیس دارد

## هنگام جایگزینی وردپرس

- فایل `.htaccess` جدید حتماً آپلود شود (ریدایرکت ۳۰۱ پرمالینک‌های قدیمی وردپرس به صفحات استاتیک بلاگ + هدرهای امنیتی + کش).
- `.htaccess` قدیمی وردپرس و پوشه‌های `wp-*` از هاست حذف شوند (اول بکاپ کامل بگیرید).
- بعد از دیپلوی این آدرس‌ها تست شوند:
  - `https://kohandezh.com/` → سایت جدید
  - `https://kohandezh.com/2025/05/11/...` → ریدایرکت به `/blog/generative-ai-tools.html`
  - `https://kohandezh.com/blog/` → ایندکس بلاگ
  - `https://kohandezh.com/Certificates.html` → آرشیو گواهینامه‌ها
  - یک آدرس ساختگی مثل `https://kohandezh.com/this-page-does-not-exist` → 404 واقعی با یکی از بازی‌های تصادفی
  - `https://kohandezh.com/sitemap.xml`
- در Google Search Console سایت‌مپ دوباره submit شود.

## بعد از دیپلوی

- [ ] فرم تماس را واقعی تست کنید و صندوق متصل به کلید Web3Forms را بررسی کنید.
- [ ] هر ۸ زبان را باز کنید.
- [ ] در اولین بازدید، لودر AI نمایش داده شود و در ادامه همان session دوباره مزاحم کاربر نشود.
- [ ] نام و امتیاز بازی 404 در همان مرورگر ذخیره شود (جدول امتیاز فعلی محلی است، نه سراسری).
- [ ] مسیر 404 تصادفی بین چهار بازی Invaders، Breakout، Neural Pulse و Packet Escape جابه‌جا شود.
- [ ] با https://pagespeed.web.dev امتیاز بگیرید.

## تست‌های اختصاصی P0 (ریلیز ۲۰۲۶-۰۸-۰۲)

بعد از آپلود قالب جدید، این موارد حتماً تست شوند:

### locale-router v2 (bot-safe)
```bash
# 1) Googlebot باید روی / بمونه (بدون redirect به /fa/)
curl -sI -A "Googlebot/2.1" https://kohandezh.com/ | head -3
# انتظار: HTTP/2 200 (نه 301/302)

# 2) ?lang=fa باید redirect کنه به fa.html یا /fa/
curl -sI "https://kohandezh.com/?lang=fa" | grep -i location
# انتظار: location: https://kohandezh.com/fa/ (یا fa.html)

# 3) ?lang=en باید روی / بمونه
curl -sI "https://kohandezh.com/?lang=en" | head -3
# انتظار: HTTP/2 200
```

### legacy redirects (۳۰۱)
```bash
# هرکدام باید HTTP/2 301 با Location: https://kohandezh.com/#service بده
for slug in shop cart checkout affiliate-marketing email-marketing \
            resume music connections profile/login search-engine-optimization; do
  printf "%-30s -> " "/$slug/"
  curl -sI "https://kohandezh.com/$slug/" | grep -i "^location" | head -1
done

# Static-only files
curl -sI https://kohandezh.com/Certificates.html | grep -i location  # -> /certificates/
curl -sI https://kohandezh.com/PSN.html          | grep -i location  # -> /psn/
```

### Canonical dedup
```bash
# فقط یک <link rel=canonical> در HTML
curl -s https://kohandezh.com/ | grep -c 'rel="canonical"'
# انتظار: 1
```

### robots.txt با AI bot rules
```bash
curl -s https://kohandezh.com/robots.txt
# انتظار: شامل GPTBot, ClaudeBot, PerplexityBot, … با Allow: /
```

### robots.txt sitemap reference
```bash
curl -s https://kohandezh.com/robots.txt | grep -i sitemap
# انتظار: Sitemap: https://kohandezh.com/sitemap.xml
```

### FAQ آپدیت‌شده
```bash
curl -s https://kohandezh.com/ | grep -o "Over 18 years"
# انتظار: خروجی "Over 18 years"
```

### llms.txt (آپلود دستی)
`llms.txt` در repo هست ولی WordPress به‌صورت پیش‌فرض آن را سرو نمی‌کنه. یکی از این دو راه:
1. از wp-admin ← Pages ← Add New یک page با slug `llms.txt` بسازید و محتوای فایل را در آن قرار دهید (به‌صورت text/plain).
2. یا فایل `llms.txt` را با FTP در ریشه‌ی هاست کنار `wp-config.php` آپلود کنید (سریع‌تر، بدون rewrite).

بعد از تست:
- [ ] در Google Search Console → Coverage، تعداد صفحات "Excluded by ‘noindex’ tag" و "Page with redirect" باید در چند هفته آینده بره بالا (یعنی legacyها در حال حذف از ایندکس هستن).
- [ ] در Search Console → Sitemaps، `sitemap.xml` را دوباره submit کنید.
- [ ] یک هفته بعد، در Google Analytics یا Search Console، query "Mohammad Ali Kohandezh" را چک کنید تا مطمئن بشید صفحات قدیمی از نتایج حذف شدن.

## تست‌های اختصاصی P1 (Geo-IP routing)

این مهم‌ترین بخش تسته. بعد از deploy باید با IPهای مختلف (یا VPN/proxy) رفتن به سایت را تست کنید:

### با VPN به کشورهای مختلف:
```bash
# 1) IP ایران (با VPN ایران، یا از یک Iranian proxy)
# انتظار: redirect به https://kohandezh.com/fa/
curl -sI https://kohandezh.com/ -H 'X-Forwarded-For: 5.1.1.1' | grep -i location

# 2) IP آلمان
# انتظار: redirect به https://kohandezh.com/de/
curl -sI https://kohandezh.com/ -H 'X-Forwarded-For: 9.9.9.9' | grep -i location

# 3) IP فرانسه
# انتظار: redirect به https://kohandezh.com/fr/
curl -sI https://kohandezh.com/ -H 'X-Forwarded-For: 2.2.2.2' | grep -i location

# 4) IP آمریکا
# انتظار: STAY on https://kohandezh.com/ (English canonical)
curl -sI https://kohandezh.com/ -H 'X-Forwarded-For: 3.3.3.3' | head -3
```

توجه: هدر `X-Forwarded-For` ممکن است روی هاست شما پذیرفته نشه (امنیت). راه بهتر: واقعاً با VPN/test proxy از کشورهای مختلف تست کنید.

### Bot-safe:
```bash
# Googlebot از IP آمریکا نباید redirect بشه (هم English canonical)
curl -sI -A "Googlebot/2.1" https://kohandezh.com/ | head -3
# انتظار: HTTP/2 200 (نه 302)

# GPTBot
curl -sI -A "GPTBot/1.0" https://kohandezh.com/ | head -3
# انتظار: HTTP/2 200
```

### Cookie-based override:
1. با IP ایران به سایت برید → باید به `/fa/` redirect بشید
2. در URL بنویسید `https://kohandezh.com/?lang=en` → باید روی `/` بمونید و cookie ست بشه
3. دوباره به `https://kohandezh.com/` برید → cookie باعث می‌شه روی `/` بمونید (نه `/fa/`)

### Tap target (mobile):
1. در DevTools موبایل (375px) سایت را باز کنید
2. هر آیتم منوی موبایل باید حداقل 44×44px باشه (Apple HIG)
3. تأیید: `document.querySelectorAll('.nav-mobile-list .item-link').forEach(a => console.log(a.getBoundingClientRect()))`

### Performance savings:
- قبل از P1: ۱۹ فایل CSS + ۳۳ فایل JS بارگذاری می‌شد
- بعد از P1: **۱۳۳KB کمتر** (۲۱٪ JS + ۲۲٪ CSS savings از minification بهتر)
- می‌توانید با PageSpeed Insights مقایسه کنید

## نکات مهم Geo-IP

**اولین بار کند است، دفعات بعد سریع:**
- اولین بازدید هر IP → ~1-2s کندتر (به‌خاطر ip-api.com lookup)
- دفعات بعد → transient cache (24h per IP) → instant
- بعد از geo-redirect، cookie ۳۰ روزه ست می‌شه → دفعات بعدی هیچ API call ای نیست

**بهترین حالت (پیشنهاد):**
اگر Cloudflare را در front بذارید (رایگان، 5 دقیقه setup)، هدر `CF-IPCountry` خودکار ست می‌شه و هیچ API call ای لازم نیست. کد به‌صورت خودکار تشخیص می‌ده و ازش استفاده می‌کنه.

**اگر ip-api.com از ایران block بود:**
- سرور شما در ایران هست. اگر ip-api.com از ایران قابل دسترسی نباشد، geo-redirect کار نمی‌کنه و همه روی English می‌مونن.
- راه حل: Cloudflare در front بذارید (توصیه می‌شه) یا از MaxMind GeoLite2 PHP extension استفاده کنید.

## تست‌های اختصاصی P2 (Semantic structure + schemas)

### Heading hierarchy:
```bash
# باید ۷ تا H2 داشته باشه (قبلاً ۱ تا بود)
curl -s https://kohandezh.com/ | grep -oE '<h[1-6]' | sort | uniq -c
# انتظار: 1 H1, 7 H2, 8+ H3, 0 H4 (در محتوای اصلی)
```

### Schema validation:
بعد از deploy، این URL را در Google Rich Results Test بزنید:
- https://search.google.com/test/rich-results?url=https://kohandezh.com/

انتظار:
- ✅ Person (Mohammad Ali Kohandezh)
- ✅ FAQPage (۵ سؤال)
- ✅ Organization (KSF) — جدید
- ✅ SoftwareApplication × ۳ (پادیار، همایار، نت‌یار) — جدید
- ✅ ProfessionalService — جدید
- ✅ WebSite — جدید

همچنین Schema.org Validator: https://validator.schema.org/

### Entity graph (برای Perplexity / ChatGPT / Google AI Overviews):
بعد از ۲-۴ هفته، این queryها را در Perplexity/ChatGPT تست کنید:
- "Who is the CEO of Kohan System Farda?" → باید Mohammad Ali Kohandezh را برگردونه
- "What products does KSF make?" → باید پادیار، همایار، نت‌یار را list کنه
- "Tell me about Padyar AI Avatar Platform" → باید توضیحات کامل + link بده

این نشانه‌ی اینه که entity graph به‌خوبی indexed شده.
