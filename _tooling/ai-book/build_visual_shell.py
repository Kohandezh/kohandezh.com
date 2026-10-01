#!/usr/bin/env python3
"""Build the local, design-frozen Persian AI Book visual shell.

This is intentionally a static presentation build. It does not import, mutate, or
republish the canonical book. Functional adapters replace representative blocks
after the visual system is frozen.
"""

from pathlib import Path
from html import escape
from shutil import copyfile

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / "books"

ROUTES = {
    "index": ("", "خانه کتاب", "مرجع فارسی حاکمیت، امنیت و مدیریت ریسک هوش مصنوعی"),
    "read": ("read", "مطالعه آنلاین", "مبانی و زبان مشترک هوش مصنوعی"),
    "search": ("search", "جست‌وجو", "جست‌وجوی دقیق در متن و شواهد کتاب"),
    "ask": ("ask", "از کتاب بپرس", "پاسخ فقط با شاهد و پیوند مستقیم به کتاب"),
    "concepts": ("concepts", "مفاهیم", "واژگان و روابط کلیدی کتاب"),
    "graph": ("graph", "نقشه دانش", "مسیر دیداری میان مفهوم، منبع و بخش"),
    "templates": ("templates", "الگوها", "فرم‌های اجرایی برگرفته از کتاب"),
    "sources": ("sources", "منابع", "۱۸ سند مرجع با نگاشت به محتوای فارسی"),
    "glossary": ("glossary", "واژه‌نامه", "اصطلاحات مصوب فارسی و معادل انگلیسی"),
    "pdf": ("pdf", "نسخه PDF", "مطالعهٔ کنترل‌شدهٔ نسخهٔ 2026E1"),
    "request-pdf": ("request-pdf", "درخواست PDF", "ثبت درخواست نسخهٔ شخصی‌سازی‌شده"),
    "cite": ("cite", "استناد", "ساخت استناد برای کتاب، بخش یا قطعه"),
    "verify": ("verify", "اعتبارسنجی", "بررسی شناسه، اثر انگشت و ریشهٔ مرکل"),
}

NAV = [("read", "مطالعه"), ("search", "جست‌وجو"), ("ask", "پرسش"), ("concepts", "مفاهیم")]


def url(current: str, target: str = "") -> str:
    if current == "index":
        return "./" if not target else f"{target}/"
    return "../" if not target else f"../{target}/"


def icon(name: str) -> str:
    icons = {
        "book": '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H11v15H6.5A2.5 2.5 0 0 0 4 20.5zM20 5.5A2.5 2.5 0 0 0 17.5 3H13v15h4.5a2.5 2.5 0 0 1 2.5 2.5z"/>',
        "arrow": '<path d="m9 18 6-6-6-6"/>',
        "search": '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        "quote": '<path d="M8 10H4a4 4 0 0 0 4 4v4H4v-4a8 8 0 0 1 8-8v4m8 0h-4a4 4 0 0 0 4 4v4h-4v-4a8 8 0 0 1 8-8v4"/>',
    }
    return f'<svg aria-hidden="true" viewBox="0 0 24 24">{icons.get(name, icons["arrow"])}</svg>'


def card(title: str, text: str, href: str = "#", meta: str = "") -> str:
    return f'<a class="ab-card" href="{href}"><span class="ab-card-meta">{meta}</span><h3>{title}</h3><p>{text}</p><span class="ab-card-link">مشاهده {icon("arrow")}</span></a>'


def page_header(key: str) -> str:
    _, label, _ = ROUTES[key]
    nav = "".join(
        f'<a href="{url(key, route)}" {"aria-current=page" if key == route else ""}>{text}</a>'
        for route, text in NAV
    )
    return f'''<a class="ab-skip" href="#main">رفتن به محتوای اصلی</a>
<header class="ab-header">
  <a class="ab-brand" href="{url(key)}" aria-label="خانهٔ کتاب هوش مصنوعی کهن‌دژ"><span class="ab-brand-mark">ک</span><span><b>کهن‌دژ</b><small>کتاب مرجع هوش مصنوعی</small></span></a>
  <nav class="ab-primary" aria-label="ناوبری اصلی کتاب">{nav}</nav>
  <div class="ab-tools"><a href="{url(key, 'sources')}">منابع</a><a href="{url(key, 'verify')}">اعتبارسنجی</a><button class="ab-menu-button" type="button" aria-expanded="false" aria-controls="mobile-nav">فهرست</button></div>
</header>
<nav id="mobile-nav" class="ab-mobile-nav" aria-label="فهرست همراه" hidden>{nav}<a href="{url(key, 'graph')}">نقشه دانش</a><a href="{url(key, 'pdf')}">PDF</a></nav>'''


