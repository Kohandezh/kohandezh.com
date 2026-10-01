<?php
/** Persian AI Book landing/reader template. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$view       = KBK_AI_Book::current_view();
$repository = KBK_AI_Book::repository();
$summary    = $repository ? $repository->summary() : null;
$parts      = $repository ? $repository->parts() : array();
$chapter    = $repository ? $repository->find_chapter( 'P01', 'C01' ) : null;
$page_title = 'home' === $view ? 'کتاب مرجع هوش مصنوعی کهن‌دژ' : 'مطالعه آنلاین کتاب هوش مصنوعی';
$origin_map = array(
	'source_translation'        => array( 'class' => 'ab-layer-source', 'label' => 'ترجمهٔ منبع' ),
	'editorial_synthesis'       => array( 'class' => 'ab-layer-editorial', 'label' => 'جمع‌بندی تدوینگر' ),
	'editorial_localization_ir' => array( 'class' => 'ab-layer-local', 'label' => 'کاربرد در سازمان‌های ایرانی' ),
);
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $page_title ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="kbk-ai-book-page">
<?php wp_body_open(); ?>
<!-- WAITING:BEGIN generated from _tooling/waiting/waiting.partial.html by _tooling/waiting/build.py -- do not edit by hand -->
<style>
#kdcv-waiting{--kw-bg:#ebebeb;--kw-ink:#0a7233;--kw-trk:rgba(10,114,51,.14);position:fixed;inset:0;z-index:2147483000;display:grid;place-items:center;background:var(--kw-bg);animation:kdcvw-h .4s 6s forwards}
#kdcv-waiting.kdcv-waiting-dk,html[data-kdcv-theme=dark] #kdcv-waiting{--kw-bg:#0f0f0f;--kw-ink:#a8ff46;--kw-trk:rgba(240,255,222,.12)}
html[data-kdcv-theme=light] #kdcv-waiting{--kw-bg:#ebebeb;--kw-ink:#0a7233;--kw-trk:rgba(10,114,51,.14)}
.kdcv-waiting-out{opacity:0;visibility:hidden;pointer-events:none;transition:opacity .36s .26s,visibility 0s .62s}
.kdcv-waiting-r.kdcv-waiting-out{transition:opacity .2s,visibility 0s .2s}
#kdcv-waiting svg{width:clamp(96px,24vmin,132px);height:auto;overflow:visible}
.kdcvw-t{stroke:var(--kw-trk)}
.kdcvw-c,.kdcvw-f{stroke:var(--kw-ink)}
.kdcvw-c{stroke-dasharray:487 1542}
.kdcvw-f{stroke-dasharray:2029;stroke-dashoffset:2029;opacity:0}
.kdcv-waiting-out .kdcvw-c{opacity:0;transition:opacity .15s}
.kdcv-waiting-out .kdcvw-f{opacity:1;stroke-dashoffset:0;transition:stroke-dashoffset .36s cubic-bezier(.65,0,.35,1)}
@media (prefers-reduced-motion:no-preference){
#kdcv-waiting svg{animation:kdcvw-in .5s cubic-bezier(.2,.8,.2,1) both}
.kdcvw-c{animation:kdcvw-run 1.4s linear infinite}
.kdcvw-a{animation:kdcvw-p 2.8s ease-in-out infinite}
.kdcv-waiting-r svg{animation:none}
}
@media (prefers-reduced-motion:reduce){.kdcvw-c,.kdcvw-f{display:none}}
@keyframes kdcvw-h{from{pointer-events:none}to{opacity:0;visibility:hidden;pointer-events:none}}
@keyframes kdcvw-in{from{opacity:0;transform:scale(.9)}}
@keyframes kdcvw-run{to{stroke-dashoffset:-2029}}
@keyframes kdcvw-p{50%{opacity:.6}}
</style>
<noscript><style>#kdcv-waiting{display:none}</style></noscript>
<div id="kdcv-waiting" role="progressbar" aria-label="Loading"><svg viewBox="-40 -40 592 592" aria-hidden="true"><defs><linearGradient id="kdcvw-tile" x1="0" y1="0" x2="512" y2="512" gradientUnits="userSpaceOnUse"><stop stop-color="#171D1B"/><stop offset="1" stop-color="#050807"/></linearGradient><linearGradient id="kdcvw-accent" x1="200" y1="270" x2="255" y2="380" gradientUnits="userSpaceOnUse"><stop stop-color="#D9FF8A"/><stop offset="1" stop-color="#4FD35F"/></linearGradient></defs><g fill="none" stroke-width="12" stroke-linecap="round"><rect class="kdcvw-t" x="-28" y="-28" width="568" height="568" rx="142"/><rect class="kdcvw-c" x="-28" y="-28" width="568" height="568" rx="142"/><rect class="kdcvw-f" x="-28" y="-28" width="568" height="568" rx="142"/></g><rect width="512" height="512" rx="114" fill="url(#kdcvw-tile)"/><path class="kdcvw-a" d="M262 268 205 380 258 380 258 268Z" fill="url(#kdcvw-accent)"/><g fill="none" stroke="#F0FFDE" stroke-width="46" stroke-linecap="round" stroke-linejoin="round"><path d="M126 372V174l79 100 79-100v198"/><path d="M284 268 396 156M284 268l112 112"/></g></svg></div>
<script>(function(){var d=document,w=window,h=d.documentElement,e=d.getElementById("kdcv-waiting"),T=setTimeout,D=Date.now,t0=D(),ti,n,f,t,m,M,C;if(!e)return;
function k(){n=1;e.remove()}
function o(){n||(n=1,e.className+=" kdcv-waiting-out",T(k,f?680:260))}
function q(s){try{return matchMedia(s).matches}catch(x){}}
function a(y,z,p){w.addEventListener(y,z,p)}
if(/bot|crawl|spider|slurp|facebookexternalhit/i.test(navigator.userAgent)||d.readyState=="complete")return k();
try{t=sessionStorage;f=!t.getItem("kdcvWaitingSeen");t.setItem("kdcvWaitingSeen",1)}catch(x){}
try{t=h.getAttribute("data-kdcv-theme");if(!t){try{m=localStorage.getItem("darkMode")}catch(x){}t=m!=null?m=="enabled"?"dark":"light":d.body.dataset.defaultMode||(q("(prefers-color-scheme:dark)")?"dark":"light")}
e.className+=(t=="dark"?" kdcv-waiting-dk":"")+(f?"":" kdcv-waiting-r");
t={fa:"در حال بارگذاری",ar:"جارٍ التحميل",de:"Wird geladen",es:"Cargando",fr:"Chargement",tr:"Yükleniyor",zh:"加载中",ja:"読み込み中",ru:"Загрузка"}[h.lang.slice(0,2)];t&&e.setAttribute("aria-label",t)}catch(x){}
M=f&&!q("(prefers-reduced-motion:reduce)")?940:0;C=f?1200:0;
function g(){var z=D();z-t0<M||!(d.readyState=="complete"||ti&&z-ti>=C||z-t0>=3e3)||o()}
function i(){ti=ti||D();g();T(g,C)}
a("keydown",o,!0);a("pointerdown",o,!0);a("pageshow",function(v){v.persisted&&k()});
d.addEventListener("load",g,!0);d.readyState=="loading"?d.addEventListener("readystatechange",i):i();T(g,M);T(g,3e3)})()</script>
<!-- WAITING:END -->
<a class="ab-skip" href="#main">رفتن به محتوای اصلی</a>
<header class="ab-header">
	<a class="ab-brand" href="<?php echo esc_url( home_url( '/books/' ) ); ?>"><span class="ab-brand-mark">ک</span><span><b>کهن‌دژ</b><small>کتاب مرجع هوش مصنوعی</small></span></a>
	<nav class="ab-primary" aria-label="ناوبری اصلی کتاب"><a href="<?php echo esc_url( home_url( '/books/read/' ) ); ?>"<?php echo 'read' === $view ? ' aria-current="page"' : ''; ?>>مطالعه</a><a href="<?php echo esc_url( home_url( '/books/search/' ) ); ?>">جست‌وجو</a><a href="<?php echo esc_url( home_url( '/books/ask/' ) ); ?>">پرسش</a><a href="<?php echo esc_url( home_url( '/books/concepts/' ) ); ?>">مفاهیم</a></nav>
	<div class="ab-tools"><a href="<?php echo esc_url( home_url( '/books/sources/' ) ); ?>">منابع</a><a href="<?php echo esc_url( home_url( '/books/verify/' ) ); ?>">اعتبارسنجی</a><button class="ab-menu-button" type="button" aria-expanded="false" aria-controls="mobile-nav">فهرست</button></div>
</header>
<nav id="mobile-nav" class="ab-mobile-nav" aria-label="فهرست همراه" hidden><a href="<?php echo esc_url( home_url( '/books/read/' ) ); ?>">مطالعه</a><a href="<?php echo esc_url( home_url( '/books/search/' ) ); ?>">جست‌وجو</a><a href="<?php echo esc_url( home_url( '/books/graph/' ) ); ?>">نقشه دانش</a></nav>
<main id="main" class="ab-main" tabindex="-1">
	<?php if ( ! $repository ) : ?>
		<header class="ab-page-hero"><p class="ab-kicker">وضعیت داده</p><h1>کتاب موقتاً در دسترس نیست</h1><p class="ab-lead">پیکربندی منبع canonical کامل نشده یا اعتبار داده تأیید نشده است. هیچ محتوای حدسی نمایش داده نمی‌شود.</p><p><bdi><?php echo esc_html( KBK_AI_Book::repository_status() ); ?></bdi></p></header>
	<?php elseif ( 'home' === $view ) : ?>
		<header class="ab-page-hero"><p class="ab-kicker">نسخهٔ فارسی · <?php echo esc_html( $summary['edition_id'] ); ?></p><h1>دانش فنی، با مسیر روشن تا منبع</h1><p class="ab-lead"><?php echo esc_html( $summary['title_fa'] ); ?>؛ هر بخش با شناسه، منشأ و مسیر بازگشت به شاهد.</p><div class="ab-actions"><a class="ab-button ab-button-primary" href="<?php echo esc_url( home_url( '/books/read/' ) ); ?>">مطالعه آنلاین</a></div></header>
		<section class="ab-stat-grid" aria-label="مشخصات نسخه"><div><b><?php echo esc_html( $summary['parts'] ); ?></b><span>بخش</span></div><div><b><?php echo esc_html( $summary['chapters'] ); ?></b><span>فصل</span></div><div><b><?php echo esc_html( $summary['sections'] ); ?></b><span>بخش محتوایی</span></div><div><b>۱۸</b><span>منبع اصلی</span></div></section>
		<?php // Library shelf -- mirrors shelf() in _tooling/ai-book/build_visual_shell.py; change both together. ?>
		<?php $kbk_img = plugins_url( 'assets/books/', KBK_PLUGIN_FILE ); ?>
		<section class="ab-section ab-shelf" aria-labelledby="ab-shelf-title"><div class="ab-section-head"><div><p class="ab-kicker">کتابخانه</p><h2 id="ab-shelf-title">کتاب‌های کهن‌دژ</h2></div></div><div class="ab-shelf-grid">
			<article class="ab-book"><img class="ab-book-cover" src="<?php echo esc_url( $kbk_img . 'ai-governance-cover-w320.webp' ); ?>" srcset="<?php echo esc_url( $kbk_img . 'ai-governance-cover-w320.webp' ); ?> 320w, <?php echo esc_url( $kbk_img . 'ai-governance-cover-w640.webp' ); ?> 640w" sizes="(max-width: 420px) 110px, 200px" width="320" height="512" alt="جلد کتاب AI Governance — حاکمیت هوش مصنوعی، نوشتهٔ محمدعلی کهن‌دژ" loading="lazy" decoding="async"><div class="ab-book-body"><span class="ab-book-status ab-book-status-live">نسخهٔ آنلاین در دسترس</span><h3>حاکمیت هوش مصنوعی</h3><p class="ab-book-sub">راهنمای جامع حاکمیت، امنیت و مدیریت ریسک هوش مصنوعی</p><p class="ab-book-meta">محمدعلی کهن‌دژ</p><a class="ab-button ab-button-primary" href="<?php echo esc_url( home_url( '/books/read/' ) ); ?>">مطالعه آنلاین</a></div></article>
			<article class="ab-book"><img class="ab-book-cover" src="<?php echo esc_url( $kbk_img . 'ics-cybersecurity-cover-w320.webp' ); ?>" srcset="<?php echo esc_url( $kbk_img . 'ics-cybersecurity-cover-w320.webp' ); ?> 320w, <?php echo esc_url( $kbk_img . 'ics-cybersecurity-cover-w640.webp' ); ?> 640w" sizes="(max-width: 420px) 110px, 200px" width="320" height="512" alt="جلد کتاب امنیت سایبری در سیستم‌های اتوماسیون صنعتی" loading="lazy" decoding="async"><div class="ab-book-body"><span class="ab-book-status ab-book-status-soon">انتشار دیجیتال به‌زودی</span><h3>امنیت سایبری در سیستم‌های اتوماسیون صنعتی</h3><p class="ab-book-sub">راهنمای جامع بر اساس استانداردهای IEC 62443</p><p class="ab-book-meta">محمدعلی کهن‌دژ، روزبه بابازاده، نیلوفر کریمی آذر · تابستان ۱۴۰۴</p><p class="ab-book-note">نسخهٔ دیجیتال این کتاب به‌زودی در همین بخش منتشر می‌شود.</p></div></article>
		</div></section>
		<section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker">ساختار کتاب</p><h2>هفت بخش canonical</h2></div></div><div class="ab-card-grid">
		<?php foreach ( $parts as $part ) : ?>
			<a class="ab-card" href="<?php echo esc_url( home_url( '/books/read/?part=' . rawurlencode( $part['part_id'] ) ) ); ?>"><span class="ab-card-meta"><?php echo esc_html( $part['part_id'] ); ?></span><h3><?php echo esc_html( $part['title_fa'] ); ?></h3><p><?php echo esc_html( count( $part['chapters'] ) ); ?> فصل</p><span class="ab-card-link">مطالعه</span></a>
		<?php endforeach; ?>
		</div></section>
	<?php elseif ( $chapter ) : ?>
		<div class="ab-reader"><aside class="ab-toc" aria-label="فهرست فصل"><p class="ab-kicker">P01 · C01</p><h2><?php echo esc_html( $chapter['title_fa'] ); ?></h2><?php foreach ( $chapter['sections'] as $index => $section ) : ?><a<?php echo 0 === $index ? ' class="is-current"' : ''; ?> href="#<?php echo esc_attr( $section['structural_id'] ); ?>"><?php echo esc_html( $section['title_fa'] ); ?></a><?php endforeach; ?></aside>
		<article class="ab-prose"><header class="ab-page-hero"><p class="ab-kicker">P01 · C01</p><h1><?php echo esc_html( $chapter['title_fa'] ); ?></h1><p class="ab-lead">متن canonical فارسی با منشأ، شناسه و پیوند استناد پایدار.</p></header>
		<?php foreach ( $chapter['sections'] as $section ) : $origin = $origin_map[ $section['origin'] ]; ?>
		<section id="<?php echo esc_attr( $section['structural_id'] ); ?>"><h2><?php echo esc_html( $section['title_fa'] ); ?></h2><div class="ab-layer <?php echo esc_attr( $origin['class'] ); ?>"><span><?php echo esc_html( $origin['label'] ); ?></span><?php echo wp_kses_post( wpautop( esc_html( $section['fa_text'] ) ) ); ?></div><p class="ab-card-meta"><bdi><?php echo esc_html( $section['content_id'] ); ?></bdi></p><a href="<?php echo esc_url( $section['canonical_url'] ); ?>">پیوند canonical این بخش</a></section>
		<?php endforeach; ?></article></div>
	<?php endif; ?>
</main>
<footer class="ab-footer"><div><b><?php echo esc_html( $summary['title_fa'] ?? 'کتاب مرجع هوش مصنوعی' ); ?></b><p>ترجمه، تدوین و بازآرایی فارسی: محمدعلی کهن‌دژ</p></div><p class="ab-disclaimer">این اثر ترجمهٔ رسمی NIST نیست و NIST آن را تأیید نکرده است.</p></footer>
<?php wp_footer(); ?>
</body>
</html>
