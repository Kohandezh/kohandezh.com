<?php
/** Persian AI Book landing/reader template. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$view       = KBK_AI_Book::current_view();
$repository = KBK_AI_Book::repository();
$summary    = $repository ? $repository->summary() : null;
$parts      = $repository ? $repository->parts() : array();
$selection  = 'read' === $view ? KBK_AI_Book::current_reader_selection() : null;
$part       = $selection['part'] ?? null;
$chapter    = $selection['chapter'] ?? null;
$adjacent   = ( $repository && $part && $chapter ) ? $repository->adjacent_chapters( $part['part_id'], $chapter['chapter_id'] ) : array( 'prev' => null, 'next' => null );
$page_titles = array(
	'library'         => 'کتاب‌های کهن‌دژ — کتابخانه',
	'home'            => 'کتاب مرجع هوش مصنوعی کهن‌دژ',
	'governance'      => 'AI Governance — حاکمیت هوش مصنوعی برای سازمان‌ها',
	'governance-news' => 'اخبار و تحلیل‌های حاکمیت هوش مصنوعی',
	'search'          => 'جست‌وجو — کتاب حاکمیت هوش مصنوعی',
	'glossary'        => 'واژه‌نامه — کتاب حاکمیت هوش مصنوعی',
	'sources'         => 'منابع حاکمیت و مدیریت ریسک هوش مصنوعی',
	'templates'       => 'قالب‌های اجرایی حاکمیت هوش مصنوعی',
	'concepts'        => 'مفاهیم حاکمیت هوش مصنوعی',
	'graph'           => 'نگارهٔ مفاهیم حاکمیت هوش مصنوعی',
	'ask'             => 'پرسش از کتاب حاکمیت هوش مصنوعی',
	'pdf'             => 'نسخه PDF کتاب حاکمیت هوش مصنوعی',
	'request-pdf'     => 'درخواست نسخه PDF کتاب حاکمیت هوش مصنوعی',
);
$reader_heading = 'read' === $view && $selection && $selection['section'] ? KBK_AI_Book::reader_section_title( $selection['section'] ) : ( $chapter['title_fa'] ?? '' );
$page_title = 'read' === $view && $chapter ? $reader_heading . ' — کتاب حاکمیت هوش مصنوعی' : ( $page_titles[ $view ] ?? 'کتاب حاکمیت هوش مصنوعی' );
$preferred_paths = array(
	'library'         => '/books/',
	'home'            => '/fa/books/ai-governance/',
	'governance'      => '/fa/books/ai-governance/',
	'governance-news' => '/fa/books/ai-governance/news/',
);
$preferred_path = $preferred_paths[ $view ] ?? '/fa/books/ai-governance/' . $view . '/';
$canonical_url = home_url( $preferred_path );
if ( 'read' === $view && $part && $chapter ) {
	$canonical_url = add_query_arg(
		array(
			KBK_AI_Book::PART_QUERY_VAR    => $part['part_id'],
			KBK_AI_Book::CHAPTER_QUERY_VAR => $chapter['chapter_id'],
		),
		$canonical_url
	);
}
$page_description = 'مرجع فارسی AI Governance و حاکمیت هوش مصنوعی برای سازمان‌های دولتی، عمومی و خصوصی؛ مبتنی بر منابع NIST، با متن قابل استناد، واژه‌نامه و قالب‌های اجرایی.';
$book_schema = array(
	'@context'      => 'https://schema.org',
	'@type'         => 'Book',
	'@id'           => home_url( '/fa/books/ai-governance/#book' ),
	'name'          => 'AI Governance — حاکمیت هوش مصنوعی',
	'alternateName' => $summary['title_fa'] ?? 'کتاب مرجع هوش مصنوعی کهن‌دژ',
	'url'           => home_url( '/fa/books/ai-governance/' ),
	'inLanguage'    => 'fa-IR',
	'author'        => array( '@type' => 'Person', 'name' => 'محمدعلی کهن‌دژ' ),
	'publisher'     => array( '@type' => 'Organization', 'name' => 'کهن‌دژ', 'url' => home_url( '/' ) ),
);
$page_schema = array(
	'@context'   => 'https://schema.org',
	'@type'      => 'read' === $view ? 'Chapter' : ( 'governance-news' === $view ? 'CollectionPage' : 'WebPage' ),
	'@id'        => $canonical_url . '#webpage',
	'name'       => $page_title,
	'description'=> $page_description,
	'url'        => $canonical_url,
	'inLanguage' => 'fa-IR',
	'isPartOf'   => array( '@id' => home_url( '/fa/books/ai-governance/#book' ) ),
);
$origin_map = array(
	'source_translation'        => array( 'class' => 'ab-layer-source', 'label' => 'ترجمهٔ منبع' ),
	'editorial_synthesis'       => array( 'class' => 'ab-layer-editorial', 'label' => 'جمع‌بندی تدوینگر' ),
	'editorial_localization_ir' => array( 'class' => 'ab-layer-local', 'label' => 'کاربرد در سازمان‌های ایرانی' ),
);
$glossary_status_map = array(
	'approved'     => 'تأییدشده',
	'proposed'     => 'پیشنهادی',
	'english_only' => 'فقط انگلیسی',
);
/** Escaped segment renderer: text is always escaped, <mark> is template-owned. */
$render_segments = static function ( array $segments ): string {
	$output = '';
	foreach ( $segments as $segment ) {
		$escaped = esc_html( (string) $segment['text'] );
		$output .= $segment['mark'] ? '<mark>' . $escaped . '</mark>' : $escaped;
	}
	return $output;
};
$read_link = static function ( string $part_id, ?string $chapter_id = null, ?string $section_id = null ): string {
	$url = home_url( '/ai-book/read/?' . KBK_AI_Book::PART_QUERY_VAR . '=' . rawurlencode( $part_id ) );
	if ( null !== $chapter_id ) {
		$url .= '&' . KBK_AI_Book::CHAPTER_QUERY_VAR . '=' . rawurlencode( $chapter_id );
	}
	if ( null !== $section_id ) {
		$url .= '&' . KBK_AI_Book::SECTION_QUERY_VAR . '=' . rawurlencode( $section_id ) . '#' . rawurlencode( $section_id );
	}
	return $url;
};
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $page_title ); ?></title>
	<meta name="description" content="<?php echo esc_attr( $page_description ); ?>">
	<link rel="canonical" href="<?php echo esc_url( $canonical_url ); ?>">
	<link rel="alternate" hreflang="fa" href="<?php echo esc_url( $canonical_url ); ?>">
	<meta property="og:type" content="<?php echo 'governance-news' === $view ? 'website' : 'article'; ?>">
	<meta property="og:locale" content="fa_IR">
	<meta property="og:title" content="<?php echo esc_attr( $page_title ); ?>">
	<meta property="og:description" content="<?php echo esc_attr( $page_description ); ?>">
	<meta property="og:url" content="<?php echo esc_url( $canonical_url ); ?>">
	<script>(function(){try{var l=localStorage.getItem('kbk-ai-book-theme'),s=localStorage.getItem('darkMode'),t;if(l==='light'||l==='dark'){t=l;localStorage.setItem('darkMode',t==='light'?'disabled':'enabled');localStorage.removeItem('kbk-ai-book-theme');}else if(s==='disabled'||s==='light'){t='light';}else if(s==='enabled'||s==='dark'){t='dark';}else{t=matchMedia('(prefers-color-scheme: light)').matches?'light':'dark';}document.documentElement.setAttribute('data-ab-theme',t);}catch(e){}})();</script>
	<script type="application/ld+json"><?php echo wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => array( $book_schema, $page_schema ) ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
	<?php wp_head(); ?>
</head>
<body class="kbk-ai-book-page ab-view-<?php echo esc_attr( $view ); ?> dark-mode">
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
	<a class="ab-brand" href="<?php echo esc_url( home_url( '/ai-book/' ) ); ?>"><img class="ab-brand-logo" src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/logo/logo.webp' ); ?>" width="44" height="44" alt="Mohammad Kohandezh"><span><b>کهن‌دژ</b><small>کتاب مرجع هوش مصنوعی</small></span></a>
	<nav class="ab-primary" aria-label="ناوبری اصلی کتاب"><a href="<?php echo esc_url( home_url( '/ai-book/read/' ) ); ?>"<?php echo 'read' === $view ? ' aria-current="page"' : ''; ?>>مطالعه</a><a href="<?php echo esc_url( home_url( '/ai-book/search/' ) ); ?>"<?php echo 'search' === $view ? ' aria-current="page"' : ''; ?>>جست‌وجو</a><a href="<?php echo esc_url( home_url( '/ai-book/ask/' ) ); ?>"<?php echo 'ask' === $view ? ' aria-current="page"' : ''; ?>>پرسش</a><a href="<?php echo esc_url( home_url( '/ai-book/concepts/' ) ); ?>"<?php echo 'concepts' === $view ? ' aria-current="page"' : ''; ?>>مفاهیم</a></nav>
	<div class="ab-tools"><a href="<?php echo esc_url( home_url( '/ai-book/sources/' ) ); ?>"<?php echo 'sources' === $view ? ' aria-current="page"' : ''; ?>>منابع</a><a href="<?php echo esc_url( home_url( '/ai-book/verify/' ) ); ?>"<?php echo 'verify' === $view ? ' aria-current="page"' : ''; ?>>اعتبارسنجی</a><button class="ab-theme-toggle" type="button" aria-pressed="false" aria-label="فعال‌کردن حالت روشن"><span class="ab-theme-icon" aria-hidden="true">☀</span><span class="ab-theme-label">حالت روشن</span></button><button class="ab-menu-button" type="button" aria-expanded="false" aria-controls="mobile-nav">فهرست کتاب</button></div>