def footer(key: str) -> str:
    return f'''<footer class="ab-footer"><div><b>راهنمای جامع حاکمیت، امنیت و مدیریت ریسک هوش مصنوعی</b><p>ترجمه، تدوین و بازآرایی فارسی: محمدعلی کهن‌دژ · نسخهٔ 2026E1</p></div><nav aria-label="پیوندهای انتهایی"><a href="{url(key, 'cite')}">استناد</a><a href="{url(key, 'verify')}">اعتبارسنجی</a><a href="{url(key, 'request-pdf')}">درخواست PDF</a></nav><p class="ab-disclaimer">این اثر ترجمهٔ رسمی NIST نیست و NIST آن را تأیید نکرده است.</p></footer>'''


def hero(kicker: str, title: str, lead: str, actions: str = "") -> str:
    return f'<header class="ab-page-hero"><p class="ab-kicker">{kicker}</p><h1>{title}</h1><p class="ab-lead">{lead}</p>{actions}</header>'


def home(key: str) -> str:
    parts = [
        ("P01", "مبانی و زبان مشترک", "۶ فصل"), ("P02", "مدیریت ریسک هوش مصنوعی", "۱۰ فصل"),
        ("P03", "امنیت هوش مصنوعی", "۱۱ فصل"), ("P04", "اعتماد، سوگیری و تبیین‌پذیری", "۷ فصل"),
        ("P05", "هوش مصنوعی مولد و ارزیابی", "۷ فصل"), ("P06", "هوش مصنوعی و حقوق", "۷ فصل"),
        ("P07", "استانداردها و نقشهٔ راه", "۵ فصل"),
    ]
    part_cards = "".join(card(t, f"{n} · بخش {pid}", url(key, "read"), pid) for pid, t, n in parts)
    return f'''{hero("نسخهٔ فارسی · 2026E1", "دانش فنی، با مسیر روشن تا منبع", "یک کتاب مرجع زنده برای فهم، تصمیم‌گیری و اجرای مسئولانهٔ هوش مصنوعی؛ ۴۷۹ بخش قابل استناد بر پایهٔ ۱۸ سند NIST و AAAS.", f'<div class="ab-actions"><a class="ab-button ab-button-primary" href="{url(key,"read")}">مطالعه آنلاین</a><a class="ab-button" href="{url(key,"search")}">جست‌وجو در کتاب</a><a class="ab-button" href="{url(key,"ask")}">از کتاب بپرس</a><a class="ab-button" href="{url(key,"request-pdf")}">درخواست PDF</a></div>')}
<section class="ab-stat-grid" aria-label="مشخصات نسخه"><div><b>۷</b><span>بخش</span></div><div><b>۵۳</b><span>فصل</span></div><div><b>۴۷۹</b><span>بخش محتوایی</span></div><div><b>۱۸</b><span>منبع اصلی</span></div></section>{shelf(key, "../assets/images/books/")}
<section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker">ساختار کتاب</p><h2>از زبان مشترک تا اقدام سازمانی</h2></div><a href="{url(key,'concepts')}">کاوش مفاهیم</a></div><div class="ab-card-grid">{part_cards}</div></section>
<section class="ab-proof"><div><p class="ab-kicker">اعتبار قابل بررسی</p><h2>هر ادعا یک مسیر بازگشت دارد</h2><p>شناسهٔ محتوا، منبع، نسخه و اثر انگشت در کنار متن باقی می‌مانند؛ یادداشت تدوینگر و بومی‌سازی نیز از ترجمهٔ منبع جدا هستند.</p></div><a class="ab-button ab-button-primary" href="{url(key,'verify')}">اعتبارسنجی نسخه</a></section>'''


# The library shelf on the book home. Facts come only from the covers the owner
# supplied (title, subtitle, authors, date); nothing is added to them. The second
# title has no digital edition yet, so it carries a "coming soon" status and no
# link. The WordPress template (kohandezh-knowledge/templates/ai-book.php) mirrors
# this markup -- change both together.
BOOKS = (
    ("ai-governance-cover", "جلد کتاب AI Governance — حاکمیت هوش مصنوعی، نوشتهٔ محمدعلی کهن‌دژ",
     "live", "نسخهٔ آنلاین در دسترس", "حاکمیت هوش مصنوعی",
     "راهنمای جامع حاکمیت، امنیت و مدیریت ریسک هوش مصنوعی", "محمدعلی کهن‌دژ", "", "read"),
    ("ics-cybersecurity-cover", "جلد کتاب امنیت سایبری در سیستم‌های اتوماسیون صنعتی",
     "soon", "انتشار دیجیتال به‌زودی", "امنیت سایبری در سیستم‌های اتوماسیون صنعتی",
     "راهنمای جامع بر اساس استانداردهای IEC 62443", "محمدعلی کهن‌دژ، روزبه بابازاده، نیلوفر کریمی آذر · تابستان ۱۴۰۴",
     "نسخهٔ دیجیتال این کتاب به‌زودی در همین بخش منتشر می‌شود.", ""),
)


def shelf(key: str, img: str) -> str:
    books = []
    for stem, alt, state, badge, title, sub, meta, note, target in BOOKS:
        action = f'<a class="ab-button ab-button-primary" href="{url(key, target)}">مطالعه آنلاین</a>' if target else ""
        note_html = f'<p class="ab-book-note">{note}</p>' if note else ""
        books.append(
            f'<article class="ab-book"><img class="ab-book-cover" src="{img}{stem}-w320.webp" '
            f'srcset="{img}{stem}-w320.webp 320w, {img}{stem}-w640.webp 640w" sizes="(max-width: 420px) 110px, 200px" '
            f'width="320" height="512" alt="{alt}" loading="lazy" decoding="async">'
            f'<div class="ab-book-body"><span class="ab-book-status ab-book-status-{state}">{badge}</span>'
            f'<h3>{title}</h3><p class="ab-book-sub">{sub}</p><p class="ab-book-meta">{meta}</p>{note_html}{action}</div></article>'
        )
    return ('<section class="ab-section ab-shelf" aria-labelledby="ab-shelf-title"><div class="ab-section-head"><div>'
            '<p class="ab-kicker">کتابخانه</p><h2 id="ab-shelf-title">کتاب‌های کهن‌دژ</h2></div></div>'
            f'<div class="ab-shelf-grid">{"".join(books)}</div></section>')