</header>
<nav id="mobile-nav" class="ab-mobile-nav" aria-label="فهرست کتاب" hidden>
	<a href="<?php echo esc_url( home_url( '/ai-book/read/' ) ); ?>"<?php echo 'read' === $view ? ' aria-current="page"' : ''; ?>>مطالعه کتاب</a>
	<a href="<?php echo esc_url( home_url( '/ai-book/search/' ) ); ?>"<?php echo 'search' === $view ? ' aria-current="page"' : ''; ?>>جست‌وجو</a>
	<a href="<?php echo esc_url( home_url( '/ai-book/ask/' ) ); ?>"<?php echo 'ask' === $view ? ' aria-current="page"' : ''; ?>>پرسش از کتاب</a>
	<a href="<?php echo esc_url( home_url( '/ai-book/concepts/' ) ); ?>"<?php echo 'concepts' === $view ? ' aria-current="page"' : ''; ?>>مفاهیم</a>
	<a href="<?php echo esc_url( home_url( '/ai-book/graph/' ) ); ?>"<?php echo 'graph' === $view ? ' aria-current="page"' : ''; ?>>نقشه دانش</a>
	<a href="<?php echo esc_url( home_url( '/ai-book/glossary/' ) ); ?>"<?php echo 'glossary' === $view ? ' aria-current="page"' : ''; ?>>واژه‌نامه</a>
	<a href="<?php echo esc_url( home_url( '/ai-book/sources/' ) ); ?>"<?php echo 'sources' === $view ? ' aria-current="page"' : ''; ?>>منابع</a>
	<a href="<?php echo esc_url( home_url( '/ai-book/templates/' ) ); ?>"<?php echo 'templates' === $view ? ' aria-current="page"' : ''; ?>>الگوها</a>
	<a href="<?php echo esc_url( home_url( '/ai-book/pdf/' ) ); ?>"<?php echo 'pdf' === $view ? ' aria-current="page"' : ''; ?>>نسخه PDF</a>