def read(key: str) -> str:
    return f'''<div class="ab-reader">
<aside class="ab-toc" aria-label="فهرست کتاب"><p class="ab-kicker">بخش نخست</p><h2>مبانی و زبان مشترک</h2><a class="is-current" href="#s1">دامنه و اسناد پشتوانه</a><a href="#s2">ترتیب پیشنهادی مطالعه</a><a href="#s3">راهنمای مخاطبان</a></aside>
<article class="ab-prose"><nav class="ab-breadcrumb" aria-label="مسیر صفحه"><a href="{url(key)}">کتاب</a><span>/</span><span>P01</span><span>/</span><span>فصل نخست</span></nav>
{hero("P01 · فصل ۱ · جمع‌بندی تدوینگر", "مبانی و زبان مشترک هوش مصنوعی", "این بخش شالودهٔ مفهومی و واژگانی کتاب را بر پایهٔ NIST AI 100-3 و سند آموزشی AAAS–NIST می‌سازد.")}
<dl class="ab-meta"><div><dt>شناسه محتوا</dt><dd><bdi>KDJ-AI-2026E1-P01-C01-S01-04D3A306</bdi></dd></div><div><dt>منابع</dt><dd><bdi>NIST-AI-100-3</bdi> · <bdi>NIST-PAPER-1</bdi></dd></div><div><dt>نسخه</dt><dd><bdi>2026E1</bdi></dd></div></dl>
<section id="s1"><h2>دامنهٔ بخش و اسناد پشتوانهٔ آن</h2><div class="ab-layer ab-layer-editorial"><span>جمع‌بندی تدوینگر</span><p>بخش نخست شالودهٔ مفهومی و واژگانی کل کتاب را می‌سازد و بر دو سند استوار است: سند آموزشی «مسائل بنیادین و واژه‌نامه» و واژه‌نامهٔ ژرف NIST دربارهٔ زبان هوش مصنوعی اعتمادپذیر.</p></div><p>این دو منبع مکمل یکدیگرند. یکی زبان در دسترس برای فهم چرخهٔ حیات و محدودیت‌های سامانه ارائه می‌کند و دیگری تعریف‌های دقیق را در اختیار خواننده می‌گذارد.</p></section>
<section id="s2"><h2>ترتیب پیشنهادی مطالعه</h2><div class="ab-layer ab-layer-source"><span>ترجمهٔ منبع</span><p>سامانهٔ هوش مصنوعی تنها یک مدل نیست؛ داده، زمینهٔ استفاده، انسان و فرایندهای پیرامون آن در عملکرد و ریسک نهایی نقش دارند.</p></div><div class="ab-layer ab-layer-local"><span>کاربرد در سازمان‌های ایرانی</span><p>پیش از خرید یا استقرار، شناسنامهٔ دارایی هوش مصنوعی را با مالک کسب‌وکار، منبع داده، محدودیت استفاده و مسیر ارجاع خطا تکمیل کنید.</p></div></section>
<section id="s3"><h2>راهنمای مخاطبان</h2><div class="ab-table-wrap" tabindex="0" role="region" aria-label="راهنمای مطالعه برای نقش‌های سازمانی"><table><thead><tr><th>مخاطب</th><th>تمرکز اصلی</th><th>بخش‌های کلیدی</th></tr></thead><tbody><tr><td>مدیران</td><td>تصویر کلان برای تصمیم‌گیری</td><td>تعریف AI، عملکرد، نتیجه‌گیری</td></tr><tr><td>تیم فنی</td><td>چرخهٔ حیات و محدودیت‌ها</td><td>طراحی، استقرار و پایش</td></tr><tr><td>ممیزان</td><td>زبان دقیق و نقاط ضعف</td><td>عدم قطعیت، سوگیری و امنیت</td></tr></tbody></table></div></section>
<nav class="ab-prev-next" aria-label="پیمایش فصل"><a href="#">فصل پیشین</a><a href="#">فصل بعدی: زبان هوش مصنوعی اعتمادپذیر</a></nav></article>
<aside class="ab-related" aria-label="دانش مرتبط"><p class="ab-kicker">دانش مرتبط</p>{card("ریسک", "معیار مرکب احتمال و پیامد", url(key,"glossary"), "RISK")}{card("نقشهٔ مفهومی", "پیوند این فصل با چارچوب AI RMF", url(key,"graph"), "۳۷ پیوند")}</aside></div>'''


def search_page(key: str) -> str:
    results = "".join([
        card("دامنهٔ بخش و اسناد پشتوانهٔ آن", "بخش نخست شالودهٔ مفهومی و واژگانی کل کتاب را می‌سازد…", url(key,"read")+"#s1", "P01 · NIST AI 100-3"),
        card("مدیریت ریسک هوش مصنوعی", "چارچوب AI RMF برای مدیریت ریسک در سراسر چرخهٔ حیات…", url(key,"read"), "P02 · NIST AI 100-1"),
        card("ریسک", "ریسک معیاری مرکب از احتمال وقوع و اندازهٔ پیامد است.", url(key,"glossary"), "واژه‌نامه · RISK"),
    ])
    return f'''{hero("جست‌وجوی فارسی و انگلیسی", "در کتاب چه می‌خواهید پیدا کنید؟", "عنوان، متن، مفهوم، واژه‌نامه، نام منبع، سرواژه و شناسهٔ محتوا قابل جست‌وجو است.")}
<form class="ab-search" role="search"><label for="q">عبارت جست‌وجو</label><div><input id="q" name="q" value="ریسک" autocomplete="off"><button class="ab-button ab-button-primary" type="submit">جست‌وجو</button></div><fieldset><legend>محدوده</legend><label><input type="checkbox" checked> متن کتاب</label><label><input type="checkbox" checked> واژه‌نامه</label><label><input type="checkbox"> منابع</label></fieldset></form>
<section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker">۳ نتیجهٔ نماینده</p><h2>نتایج برای «ریسک»</h2></div><button class="ab-filter-button" type="button">فیلتر نتایج</button></div><div class="ab-results">{results}</div></section>'''


def ask_page(key: str) -> str:
    return f'''{hero("RAG مبتنی بر شواهد", "پرسش خود را از کتاب بپرسید", "این صفحه فقط پاسخ‌هایی را می‌پذیرد که به بخش‌های canonical و منابع پشتیبان پیوند داشته باشند.")}
<div class="ab-chat"><div class="ab-chat-log" aria-live="polite"><div class="ab-message ab-message-user"><b>شما</b><p>برای شروع مدیریت ریسک هوش مصنوعی در سازمان چه اقدام‌هایی اولویت دارند؟</p></div><div class="ab-message ab-message-answer"><b>پاسخ نماینده · اتصال مدل لازم است</b><p>رابط بازیابی و قرارداد citation طراحی شده است. پاسخ runtime تا پیکربندی provider نمایش داده نمی‌شود تا محتوای بدون منبع تولید نشود.</p><div class="ab-citations">{card("بخش P02", "چارچوب مدیریت ریسک هوش مصنوعی", url(key,"read"), "KDJ-AI-2026E1")}{card("منبع اصلی", "NIST AI Risk Management Framework", url(key,"sources"), "NIST-AI-100-1")}</div></div></div><form class="ab-ask-form"><label for="ask">پرسش</label><textarea id="ask" rows="3" placeholder="پرسش را دقیق و بدون اطلاعات محرمانه بنویسید"></textarea><div><span>پاسخ بدون شاهد ارائه نمی‌شود.</span><button class="ab-button ab-button-primary" type="button" disabled>نیازمند پیکربندی</button></div></form></div>'''


def concepts_page(key: str) -> str:
    items = [
        ("ARTIFICIAL_INTELLIGENCE", "هوش مصنوعی", "Artificial Intelligence · AI"), ("RISK", "ریسک", "Risk"),
        ("MACHINE_LEARNING", "یادگیری ماشین", "Machine Learning · ML"), ("PRIVACY", "حریم خصوصی", "Privacy"),
        ("SECURITY", "امنیت", "Security"), ("EXPLAINABILITY", "تبیین‌پذیری", "Explainability"),
    ]
    cards = "".join(card(fa, en, url(key,"glossary"), cid) for cid, fa, en in items)
    return f'''{hero("کاوشگر مفهوم", "از اصطلاح تا شاهد", "هر مفهوم به تعریف مصوب، بخش‌های کتاب، منابع و روابط دانشی خود متصل می‌شود.")}<section class="ab-section"><div class="ab-toolbar"><label>جست‌وجوی مفهوم<input placeholder="نام فارسی، انگلیسی یا شناسه"></label><label>حوزه<select><option>همهٔ حوزه‌ها</option><option>ریسک</option><option>امنیت</option><option>حقوق</option></select></label></div><div class="ab-card-grid">{cards}</div></section>'''