</nav>
<main id="main" class="ab-main" tabindex="-1">
	<?php if ( 'library' === $view ) :
		// The library shelf -- mirrors shelf() in _tooling/ai-book/build_visual_shell.py; change both together.
		$kbk_img = plugins_url( 'assets/books/', KBK_PLUGIN_FILE );
		?>
		<header class="ab-page-hero"><p class="ab-kicker">کتابخانه</p><h1>کتاب‌های کهن‌دژ</h1><p class="ab-lead">کتاب‌های محمدعلی کهن‌دژ؛ هر کتاب با ساختار، منبع و وضعیت انتشار خودش.</p></header>
		<section class="ab-section ab-shelf" aria-label="کتاب‌ها"><div class="ab-shelf-grid">
			<article class="ab-book"><img class="ab-book-cover" src="<?php echo esc_url( $kbk_img . 'ai-governance-cover-w320.webp' ); ?>" srcset="<?php echo esc_url( $kbk_img . 'ai-governance-cover-w320.webp' ); ?> 320w, <?php echo esc_url( $kbk_img . 'ai-governance-cover-w640.webp' ); ?> 640w" sizes="(max-width: 420px) 110px, 200px" width="320" height="512" alt="جلد کتاب AI Governance — حاکمیت هوش مصنوعی، نوشتهٔ محمدعلی کهن‌دژ" loading="lazy" decoding="async"><div class="ab-book-body"><span class="ab-book-status ab-book-status-live">نسخهٔ آنلاین در دسترس</span><h3>حاکمیت هوش مصنوعی</h3><p class="ab-book-sub">راهنمای جامع حاکمیت، امنیت و مدیریت ریسک هوش مصنوعی</p><p class="ab-book-meta">محمدعلی کهن‌دژ</p><div class="ab-book-actions"><a class="ab-button ab-button-primary" href="<?php echo esc_url( home_url( '/fa/books/ai-governance/read/' ) ); ?>">مطالعه آنلاین</a><a class="ab-button" href="<?php echo esc_url( home_url( '/fa/books/ai-governance/' ) ); ?>">معرفی و ساختار کتاب</a></div></div></article>
			<article class="ab-book"><img class="ab-book-cover" src="<?php echo esc_url( $kbk_img . 'ics-cybersecurity-cover-w320.webp' ); ?>" srcset="<?php echo esc_url( $kbk_img . 'ics-cybersecurity-cover-w320.webp' ); ?> 320w, <?php echo esc_url( $kbk_img . 'ics-cybersecurity-cover-w640.webp' ); ?> 640w" sizes="(max-width: 420px) 110px, 200px" width="320" height="512" alt="جلد کتاب امنیت سایبری در سیستم‌های اتوماسیون صنعتی" loading="lazy" decoding="async"><div class="ab-book-body"><span class="ab-book-status ab-book-status-soon">انتشار دیجیتال به‌زودی</span><h3>امنیت سایبری در سیستم‌های اتوماسیون صنعتی</h3><p class="ab-book-sub">راهنمای جامع بر اساس استانداردهای IEC 62443</p><p class="ab-book-meta">محمدعلی کهن‌دژ، روزبه بابازاده، نیلوفر کریمی آذر · تابستان ۱۴۰۴</p><p class="ab-book-note">نسخهٔ دیجیتال این کتاب به‌زودی در همین بخش منتشر می‌شود.</p></div></article>
		</div></section>
	<?php elseif ( ! $repository ) : ?>
		<header class="ab-page-hero"><p class="ab-kicker">وضعیت داده</p><h1>کتاب موقتاً در دسترس نیست</h1><p class="ab-lead">پیکربندی منبع اصلی کتاب کامل نشده یا اعتبار داده تأیید نشده است. هیچ محتوای حدسی نمایش داده نمی‌شود.</p><p><bdi><?php echo esc_html( KBK_AI_Book::repository_status() ); ?></bdi></p></header>
	<?php elseif ( 'governance' === $view ) :
		$governance_chapter = $repository->find_chapter( 'P02', 'C09' );
		$book_assets = plugins_url( 'assets/', KBK_PLUGIN_FILE );
		$book_article = get_page_by_path( 'ai-governance-nist-guide', OBJECT, 'post' );
		?>
		<nav class="ab-breadcrumb" aria-label="مسیر"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">کهن‌دژ</a><a href="<?php echo esc_url( home_url( '/books/' ) ); ?>">کتاب‌ها</a><span aria-current="page">AI Governance</span></nav>
		<header class="ab-page-hero"><p class="ab-kicker">کتاب‌ها · نسخهٔ فارسی · مبتنی بر منابع NIST</p><h1>AI Governance — حاکمیت هوش مصنوعی</h1><p class="ab-lead">مرجع مستند برای طراحی سیاست، نقش، پاسخگویی، مدیریت ریسک و شواهد قابل ممیزی هوش مصنوعی در سازمان‌های دولتی، نهادهای عمومی، بانک‌ها، بیمه‌ها، صنایع و کسب‌وکارهای ایرانی.</p><div class="ab-actions"><a class="ab-button ab-button-primary" href="<?php echo esc_url( home_url( '/fa/books/ai-governance/read/?kbk_part=P02&kbk_chapter=C09' ) ); ?>">مطالعه فصل حاکمیت</a><a class="ab-button" href="<?php echo esc_url( home_url( '/fa/books/ai-governance/news/' ) ); ?>">اخبار و تحلیل‌ها</a></div></header>
		<section class="ab-book-showcase" aria-label="جلد و دسترسی به کتاب">
			<figure><img src="<?php echo esc_url( $book_assets . 'book-front.webp' ); ?>" width="1024" height="1536" alt="روی جلد کتاب AI Governance؛ حاکمیت هوش مصنوعی، Mohammad Kohandezh"><figcaption>روی جلد · طرح معرفی نسخهٔ دیجیتال</figcaption></figure>
			<figure><img src="<?php echo esc_url( $book_assets . 'book-back.webp' ); ?>" width="1024" height="1536" loading="lazy" alt="پشت جلد؛ راهنمای مطالعه و اقدام، سیاست‌گذاری، پاسخگویی و مدیریت ریسک"><figcaption>پشت جلد</figcaption></figure>
			<div class="ab-book-access"><p class="ab-kicker">از مطالعه تا اقدام</p><h2>حاکمیت هوش مصنوعی، با مسیر روشن تا منبع</h2><p>منابع، مفاهیم و بخش‌های قابل استناد را در یک محیط مطالعه دنبال کنید.</p><a class="ab-book-qr" href="https://kohandezh.com/fa/books/ai-governance/"><img src="<?php echo esc_url( $book_assets . 'book-qr.png' ); ?>" width="180" height="180" alt="QR آدرس عمومی صفحهٔ کتاب"></a><p>اسکن برای بازکردن صفحهٔ کتاب</p><bdi class="ab-public-url">kohandezh.com/fa/books/ai-governance/</bdi><?php if ( 'localhost' === wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) : ?><p class="ab-card-meta">این پیش‌نمایش محلی است؛ QR به دامنهٔ عمومی می‌رود و دسترسی به کتاب در آن دامنه وابسته به انتشار نسخهٔ نهایی است.</p><?php endif; ?></div>
		</section>
		<?php if ( $summary ) : ?><section class="ab-section ab-book-detail" id="structure" aria-labelledby="ab-structure-title"><div class="ab-section-head"><div><p class="ab-kicker">نسخهٔ فارسی · <?php echo esc_html( $summary['edition_id'] ); ?></p><h2 id="ab-structure-title">ساختار کتاب: هفت بخش اصلی</h2></div><a href="<?php echo esc_url( home_url( '/fa/books/ai-governance/read/' ) ); ?>">مطالعه آنلاین</a></div><section class="ab-stat-grid" aria-label="مشخصات نسخه"><div><b><?php echo esc_html( strtr( (string) $summary['parts'], array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) ) ); ?></b><span>بخش</span></div><div><b><?php echo esc_html( strtr( (string) $summary['chapters'], array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) ) ); ?></b><span>فصل</span></div><div><b><?php echo esc_html( strtr( (string) $summary['sections'], array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) ) ); ?></b><span>بخش محتوایی</span></div><div><b>۱۸</b><span>منبع اصلی</span></div></section><div class="ab-card-grid"><?php foreach ( $parts as $structure_part ) : ?><a class="ab-card" href="<?php echo esc_url( home_url( '/fa/books/ai-governance/read/?' . KBK_AI_Book::PART_QUERY_VAR . '=' . rawurlencode( $structure_part['part_id'] ) ) ); ?>"><span class="ab-card-meta"><?php echo esc_html( $structure_part['part_id'] ); ?></span><h3><?php echo esc_html( $structure_part['title_fa'] ); ?></h3><p><?php echo esc_html( strtr( (string) count( $structure_part['chapters'] ), array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) ) ); ?> فصل</p></a><?php endforeach; ?></div></section><?php endif; ?>
		<?php if ( $book_article && 'publish' === $book_article->post_status ) : ?><section class="ab-section ab-book-blog"><p class="ab-kicker">از وبلاگ کهن‌دژ</p><h2>برای شروع مطالعه</h2><a class="ab-card" href="<?php echo esc_url( get_permalink( $book_article ) ); ?>"><h3><?php echo esc_html( get_the_title( $book_article ) ); ?></h3><p><?php echo esc_html( $book_article->post_excerpt ); ?></p><span class="ab-card-link">خواندن راهنما ←</span></a></section><?php endif; ?>
		<section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker">تعریف روشن</p><h2>حاکمیت هوش مصنوعی چیست؟</h2></div></div><div class="ab-layer ab-layer-source"><span>راهنمای مرجع</span><p>حاکمیت هوش مصنوعی مجموعهٔ سیاست‌ها، نقش‌ها، فرایندهای تصمیم‌گیری، کنترل‌ها و شواهدی است که استفاده از سامانه‌های هوش مصنوعی را با اهداف سازمان، الزامات قانونی، تحمل ریسک و مسئولیت‌پذیری هم‌راستا می‌کند.</p></div></section>
		<?php if ( $governance_chapter ) : ?><section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker">NIST AI RMF Playbook</p><h2>عناوین AI Governance</h2></div></div><div class="ab-results"><?php foreach ( $governance_chapter['sections'] as $governance_section ) : ?><a class="ab-card" href="<?php echo esc_url( home_url( '/fa/books/ai-governance/read/?kbk_part=P02&kbk_chapter=C09&kbk_section=' . rawurlencode( $governance_section['structural_id'] ) . '#' . rawurlencode( $governance_section['structural_id'] ) ) ); ?>"><span class="ab-card-meta"><bdi><?php echo esc_html( $governance_section['structural_id'] ); ?></bdi></span><h3><?php echo esc_html( KBK_AI_Book::reader_section_title( $governance_section ) ); ?></h3><span class="ab-card-link">مطالعه و استناد</span></a><?php endforeach; ?></div></section><?php endif; ?>
		<section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker">مسیرهای کاربردی</p><h2>برای چه سازمان‌هایی قابل استفاده است؟</h2></div></div><div class="ab-card-grid"><article class="ab-card"><h3>دستگاه‌های دولتی و نهادهای عمومی</h3><p>برای سیاست‌گذاری داخلی، تعیین مسئولیت، ثبت سامانه‌ها و مستندسازی تصمیم‌ها.</p></article><article class="ab-card"><h3>بانک، بیمه و خدمات مالی</h3><p>برای کنترل ریسک مدل، نظارت انسانی، مدیریت تأمین‌کننده و شواهد ممیزی.</p></article><article class="ab-card"><h3>صنعت، سلامت و زیرساخت</h3><p>برای پایش عملکرد، مدیریت تغییر، امنیت و تصمیم‌گیری متناسب با ریسک.</p></article></div></section>
	<?php elseif ( 'governance-news' === $view ) :
		$news_query = new WP_Query(
			array(
				'post_type'      => array( 'kbk_news', 'post' ),
				'post_status'    => 'publish',
				'posts_per_page' => 12,
				's'              => 'حاکمیت هوش مصنوعی AI Governance',
			)
		);
		?>
		<nav class="ab-breadcrumb" aria-label="مسیر"><a href="<?php echo esc_url( home_url( '/fa/books/ai-governance/' ) ); ?>">AI Governance</a><span aria-current="page">اخبار و تحلیل‌ها</span></nav>
		<header class="ab-page-hero"><p class="ab-kicker">رصد و تحلیل</p><h1>اخبار و تحلیل‌های حاکمیت هوش مصنوعی</h1><p class="ab-lead">مطالب منتشرشده دربارهٔ سیاست‌گذاری، استانداردها، مدیریت ریسک و الزامات سازمانی هوش مصنوعی؛ هر مطلب با تاریخ، نویسنده و منبع مشخص منتشر می‌شود.</p></header>
		<section class="ab-section"><div class="ab-results"><?php if ( $news_query->have_posts() ) : while ( $news_query->have_posts() ) : $news_query->the_post(); ?><a class="ab-card" href="<?php the_permalink(); ?>"><span class="ab-card-meta"><?php echo esc_html( get_the_date() ); ?></span><h2><?php the_title(); ?></h2><p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 32 ) ); ?></p><span class="ab-card-link">مطالعه تحلیل</span></a><?php endwhile; wp_reset_postdata(); else : ?><div class="ab-fallback"><h2>هنوز مطلب مرتبطی منتشر نشده است</h2><p>این صفحه فقط مطالب واقعی و منتشرشده را نمایش می‌دهد و برای پرکردن فهرست، خبر یا ادعای ساختگی تولید نمی‌کند.</p></div><?php endif; ?></div></section>
	<?php elseif ( 'home' === $view ) : ?>
		<header class="ab-page-hero"><p class="ab-kicker">نسخهٔ فارسی · <?php echo esc_html( $summary['edition_id'] ); ?></p><h1>دانش فنی، با مسیر روشن تا منبع</h1><p class="ab-lead"><?php echo esc_html( $summary['title_fa'] ); ?>؛ هر بخش با شناسه، منشأ و مسیر بازگشت به شاهد.</p><div class="ab-actions"><a class="ab-button ab-button-primary" href="<?php echo esc_url( home_url( '/ai-book/read/' ) ); ?>">مطالعه آنلاین</a></div></header>
		<section class="ab-stat-grid" aria-label="مشخصات نسخه"><div><b><?php echo esc_html( $summary['parts'] ); ?></b><span>بخش</span></div><div><b><?php echo esc_html( $summary['chapters'] ); ?></b><span>فصل</span></div><div><b><?php echo esc_html( $summary['sections'] ); ?></b><span>بخش محتوایی</span></div><div><b>۱۸</b><span>منبع اصلی</span></div></section>
		<section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker">ساختار کتاب</p><h2>هفت بخش اصلی</h2></div></div><div class="ab-card-grid">
		<?php foreach ( $parts as $part_card ) : ?>
			<a class="ab-card" href="<?php echo esc_url( home_url( '/ai-book/read/?' . KBK_AI_Book::PART_QUERY_VAR . '=' . rawurlencode( $part_card['part_id'] ) ) ); ?>"><span class="ab-card-meta"><?php echo esc_html( $part_card['part_id'] ); ?></span><h3><?php echo esc_html( $part_card['title_fa'] ); ?></h3><p><?php echo esc_html( count( $part_card['chapters'] ) ); ?> فصل</p><span class="ab-card-link">مطالعه</span></a>
		<?php endforeach; ?>
		</div></section>
	<?php elseif ( 'search' === $view ) :
		$search = KBK_AI_Book::current_search();
		?>
		<header class="ab-page-hero"><p class="ab-kicker">جست‌وجوی فارسی و انگلیسی</p><h1>در کتاب چه می‌خواهید پیدا کنید؟</h1><p class="ab-lead">عنوان، متن، مفهوم، واژه‌نامه، نام منبع، سرواژه و شناسهٔ محتوا قابل جست‌وجو است.</p></header>
		<form class="ab-search" role="search" method="get" action="<?php echo esc_url( home_url( '/ai-book/search/' ) ); ?>"><label for="q">عبارت جست‌وجو</label><div><input id="q" name="<?php echo esc_attr( KBK_AI_Book::SEARCH_QUERY_VAR ); ?>" value="<?php echo esc_attr( (string) $search['query'] ); ?>" autocomplete="off"><button class="ab-button ab-button-primary" type="submit">جست‌وجو</button></div></form>
		<?php if ( 'READY' !== $search['status'] ) : ?>
		<section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker">وضعیت داده</p><h2>جست‌وجو موقتاً در دسترس نیست</h2></div></div><p class="ab-lead">پیکربندی منبع اصلی کتاب کامل نشده یا اعتبار داده تأیید نشده است. هیچ محتوای حدسی نمایش داده نمی‌شود.</p><p><bdi><?php echo esc_html( $search['status'] ); ?></bdi></p></section>
		<?php elseif ( null === $search['query'] ) : ?>
		<section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker">راهنما</p><h2>عبارتی برای جست‌وجو وارد کنید</h2></div></div><p class="ab-lead">می‌توانید متن فارسی، اصطلاح یا سرواژهٔ انگلیسی (مانند <bdi>RMF</bdi>)، نام سند (مانند <bdi>NIST AI 100-1</bdi>) یا شناسهٔ محتوا (مانند <bdi>KDJ-AI-2026E1-P01-C01-S01</bdi>) را جست‌وجو کنید.</p></section>
		<?php elseif ( 0 === $search['total'] ) : ?>
		<section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker">نتیجه‌ای یافت نشد</p><h2>نتیجه‌ای برای «<?php echo esc_html( $search['query'] ); ?>» وجود ندارد</h2></div></div><p class="ab-lead">جست‌وجو دقیق است و نتیجهٔ حدسی تولید نمی‌کند؛ عبارت کوتاه‌تر یا دیگری را امتحان کنید.</p></section>
		<?php else : ?>
		<section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker"><?php echo esc_html( $search['total'] ); ?> نتیجه · صفحهٔ <?php echo esc_html( $search['page'] ); ?> از <?php echo esc_html( $search['pages'] ); ?></p><h2>نتایج برای «<?php echo esc_html( $search['query'] ); ?>»</h2></div></div><div class="ab-results">
			<?php foreach ( $search['results'] as $result ) : ?>
			<?php if ( 'part' === $result['type'] ) : ?>
			<a class="ab-card" href="<?php echo esc_url( $read_link( $result['part']['id'] ) ); ?>"><span class="ab-card-meta">بخش · <?php echo esc_html( $result['part']['id'] ); ?></span><h3><?php echo esc_html( $result['title'] ); ?></h3><p><?php echo esc_html( $result['chapters'] ); ?> فصل</p><span class="ab-card-link">مطالعه بخش</span></a>
			<?php elseif ( 'chapter' === $result['type'] ) : ?>
			<a class="ab-card" href="<?php echo esc_url( $read_link( $result['part']['id'], $result['chapter']['id'] ) ); ?>"><span class="ab-card-meta"><?php echo esc_html( $result['part']['id'] . ' · ' . $result['chapter']['id'] ); ?></span><h3><?php echo esc_html( $result['title'] ); ?></h3><p>فصل در بخش «<?php echo esc_html( $result['part']['title_fa'] ); ?>»</p><span class="ab-card-link">مطالعه فصل</span></a>
			<?php elseif ( 'glossary' === $result['type'] ) : ?>
			<a class="ab-card" href="<?php echo esc_url( home_url( '/ai-book/glossary/?' . KBK_AI_Book::CONCEPT_QUERY_VAR . '=' . rawurlencode( $result['concept_id'] ) ) ); ?>"><span class="ab-card-meta">واژه‌نامه<?php echo '' !== $result['acronym'] ? ' · ' . esc_html( $result['acronym'] ) : ''; ?></span><h3><?php echo esc_html( $result['title'] ); ?></h3><p><?php echo $render_segments( $result['snippet_segments'] ); ?></p><span class="ab-card-link">واژه‌نامه</span></a>
			<?php else : ?>
			<a class="ab-card" href="<?php echo esc_url( $read_link( $result['part']['id'], $result['chapter']['id'], $result['section']['structural_id'] ) ); ?>"><span class="ab-card-meta"><?php echo esc_html( $result['part']['id'] . ' · ' . $result['chapter']['id'] . ( $result['docs'] ? ' · ' . implode( '، ', $result['docs'] ) : '' ) ); ?></span><h3><?php echo esc_html( $result['title'] ); ?></h3><p><?php echo $render_segments( $result['snippet_segments'] ); ?></p><span class="ab-card-meta"><bdi><?php echo esc_html( $result['section']['content_id'] ); ?></bdi> · <?php echo esc_html( $origin_map[ $result['origin'] ]['label'] ); ?></span><span class="ab-card-link">مشاهده بخش</span></a>
			<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php if ( $search['pages'] > 1 ) : ?>
		<nav class="ab-actions" aria-label="صفحه‌بندی نتایج جست‌وجو">
		<?php foreach ( array( -1 => 'صفحهٔ قبل', 1 => 'صفحهٔ بعد' ) as $step => $label ) :
			$target_page = $search['page'] + $step;
			if ( $target_page < 1 || $target_page > $search['pages'] ) { continue; }
			$page_url = home_url( '/ai-book/search/?' . http_build_query( array( KBK_AI_Book::SEARCH_QUERY_VAR => $search['query'], KBK_AI_Book::PAGE_QUERY_VAR => $target_page ) ) );
		?>
		<a class="ab-button ab-button-secondary" rel="<?php echo $step < 0 ? 'prev' : 'next'; ?>" href="<?php echo esc_url( $page_url ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
		</nav>
		<?php endif; ?>
		</section>
		<?php endif; ?>
	<?php elseif ( 'glossary' === $view ) :
		$glossary_term     = KBK_AI_Book::current_glossary_term();
		$requested_concept = KBK_AI_Book::requested_concept();
		?>
		<?php if ( null !== $glossary_term ) : ?>
		<nav class="ab-breadcrumb" aria-label="مسیر"><a href="<?php echo esc_url( home_url( '/ai-book/' ) ); ?>">کتاب</a><a href="<?php echo esc_url( home_url( '/ai-book/glossary/' ) ); ?>">واژه‌نامه</a><span aria-current="page"><?php echo esc_html( $glossary_term['title'] ); ?></span></nav>
		<article class="ab-prose"><header class="ab-page-hero"><p class="ab-kicker">واژه‌نامه · <?php echo esc_html( $glossary_status_map[ $glossary_term['status'] ] ?? $glossary_term['status'] ); ?><?php echo '' !== $glossary_term['domain'] ? ' · ' . esc_html( $glossary_term['domain'] ) : ''; ?></p><h1><?php echo esc_html( $glossary_term['title'] ); ?></h1><p class="ab-lead"><bdi><?php echo esc_html( $glossary_term['english'] ); ?></bdi><?php echo '' !== $glossary_term['acronym'] ? ' · <bdi>' . esc_html( $glossary_term['acronym'] ) . '</bdi>' : ''; ?></p></header>
		<section><h2>تعریف</h2><div class="ab-layer ab-layer-source"><span>واژه‌نامهٔ تأییدشده</span><p><?php echo $render_segments( $glossary_term['snippet_segments'] ); ?></p></div>
		<?php if ( $glossary_term['alternatives_fa'] ) : ?><p class="ab-lead">نام‌های دیگر: <?php echo esc_html( implode( '، ', $glossary_term['alternatives_fa'] ) ); ?></p><?php endif; ?>
		<?php if ( $glossary_term['source_documents'] ) : ?><p class="ab-card-meta">منابع: <bdi><?php echo esc_html( implode( '، ', $glossary_term['source_documents'] ) ); ?></bdi></p><?php endif; ?>
		<p class="ab-card-meta"><bdi><?php echo esc_html( $glossary_term['concept_id'] ); ?></bdi></p></section></article>
		<?php else :
			$glossary_catalog = KBK_AI_Book::catalog();
			$glossary_terms   = $glossary_catalog ? $glossary_catalog->glossary_terms() : array();
			?>
			<?php if ( null !== $requested_concept ) : ?>
			<header class="ab-page-hero"><p class="ab-kicker">یافت نشد</p><h1>این واژه در واژه‌نامه موجود نیست</h1><p class="ab-lead">شناسهٔ مفهوم درخواستی با هیچ واژهٔ تأییدشده‌ای مطابقت ندارد.</p><div class="ab-actions"><a class="ab-button ab-button-primary" href="<?php echo esc_url( home_url( '/ai-book/glossary/' ) ); ?>">فهرست واژه‌ها</a></div></header>
			<?php elseif ( ! $glossary_catalog || array() === $glossary_terms ) : ?>
			<header class="ab-page-hero"><p class="ab-kicker">وضعیت داده</p><h1>واژه‌نامه موقتاً در دسترس نیست</h1><p class="ab-lead">پیکربندی منبع اصلی کتاب کامل نشده یا اعتبار داده تأیید نشده است.</p><p><bdi><?php echo esc_html( KBK_AI_Book::catalog_status() ); ?></bdi></p></header>
			<?php else : ?>
			<header class="ab-page-hero"><p class="ab-kicker">واژه‌نامه · <?php echo esc_html( count( $glossary_terms ) ); ?> واژه</p><h1>واژه‌نامهٔ کتاب</h1><p class="ab-lead">واژه‌های تأییدشدهٔ فارسی با معادل انگلیسی و سرواژه؛ مرتب بر پایهٔ واژهٔ فارسی.</p></header>
			<section class="ab-section"><nav class="ab-term-list" aria-label="فهرست واژه‌ها"><?php foreach ( $glossary_terms as $term_row ) : ?><a href="<?php echo esc_url( home_url( '/ai-book/glossary/?' . KBK_AI_Book::CONCEPT_QUERY_VAR . '=' . rawurlencode( $term_row['concept_id'] ) ) ); ?>"><b><?php echo esc_html( $term_row['preferred_fa'] ); ?></b><bdi><?php echo '' !== $term_row['acronym'] ? esc_html( $term_row['acronym'] ) : esc_html( $term_row['english'] ); ?></bdi></a><?php endforeach; ?></nav></section>
			<?php endif; ?>
		<?php endif; ?>
	<?php elseif ( 'sources' === $view ) :
		$sources_catalog = KBK_AI_Book::catalog();
		$sources         = $sources_catalog ? $sources_catalog->sources() : array();
		?>
		<?php if ( ! $sources_catalog || array() === $sources ) : ?>
		<header class="ab-page-hero"><p class="ab-kicker">وضعیت داده</p><h1>فهرست منابع موقتاً در دسترس نیست</h1><p class="ab-lead">پیکربندی منبع اصلی کتاب کامل نشده یا اعتبار داده تأیید نشده است.</p><p><bdi><?php echo esc_html( KBK_AI_Book::catalog_status() ); ?></bdi></p></header>
		<?php else : ?>
		<header class="ab-page-hero"><p class="ab-kicker">مستندات پایه · <?php echo esc_html( count( $sources ) ); ?> سند</p><h1>منابع کتاب</h1><p class="ab-lead">هجده سند اصلی ممیزی‌شده؛ هر فصل کتاب به سند و صفحات منبع خود ارجاع می‌دهد.</p></header>
		<section class="ab-section"><div class="ab-results">
			<?php foreach ( $sources as $source ) : ?>
			<article class="ab-card"><span class="ab-card-meta"><?php echo esc_html( $source['doc_key'] ); ?></span><h3><?php echo esc_html( $source['title'] ); ?></h3><p><?php echo esc_html( trim( $source['organization'] . ( $source['publication_date'] ? ' · ' . $source['publication_date'] : '' ) . ( $source['publication_type'] ? ' · ' . $source['publication_type'] : '' ) . ( $source['publication_status'] ? ' · ' . $source['publication_status'] : '' ) ) ); ?></p><p class="ab-card-meta"><?php echo esc_html( $source['sections_count'] ); ?> بخش از کتاب · <?php echo esc_html( $source['pages'] ); ?> صفحه<?php echo '' !== $source['doi_url'] ? ' · <bdi><a href="' . esc_url( $source['doi_url'] ) . '">DOI</a></bdi>' : ''; ?></p><span class="ab-card-meta"><bdi><?php echo esc_html( substr( $source['sha256'], 0, 16 ) ); ?>…</bdi></span></article>
			<?php endforeach; ?>
		</div></section>
		<?php endif; ?>
	<?php elseif ( 'templates' === $view ) :
		$templates_catalog = KBK_AI_Book::catalog();
		$templates         = $templates_catalog ? $templates_catalog->templates() : array();
		$template_detail   = $templates_catalog ? $templates_catalog->template( (string) KBK_AI_Book::requested_template() ) : null;
		?>
		<?php if ( ! $templates_catalog || array() === $templates ) : ?>
		<header class="ab-page-hero"><p class="ab-kicker">وضعیت داده</p><h1>قالب‌ها موقتاً در دسترس نیستند</h1><p class="ab-lead">پیکربندی منبع اصلی کتاب کامل نشده یا اعتبار داده تأیید نشده است.</p><p><bdi><?php echo esc_html( KBK_AI_Book::catalog_status() ); ?></bdi></p></header>
		<?php elseif ( null !== $template_detail ) : ?>
		<nav class="ab-breadcrumb" aria-label="مسیر"><a href="<?php echo esc_url( home_url( '/ai-book/' ) ); ?>">کتاب</a><a href="<?php echo esc_url( home_url( '/ai-book/templates/' ) ); ?>">قالب‌ها</a><span aria-current="page"><?php echo esc_html( $template_detail['template_id'] ); ?></span></nav>
		<article class="ab-prose"><header class="ab-page-hero"><p class="ab-kicker">قالب · <?php echo esc_html( $template_detail['template_id'] ); ?></p><h1><?php echo esc_html( $template_detail['title_fa'] ); ?></h1><p class="ab-lead"><?php echo esc_html( $template_detail['note_fa'] ); ?></p></header>
		<section><h2>فیلدهای قالب</h2><?php foreach ( $template_detail['fields'] as $field ) : ?><div class="ab-layer ab-layer-editorial"><span><bdi><?php echo esc_html( $field['name_en'] ); ?></bdi></span><p><b><?php echo esc_html( $field['name_fa'] ); ?></b> — <?php echo esc_html( $field['description_fa'] ); ?><?php echo '' !== $field['example_fa'] ? '<br>نمونه: ' . esc_html( $field['example_fa'] ) : ''; ?></p></div><?php endforeach; ?>
		<?php if ( $template_detail['derived_from'] ) : ?><p class="ab-card-meta">برگرفته از: <bdi><?php echo esc_html( implode( '، ', $template_detail['derived_from'] ) ); ?></bdi></p><?php endif; ?></section></article>
		<?php else : ?>
		<header class="ab-page-hero"><p class="ab-kicker">قالب‌های کاربردی · <?php echo esc_html( count( $templates ) ); ?> قالب</p><h1>قالب‌های کتاب</h1><p class="ab-lead">نمونه‌های پیشنهادی تدوینگر برای پیاده‌سازی حاکمیت هوش مصنوعی در سازمان.</p></header>
		<section class="ab-section"><div class="ab-results">
			<?php foreach ( $templates as $template_card ) : ?>
			<a class="ab-card" href="<?php echo esc_url( home_url( '/ai-book/templates/?' . KBK_AI_Book::TEMPLATE_QUERY_VAR . '=' . rawurlencode( $template_card['template_id'] ) ) ); ?>"><span class="ab-card-meta"><?php echo esc_html( $template_card['template_id'] ); ?></span><h3><?php echo esc_html( $template_card['title_fa'] ); ?></h3><p><?php echo esc_html( count( $template_card['fields'] ) ); ?> فیلد</p><span class="ab-card-link">مشاهده قالب</span></a>
			<?php endforeach; ?>
		</div></section>
		<?php endif; ?>
	<?php elseif ( 'concepts' === $view ) :
		$concepts_catalog = KBK_AI_Book::catalog();
		$entity_detail    = $concepts_catalog ? $concepts_catalog->entity( (string) KBK_AI_Book::requested_entity() ) : null;
		?>
		<?php if ( ! $concepts_catalog ) : ?>
		<header class="ab-page-hero"><p class="ab-kicker">وضعیت داده</p><h1>مفاهیم موقتاً در دسترس نیستند</h1><p class="ab-lead">پیکربندی منبع اصلی کتاب کامل نشده یا اعتبار داده تأیید نشده است.</p><p><bdi><?php echo esc_html( KBK_AI_Book::catalog_status() ); ?></bdi></p></header>
		<?php elseif ( null !== $entity_detail ) : ?>
		<nav class="ab-breadcrumb" aria-label="مسیر"><a href="<?php echo esc_url( home_url( '/ai-book/' ) ); ?>">کتاب</a><a href="<?php echo esc_url( home_url( '/ai-book/concepts/' ) ); ?>">مفاهیم</a><span aria-current="page"><?php echo esc_html( $entity_detail['label_fa'] !== '' ? $entity_detail['label_fa'] : $entity_detail['label_en'] ); ?></span></nav>
		<article class="ab-prose"><header class="ab-page-hero"><p class="ab-kicker">مفهوم · <?php echo esc_html( $entity_detail['type'] ); ?></p><h1><?php echo esc_html( '' !== $entity_detail['label_fa'] ? $entity_detail['label_fa'] : $entity_detail['label_en'] ); ?></h1><p class="ab-lead"><bdi><?php echo esc_html( $entity_detail['label_en'] ); ?></bdi></p></header>
		<section><h2>ارجاع در کتاب</h2><div class="ab-layer <?php echo esc_attr( 'editorial' === $entity_detail['origin'] ? 'ab-layer-editorial' : 'ab-layer-source' ); ?>"><span><?php echo esc_html( 'editorial' === $entity_detail['origin'] ? 'جمع‌بندی تدوینگر' : 'استخراج از منبع' ); ?></span>
		<?php if ( '' !== $entity_detail['definition_en'] ) : ?><p><bdi><?php echo esc_html( $entity_detail['definition_en'] ); ?></bdi></p><?php endif; ?>
		<?php if ( $entity_detail['documents'] ) : ?><p class="ab-card-meta">اسناد: <bdi><?php echo esc_html( implode( '، ', $entity_detail['documents'] ) ); ?></bdi></p><?php endif; ?></div>
		<?php if ( $entity_detail['sections'] ) : ?><p class="ab-lead"><?php echo esc_html( $entity_detail['mentions_total'] ); ?> بخش از کتاب به این مفهوم ارجاع می‌دهد<?php echo $entity_detail['mentions_total'] > count( $entity_detail['sections'] ) ? '؛ نمایش اولین ' . esc_html( count( $entity_detail['sections'] ) ) . ' بخش' : ''; ?>:</p><div class="ab-results"><?php foreach ( $entity_detail['sections'] as $mention ) : ?><a class="ab-card" href="<?php echo esc_url( $read_link( $mention['part_id'], $mention['chapter_id'], $mention['structural_id'] ) ); ?>"><span class="ab-card-meta"><?php echo esc_html( $mention['part_id'] . ' · ' . $mention['chapter_id'] ); ?></span><h3><?php echo esc_html( $mention['title_fa'] ); ?></h3><p class="ab-card-meta"><bdi><?php echo esc_html( $mention['content_id'] ); ?></bdi> · <?php echo esc_html( $origin_map[ $mention['origin'] ]['label'] ); ?></p><span class="ab-card-link">مشاهده بخش</span></a><?php endforeach; ?></div><?php endif; ?>
		<p class="ab-card-meta"><bdi><?php echo esc_html( $entity_detail['entity_id'] ); ?></bdi></p></section></article>
		<?php else :
			$entity_type   = KBK_AI_Book::requested_entity_type();
			$entities_page = $concepts_catalog->entities( $entity_type, KBK_AI_Book::requested_page() );
			$entity_types  = $concepts_catalog->entity_types();
			?>
			<?php if ( array() === $entities_page['items'] ) : ?>
			<header class="ab-page-hero"><p class="ab-kicker">یافت نشد</p><h1>این دسته از مفاهیم موجود نیست</h1><p class="ab-lead">نوع درخواستی با هیچ مفهومی مطابقت ندارد یا شمارهٔ صفحه بیرون از محدوده است.</p><div class="ab-actions"><a class="ab-button ab-button-primary" href="<?php echo esc_url( home_url( '/ai-book/concepts/' ) ); ?>">همهٔ مفاهیم</a></div></header>
			<?php else : ?>
			<header class="ab-page-hero"><p class="ab-kicker">مفاهیم · <?php echo esc_html( $entities_page['total'] ); ?> مورد<?php echo null !== $entity_type ? ' · نوع ' . esc_html( $entity_type ) : ''; ?></p><h1>مفاهیم کتاب</h1><p class="ab-lead">موجودیت‌های استخراج‌شده از منابع و تدوین، با ارجاع به بخش‌های کتاب.</p></header>
			<nav class="ab-breadcrumb" aria-label="فیلتر نوع"><a href="<?php echo esc_url( home_url( '/ai-book/concepts/' ) ); ?>"<?php echo null === $entity_type ? ' aria-current="page"' : ''; ?>>همه</a><?php foreach ( $entity_types as $type_name => $type_count ) : ?><a href="<?php echo esc_url( home_url( '/ai-book/concepts/?' . KBK_AI_Book::TYPE_QUERY_VAR . '=' . rawurlencode( $type_name ) ) ); ?>"<?php echo $type_name === $entity_type ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $type_name ); ?> (<?php echo esc_html( $type_count ); ?>)</a><?php endforeach; ?></nav>
			<section class="ab-section"><div class="ab-results">
				<?php foreach ( $entities_page['items'] as $entity_card ) : ?>
				<a class="ab-card" href="<?php echo esc_url( home_url( '/ai-book/concepts/?' . KBK_AI_Book::ENTITY_QUERY_VAR . '=' . rawurlencode( $entity_card['entity_id'] ) ) ); ?>"><span class="ab-card-meta"><?php echo esc_html( $entity_card['type'] ); ?></span><h3><?php echo esc_html( '' !== $entity_card['labels']['fa'] ? $entity_card['labels']['fa'] : $entity_card['labels']['en'] ); ?></h3><p><bdi><?php echo esc_html( $entity_card['labels']['en'] ); ?></bdi></p><span class="ab-card-meta"><?php echo esc_html( $entity_card['mentions_count'] ); ?> ارجاع</span></a>
				<?php endforeach; ?>
			</div>
			<?php if ( $entities_page['pages'] > 1 ) : ?><nav class="ab-prev-next" aria-label="صفحه‌بندی"><?php if ( $entities_page['page'] > 1 ) : ?><a href="<?php echo esc_url( home_url( '/ai-book/concepts/?' . KBK_AI_Book::TYPE_QUERY_VAR . '=' . rawurlencode( (string) $entity_type ) . '&' . KBK_AI_Book::PAGE_QUERY_VAR . '=' . ( $entities_page['page'] - 1 ) ) ); ?>">« صفحهٔ قبل</a><?php else : ?><span></span><?php endif; ?><span>صفحهٔ <?php echo esc_html( $entities_page['page'] ); ?> از <?php echo esc_html( $entities_page['pages'] ); ?></span><?php if ( $entities_page['page'] < $entities_page['pages'] ) : ?><a href="<?php echo esc_url( home_url( '/ai-book/concepts/?' . KBK_AI_Book::TYPE_QUERY_VAR . '=' . rawurlencode( (string) $entity_type ) . '&' . KBK_AI_Book::PAGE_QUERY_VAR . '=' . ( $entities_page['page'] + 1 ) ) ); ?>">صفحهٔ بعد »</a><?php endif; ?></nav><?php endif; ?>
			</section>
			<?php endif; ?>
		<?php endif; ?>
	<?php elseif ( 'graph' === $view ) :
		$graph_engine = KBK_AI_Book::graph();
		$graph_node   = $graph_engine ? $graph_engine->node( (string) KBK_AI_Book::requested_entity() ) : null;
		?>
		<?php if ( ! $graph_engine ) : ?>
		<header class="ab-page-hero"><p class="ab-kicker">وضعیت داده</p><h1>نگارهٔ مفاهیم موقتاً در دسترس نیست</h1><p class="ab-lead">پیکربندی منبع اصلی کتاب کامل نشده یا اعتبار داده تأیید نشده است.</p><p><bdi><?php echo esc_html( KBK_AI_Book::graph_status() ); ?></bdi></p></header>
		<?php elseif ( null !== $graph_node ) :
			$graph_hood = $graph_engine->neighborhood( $graph_node['entity_id'] );
			$hood_edges = $graph_hood ? $graph_hood['edges'] : array();
			$viz_edges  = array_slice( $hood_edges, 0, KBK_AI_Book_Graph::MAX_VIZ_NODES );
			$viz_count  = max( 1, count( $viz_edges ) );
			?>
		<nav class="ab-breadcrumb" aria-label="مسیر"><a href="<?php echo esc_url( home_url( '/ai-book/' ) ); ?>">کتاب</a><a href="<?php echo esc_url( home_url( '/ai-book/graph/' ) ); ?>">نگارهٔ مفاهیم</a><span aria-current="page"><?php echo esc_html( '' !== $graph_node['label_fa'] ? $graph_node['label_fa'] : $graph_node['label_en'] ); ?></span></nav>
		<article class="ab-prose"><header class="ab-page-hero"><p class="ab-kicker">گره · <?php echo esc_html( $graph_node['type'] ); ?></p><h1><?php echo esc_html( '' !== $graph_node['label_fa'] ? $graph_node['label_fa'] : $graph_node['label_en'] ); ?></h1><p class="ab-lead"><bdi><?php echo esc_html( $graph_node['label_en'] ); ?></bdi></p></header>
		<section><h2>همسایگی در نگاره</h2><div class="ab-layer <?php echo esc_attr( 'editorial' === $graph_node['origin'] ? 'ab-layer-editorial' : 'ab-layer-source' ); ?>"><span><?php echo esc_html( 'editorial' === $graph_node['origin'] ? 'جمع‌بندی تدوینگر' : 'استخراج از منبع' ); ?></span><?php if ( '' !== $graph_node['definition_en'] ) : ?><p><bdi><?php echo esc_html( $graph_node['definition_en'] ); ?></bdi></p><?php endif; ?><?php if ( $graph_node['documents'] ) : ?><p class="ab-card-meta">اسناد: <bdi><?php echo esc_html( implode( '، ', $graph_node['documents'] ) ); ?></bdi></p><?php endif; ?></div>
		<?php if ( $hood_edges ) : ?>
		<div class="ab-graph-canvas" role="img" aria-label="نمودار همسایگی؛ فهرست متنی زیر مرجع کامل است"><svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><?php foreach ( $viz_edges as $viz_index => $viz_edge ) : $angle = ( $viz_index / $viz_count ) * 2 * M_PI; $x = 50 + 38 * cos( $angle ); $y = 50 + 38 * sin( $angle ); ?><line x1="50" y1="50" x2="<?php echo esc_attr( round( $x, 1 ) ); ?>" y2="<?php echo esc_attr( round( $y, 1 ) ); ?>" /><?php endforeach; ?></svg><span class="ab-node ab-node-main" style="top:50%;right:auto;left:50%;transform:translate(-50%,-50%)"><?php echo esc_html( '' !== $graph_node['label_fa'] ? $graph_node['label_fa'] : $graph_node['label_en'] ); ?></span><?php foreach ( $viz_edges as $viz_index => $viz_edge ) : if ( null === $viz_edge['other'] ) { continue; } $angle = ( $viz_index / $viz_count ) * 2 * M_PI; $x = 50 + 38 * cos( $angle ); $y = 50 + 38 * sin( $angle ); ?><a class="ab-node" style="top:<?php echo esc_attr( round( $y, 1 ) ); ?>%;left:<?php echo esc_attr( round( $x, 1 ) ); ?>%;transform:translate(-50%,-50%)" href="<?php echo esc_url( home_url( '/ai-book/graph/?' . KBK_AI_Book::ENTITY_QUERY_VAR . '=' . rawurlencode( $viz_edge['other']['entity_id'] ) ) ); ?>"><?php echo esc_html( '' !== $viz_edge['other']['label_fa'] ? $viz_edge['other']['label_fa'] : $viz_edge['other']['label_en'] ); ?></a><?php endforeach; ?></div>
		<p class="ab-card-meta"><?php echo esc_html( $graph_hood['total'] ); ?> یال مستقیم<?php echo $graph_hood['total'] > count( $hood_edges ) ? '؛ نمایش ' . esc_html( count( $hood_edges ) ) . ' یال' : ''; ?></p>
		<div class="ab-results"><?php foreach ( $hood_edges as $hood_edge ) : ?><div class="ab-card"><span class="ab-card-meta"><?php echo esc_attr( $hood_edge['direction_out'] ? $hood_edge['relation'] . ' ←' : '→ ' . $hood_edge['relation'] ); ?></span><?php if ( null !== $hood_edge['other'] ) : ?><h3><a href="<?php echo esc_url( home_url( '/ai-book/graph/?' . KBK_AI_Book::ENTITY_QUERY_VAR . '=' . rawurlencode( $hood_edge['other']['entity_id'] ) ) ); ?>"><?php echo esc_html( '' !== $hood_edge['other']['label_fa'] ? $hood_edge['other']['label_fa'] : $hood_edge['other']['label_en'] ); ?></a></h3><p><bdi><?php echo esc_html( $hood_edge['other']['label_en'] ); ?></bdi> · <?php echo esc_html( $hood_edge['other']['type'] ); ?></p><?php endif; ?><?php if ( '' !== $hood_edge['quote'] ) : ?><p>شاهد: «<?php echo esc_html( $hood_edge['quote'] ); ?>»</p><?php endif; ?><?php if ( $hood_edge['anchor_section'] ) : ?><span class="ab-card-meta"><a href="<?php echo esc_url( $read_link( $hood_edge['anchor_section']['part_id'], $hood_edge['anchor_section']['chapter_id'], $hood_edge['anchor_section']['structural_id'] ) ); ?>"><?php echo esc_html( $hood_edge['anchor_section']['title_fa'] ); ?></a> · <bdi><?php echo esc_html( $hood_edge['anchor_section']['content_id'] ); ?></bdi></span><?php endif; ?></div><?php endforeach; ?></div>
		<?php else : ?><div class="ab-fallback"><p>این گره در نگارهٔ فعلی یال مستقیمی ندارد.</p></div><?php endif; ?>
		<p class="ab-card-meta"><bdi><?php echo esc_html( $graph_node['entity_id'] ); ?></bdi></p></section></article>
		<?php else :
			$graph_stats = $graph_engine->stats();
			$graph_relations = $graph_engine->relation_counts();
			?>
		<header class="ab-page-hero"><p class="ab-kicker">نگارهٔ مفاهیم · <?php echo esc_html( $graph_stats['nodes'] ); ?> گره · <?php echo esc_html( $graph_stats['edges'] ); ?> یال</p><h1>نگارهٔ مفاهیم کتاب</h1><p class="ab-lead">شبکهٔ روابط میان مفاهیم، ریسک‌ها، چارچوب‌ها و اسناد؛ برای گره‌ها از فهرست مفاهیم شروع کنید.</p><div class="ab-actions"><a class="ab-button ab-button-primary" href="<?php echo esc_url( home_url( '/ai-book/concepts/' ) ); ?>">فهرست مفاهیم</a></div></header>
		<section class="ab-section"><h2>نوع روابط</h2><div class="ab-term-list"><?php foreach ( $graph_relations as $relation_name => $relation_count ) : ?><span><b><bdi><?php echo esc_html( $relation_name ); ?></bdi></b><bdi><?php echo esc_html( $relation_count ); ?> یال</bdi></span><?php endforeach; ?></div></section>
		<?php endif; ?>
	<?php elseif ( 'ask' === $view ) :
		$ask_state = KBK_AI_Book::current_ask();
		$ask_status = KBK_AI_Book::ask_status();
		?>
		<header class="ab-page-hero"><p class="ab-kicker">پرسش با استناد</p><h1>از کتاب بپرسید</h1><p class="ab-lead">پاسخ‌ها فقط از متن اصلی همین کتاب ساخته می‌شوند و به بخش‌های کتاب ارجاع می‌دهند.</p></header>
		<form class="ab-ask-form" method="get" action="<?php echo esc_url( home_url( '/ai-book/ask/' ) ); ?>"><label for="ab-ask-q">پرسش شما (حداقل دو واژه)</label><div><input id="ab-ask-q" type="search" name="<?php echo esc_attr( KBK_AI_Book::SEARCH_QUERY_VAR ); ?>" value="<?php echo esc_attr( (string) $ask_state['query'] ); ?>" maxlength="120" dir="auto" required><button class="ab-button ab-button-primary" type="submit">پرسش</button></div><p><?php echo 'READY' === $ask_status ? 'موتور پاسخ پیکربندی شده است.' : 'موتور تولید پاسخ هنوز پیکربندی نشده است؛ گذرواژه‌ها به‌صورت محلی نگه‌داری می‌شوند و این‌جا وارد نمی‌شوند. با این حال، بازیابی ارجاع‌دار انجام می‌شود.'; ?></p></form>
		<?php if ( 'ARTIFACT_INVALID' === $ask_state['status'] ) : ?>
		<section class="ab-section"><div class="ab-fallback"><p>پیکرهٔ پرسش‌وپاسخ موقتاً نامعتبر است.</p></div></section>
		<?php elseif ( null !== $ask_state['query'] ) : ?>
		<section class="ab-section">
			<?php if ( null !== $ask_state['answer'] ) : ?>
			<div class="ab-message ab-message-answer"><span class="ab-kicker">پاسخ</span><p><?php echo esc_html( $ask_state['answer']['text'] ); ?></p><?php if ( '' !== $ask_state['answer']['model'] ) : ?><p class="ab-card-meta">مدل: <bdi><?php echo esc_html( $ask_state['answer']['model'] ); ?></bdi></p><?php endif; ?></div>
			<?php elseif ( null !== $ask_state['provider_error'] ) : ?>
			<div class="ab-fallback"><p>تولید پاسخ در دسترس نبود؛ گذرواژه‌های بازیابی‌شده در ادامه ارجاع دارند.</p></div>
			<?php endif; ?>
			<?php if ( $ask_state['retrieval'] ) : ?>
			<h2>گذرواژه‌های بازیابی‌شده با استناد</h2><div class="ab-citations">
			<?php foreach ( $ask_state['retrieval'] as $ask_item ) : ?>
			<div class="ab-card"><span class="ab-card-meta"><?php echo esc_html( $ask_item['part_id'] . ' · ' . $ask_item['chapter_id'] ); ?> · <?php echo esc_html( $origin_map[ $ask_item['origin'] ]['label'] ); ?></span><h3><?php echo esc_html( $ask_item['section_fa'] ); ?></h3><p><?php echo $render_segments( $ask_item['excerpt_segments'] ); ?><?php echo $ask_item['reader_resolvable'] ? '' : ' <bdi>(ارجاع در دسترس نیست)</bdi>'; ?></p><?php if ( $ask_item['reader_resolvable'] ) : ?><a class="ab-card-link" href="<?php echo esc_url( $read_link( $ask_item['part_id'], $ask_item['chapter_id'], $ask_item['structural_id'] ) ); ?>">مطالعهٔ بخش مستند</a><?php endif; ?><span class="ab-card-meta"><bdi><a href="<?php echo esc_url( $read_link( $ask_item['part_id'], $ask_item['chapter_id'], $ask_item['structural_id'] ) ); ?>"><?php echo esc_html( $ask_item['content_id'] ); ?></a></bdi></span></div>
			<?php endforeach; ?>
			</div>
			<?php else : ?>
			<div class="ab-fallback"><p>برای این پرسش گذرواژهٔ مستند مرتبطی یافت نشد.</p></div>
			<?php endif; ?>
		</section>
		<?php elseif ( 'PROVIDER_REQUIRED' === $ask_status || 'READY' === $ask_status ) : ?>
		<section class="ab-section"><div class="ab-fallback"><p>برای شروع، پرسش خود را بنویسید؛ پاسخ‌ها همیشه به بخش‌های کتاب استناد می‌دهند.</p></div></section>
		<?php endif; ?>
	<?php elseif ( 'pdf' === $view ) :
		$pdf_engine = KBK_AI_Book::pdf();
		$pdf_meta   = null === $pdf_engine ? null : $pdf_engine->meta();
		$pdf_status = KBK_AI_Book::pdf_status();
		$pdf_stream = home_url( KBK_AI_Book_Pdf::STREAM_PATH );
		?>
		<header class="ab-page-hero"><p class="ab-kicker">نسخهٔ PDF · <?php echo esc_html( null !== $pdf_meta ? $pdf_meta['edition'] : '' ); ?></p><h1>نسخهٔ PDF کتاب</h1><p class="ab-lead">همان متن اصلی در قالب کتاب؛ نمایش در مرورگر شما و بدون سرویس شخص ثالث.</p></header>
		<?php if ( null !== $pdf_meta ) : ?>
		<div class="ab-pdf">
			<div class="ab-pdf-toolbar"><span>نوار ابزار تعاملی PDF.js در فاز بعدی فعال می‌شود؛ تا آن زمان مرورگر شما فایل را مستقیم نمایش می‌دهد.</span><a class="ab-button" href="<?php echo esc_url( $pdf_stream ); ?>">دانلود فایل</a></div>
			<div class="ab-pdf-stage"><object class="ab-pdf-embed" type="application/pdf" data="<?php echo esc_url( $pdf_stream ); ?>" aria-label="نمایش نسخهٔ PDF کتاب"><div class="ab-fallback"><p>مرورگر شما نمایش PDF داخلی ندارد.</p><p><a href="<?php echo esc_url( $pdf_stream ); ?>">دانلود نسخهٔ PDF</a> · یا <a href="<?php echo esc_url( home_url( '/ai-book/read/' ) ); ?>">مطالعهٔ متنی همان محتوا</a></p></div></object></div>
			<aside><h2>دربارهٔ نسخه</h2><dl><dt>ویرایش</dt><dd><?php echo esc_html( $pdf_meta['edition'] ); ?></dd><dt>وضعیت امضا</dt><dd>امضای توسعه؛ امضای ناشر لازم است</dd><dt>ریشهٔ مرکل</dt><dd><bdi><?php echo esc_html( substr( $pdf_meta['merkle_root'], 0, 8 ) ); ?>…<?php echo esc_html( substr( $pdf_meta['merkle_root'], -7 ) ); ?></bdi></dd><dt>اثر انگشت SHA-256</dt><dd><bdi><?php echo esc_html( substr( $pdf_meta['sha256'], 0, 8 ) ); ?>…<?php echo esc_html( substr( $pdf_meta['sha256'], -7 ) ); ?></bdi></dd><dt>حجم</dt><dd><?php echo esc_html( number_format_i18n( $pdf_meta['bytes'] ) ); ?> بایت</dd><dt>ساخت</dt><dd><bdi><?php echo esc_html( $pdf_meta['build_date'] ); ?></bdi></dd></dl><a class="ab-button ab-button-primary" href="<?php echo esc_url( home_url( '/ai-book/request-pdf/' ) ); ?>">درخواست نسخهٔ شخصی</a></aside>
		</div>
		<?php else : ?>
		<section class="ab-section"><div class="ab-fallback"><p><?php echo 'CONFIG_REQUIRED' === $pdf_status ? 'نسخهٔ PDF هنوز پیکربندی نشده است.' : 'نسخهٔ PDF موقتاً در دسترس نیست؛ یکپارچگی فایل تأیید نشد.'; ?></p><p>تا آن زمان می‌توانید <a href="<?php echo esc_url( home_url( '/ai-book/read/' ) ); ?>">همان محتوا را متنی بخوانید</a>.</p></div></section>
		<?php endif; ?>
	<?php elseif ( 'request-pdf' === $view ) :
		$request_state = KBK_AI_Book::current_request();
		$request_provider = $request_state['provider'];
		$error_labels = array(
			'name'    => 'نام',
			'email'   => 'نشانی رایانامه',
			'use'     => 'نوع استفاده',
			'reason'  => 'دلیل درخواست',
			'consent' => 'رضایت ثبت اطلاعات',
			'session' => 'نشست ارسال فرم',
		);
		$code_labels  = array(
			'REQUIRED' => 'الزامی است',
			'INVALID'  => 'نامعتبر است',
			'BOUNDED'  => 'بیش از حد مجاز است',
			'EXPIRED'  => 'منقضی شده؛ دوباره تلاش کنید',
		);
		?>
		<header class="ab-page-hero"><p class="ab-kicker">درخواست PDF</p><h1>درخواست نسخهٔ شخصی‌سازی‌شده</h1><p class="ab-lead">هیچ داده‌ای در همین سایت ذخیره نمی‌شود؛ درخواست شما فقط پس از پیکربندی سرویس صدور ارسال می‌شود.</p></header>
		<div class="ab-form-layout">
			<div>
			<?php if ( 'SUBMITTED' === $request_state['status'] && null !== $request_state['reference'] ) : ?>
			<div class="ab-message"><span class="ab-kicker">ثبت شد</span><p>درخواست شما با کد رهگیری <bdi><?php echo esc_html( $request_state['reference'] ); ?></bdi> ثبت شد. پاسخ از طریق رایانامه اعلام می‌شود.</p></div>
			<?php elseif ( 'PROVIDER_UNAVAILABLE' === $request_state['status'] ) : ?>
			<div class="ab-fallback"><p>سرویس صدور در دسترس نبود؛ لطفاً بعداً دوباره تلاش کنید.</p></div>
			<?php elseif ( 'PROVIDER_INVALID' === $request_state['status'] ) : ?>
			<div class="ab-fallback"><p>پاسخ سرویس صدور نامعتبر بود؛ درخواست شما ثبت نشد.</p></div>
			<?php elseif ( 'REJECTED' === $request_state['status'] ) : ?>
			<div class="ab-fallback"><p>درخواست شما پذیرفته نشد؛ برای بررسی با ما در تماس باشید.</p></div>
			<?php elseif ( $request_state['errors'] ) : ?>
			<div class="ab-fallback"><p>چند مورد نیاز به اصلاح دارد:</p><ul><?php foreach ( $request_state['errors'] as $request_error ) : ?><li><?php echo esc_html( ( $error_labels[ $request_error['field'] ] ?? $request_error['field'] ) . ' ' . ( $code_labels[ $request_error['code'] ] ?? $request_error['code'] ) ); ?></li><?php endforeach; ?></ul></div>
			<?php elseif ( ! $request_provider ) : ?>
			<div class="ab-fallback"><p>سرویس صدور نسخهٔ شخصی هنوز پیکربندی نشده است؛ فرم فعلاً ارسال نمی‌شود.</p></div>
			<?php endif; ?>
			<form class="ab-request-form" method="post" action="<?php echo esc_url( home_url( '/ai-book/request-pdf/' ) ); ?>"><?php wp_nonce_field( KBK_AI_Book_Request::NONCE_ACTION, KBK_AI_Book_Request::NONCE_FIELD ); ?><div class="ab-form-grid"><label for="ab-req-name">نام (اختیاری)</label><input id="ab-req-name" type="text" name="name" value="<?php echo esc_attr( (string) $request_state['reposted']['name'] ); ?>" maxlength="80"><label for="ab-req-email">نشانی رایانامه</label><input id="ab-req-email" type="email" name="email" value="<?php echo esc_attr( (string) $request_state['reposted']['email'] ); ?>" maxlength="120" required><label class="ab-span-2" for="ab-req-use">نوع استفاده</label><select class="ab-span-2" id="ab-req-use" name="use"><option value="personal"<?php echo 'personal' === $request_state['reposted']['use'] ? ' selected' : ''; ?>>مطالعهٔ شخصی</option><option value="education"<?php echo 'education' === $request_state['reposted']['use'] ? ' selected' : ''; ?>>آموزش سازمانی</option><option value="research"<?php echo 'research' === $request_state['reposted']['use'] ? ' selected' : ''; ?>>پژوهش و استناد</option></select><label class="ab-span-2" for="ab-req-reason">دلیل درخواست (اختیاری)</label><textarea class="ab-span-2" id="ab-req-reason" name="reason" rows="4" maxlength="500"><?php echo esc_textarea( (string) $request_state['reposted']['reason'] ); ?></textarea></div><label class="ab-consent"><input type="checkbox" name="consent" value="1"<?php echo '1' === $request_state['reposted']['consent'] ? ' checked' : ''; ?> required> اطلاعات بالا برای بررسی و صدور نسخه ثبت شود.</label><button class="ab-button ab-button-primary" type="submit"<?php echo $request_provider ? '' : ' disabled'; ?>>ثبت درخواست</button></form>
			</div>
			<aside class="ab-process"><h2>مسیر درخواست</h2><ol><li class="is-active">ثبت درخواست</li><li>اعتبارسنجی تماس</li><li>بررسی</li><li>تأیید و شخصی‌سازی</li><li>صدور قابل رهگیری</li></ol><p>درخواست شما فقط برای ویرایش فعال کتاب پردازش می‌شود و هیچ داده‌ای در همین سایت باقی نمی‌ماند.</p></aside>
		</div>
	<?php elseif ( $selection['part_not_found'] ) : ?>
		<header class="ab-page-hero"><p class="ab-kicker">یافت نشد</p><h1>این بخش از کتاب موجود نیست</h1><p class="ab-lead">شناسهٔ درخواستی با هیچ‌یک از هفت بخش اصلی کتاب مطابقت ندارد.</p><div class="ab-actions"><a class="ab-button ab-button-primary" href="<?php echo esc_url( home_url( '/ai-book/' ) ); ?>">بازگشت به فهرست بخش‌ها</a></div></header>
	<?php elseif ( $part && $chapter ) :
		$related_map = array();
		$graph_engine = KBK_AI_Book::graph();
		if ( null !== $graph_engine ) {
			$related_map = $graph_engine->related_map( array_column( $chapter['sections'], 'content_id' ) );
		}
		?>
		<nav class="ab-breadcrumb" aria-label="مسیر"><a href="<?php echo esc_url( home_url( '/ai-book/' ) ); ?>">کتاب</a><a href="<?php echo esc_url( home_url( '/ai-book/read/?' . KBK_AI_Book::PART_QUERY_VAR . '=' . rawurlencode( $part['part_id'] ) ) ); ?>"><?php echo esc_html( $part['title_fa'] ); ?></a><span aria-current="page"><?php echo esc_html( $chapter['title_fa'] ); ?></span></nav>
		<div class="ab-reader"><aside class="ab-toc" aria-label="فهرست فصل"><p class="ab-kicker"><?php echo esc_html( $part['part_id'] . ' · ' . $chapter['chapter_id'] ); ?></p><h2><?php echo esc_html( $chapter['title_fa'] ); ?></h2><?php foreach ( $chapter['sections'] as $index => $section ) : $is_current_section = ( null !== $selection['section'] && $selection['section']['structural_id'] === $section['structural_id'] ) || ( null === $selection['section'] && 0 === $index ); ?><a<?php echo $is_current_section ? ' class="is-current" aria-current="location"' : ''; ?> href="#<?php echo esc_attr( $section['structural_id'] ); ?>"><?php echo esc_html( KBK_AI_Book::reader_section_title( $section ) ); ?></a><?php endforeach; ?></aside>
		<article class="ab-prose"><?php if ( $selection['chapter_not_found'] ) : ?><p class="ab-lead">فصل درخواستی در این بخش یافت نشد؛ نخستین فصل بخش <bdi><?php echo esc_html( $part['title_fa'] ); ?></bdi> نمایش داده می‌شود.</p><?php endif; ?>
		<details class="ab-reader-index"><summary><span>فهرست این فصل</span><bdi><?php echo esc_html( $part['part_id'] . ' · ' . $chapter['chapter_id'] ); ?></bdi></summary><nav aria-label="فهرست فصل در نمایش فشرده"><?php foreach ( $chapter['sections'] as $index => $section ) : $is_current_section = ( null !== $selection['section'] && $selection['section']['structural_id'] === $section['structural_id'] ) || ( null === $selection['section'] && 0 === $index ); ?><a<?php echo $is_current_section ? ' class="is-current" aria-current="location"' : ''; ?> href="#<?php echo esc_attr( $section['structural_id'] ); ?>"><?php echo esc_html( KBK_AI_Book::reader_section_title( $section ) ); ?></a><?php endforeach; ?></nav></details>
		<header class="ab-page-hero ab-reader-hero"><p class="ab-kicker"><?php echo esc_html( $part['part_id'] . ' · ' . $chapter['chapter_id'] ); ?></p><h1><?php echo esc_html( $chapter['title_fa'] ); ?></h1><p class="ab-lead">متن اصلی فارسی با منشأ، شناسه و پیوند استناد پایدار.</p></header>
		<?php foreach ( $chapter['sections'] as $section ) : $origin = $origin_map[ $section['origin'] ]; $related = $related_map[ $section['content_id'] ] ?? null; ?>
		<section id="<?php echo esc_attr( $section['structural_id'] ); ?>"><h2><?php echo esc_html( KBK_AI_Book::reader_section_title( $section ) ); ?></h2><div class="ab-layer <?php echo esc_attr( $origin['class'] ); ?>"><span><?php echo esc_html( $origin['label'] ); ?></span><div class="ab-reader-content"><?php echo wp_kses_post( KBK_AI_Book::render_reader_markdown( KBK_AI_Book::reader_section_body( $section ) ) ); ?></div></div><div class="ab-section-reference"><span>شناسهٔ استناد</span><bdi><?php echo esc_html( $section['content_id'] ); ?></bdi><a href="<?php echo esc_url( $read_link( $part['part_id'], $chapter['chapter_id'], $section['structural_id'] ) ); ?>">پیوند مستقیم این بخش ↗</a></div>
		<?php if ( $related ) : ?>
		<nav class="ab-related-inline" aria-label="مطالعهٔ مرتبط"><div><h3>مفاهیم مرتبط</h3><ul class="ab-concept-links"><?php foreach ( $related['concepts'] as $related_concept ) : ?><li><a dir="auto" href="<?php echo esc_url( home_url( '/ai-book/concepts/?' . KBK_AI_Book::ENTITY_QUERY_VAR . '=' . rawurlencode( $related_concept['entity_id'] ) ) ); ?>"><?php echo esc_html( '' !== $related_concept['label_fa'] ? $related_concept['label_fa'] : $related_concept['label_en'] ); ?></a></li><?php endforeach; ?></ul></div>
		<?php if ( $related['sections'] ) : ?><div><h3>ادامهٔ مطالعه</h3><ul class="ab-section-links"><?php foreach ( $related['sections'] as $related_section ) : ?><li><a href="<?php echo esc_url( $read_link( $related_section['part_id'], $related_section['chapter_id'], $related_section['structural_id'] ) ); ?>"><bdi><?php echo esc_html( $related_section['title_fa'] ); ?></bdi><span aria-hidden="true">←</span></a></li><?php endforeach; ?></ul></div><?php endif; ?>
		</nav>
		<?php endif; ?>
		</section>
		<?php endforeach; ?>
		<nav class="ab-prev-next" aria-label="فصل قبل و بعد">
		<?php if ( $adjacent['prev'] ) : ?><a href="<?php echo esc_url( home_url( '/ai-book/read/?' . KBK_AI_Book::PART_QUERY_VAR . '=' . rawurlencode( $adjacent['prev']['part_id'] ?? $part['part_id'] ) . '&' . KBK_AI_Book::CHAPTER_QUERY_VAR . '=' . rawurlencode( $adjacent['prev']['chapter_id'] ) ) ); ?>">« <?php echo esc_html( $adjacent['prev']['title_fa'] ); ?></a><?php else : ?><span></span><?php endif; ?>
		<?php if ( $adjacent['next'] ) : ?><a href="<?php echo esc_url( home_url( '/ai-book/read/?' . KBK_AI_Book::PART_QUERY_VAR . '=' . rawurlencode( $adjacent['next']['part_id'] ?? $part['part_id'] ) . '&' . KBK_AI_Book::CHAPTER_QUERY_VAR . '=' . rawurlencode( $adjacent['next']['chapter_id'] ) ) ); ?>"><?php echo esc_html( $adjacent['next']['title_fa'] ); ?> »</a><?php endif; ?>
		</nav>
		</article></div>
	<?php endif; ?>
</main>
<footer class="ab-footer"><div><b><?php echo esc_html( $summary['title_fa'] ?? 'کتاب مرجع هوش مصنوعی' ); ?></b><p>ترجمه، تدوین و بازآرایی فارسی: محمدعلی کهن‌دژ</p></div><p class="ab-disclaimer">این اثر ترجمهٔ رسمی NIST نمی‌باشد و توسط محمدعلی کهن‌دژ جهت آموزش و آگاهی‌رسانی حاکمیت هوش مصنوعی طراحی و توسعه داده شده است.</p></footer>
<footer class="ab-site-footer blog-footer" aria-label="پاورقی سایت"></footer>
<?php wp_footer(); ?>
</body>
</html>