def graph_page(key: str) -> str:
    return f'''{hero("۳٬۶۷۱ گره · ۱۰٬۴۷۴ یال", "نقشهٔ دانش کتاب", "نمای دیداری برای کشف رابطه‌ها؛ فهرست ساختاریافته همواره جایگزین دسترس‌پذیر آن است.")}
<div class="ab-graph-layout"><section class="ab-graph-canvas" aria-label="نمای نمایندهٔ نقشهٔ دانش"><div class="ab-node ab-node-main">مدیریت ریسک</div><div class="ab-node n1">AI RMF</div><div class="ab-node n2">حاکمیت</div><div class="ab-node n3">ارزیابی</div><div class="ab-node n4">NIST AI 100-1</div><svg aria-hidden="true" viewBox="0 0 800 480"><path d="M400 240 180 120M400 240 620 110M400 240 180 370M400 240 640 360"/></svg><div class="ab-graph-controls"><button type="button" aria-label="بزرگ‌نمایی">+</button><button type="button" aria-label="کوچک‌نمایی">−</button><button type="button">بازنشانی</button></div></section><aside class="ab-detail"><p class="ab-kicker">مفهوم انتخاب‌شده</p><h2>مدیریت ریسک هوش مصنوعی</h2><p>فرایندی پیوسته برای نگاشت، سنجش، مدیریت و حاکمیت ریسک‌های سامانهٔ هوش مصنوعی.</p><dl><dt>بخش‌های مرتبط</dt><dd>۴۲</dd><dt>منابع</dt><dd>۶</dd><dt>شناسه</dt><dd><bdi>AI_RISK_MANAGEMENT</bdi></dd></dl><a class="ab-button" href="{url(key,'read')}">مطالعه در کتاب</a></aside></div>
<details class="ab-fallback"><summary>نمای فهرستی دسترس‌پذیر</summary><ul><li>مدیریت ریسک ← چارچوب AI RMF</li><li>مدیریت ریسک ← حاکمیت</li><li>مدیریت ریسک ← ارزیابی</li><li>مفهوم ← منبع NIST AI 100-1</li></ul></details>'''


def simple_cards(key: str, kind: str) -> str:
    data = {
        "templates": ("الگوهای اجرایی", "ابزارهایی برای تبدیل فصل‌ها به کار سازمانی", [("فهرست دارایی‌ها و موارد کاربرد هوش مصنوعی", "TPL-P01-1"), ("فرم ارزیابی اولیهٔ ریسک", "TPL-P02-1"), ("ثبت شواهد و تصمیم‌ها", "TPL-P02-4")]),
        "sources": ("منابع و نقشهٔ شواهد", "۱۸ سند اصلی بدون جایگزینی یا به‌روزرسانی خاموش", [("NIST AI Risk Management Framework", "NIST-AI-100-1"), ("The Language of Trustworthy AI", "NIST-AI-100-3"), ("Secure Software Development Practices for Generative AI", "NIST-SP-800-218A")]),
        "glossary": ("واژه‌نامهٔ مصوب", "تعریف فارسی، معادل انگلیسی و مسیر بازگشت به منبع", [("ریسک", "Risk · RISK"), ("تبیین‌پذیری", "Explainability · EXPLAINABILITY"), ("یادگیری ماشین", "Machine Learning · ML")]),
    }
    title, lead, items = data[kind]
    cards = "".join(card(t, "مشاهدهٔ جزئیات، بخش‌های مرتبط و منشأ", "#", m) for t, m in items)
    return f'''{hero("دانش ساختاریافته", title, lead)}<section class="ab-section"><div class="ab-toolbar"><label>جست‌وجو<input placeholder="عنوان، شناسه یا اصطلاح"></label><label>مرتب‌سازی<select><option>ترتیب کتاب</option><option>الفبایی</option></select></label></div><div class="ab-card-grid">{cards}</div></section>'''


def pdf_page(key: str) -> str:
    return f'''{hero("نسخهٔ 2026E1 · ۱٬۲۹۲ صفحه", "خواندن نسخهٔ PDF", "نمایشگر اختصاصی بعد از فعال‌سازی Phase B به PDF canonical متصل می‌شود؛ این shell هیچ فایل سنگینی را در صفحهٔ عادی بار نمی‌کند.")}
<div class="ab-pdf"><div class="ab-pdf-toolbar"><button type="button" disabled>صفحهٔ قبل</button><label>صفحه <input value="1" inputmode="numeric" disabled> از ۱٬۲۹۲</label><button type="button" disabled>صفحهٔ بعد</button><span></span><button type="button" disabled>−</button><b>۱۰۰٪</b><button type="button" disabled>+</button></div><div class="ab-pdf-stage"><div class="ab-paper"><p class="ab-kicker">راهنمای جامع</p><h2>حاکمیت، امنیت و مدیریت ریسک هوش مصنوعی</h2><p>نسخهٔ فارسی · 2026E1</p><span>پیش‌نمایش shell — PDF.js در Phase B</span></div></div><aside><h2>دربارهٔ نسخه</h2><dl><dt>ویرایش</dt><dd>2026E1</dd><dt>وضعیت امضا</dt><dd>امضای توسعه؛ امضای ناشر لازم است</dd><dt>ریشهٔ مرکل</dt><dd><bdi>2b00d364…c350c2c</bdi></dd></dl><a class="ab-button ab-button-primary" href="{url(key,'request-pdf')}">درخواست نسخهٔ شخصی</a></aside></div>'''


def request_pdf(key: str) -> str:
    fields = [("first","نام","text"),("last","نام خانوادگی","text"),("org","سازمان","text"),("role","سمت","text"),("industry","صنعت","text"),("email","ایمیل","email"),("mobile","موبایل","tel")]
    inputs = "".join(f'<label for="{i}">{l}<input id="{i}" name="{i}" type="{t}" autocomplete="{("given-name" if i=="first" else "family-name" if i=="last" else "organization" if i=="org" else "email" if i=="email" else "tel" if i=="mobile" else "off")}"></label>' for i,l,t in fields)
    return f'''{hero("نسخهٔ شخصی‌سازی‌شده", "درخواست PDF کتاب", "پس از بررسی و تأیید، مشتق شخصی‌سازی‌شده بدون تغییر در PDF canonical صادر می‌شود.")}
<div class="ab-form-layout"><form class="ab-request-form"><div class="ab-form-grid">{inputs}<label class="ab-span-2" for="reason">دلیل درخواست<textarea id="reason" rows="4"></textarea></label><label class="ab-span-2" for="use">نوع استفاده<select id="use"><option>مطالعهٔ شخصی</option><option>آموزش سازمانی</option><option>پژوهش و استناد</option></select></label></div><label class="ab-consent"><input type="checkbox"> اطلاعات بالا برای بررسی و صدور نسخه ثبت شود.</label><button class="ab-button ab-button-primary" type="button">ثبت درخواست نمایشی</button></form><aside class="ab-process"><h2>مسیر درخواست</h2><ol><li class="is-active">ثبت درخواست</li><li>اعتبارسنجی تماس</li><li>بررسی</li><li>تأیید و شخصی‌سازی</li><li>صدور قابل رهگیری</li></ol><p>هیچ درخواست واقعی در این مرحله ارسال نمی‌شود.</p></aside></div>'''


def cite_page(key: str) -> str:
    return f'''{hero("استناد پایدار", "ساخت استناد به کتاب", "سطح استناد را انتخاب کنید؛ DOI یا ISBN ساخته نمی‌شود.")}<div class="ab-cite"><form><label>سطح استناد<select><option>کل کتاب</option><option>بخش</option><option>فصل</option><option>قطعهٔ محتوا</option></select></label><label>شناسهٔ محتوا<input value="KDJ-AI-2026E1-P01-C01-S01-04D3A306" dir="ltr"></label><button class="ab-button ab-button-primary" type="button">ساخت استناد</button></form><section><p class="ab-kicker">استناد فارسی</p><blockquote>کهن‌دژ، محمدعلی. «مبانی و زبان مشترک هوش مصنوعی». در راهنمای جامع حاکمیت، امنیت و مدیریت ریسک هوش مصنوعی، نسخهٔ 2026E1.</blockquote><div class="ab-code"><code dir="ltr">https://kohandezh.com/fa/ai-book/foundations/foundations-reading-guide/#KDJ-AI-2026E1-P01-C01-S01</code><button type="button">کپی</button></div></section></div>'''


def verify_page(key: str) -> str:
    return f'''{hero("Provenance · kdj-hash-1", "اعتبارسنجی نسخه و محتوا", "شناسهٔ محتوا یا اثر انگشت را وارد کنید تا جایگاه آن در نسخهٔ منتشرشده بررسی شود.")}<form class="ab-verify-form"><label for="verify-id">شناسه یا SHA-256</label><div><input id="verify-id" dir="ltr" value="KDJ-AI-2026E1-P01-C01-S01-04D3A306"><button type="button" class="ab-button ab-button-primary">بررسی</button></div></form><section class="ab-verification"><div class="ab-status"><span>نمونهٔ معتبر در manifest محلی</span><b>VERIFIED</b></div><dl><div><dt>ویرایش</dt><dd><bdi>2026E1</bdi></dd></div><div><dt>شناسهٔ ساختاری</dt><dd><bdi>KDJ-AI-2026E1-P01-C01-S01</bdi></dd></div><div><dt>ریشهٔ مرکل</dt><dd><bdi>2b00d364f4f9a3b2f2daee8d2a412479b15828e84d8ba50672fd35ee4c350c2c</bdi></dd></div><div><dt>وضعیت امضا</dt><dd>امضای توسعه؛ کلید امضای ناشر هنوز لازم است</dd></div></dl></section>'''


def content(key: str) -> str:
    if key == "index": return home(key)
    if key == "read": return read(key)
    if key == "search": return search_page(key)
    if key == "ask": return ask_page(key)
    if key == "concepts": return concepts_page(key)
    if key == "graph": return graph_page(key)
    if key in {"templates", "sources", "glossary"}: return simple_cards(key, key)
    if key == "pdf": return pdf_page(key)
    if key == "request-pdf": return request_pdf(key)
    if key == "cite": return cite_page(key)
    if key == "verify": return verify_page(key)
    raise KeyError(key)


# The MK "Waiting" loader, emitted verbatim so _tooling/waiting/build.py --check
# finds these generated pages already current (regenerating must not strip it).
WAITING = (ROOT / "_tooling/waiting/waiting.partial.html").read_text(encoding="utf-8").strip()


def render(key: str) -> str:
    slug, label, description = ROUTES[key]
    prefix = "../" if key == "index" else "../../"
    canonical = "https://kohandezh.com/books/" + (f"{slug}/" if slug else "")
    return f'''<!doctype html>
<html lang="fa" dir="rtl" data-ab-page="{escape(key)}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="{escape(description)}"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#080b0d"><title>{escape(label)} — کتاب هوش مصنوعی کهن‌دژ</title><link rel="canonical" href="{canonical}"><link rel="stylesheet" href="{prefix}assets/fonts/estedad/estedad.css"><link rel="stylesheet" href="{prefix}assets/fonts/inter/inter.css"><link rel="stylesheet" href="{prefix}assets/css/ai-book.css?v=2026092901"></head>
<body>
{WAITING}
{page_header(key)}<main id="main" class="ab-main" tabindex="-1">{content(key)}</main>{footer(key)}<script src="{prefix}assets/js/ai-book.js?v=2026091901" defer></script></body></html>'''


def main() -> None:
    for key, (slug, _, _) in ROUTES.items():
        directory = OUT if not slug else OUT / slug
        directory.mkdir(parents=True, exist_ok=True)
        (directory / "index.html").write_text(render(key), encoding="utf-8")
    plugin_assets = ROOT / "_tooling/wp-theme/kohandezh-knowledge/assets"
    plugin_assets.mkdir(parents=True, exist_ok=True)
    copyfile(ROOT / "assets/css/ai-book.css", plugin_assets / "ai-book.css")
    copyfile(ROOT / "assets/js/ai-book.js", plugin_assets / "ai-book.js")
    (plugin_assets / "books").mkdir(exist_ok=True)
    for cover in sorted((ROOT / "assets/images/books").glob("*.webp")):
        copyfile(cover, plugin_assets / "books" / cover.name)
    print(f"Built {len(ROUTES)} AI Book visual routes under {OUT}")


if __name__ == "__main__":
    main()
