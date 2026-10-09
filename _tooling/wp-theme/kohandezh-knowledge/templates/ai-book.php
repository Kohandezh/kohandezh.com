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
// Long chapters are served in section-bounded pages (KBK_AI_Book::chapter_pages).
$reader_page = ( 'read' === $view && $part && $chapter ) ? KBK_AI_Book::current_reader_page( $chapter, $selection['section'] ?? null ) : null;
$book_slug     = KBK_AI_Book::current_link_slug();
$book          = KBK_AI_Book::book_display( $book_slug );
$legacy        = $book['legacy'];
$book_short    = $book['short'];
$book_path     = KBK_AI_Book::book_path( '', $book_slug );
$book_code     = $repository ? KBK_AI_Book_Registry::code( $book_slug ) : 'AI';
// A detail page (one term, template, concept or graph node) is titled and
// canonicalized by what it shows, so no two addresses share a title.
$detail_label = '';
$detail_arg   = array();
if ( 'glossary' === $view && null !== KBK_AI_Book::requested_concept() ) {
	$detail_term = KBK_AI_Book::current_glossary_term();
	if ( $detail_term ) {
		$detail_label = (string) $detail_term['title'];
		$detail_arg   = array( KBK_AI_Book::CONCEPT_QUERY_VAR => (string) $detail_term['concept_id'] );
	}
} elseif ( 'templates' === $view && null !== KBK_AI_Book::requested_template() ) {
	$detail_catalog  = KBK_AI_Book::catalog();
	$detail_template = $detail_catalog ? $detail_catalog->template( (string) KBK_AI_Book::requested_template() ) : null;
	if ( $detail_template ) {
		$detail_label = (string) ( '' !== (string) ( $detail_template['title_fa'] ?? '' ) ? $detail_template['title_fa'] : $detail_template['template_id'] );
		$detail_arg   = array( KBK_AI_Book::TEMPLATE_QUERY_VAR => (string) $detail_template['template_id'] );
	}
} elseif ( ( 'concepts' === $view || 'graph' === $view ) && null !== KBK_AI_Book::requested_entity() ) {
	$detail_engine = 'graph' === $view ? KBK_AI_Book::graph() : KBK_AI_Book::catalog();
	$detail_entity = null === $detail_engine ? null : ( 'graph' === $view ? $detail_engine->node( (string) KBK_AI_Book::requested_entity() ) : $detail_engine->entity( (string) KBK_AI_Book::requested_entity() ) );
	if ( $detail_entity ) {
		$detail_label = (string) ( '' !== (string) $detail_entity['label_fa'] ? $detail_entity['label_fa'] : $detail_entity['label_en'] );
		$detail_arg   = array( KBK_AI_Book::ENTITY_QUERY_VAR => (string) $detail_entity['entity_id'] );
	}
}
// The first book keeps its hand-written titles; every other book is named
// by its own registry row or master/book.json.
$page_titles = $legacy ? array(
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
	'verify'          => 'اعتبارسنجی نسخه — کتاب حاکمیت هوش مصنوعی',
) : array(
	'library'         => 'کتاب‌های کهن‌دژ — کتابخانه',
	'home'            => $book['name'],
	'governance'      => $book['name'],
	'governance-news' => KBK_AI_Book::fitted_title( 'اخبار و تحلیل‌ها', $book_short ),
	'search'          => KBK_AI_Book::fitted_title( 'جست‌وجو', 'کتاب ' . $book_short ),
	'glossary'        => KBK_AI_Book::fitted_title( 'واژه‌نامه', 'کتاب ' . $book_short ),
	'sources'         => KBK_AI_Book::fitted_title( 'منابع', 'کتاب ' . $book_short ),
	'templates'       => KBK_AI_Book::fitted_title( 'قالب‌های اجرایی', $book_short ),
	'concepts'        => KBK_AI_Book::fitted_title( 'مفاهیم', $book_short ),
	'graph'           => KBK_AI_Book::fitted_title( 'نگارهٔ مفاهیم', $book_short ),
	'ask'             => KBK_AI_Book::fitted_title( 'پرسش از کتاب', $book_short ),
	'pdf'             => KBK_AI_Book::fitted_title( 'نسخه PDF', 'کتاب ' . $book_short ),
	'request-pdf'     => KBK_AI_Book::fitted_title( 'درخواست نسخه PDF', 'کتاب ' . $book_short ),
	'verify'          => KBK_AI_Book::fitted_title( 'اعتبارسنجی نسخه', 'کتاب ' . $book_short ),
);
$reader_heading = 'read' === $view && $selection && $selection['section'] ? KBK_AI_Book::reader_section_title( $selection['section'] ) : ( $chapter['title_fa'] ?? '' );
if ( 'read' === $view && $chapter ) {
	// "{chapter} — {book}" up to 70 characters; past that the book name goes, never the chapter's.
	$page_title = KBK_AI_Book::fitted_title( $reader_heading . ( $reader_page && $reader_page['page'] > 1 && ! $selection['section'] ? ' — صفحهٔ ' . KBK_AI_Book::fa_digits( $reader_page['page'] ) : '' ), $book_short );
} elseif ( '' !== $detail_label ) {
	$page_title = KBK_AI_Book::fitted_title( $detail_label, $page_titles[ $view ] );
	$page_title = $page_title === $detail_label ? KBK_AI_Book::fitted_title( $detail_label, $book_short ) : $page_title;
} else {
	$page_title = $page_titles[ $view ] ?? 'کتاب ' . $book_short;
}
$preferred_paths = array(
	'library'         => '/books/',
	'home'            => $book_path,
	'governance'      => $book_path,
	'governance-news' => $book_path . 'news/',
	'verify'          => '/ai-book/verify/',
);
$preferred_path = $preferred_paths[ $view ] ?? $book_path . $view . '/';
$canonical_url = home_url( $preferred_path );
if ( 'read' === $view && $part && $chapter ) {
	$canonical_url = add_query_arg(
		array(
			KBK_AI_Book::PART_QUERY_VAR    => $part['part_id'],
			KBK_AI_Book::CHAPTER_QUERY_VAR => $chapter['chapter_id'],
		),
		$canonical_url
	);
	if ( $reader_page && $reader_page['page'] > 1 ) {
		$canonical_url = add_query_arg( KBK_AI_Book::PAGE_QUERY_VAR, $reader_page['page'], $canonical_url );
	}
} elseif ( array() !== $detail_arg ) {
	$canonical_url = add_query_arg( $detail_arg, $canonical_url );
}
// One description per page: chapter pages quote their own first section,
// tool pages say what they hold, in which book.
$view_blurbs = array(
	'governance-news' => 'اخبار و تحلیل‌های منتشرشده دربارهٔ موضوع کتاب «%s»؛ هر مطلب با تاریخ، نویسنده و منبع مشخص.',
	'search'          => 'جست‌وجو در متن کامل کتاب «%s»: عبارت فارسی، اصطلاح یا سرواژهٔ انگلیسی، نام سند یا شناسهٔ محتوا.',
	'glossary'        => 'واژه‌نامهٔ فارسی و انگلیسی کتاب «%s» با تعریف، سرواژه و وضعیت تأیید هر اصطلاح.',
	'sources'         => 'منابع و اسناد پشتوانهٔ کتاب «%s» با مشخصات انتشار و شمار بخش‌های مستند به هر سند.',
	'templates'       => 'قالب‌های اجرایی کتاب «%s»؛ نمونه‌فرم‌های پیشنهادی تدوینگر برای به‌کارگیری در سازمان.',
	'concepts'        => 'مفاهیم، نهادها و چارچوب‌های کلیدی کتاب «%s» با پیوند به بخش‌های مرتبط کتاب.',
	'graph'           => 'نگارهٔ روابط میان مفاهیم کتاب «%s»؛ هر رابطه با شاهد متنی و پیوند به بخش منبع.',
	'ask'             => 'پرسش از متن کتاب «%s» با پاسخ مستند به بخش‌های قابل استناد.',
	'pdf'             => 'نسخهٔ PDF کتاب «%s» با اثر انگشت SHA-256 و مشخصات ویرایش، برای مطالعه در مرورگر.',
	'request-pdf'     => 'درخواست نسخهٔ شخصی PDF کتاب «%s»؛ بدون ذخیرهٔ داده در همین سایت.',
	'verify'          => 'اعتبارسنجی شناسهٔ محتوا، شناسهٔ ساختاری یا اثر انگشت SHA-256 در نسخهٔ منتشرشدهٔ کتاب «%s».',
);
if ( 'library' === $view ) {
	$page_description = 'کتاب‌های محمدعلی کهن‌دژ؛ هر کتاب با ساختار، منبع و وضعیت انتشار خودش.';
} elseif ( 'read' === $view && $chapter && $reader_page ) {
	$page_description = KBK_AI_Book::chapter_description( $chapter, $reader_page['indexes'] );
} elseif ( 'governance' === $view || 'home' === $view ) {
	$page_description = $legacy
		? 'مرجع فارسی AI Governance و حاکمیت هوش مصنوعی برای سازمان‌های دولتی، عمومی و خصوصی؛ مبتنی بر منابع NIST، با متن قابل استناد، واژه‌نامه و قالب‌های اجرایی.'
		: KBK_AI_Book::plain_excerpt( implode( '؛ ', array_filter( array( '' !== $book['title_fa'] ? $book['title_fa'] : $book['name'], $book['attribution'] ) ) ) );
} else {
	$page_description = sprintf( $view_blurbs[ $view ] ?? 'کتاب «%s».', $book_short );
	if ( '' !== $detail_label ) {
		$page_description = KBK_AI_Book::plain_excerpt( '«' . $detail_label . '» — ' . $page_description );
	}
}
$og_image     = 'library' === $view || null === $book['cover'] ? '' : $book['cover']['w640'];
$book_id      = home_url( $book_path . '#book' );
$person       = array( '@type' => 'Person', '@id' => home_url( '/#person' ), 'name' => '' !== $book['compiler'] ? $book['compiler'] : 'محمدعلی کهن‌دژ' );
$in_language  = 'fa' === $book['locale'] || '' === $book['locale'] ? 'fa-IR' : $book['locale'];
$book_schema  = array(
	'@context'      => 'https://schema.org',
	'@type'         => 'Book',
	'@id'           => $book_id,
	'name'          => $book['name'],
	'alternateName' => $legacy ? ( $summary['title_fa'] ?? 'کتاب مرجع هوش مصنوعی کهن‌دژ' ) : ( '' !== $book['title_en'] ? $book['title_en'] : $book['title_fa'] ),
	'url'           => home_url( $book_path ),
	'inLanguage'    => $in_language,
	'author'        => $person,
	'publisher'     => array( '@type' => 'Organization', 'name' => 'کهن‌دژ', 'url' => home_url( '/' ) ),
);
if ( '' !== $book['edition_id'] ) {
	$book_schema['bookEdition'] = $book['edition_id'];
}
if ( '' !== $book['built_date'] ) {
	$book_schema['datePublished'] = $book['built_date'];
	$book_schema['dateModified']  = $book['built_date'];
}
if ( null !== $book['cover'] ) {
	$book_schema['image'] = $book['cover']['w640'];
}
$page_schema = array(
	'@context'   => 'https://schema.org',
	'@type'      => 'read' === $view ? 'Chapter' : ( 'governance-news' === $view || 'library' === $view ? 'CollectionPage' : 'WebPage' ),
	'@id'        => $canonical_url . '#webpage',
	'name'       => $page_title,
	'description'=> $page_description,
	'url'        => $canonical_url,
	'inLanguage' => $in_language,
	'isPartOf'   => array( '@id' => $book_id ),
);
$schema_graph = array( $book_schema, $page_schema );
if ( 'library' === $view ) {
	// The library is the shelf: it lists the books rather than belonging to one.
	unset( $page_schema['isPartOf'] );
	$page_schema['hasPart'] = array();
	foreach ( KBK_AI_Book_Registry::routable_slugs() as $shelf_slug ) {
		$shelf_book = KBK_AI_Book::book_display( $shelf_slug );
		$page_schema['hasPart'][] = array( '@type' => 'Book', '@id' => home_url( KBK_AI_Book::book_path( '', $shelf_slug ) . '#book' ), 'name' => $shelf_book['name'], 'url' => $shelf_book['url'] );
	}
	$schema_graph = array( $page_schema );
} elseif ( 'read' === $view && $part && $chapter ) {
	$page_schema['breadcrumb'] = array( '@id' => $canonical_url . '#breadcrumb' );
	$schema_graph = array(
		$book_schema,
		$page_schema,
		array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $canonical_url . '#breadcrumb',
			'itemListElement' => array(
				array( '@type' => 'ListItem', 'position' => 1, 'name' => 'کتابخانه', 'item' => home_url( '/books/' ) ),
				array( '@type' => 'ListItem', 'position' => 2, 'name' => $book['name'], 'item' => home_url( $book_path ) ),
				array( '@type' => 'ListItem', 'position' => 3, 'name' => $part['title_fa'], 'item' => KBK_AI_Book::reader_url( $part['part_id'] ) ),
				array( '@type' => 'ListItem', 'position' => 4, 'name' => $chapter['title_fa'], 'item' => KBK_AI_Book::reader_url( $part['part_id'], $chapter['chapter_id'] ) ),
			),
		),
	);
}
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
// Every reader link goes through the plugin's page-aware helpers.
$read_link = static function ( string $part_id, ?string $chapter_id = null, ?string $section_id = null ): string {
	if ( null !== $chapter_id && null !== $section_id ) {
		return KBK_AI_Book::section_url( $part_id, $chapter_id, $section_id );
	}
	return KBK_AI_Book::reader_url( $part_id, $chapter_id );
};
$fa = array( 'KBK_AI_Book', 'fa_digits' );
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
	<?php if ( $reader_page && $reader_page['page'] > 1 ) : ?><link rel="prev" href="<?php echo esc_url( KBK_AI_Book::reader_url( $part['part_id'], $chapter['chapter_id'], $reader_page['page'] - 1 ) ); ?>"><?php endif; ?>
	<?php if ( $reader_page && $reader_page['page'] < $reader_page['pages'] ) : ?><link rel="next" href="<?php echo esc_url( KBK_AI_Book::reader_url( $part['part_id'], $chapter['chapter_id'], $reader_page['page'] + 1 ) ); ?>"><?php endif; ?>
	<meta property="og:type" content="<?php echo 'governance-news' === $view ? 'website' : 'article'; ?>">
	<meta property="og:locale" content="fa_IR">
	<meta property="og:title" content="<?php echo esc_attr( $page_title ); ?>">
	<meta property="og:description" content="<?php echo esc_attr( $page_description ); ?>">
	<meta property="og:url" content="<?php echo esc_url( $canonical_url ); ?>">
<?php if ( '' !== $og_image ) : ?>	<meta property="og:image" content="<?php echo esc_url( $og_image ); ?>">
	<meta property="og:image:alt" content="<?php echo esc_attr( 'جلد کتاب ' . $book['name'] ); ?>">
<?php endif; ?>	<meta name="twitter:card" content="<?php echo '' !== $og_image ? 'summary_large_image' : 'summary'; ?>">
	<meta name="twitter:title" content="<?php echo esc_attr( $page_title ); ?>">
	<meta name="twitter:description" content="<?php echo esc_attr( $page_description ); ?>">
<?php if ( '' !== $og_image ) : ?>	<meta name="twitter:image" content="<?php echo esc_url( $og_image ); ?>">
<?php endif; ?>	<script>(function(){try{var l=localStorage.getItem('kbk-ai-book-theme'),s=localStorage.getItem('darkMode'),t;if(l==='light'||l==='dark'){t=l;localStorage.setItem('darkMode',t==='light'?'disabled':'enabled');localStorage.removeItem('kbk-ai-book-theme');}else if(s==='disabled'||s==='light'){t='light';}else if(s==='enabled'||s==='dark'){t='dark';}else{t=matchMedia('(prefers-color-scheme: light)').matches?'light':'dark';}document.documentElement.setAttribute('data-ab-theme',t);}catch(e){}})();</script>
	<script type="application/ld+json"><?php echo wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $schema_graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
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
	<a href="<?php echo esc_url( home_url( '/ai-book/verify/' ) ); ?>"<?php echo 'verify' === $view ? ' aria-current="page"' : ''; ?>>اعتبارسنجی</a>
</nav>
<main id="main" class="ab-main" tabindex="-1">
	<?php if ( 'library' === $view ) :
		// The library shelf -- mirrors shelf() in _tooling/ai-book/build_visual_shell.py; change both together.
		?>
		<header class="ab-page-hero"><p class="ab-kicker">کتابخانه</p><h1>کتاب‌های کهن‌دژ</h1><p class="ab-lead">کتاب‌های محمدعلی کهن‌دژ؛ هر کتاب با ساختار، منبع و وضعیت انتشار خودش.</p></header>
		<section class="ab-section ab-shelf" aria-labelledby="ab-shelf-title"><h2 class="ab-visually-hidden" id="ab-shelf-title">کتاب‌ها</h2><div class="ab-shelf-grid">
		<?php foreach ( KBK_AI_Book_Registry::rows() as $shelf_row ) :
			// Routed books are live; announced ones get a card without links;
			// an admin row whose bundle is missing stays off the shelf.
			if ( ! $shelf_row['routable'] && KBK_AI_Book_Registry::STATUS_ANNOUNCED !== $shelf_row['status'] ) {
				continue;
			}
			$shelf      = KBK_AI_Book::book_display( $shelf_row['slug'] );
			$shelf_alt  = 'جلد کتاب ' . $shelf['name'] . ( '' !== $shelf['compiler'] ? '، نوشتهٔ ' . $shelf['compiler'] : '' );
			$shelf_path = KBK_AI_Book::book_path( '', $shelf_row['slug'] );
			?>
			<article class="ab-book"><?php if ( null !== $shelf['cover'] ) : ?><img class="ab-book-cover" src="<?php echo esc_url( $shelf['cover']['w320'] ); ?>"<?php if ( $shelf['cover']['w640'] !== $shelf['cover']['w320'] ) : ?> srcset="<?php echo esc_url( $shelf['cover']['w320'] ); ?> 320w, <?php echo esc_url( $shelf['cover']['w640'] ); ?> 640w" sizes="(max-width: 420px) 110px, 200px"<?php endif; ?> width="320" height="512" alt="<?php echo esc_attr( $shelf_alt ); ?>" loading="lazy" decoding="async"><?php else : ?><div class="ab-book-cover" role="img" aria-label="<?php echo esc_attr( $shelf_alt ); ?>"></div><?php endif; ?><div class="ab-book-body"><?php if ( $shelf_row['routable'] ) : ?><span class="ab-book-status ab-book-status-live">نسخهٔ آنلاین در دسترس</span><?php else : ?><span class="ab-book-status ab-book-status-soon">انتشار دیجیتال به‌زودی</span><?php endif; ?><h3><?php echo esc_html( $shelf['short'] ); ?></h3><?php if ( '' !== $shelf['subtitle_fa'] ) : ?><p class="ab-book-sub"><?php echo esc_html( $shelf['subtitle_fa'] ); ?></p><?php endif; ?><?php if ( '' !== $shelf['meta_fa'] ) : ?><p class="ab-book-meta"><?php echo esc_html( $shelf['meta_fa'] ); ?></p><?php endif; ?><?php if ( $shelf_row['routable'] ) : ?><div class="ab-book-actions"><a class="ab-button ab-button-primary" href="<?php echo esc_url( home_url( $shelf_path . 'read/' ) ); ?>">مطالعه آنلاین</a><a class="ab-button" href="<?php echo esc_url( home_url( $shelf_path ) ); ?>">معرفی و ساختار کتاب</a></div><p class="ab-resume" data-ab-resume="book" data-ab-book="<?php echo esc_attr( $shelf_row['slug'] ); ?>" hidden></p><?php elseif ( '' !== $shelf['note_fa'] ) : ?><p class="ab-book-note"><?php echo esc_html( $shelf['note_fa'] ); ?></p><?php endif; ?></div></article>
		<?php endforeach; ?>
		</div></section>
	<?php elseif ( ! $repository ) : ?>
		<header class="ab-page-hero"><p class="ab-kicker">وضعیت داده</p><h1>کتاب موقتاً در دسترس نیست</h1><p class="ab-lead">پیکربندی منبع اصلی کتاب کامل نشده یا اعتبار داده تأیید نشده است. هیچ محتوای حدسی نمایش داده نمی‌شود.</p><p><bdi><?php echo esc_html( KBK_AI_Book::repository_status() ); ?></bdi></p></header>
	<?php elseif ( 'governance' === $view && ! $legacy ) :
		// Every other book: the same landing structure, named by its own bundle.
		$landing_catalog = KBK_AI_Book::catalog();
		$landing_sources = $landing_catalog ? count( $landing_catalog->sources() ) : 0;
		?>
		<nav class="ab-breadcrumb" aria-label="مسیر"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">کهن‌دژ</a><a href="<?php echo esc_url( home_url( '/books/' ) ); ?>">کتاب‌ها</a><span aria-current="page"><?php echo esc_html( $book_short ); ?></span></nav>
		<header class="ab-page-hero"><p class="ab-kicker">کتاب‌ها · نسخهٔ فارسی<?php echo '' !== $book['edition_id'] ? ' · ' . esc_html( $book['edition_id'] ) : ''; ?></p><h1><?php echo KBK_AI_Book::bidi_html( '' !== $book['title_fa'] ? $book['title_fa'] : $book['name'] ); ?></h1><?php if ( '' !== $book['title_en'] ) : ?><?php echo KBK_AI_Book::english_html( $book['title_en'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by english_html(). ?><?php endif; ?><?php if ( '' !== $book['attribution'] ) : ?><p class="ab-lead"><?php echo KBK_AI_Book::bidi_html( $book['attribution'] ); ?></p><?php endif; ?><div class="ab-actions"><a class="ab-button ab-button-primary" href="<?php echo esc_url( home_url( $book_path . 'read/' ) ); ?>">مطالعه آنلاین</a><a class="ab-button" href="<?php echo esc_url( home_url( $book_path . 'news/' ) ); ?>">اخبار و تحلیل‌ها</a></div><p class="ab-resume" data-ab-resume="book" data-ab-book="<?php echo esc_attr( $book_slug ); ?>" hidden></p></header>
		<?php if ( $summary ) : ?><section class="ab-section ab-book-detail" id="structure" aria-labelledby="ab-structure-title"><div class="ab-section-head"><div><p class="ab-kicker">نسخهٔ فارسی · <?php echo esc_html( $summary['edition_id'] ); ?></p><h2 id="ab-structure-title">ساختار کتاب: <?php echo esc_html( $fa( $summary['parts'] ) ); ?> بخش اصلی</h2></div><a href="<?php echo esc_url( home_url( $book_path . 'read/' ) ); ?>">مطالعه آنلاین</a></div><section class="ab-stat-grid" aria-label="مشخصات نسخه"><div><b><?php echo esc_html( $fa( $summary['parts'] ) ); ?></b><span>بخش</span></div><div><b><?php echo esc_html( $fa( $summary['chapters'] ) ); ?></b><span>فصل</span></div><div><b><?php echo esc_html( $fa( $summary['sections'] ) ); ?></b><span>بخش محتوایی</span></div><?php if ( $landing_sources > 0 ) : ?><div><b><?php echo esc_html( $fa( $landing_sources ) ); ?></b><span>منبع اصلی</span></div><?php endif; ?></section><div class="ab-card-grid"><?php foreach ( $parts as $structure_part ) : ?><a class="ab-card" href="<?php echo esc_url( KBK_AI_Book::reader_url( $structure_part['part_id'] ) ); ?>"><span class="ab-card-meta ab-meta-fa"><?php echo esc_html( KBK_AI_Book::location_label( $structure_part['part_id'] ) ); ?></span><h3><?php echo KBK_AI_Book::bidi_html( $structure_part['title_fa'] ); ?></h3><p><?php echo esc_html( $fa( count( $structure_part['chapters'] ) ) ); ?> فصل</p></a><?php endforeach; ?></div></section><?php endif; ?>
	<?php elseif ( 'governance' === $view ) :
		$governance_chapter = $repository->find_chapter( 'P02', 'C09' );
		$book_assets = plugins_url( 'assets/', KBK_PLUGIN_FILE );
		$book_article = get_page_by_path( 'ai-governance-nist-guide', OBJECT, 'post' );
		?>
		<nav class="ab-breadcrumb" aria-label="مسیر"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">کهن‌دژ</a><a href="<?php echo esc_url( home_url( '/books/' ) ); ?>">کتاب‌ها</a><span aria-current="page">AI Governance</span></nav>
		<header class="ab-page-hero"><p class="ab-kicker">کتاب‌ها · نسخهٔ فارسی · مبتنی بر منابع NIST</p><h1>AI Governance — حاکمیت هوش مصنوعی</h1><p class="ab-lead">مرجع مستند برای طراحی سیاست، نقش، پاسخگویی، مدیریت ریسک و شواهد قابل ممیزی هوش مصنوعی در سازمان‌های دولتی، نهادهای عمومی، بانک‌ها، بیمه‌ها، صنایع و کسب‌وکارهای ایرانی.</p><div class="ab-actions"><a class="ab-button ab-button-primary" href="<?php echo esc_url( home_url( '/fa/books/ai-governance/read/?kbk_part=P02&kbk_chapter=C09' ) ); ?>">مطالعه فصل حاکمیت</a><a class="ab-button" href="<?php echo esc_url( home_url( '/fa/books/ai-governance/news/' ) ); ?>">اخبار و تحلیل‌ها</a></div><p class="ab-resume" data-ab-resume="book" hidden></p></header>
		<section class="ab-book-showcase" aria-label="جلد و دسترسی به کتاب">
			<figure><img src="<?php echo esc_url( $book_assets . 'book-front.webp' ); ?>" width="1024" height="1536" alt="روی جلد کتاب AI Governance؛ حاکمیت هوش مصنوعی، Mohammad Kohandezh"><figcaption>روی جلد · طرح معرفی نسخهٔ دیجیتال</figcaption></figure>
			<figure><img src="<?php echo esc_url( $book_assets . 'book-back.webp' ); ?>" width="1024" height="1536" loading="lazy" alt="پشت جلد؛ راهنمای مطالعه و اقدام، سیاست‌گذاری، پاسخگویی و مدیریت ریسک"><figcaption>پشت جلد</figcaption></figure>
			<div class="ab-book-access"><p class="ab-kicker">از مطالعه تا اقدام</p><h2>حاکمیت هوش مصنوعی، با مسیر روشن تا منبع</h2><p>منابع، مفاهیم و بخش‌های قابل استناد را در یک محیط مطالعه دنبال کنید.</p><a class="ab-book-qr" href="https://kohandezh.com/fa/books/ai-governance/"><img src="<?php echo esc_url( $book_assets . 'book-qr.png' ); ?>" width="180" height="180" alt="QR آدرس عمومی صفحهٔ کتاب"></a><p>اسکن برای بازکردن صفحهٔ کتاب</p><bdi class="ab-public-url">kohandezh.com/fa/books/ai-governance/</bdi><?php if ( 'localhost' === wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) : ?><p class="ab-card-meta">این پیش‌نمایش محلی است؛ QR به دامنهٔ عمومی می‌رود و دسترسی به کتاب در آن دامنه وابسته به انتشار نسخهٔ نهایی است.</p><?php endif; ?></div>
		</section>
		<?php if ( $summary ) : ?><section class="ab-section ab-book-detail" id="structure" aria-labelledby="ab-structure-title"><div class="ab-section-head"><div><p class="ab-kicker">نسخهٔ فارسی · <?php echo esc_html( $summary['edition_id'] ); ?></p><h2 id="ab-structure-title">ساختار کتاب: هفت بخش اصلی</h2></div><a href="<?php echo esc_url( home_url( '/fa/books/ai-governance/read/' ) ); ?>">مطالعه آنلاین</a></div><section class="ab-stat-grid" aria-label="مشخصات نسخه"><div><b><?php echo esc_html( $fa( $summary['parts'] ) ); ?></b><span>بخش</span></div><div><b><?php echo esc_html( $fa( $summary['chapters'] ) ); ?></b><span>فصل</span></div><div><b><?php echo esc_html( $fa( $summary['sections'] ) ); ?></b><span>بخش محتوایی</span></div><div><b>۱۸</b><span>منبع اصلی</span></div></section><div class="ab-card-grid"><?php foreach ( $parts as $structure_part ) : ?><a class="ab-card" href="<?php echo esc_url( home_url( '/fa/books/ai-governance/read/?' . KBK_AI_Book::PART_QUERY_VAR . '=' . rawurlencode( $structure_part['part_id'] ) ) ); ?>"><span class="ab-card-meta ab-meta-fa"><?php echo esc_html( KBK_AI_Book::location_label( $structure_part['part_id'] ) ); ?></span><h3><?php echo esc_html( $structure_part['title_fa'] ); ?></h3><p><?php echo esc_html( $fa( count( $structure_part['chapters'] ) ) ); ?> فصل</p></a><?php endforeach; ?></div></section><?php endif; ?>
		<?php if ( $book_article && 'publish' === $book_article->post_status ) : ?><section class="ab-section ab-book-blog"><p class="ab-kicker">از وبلاگ کهن‌دژ</p><h2>برای شروع مطالعه</h2><a class="ab-card" href="<?php echo esc_url( get_permalink( $book_article ) ); ?>"><h3><?php echo esc_html( get_the_title( $book_article ) ); ?></h3><p><?php echo esc_html( $book_article->post_excerpt ); ?></p><span class="ab-card-link">خواندن راهنما ←</span></a></section><?php endif; ?>
		<section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker">تعریف روشن</p><h2>حاکمیت هوش مصنوعی چیست؟</h2></div></div><div class="ab-layer ab-layer-source"><span>راهنمای مرجع</span><p>حاکمیت هوش مصنوعی مجموعهٔ سیاست‌ها، نقش‌ها، فرایندهای تصمیم‌گیری، کنترل‌ها و شواهدی است که استفاده از سامانه‌های هوش مصنوعی را با اهداف سازمان، الزامات قانونی، تحمل ریسک و مسئولیت‌پذیری هم‌راستا می‌کند.</p></div></section>
		<?php if ( $governance_chapter ) : ?><section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker">NIST AI RMF Playbook</p><h2>عناوین AI Governance</h2></div></div><div class="ab-results"><?php foreach ( $governance_chapter['sections'] as $governance_section ) : ?><a class="ab-card" href="<?php echo esc_url( KBK_AI_Book::section_url( 'P02', 'C09', $governance_section['structural_id'] ) ); ?>"><span class="ab-card-meta"><bdi><?php echo esc_html( $governance_section['structural_id'] ); ?></bdi></span><h3><?php echo esc_html( KBK_AI_Book::reader_section_title( $governance_section ) ); ?></h3><span class="ab-card-link">مطالعه و استناد</span></a><?php endforeach; ?></div></section><?php endif; ?>
		<section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker">مسیرهای کاربردی</p><h2>برای چه سازمان‌هایی قابل استفاده است؟</h2></div></div><div class="ab-card-grid"><article class="ab-card"><h3>دستگاه‌های دولتی و نهادهای عمومی</h3><p>برای سیاست‌گذاری داخلی، تعیین مسئولیت، ثبت سامانه‌ها و مستندسازی تصمیم‌ها.</p></article><article class="ab-card"><h3>بانک، بیمه و خدمات مالی</h3><p>برای کنترل ریسک مدل، نظارت انسانی، مدیریت تأمین‌کننده و شواهد ممیزی.</p></article><article class="ab-card"><h3>صنعت، سلامت و زیرساخت</h3><p>برای پایش عملکرد، مدیریت تغییر، امنیت و تصمیم‌گیری متناسب با ریسک.</p></article></div></section>
	<?php elseif ( 'governance-news' === $view ) :
		$news_query = new WP_Query(
			array(
				'post_type'      => array( 'kbk_news', 'post' ),
				'post_status'    => 'publish',
				'posts_per_page' => 12,
				's'              => $legacy ? 'حاکمیت هوش مصنوعی AI Governance' : $book_short,
			)
		);
		?>
		<nav class="ab-breadcrumb" aria-label="مسیر"><a href="<?php echo esc_url( home_url( $book_path ) ); ?>"><?php echo esc_html( $legacy ? 'AI Governance' : $book_short ); ?></a><span aria-current="page">اخبار و تحلیل‌ها</span></nav>
		<?php if ( $legacy ) : ?><header class="ab-page-hero"><p class="ab-kicker">رصد و تحلیل</p><h1>اخبار و تحلیل‌های حاکمیت هوش مصنوعی</h1><p class="ab-lead">مطالب منتشرشده دربارهٔ سیاست‌گذاری، استانداردها، مدیریت ریسک و الزامات سازمانی هوش مصنوعی؛ هر مطلب با تاریخ، نویسنده و منبع مشخص منتشر می‌شود.</p></header>
		<?php else : ?><header class="ab-page-hero"><p class="ab-kicker">رصد و تحلیل</p><h1>اخبار و تحلیل‌های <?php echo esc_html( $book_short ); ?></h1><p class="ab-lead">مطالب منتشرشده دربارهٔ موضوع‌های این کتاب؛ هر مطلب با تاریخ، نویسنده و منبع مشخص منتشر می‌شود.</p></header><?php endif; ?>
		<section class="ab-section"><div class="ab-results"><?php if ( $news_query->have_posts() ) : while ( $news_query->have_posts() ) : $news_query->the_post(); ?><a class="ab-card" href="<?php the_permalink(); ?>"><span class="ab-card-meta"><?php echo esc_html( get_the_date() ); ?></span><h2><?php the_title(); ?></h2><p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 32 ) ); ?></p><span class="ab-card-link">مطالعه تحلیل</span></a><?php endwhile; wp_reset_postdata(); else : ?><div class="ab-fallback"><h2>هنوز مطلب مرتبطی منتشر نشده است</h2><p>این صفحه فقط مطالب واقعی و منتشرشده را نمایش می‌دهد و برای پرکردن فهرست، خبر یا ادعای ساختگی تولید نمی‌کند.</p></div><?php endif; ?></div></section>
	<?php elseif ( 'home' === $view ) : ?>
		<header class="ab-page-hero"><p class="ab-kicker">نسخهٔ فارسی · <?php echo esc_html( $summary['edition_id'] ); ?></p><h1>دانش فنی، با مسیر روشن تا منبع</h1><p class="ab-lead"><?php echo esc_html( $summary['title_fa'] ); ?>؛ هر بخش با شناسه، منشأ و مسیر بازگشت به شاهد.</p><div class="ab-actions"><a class="ab-button ab-button-primary" href="<?php echo esc_url( home_url( '/ai-book/read/' ) ); ?>">مطالعه آنلاین</a></div></header>
		<section class="ab-stat-grid" aria-label="مشخصات نسخه"><div><b><?php echo esc_html( $fa( $summary['parts'] ) ); ?></b><span>بخش</span></div><div><b><?php echo esc_html( $fa( $summary['chapters'] ) ); ?></b><span>فصل</span></div><div><b><?php echo esc_html( $fa( $summary['sections'] ) ); ?></b><span>بخش محتوایی</span></div><div><b>۱۸</b><span>منبع اصلی</span></div></section>
		<section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker">ساختار کتاب</p><h2>هفت بخش اصلی</h2></div></div><div class="ab-card-grid">
		<?php foreach ( $parts as $part_card ) : ?>
			<a class="ab-card" href="<?php echo esc_url( home_url( '/ai-book/read/?' . KBK_AI_Book::PART_QUERY_VAR . '=' . rawurlencode( $part_card['part_id'] ) ) ); ?>"><span class="ab-card-meta ab-meta-fa"><?php echo esc_html( KBK_AI_Book::location_label( $part_card['part_id'] ) ); ?></span><h3><?php echo esc_html( $part_card['title_fa'] ); ?></h3><p><?php echo esc_html( $fa( count( $part_card['chapters'] ) ) ); ?> فصل</p><span class="ab-card-link">مطالعه</span></a>
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
		<section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker">راهنما</p><h2>عبارتی برای جست‌وجو وارد کنید</h2></div></div><p class="ab-lead">می‌توانید متن فارسی، اصطلاح یا سرواژهٔ انگلیسی<?php if ( $legacy ) : ?> (مانند <bdi>RMF</bdi>)<?php endif; ?>، نام سند<?php if ( $legacy ) : ?> (مانند <bdi>NIST AI 100-1</bdi>)<?php endif; ?> یا شناسهٔ محتوا (مانند <bdi><?php echo esc_html( 'KDJ-' . $book_code . '-' . ( $summary['edition_id'] ?? '2026E1' ) . '-P01-C01-S01' ); ?></bdi>) را جست‌وجو کنید.</p></section>
		<?php elseif ( 0 === $search['total'] ) : ?>
		<section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker">نتیجه‌ای یافت نشد</p><h2>نتیجه‌ای برای «<?php echo esc_html( $search['query'] ); ?>» وجود ندارد</h2></div></div><p class="ab-lead">جست‌وجو دقیق است و نتیجهٔ حدسی تولید نمی‌کند؛ عبارت کوتاه‌تر یا دیگری را امتحان کنید.</p></section>
		<?php else : ?>
		<section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker"><?php echo esc_html( $fa( $search['total'] ) ); ?> نتیجه · صفحهٔ <?php echo esc_html( $fa( $search['page'] ) ); ?> از <?php echo esc_html( $fa( $search['pages'] ) ); ?></p><h2>نتایج برای «<?php echo esc_html( $search['query'] ); ?>»</h2></div></div><div class="ab-results">
			<?php foreach ( $search['results'] as $result ) : ?>
			<?php if ( 'part' === $result['type'] ) : ?>
			<a class="ab-card" href="<?php echo esc_url( $read_link( $result['part']['id'] ) ); ?>"><span class="ab-card-meta ab-meta-fa"><?php echo esc_html( KBK_AI_Book::location_label( $result['part']['id'] ) ); ?></span><h3><?php echo KBK_AI_Book::bidi_html( $result['title'] ); ?></h3><p><?php echo esc_html( $fa( $result['chapters'] ) ); ?> فصل</p><span class="ab-card-link">مطالعه بخش</span></a>
			<?php elseif ( 'chapter' === $result['type'] ) : ?>
			<a class="ab-card" href="<?php echo esc_url( $read_link( $result['part']['id'], $result['chapter']['id'] ) ); ?>"><span class="ab-card-meta ab-meta-fa"><?php echo esc_html( KBK_AI_Book::location_label( $result['part']['id'], $result['chapter']['id'] ) ); ?></span><h3><?php echo KBK_AI_Book::bidi_html( $result['title'] ); ?></h3><p>فصل در بخش «<?php echo esc_html( $result['part']['title_fa'] ); ?>»</p><span class="ab-card-link">مطالعه فصل</span></a>
			<?php elseif ( 'glossary' === $result['type'] ) : ?>
			<a class="ab-card" href="<?php echo esc_url( home_url( '/ai-book/glossary/?' . KBK_AI_Book::CONCEPT_QUERY_VAR . '=' . rawurlencode( $result['concept_id'] ) ) ); ?>"><span class="ab-card-meta">واژه‌نامه<?php echo '' !== $result['acronym'] ? ' · ' . esc_html( $result['acronym'] ) : ''; ?></span><h3><?php echo KBK_AI_Book::bidi_html( $result['title'] ); ?></h3><p><?php echo $render_segments( $result['snippet_segments'] ); ?></p><span class="ab-card-link">واژه‌نامه</span></a>
			<?php else : ?>
			<a class="ab-card" href="<?php echo esc_url( $read_link( $result['part']['id'], $result['chapter']['id'], $result['section']['structural_id'] ) ); ?>"><span class="ab-card-meta ab-meta-fa"><?php echo esc_html( KBK_AI_Book::location_label( $result['part']['id'], $result['chapter']['id'] ) . ( $result['docs'] ? ' · ' . implode( '، ', $result['docs'] ) : '' ) ); ?></span><h3><?php echo KBK_AI_Book::bidi_html( $result['title'] ); ?></h3><p><?php echo $render_segments( $result['snippet_segments'] ); ?></p><span class="ab-card-meta"><bdi><?php echo esc_html( $result['section']['content_id'] ); ?></bdi> · <?php echo esc_html( $origin_map[ $result['origin'] ]['label'] ); ?></span><span class="ab-card-link">مشاهده بخش</span></a>
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
			<header class="ab-page-hero"><p class="ab-kicker">واژه‌نامه، <?php echo esc_html( $fa( count( $glossary_terms ) ) ); ?> واژه</p><h1>واژه‌نامهٔ کتاب</h1><p class="ab-lead">واژه‌های تأییدشدهٔ فارسی با معادل انگلیسی و سرواژه؛ مرتب بر پایهٔ واژهٔ فارسی.</p></header>
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
		<header class="ab-page-hero"><p class="ab-kicker">مستندات پایه، <?php echo esc_html( $fa( count( $sources ) ) ); ?> سند</p><h1>منابع کتاب</h1><p class="ab-lead">هجده سند اصلی ممیزی‌شده؛ هر فصل کتاب به سند و صفحات منبع خود ارجاع می‌دهد.</p></header>
		<section class="ab-section"><h2 class="ab-visually-hidden">فهرست منابع</h2><div class="ab-results">
			<?php foreach ( $sources as $source ) : ?>
			<article class="ab-card ab-source-card"><span class="ab-card-meta"><bdi><?php echo esc_html( $source['doc_key'] ); ?></bdi></span><?php if ( '' !== $source['title_fa'] ) : ?><h3><?php echo KBK_AI_Book::bidi_html( $source['title_fa'] ); ?></h3><?php if ( '' !== $source['subtitle_fa'] ) : ?><p class="ab-subtitle-fa"><?php echo KBK_AI_Book::bidi_html( $source['subtitle_fa'] ); ?></p><?php endif; ?><?php echo KBK_AI_Book::english_html( $source['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by the helper. ?><?php else : ?><h3 lang="en" dir="ltr"><?php echo esc_html( $source['title'] ); ?></h3><?php endif; ?><p><?php echo esc_html( trim( $source['organization'] . ( $source['publication_date'] ? ' · ' . $source['publication_date'] : '' ) . ( $source['publication_type'] ? ' · ' . $source['publication_type'] : '' ) . ( $source['publication_status'] ? ' · ' . $source['publication_status'] : '' ) ) ); ?></p><p class="ab-card-meta"><?php echo esc_html( $fa( $source['sections_count'] ) ); ?> بخش از کتاب، <?php echo esc_html( $fa( $source['pages'] ) ); ?> صفحه<?php echo '' !== $source['doi_url'] ? ' · <bdi><a href="' . esc_url( $source['doi_url'] ) . '">DOI</a></bdi>' : ''; ?></p><span class="ab-card-meta"><bdi><?php echo esc_html( substr( $source['sha256'], 0, 16 ) ); ?>…</bdi></span></article>
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
		<header class="ab-page-hero"><p class="ab-kicker">قالب‌های کاربردی، <?php echo esc_html( $fa( count( $templates ) ) ); ?> قالب</p><h1>قالب‌های کتاب</h1><p class="ab-lead"><?php echo $legacy ? 'نمونه‌های پیشنهادی تدوینگر برای پیاده‌سازی حاکمیت هوش مصنوعی در سازمان.' : esc_html( 'نمونه‌های پیشنهادی تدوینگر برای به‌کارگیری «' . $book_short . '» در سازمان.' ); ?></p></header>
		<section class="ab-section"><h2 class="ab-visually-hidden">فهرست الگوها</h2><div class="ab-results">
			<?php foreach ( $templates as $template_card ) : ?>
			<a class="ab-card" href="<?php echo esc_url( home_url( '/ai-book/templates/?' . KBK_AI_Book::TEMPLATE_QUERY_VAR . '=' . rawurlencode( $template_card['template_id'] ) ) ); ?>"><span class="ab-card-meta"><?php echo esc_html( $template_card['template_id'] ); ?></span><h3><?php echo esc_html( $template_card['title_fa'] ); ?></h3><p><?php echo esc_html( $fa( count( $template_card['fields'] ) ) ); ?> فیلد</p><span class="ab-card-link">مشاهده قالب</span></a>
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
		<article class="ab-prose"><header class="ab-page-hero"><p class="ab-kicker" title="<?php echo esc_attr( $entity_detail['type'] ); ?>">مفهوم · <?php echo esc_html( KBK_AI_Book::entity_type_label( $entity_detail['type'] ) ); ?></p><h1><?php echo esc_html( '' !== $entity_detail['label_fa'] ? $entity_detail['label_fa'] : $entity_detail['label_en'] ); ?></h1><?php echo '' !== $entity_detail['label_fa'] ? KBK_AI_Book::english_html( $entity_detail['label_en'] ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by the helper. ?></header>
		<section><h2>ارجاع در کتاب</h2><div class="ab-layer <?php echo esc_attr( 'editorial' === $entity_detail['origin'] ? 'ab-layer-editorial' : 'ab-layer-source' ); ?>"><span><?php echo esc_html( 'editorial' === $entity_detail['origin'] ? 'جمع‌بندی تدوینگر' : 'استخراج از منبع' ); ?></span>
		<?php echo KBK_AI_Book::definition_html( $entity_detail['definition_fa'], $entity_detail['definition_en'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by the helper. ?>
		<?php if ( $entity_detail['documents'] ) : ?><p class="ab-card-meta">اسناد: <bdi><?php echo esc_html( implode( '، ', $entity_detail['documents'] ) ); ?></bdi></p><?php endif; ?></div>
		<?php if ( $entity_detail['sections'] ) : ?><p class="ab-lead"><?php echo esc_html( $fa( $entity_detail['mentions_total'] ) ); ?> بخش از کتاب به این مفهوم ارجاع می‌دهد<?php echo $entity_detail['mentions_total'] > count( $entity_detail['sections'] ) ? '؛ نمایش اولین ' . esc_html( $fa( count( $entity_detail['sections'] ) ) ) . ' بخش' : ''; ?>:</p><div class="ab-results"><?php foreach ( $entity_detail['sections'] as $mention ) : ?><a class="ab-card" href="<?php echo esc_url( $read_link( $mention['part_id'], $mention['chapter_id'], $mention['structural_id'] ) ); ?>"><span class="ab-card-meta ab-meta-fa"><?php echo esc_html( KBK_AI_Book::location_label( $mention['part_id'], $mention['chapter_id'] ) ); ?></span><h3><?php echo KBK_AI_Book::bidi_html( $mention['title_fa'] ); ?></h3><p class="ab-card-meta"><bdi><?php echo esc_html( $mention['content_id'] ); ?></bdi> · <?php echo esc_html( $origin_map[ $mention['origin'] ]['label'] ); ?></p><span class="ab-card-link">مشاهده بخش</span></a><?php endforeach; ?></div><?php endif; ?>
		<p class="ab-card-meta"><bdi><?php echo esc_html( $entity_detail['entity_id'] ); ?></bdi></p></section></article>
		<?php else :
			$entity_type   = KBK_AI_Book::requested_entity_type();
			$entities_page = $concepts_catalog->entities( $entity_type, KBK_AI_Book::requested_page() );
			$entity_types  = $concepts_catalog->entity_types();
			?>
			<?php if ( array() === $entities_page['items'] ) : ?>
			<header class="ab-page-hero"><p class="ab-kicker">یافت نشد</p><h1>این دسته از مفاهیم موجود نیست</h1><p class="ab-lead">نوع درخواستی با هیچ مفهومی مطابقت ندارد یا شمارهٔ صفحه بیرون از محدوده است.</p><div class="ab-actions"><a class="ab-button ab-button-primary" href="<?php echo esc_url( home_url( '/ai-book/concepts/' ) ); ?>">همهٔ مفاهیم</a></div></header>
			<?php else : ?>
			<header class="ab-page-hero"><p class="ab-kicker">مفاهیم، <?php echo esc_html( $fa( $entities_page['total'] ) ); ?> مورد<?php echo null !== $entity_type ? ' · ' . esc_html( KBK_AI_Book::entity_type_label( $entity_type ) ) : ''; ?></p><h1>مفاهیم کتاب</h1><p class="ab-lead">موجودیت‌های استخراج‌شده از منابع و تدوین، با ارجاع به بخش‌های کتاب.</p></header>
			<nav class="ab-type-chips" aria-label="فیلتر نوع"><div class="ab-chip-row"><a class="ab-chip" href="<?php echo esc_url( home_url( '/ai-book/concepts/' ) ); ?>"<?php echo null === $entity_type ? ' aria-current="page"' : ''; ?>><span>همه</span> <span class="ab-chip-count"><?php echo esc_html( $fa( (int) array_sum( $entity_types ) ) ); ?></span></a><?php foreach ( $entity_types as $type_name => $type_count ) : ?><a class="ab-chip" href="<?php echo esc_url( home_url( '/ai-book/concepts/?' . KBK_AI_Book::TYPE_QUERY_VAR . '=' . rawurlencode( $type_name ) ) ); ?>"<?php echo $type_name === $entity_type ? ' aria-current="page"' : ''; ?> title="<?php echo esc_attr( $type_name ); ?>"><span><?php echo esc_html( KBK_AI_Book::entity_type_label( $type_name ) ); ?></span> <span class="ab-chip-count"><?php echo esc_html( $fa( (int) $type_count ) ); ?></span></a><?php endforeach; ?></div></nav>
			<section class="ab-section"><h2 class="ab-visually-hidden">فهرست مفاهیم</h2><div class="ab-results">
				<?php foreach ( $entities_page['items'] as $entity_card ) : ?>
				<a class="ab-card" href="<?php echo esc_url( home_url( '/ai-book/concepts/?' . KBK_AI_Book::ENTITY_QUERY_VAR . '=' . rawurlencode( $entity_card['entity_id'] ) ) ); ?>"><span class="ab-card-meta ab-meta-fa" title="<?php echo esc_attr( $entity_card['type'] ); ?>"><?php echo esc_html( KBK_AI_Book::entity_type_label( $entity_card['type'] ) ); ?></span><h3><?php echo esc_html( '' !== $entity_card['labels']['fa'] ? $entity_card['labels']['fa'] : $entity_card['labels']['en'] ); ?></h3><?php echo '' !== $entity_card['labels']['fa'] ? KBK_AI_Book::english_html( $entity_card['labels']['en'] ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by the helper. ?><span class="ab-card-meta ab-meta-fa"><?php echo esc_html( $fa( $entity_card['mentions_count'] ) ); ?> ارجاع</span></a>
				<?php endforeach; ?>
			</div>
			<?php if ( $entities_page['pages'] > 1 ) : ?><nav class="ab-prev-next" aria-label="صفحه‌بندی"><?php if ( $entities_page['page'] > 1 ) : ?><a href="<?php echo esc_url( home_url( '/ai-book/concepts/?' . KBK_AI_Book::TYPE_QUERY_VAR . '=' . rawurlencode( (string) $entity_type ) . '&' . KBK_AI_Book::PAGE_QUERY_VAR . '=' . ( $entities_page['page'] - 1 ) ) ); ?>">« صفحهٔ قبل</a><?php else : ?><span></span><?php endif; ?><span>صفحهٔ <?php echo esc_html( $fa( $entities_page['page'] ) ); ?> از <?php echo esc_html( $fa( $entities_page['pages'] ) ); ?></span><?php if ( $entities_page['page'] < $entities_page['pages'] ) : ?><a href="<?php echo esc_url( home_url( '/ai-book/concepts/?' . KBK_AI_Book::TYPE_QUERY_VAR . '=' . rawurlencode( (string) $entity_type ) . '&' . KBK_AI_Book::PAGE_QUERY_VAR . '=' . ( $entities_page['page'] + 1 ) ) ); ?>">صفحهٔ بعد »</a><?php endif; ?></nav><?php endif; ?>
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
		<article class="ab-prose"><header class="ab-page-hero"><p class="ab-kicker" title="<?php echo esc_attr( $graph_node['type'] ); ?>">گره · <?php echo esc_html( KBK_AI_Book::entity_type_label( $graph_node['type'] ) ); ?></p><h1><?php echo esc_html( '' !== $graph_node['label_fa'] ? $graph_node['label_fa'] : $graph_node['label_en'] ); ?></h1><?php echo '' !== $graph_node['label_fa'] ? KBK_AI_Book::english_html( $graph_node['label_en'] ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by the helper. ?></header>
		<section><h2>همسایگی در نگاره</h2><div class="ab-layer <?php echo esc_attr( 'editorial' === $graph_node['origin'] ? 'ab-layer-editorial' : 'ab-layer-source' ); ?>"><span><?php echo esc_html( 'editorial' === $graph_node['origin'] ? 'جمع‌بندی تدوینگر' : 'استخراج از منبع' ); ?></span><?php echo KBK_AI_Book::definition_html( $graph_node['definition_fa'], $graph_node['definition_en'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by the helper. ?><?php if ( $graph_node['documents'] ) : ?><p class="ab-card-meta">اسناد: <bdi><?php echo esc_html( implode( '، ', $graph_node['documents'] ) ); ?></bdi></p><?php endif; ?></div>
		<?php if ( $hood_edges ) : ?>
		<?php
		// Full labels: graph_layout() wraps them onto at most two lines.
		$graph_label = static function ( array $entity ): string {
			return '' !== $entity['label_fa'] ? $entity['label_fa'] : $entity['label_en'];
		};
		$viz_nodes = array();
		foreach ( $viz_edges as $viz_edge ) {
			if ( null === $viz_edge['other'] ) {
				continue;
			}
			$viz_nodes[] = array(
				'label' => $graph_label( $viz_edge['other'] ),
				'full'  => '' !== $viz_edge['other']['label_fa'] ? $viz_edge['other']['label_fa'] : $viz_edge['other']['label_en'],
				'sub'   => KBK_AI_Book::relation_label( $viz_edge['relation'] ),
				'code'  => $viz_edge['relation'],
				'out'   => (bool) $viz_edge['direction_out'],
				'href'  => home_url( '/ai-book/graph/?' . KBK_AI_Book::ENTITY_QUERY_VAR . '=' . rawurlencode( $viz_edge['other']['entity_id'] ) ),
			);
		}
		$center_label = $graph_label( $graph_node );
		$graph_view   = KBK_AI_Book::graph_layout( $center_label, $viz_nodes );
		$is_rtl_text  = static function ( string $text ): bool { return 1 === preg_match( '/\p{Arabic}/u', $text ); };
		?>
		<figure class="ab-graph-figure"><svg class="ab-graph-svg" viewBox="<?php echo esc_attr( implode( ' ', $graph_view['view'] ) ); ?>" width="<?php echo esc_attr( (int) ceil( $graph_view['view'][2] ) ); ?>" height="<?php echo esc_attr( (int) ceil( $graph_view['view'][3] ) ); ?>" role="group" aria-labelledby="ab-graph-caption"><g class="ab-graph-edges" aria-hidden="true"><?php foreach ( $graph_view['nodes'] as $graph_item ) : ?><line x1="0" y1="0" x2="<?php echo esc_attr( $graph_item['x'] ); ?>" y2="<?php echo esc_attr( $graph_item['y'] ); ?>"/><?php endforeach; ?></g><g class="ab-graph-center" aria-hidden="true"><rect x="<?php echo esc_attr( -$graph_view['center']['w'] / 2 ); ?>" y="<?php echo esc_attr( -$graph_view['center']['h'] / 2 ); ?>" width="<?php echo esc_attr( $graph_view['center']['w'] ); ?>" height="<?php echo esc_attr( $graph_view['center']['h'] ); ?>" rx="26"/><text text-anchor="middle" direction="<?php echo $is_rtl_text( $center_label ) ? 'rtl' : 'ltr'; ?>"><?php foreach ( $graph_view['center']['lines'] as $line_index => $label_line ) : ?><tspan x="0" y="<?php echo esc_attr( $graph_view['center']['ys'][ $line_index ] ); ?>"><?php echo esc_html( $label_line ); ?></tspan><?php endforeach; ?></text></g><?php foreach ( $graph_view['nodes'] as $graph_item ) : ?><a class="ab-graph-node" href="<?php echo esc_url( $graph_item['href'] ); ?>" data-relation="<?php echo esc_attr( $graph_item['code'] ); ?>"><title><?php echo esc_html( $graph_item['full'] . ' — ' . $graph_item['sub'] ); ?></title><rect x="<?php echo esc_attr( $graph_item['x'] - $graph_item['w'] / 2 ); ?>" y="<?php echo esc_attr( $graph_item['y'] - $graph_item['h'] / 2 ); ?>" width="<?php echo esc_attr( round( $graph_item['w'], 1 ) ); ?>" height="<?php echo esc_attr( $graph_item['h'] ); ?>" rx="14"/><text class="ab-graph-label" text-anchor="middle" direction="<?php echo $is_rtl_text( $graph_item['label'] ) ? 'rtl' : 'ltr'; ?>"><?php foreach ( $graph_item['lines'] as $line_index => $label_line ) : ?><tspan x="<?php echo esc_attr( $graph_item['x'] ); ?>" y="<?php echo esc_attr( round( $graph_item['y'] + $graph_item['line_y'][ $line_index ], 1 ) ); ?>"><?php echo esc_html( $label_line ); ?></tspan><?php endforeach; ?></text><text class="ab-graph-rel" x="<?php echo esc_attr( $graph_item['x'] ); ?>" y="<?php echo esc_attr( round( $graph_item['y'] + $graph_item['rel_y'], 1 ) ); ?>" text-anchor="middle" direction="rtl"><?php echo esc_html( ( $graph_item['out'] ? '' : '← ' ) . $graph_item['sub'] ); ?></text></a><?php endforeach; ?></svg><figcaption id="ab-graph-caption">همسایگی «<?php echo esc_html( $center_label ); ?>» در نگارهٔ مفاهیم؛ فهرست متنی زیر همهٔ روابط را دارد.</figcaption></figure>
		<p class="ab-card-meta ab-meta-fa"><?php echo esc_html( $fa( $graph_hood['total'] ) ); ?> یال مستقیم<?php echo $graph_hood['total'] > count( $hood_edges ) ? '؛ نمایش ' . esc_html( $fa( count( $hood_edges ) ) ) . ' یال' : ''; ?></p>
		<div class="ab-results"><?php foreach ( $hood_edges as $hood_edge ) : ?><div class="ab-card"><span class="ab-card-meta ab-meta-fa ab-relation" title="<?php echo esc_attr( $hood_edge['relation'] ); ?>" data-relation="<?php echo esc_attr( $hood_edge['relation'] ); ?>"><?php echo esc_html( $hood_edge['direction_out'] ? 'این مفهوم ' . KBK_AI_Book::relation_label( $hood_edge['relation'] ) . ' ←' : '→ ' . KBK_AI_Book::relation_label( $hood_edge['relation'] ) . ' این مفهوم' ); ?></span><?php if ( null !== $hood_edge['other'] ) : ?><h3><a href="<?php echo esc_url( home_url( '/ai-book/graph/?' . KBK_AI_Book::ENTITY_QUERY_VAR . '=' . rawurlencode( $hood_edge['other']['entity_id'] ) ) ); ?>"><?php echo esc_html( '' !== $hood_edge['other']['label_fa'] ? $hood_edge['other']['label_fa'] : $hood_edge['other']['label_en'] ); ?></a></h3><p><?php echo '' !== $hood_edge['other']['label_fa'] ? KBK_AI_Book::english_html( $hood_edge['other']['label_en'], 'span' ) . ' · ' : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by the helper. ?><?php echo esc_html( KBK_AI_Book::entity_type_label( $hood_edge['other']['type'] ) ); ?></p><?php endif; ?><?php if ( '' !== $hood_edge['quote'] ) : ?><p>شاهد: «<?php echo esc_html( $hood_edge['quote'] ); ?>»</p><?php endif; ?><?php if ( $hood_edge['anchor_section'] ) : ?><span class="ab-card-meta"><a href="<?php echo esc_url( $read_link( $hood_edge['anchor_section']['part_id'], $hood_edge['anchor_section']['chapter_id'], $hood_edge['anchor_section']['structural_id'] ) ); ?>"><?php echo KBK_AI_Book::bidi_html( $hood_edge['anchor_section']['title_fa'] ); ?></a> · <bdi><?php echo esc_html( $hood_edge['anchor_section']['content_id'] ); ?></bdi></span><?php endif; ?></div><?php endforeach; ?></div>
		<?php else : ?><div class="ab-fallback"><p>این گره در نگارهٔ فعلی یال مستقیمی ندارد.</p></div><?php endif; ?>
		<p class="ab-card-meta"><bdi><?php echo esc_html( $graph_node['entity_id'] ); ?></bdi></p></section></article>
		<?php else :
			$graph_stats = $graph_engine->stats();
			$graph_relations = $graph_engine->relation_counts();
			?>
		<header class="ab-page-hero"><p class="ab-kicker">نگارهٔ مفاهیم، <?php echo esc_html( $fa( $graph_stats['nodes'] ) ); ?> گره، <?php echo esc_html( $fa( $graph_stats['edges'] ) ); ?> یال</p><h1>نگارهٔ مفاهیم کتاب</h1><p class="ab-lead">شبکهٔ روابط میان مفاهیم، ریسک‌ها، چارچوب‌ها و اسناد؛ برای گره‌ها از فهرست مفاهیم شروع کنید.</p><div class="ab-actions"><a class="ab-button ab-button-primary" href="<?php echo esc_url( home_url( '/ai-book/concepts/' ) ); ?>">فهرست مفاهیم</a></div></header>
		<section class="ab-section ab-structure-map" aria-labelledby="ab-structure-map-title"><div class="ab-section-head"><div><p class="ab-kicker">نقشهٔ ساختار</p><h2 id="ab-structure-map-title">ساختار کتاب: بخش‌ها و فصل‌ها</h2></div></div><ol class="ab-map-parts"><?php foreach ( $parts as $map_part ) : ?><li class="ab-map-part"><a class="ab-map-part-link" href="<?php echo esc_url( KBK_AI_Book::reader_url( $map_part['part_id'] ) ); ?>"><span class="ab-map-num"><?php echo esc_html( KBK_AI_Book::location_label( $map_part['part_id'] ) ); ?></span><span class="ab-map-title"><?php echo KBK_AI_Book::bidi_html( $map_part['title_fa'] ); ?></span></a><ol class="ab-map-chapters"><?php foreach ( $map_part['chapters'] as $map_chapter ) : $map_count = count( array_filter( $map_chapter['sections'], static function ( array $map_section ): bool { return ! KBK_AI_Book::is_omitted_section( $map_section ); } ) ); ?><li><a href="<?php echo esc_url( KBK_AI_Book::reader_url( $map_part['part_id'], $map_chapter['chapter_id'] ) ); ?>"><span class="ab-map-num">فصل <?php echo esc_html( $fa( KBK_AI_Book::id_number( $map_chapter['chapter_id'] ) ) ); ?></span><span class="ab-map-title"><?php echo KBK_AI_Book::bidi_html( $map_chapter['title_fa'] ); ?></span><span class="ab-map-count"><?php echo esc_html( $fa( $map_count ) ); ?> بخش</span></a></li><?php endforeach; ?></ol></li><?php endforeach; ?></ol></section>
		<section class="ab-section"><h2>نوع روابط</h2><div class="ab-term-list"><?php foreach ( $graph_relations as $relation_name => $relation_count ) : ?><span title="<?php echo esc_attr( $relation_name ); ?>" data-relation="<?php echo esc_attr( $relation_name ); ?>"><b><?php echo esc_html( KBK_AI_Book::relation_label( $relation_name ) ); ?></b><span class="ab-term-count"><?php echo esc_html( $fa( $relation_count ) ); ?> یال</span></span><?php endforeach; ?></div></section>
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
			<div class="ab-card"><span class="ab-card-meta ab-meta-fa"><?php echo esc_html( KBK_AI_Book::location_label( $ask_item['part_id'], $ask_item['chapter_id'] ) ); ?> · <?php echo esc_html( $origin_map[ $ask_item['origin'] ]['label'] ); ?></span><h3><?php echo esc_html( $ask_item['section_fa'] ); ?></h3><p><?php echo $render_segments( $ask_item['excerpt_segments'] ); ?><?php echo $ask_item['reader_resolvable'] ? '' : ' <bdi>(ارجاع در دسترس نیست)</bdi>'; ?></p><?php if ( $ask_item['reader_resolvable'] ) : ?><a class="ab-card-link" href="<?php echo esc_url( $read_link( $ask_item['part_id'], $ask_item['chapter_id'], $ask_item['structural_id'] ) ); ?>">مطالعهٔ بخش مستند</a><?php endif; ?><span class="ab-card-meta"><bdi><a href="<?php echo esc_url( $read_link( $ask_item['part_id'], $ask_item['chapter_id'], $ask_item['structural_id'] ) ); ?>"><?php echo esc_html( $ask_item['content_id'] ); ?></a></bdi></span></div>
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
			<div class="ab-pdf-stage"><iframe class="ab-pdf-embed" src="<?php echo esc_url( $pdf_stream ); ?>" title="نمایش نسخهٔ PDF کتاب" loading="lazy"></iframe><p class="ab-pdf-fallback">اگر فایل در این قاب نمایش داده نمی‌شود (بیشتر مرورگرهای تلفن همراه PDF را درون صفحه نشان نمی‌دهند): <a href="<?php echo esc_url( $pdf_stream ); ?>" target="_blank" rel="noopener">باز کردن PDF در زبانهٔ تازه</a> · <a href="<?php echo esc_url( $pdf_stream ); ?>" download>دانلود</a> · یا <a href="<?php echo esc_url( home_url( '/ai-book/read/' ) ); ?>">مطالعهٔ متنی همان محتوا</a></p></div>
			<aside><h2>دربارهٔ نسخه</h2><dl><dt>ویرایش</dt><dd><?php echo esc_html( $pdf_meta['edition'] ); ?></dd><dt>وضعیت امضا</dt><dd>امضای توسعه؛ امضای ناشر لازم است</dd><dt>ریشهٔ مرکل</dt><dd><bdi><?php echo esc_html( substr( $pdf_meta['merkle_root'], 0, 8 ) ); ?>…<?php echo esc_html( substr( $pdf_meta['merkle_root'], -7 ) ); ?></bdi></dd><dt>اثر انگشت SHA-256</dt><dd><bdi><?php echo esc_html( substr( $pdf_meta['sha256'], 0, 8 ) ); ?>…<?php echo esc_html( substr( $pdf_meta['sha256'], -7 ) ); ?></bdi></dd><dt>حجم</dt><dd><?php echo esc_html( $fa( (int) $pdf_meta['bytes'] ) ); ?> بایت</dd><dt>ساخت</dt><dd><bdi><?php echo esc_html( $pdf_meta['build_date'] ); ?></bdi></dd></dl><a class="ab-button ab-button-primary" href="<?php echo esc_url( home_url( '/ai-book/request-pdf/' ) ); ?>">درخواست نسخهٔ شخصی</a></aside>
		</div>
		<?php else : ?>
		<section class="ab-section"><div class="ab-fallback"><p><?php echo 'CONFIG_REQUIRED' === $pdf_status ? 'نسخهٔ PDF هنوز پیکربندی نشده است.' : 'نسخهٔ PDF موقتاً در دسترس نیست؛ یکپارچگی فایل تأیید نشد.'; ?></p><p>تا آن زمان می‌توانید <a href="<?php echo esc_url( home_url( '/ai-book/read/' ) ); ?>">همان محتوا را متنی بخوانید</a>.</p></div></section>
		<?php endif; ?>
	<?php elseif ( 'verify' === $view ) :
		$verification = KBK_AI_Book::current_verification();
		$release      = $verification['release'];
		$match        = $verification['match'];
		$edition      = $release['edition_id'] ?? ( $summary['edition_id'] ?? '' );
		// The manifest carries a development key only; say so wherever a result is shown.
		$signing_note = 'امضای توسعه؛ کلید امضای ناشر هنوز لازم است';
		?>
		<header class="ab-page-hero"><p class="ab-kicker">Provenance<?php if ( $release && '' !== $release['hash_spec'] ) : ?> · <bdi><?php echo esc_html( $release['hash_spec'] ); ?></bdi><?php endif; ?></p><h1>اعتبارسنجی نسخه و محتوا</h1><p class="ab-lead">شناسهٔ محتوا، شناسهٔ ساختاری یا اثر انگشت SHA-256 را وارد کنید تا جایگاه آن در نسخهٔ منتشرشده بررسی شود.</p></header>
		<form class="ab-verify-form" method="get" action="<?php echo esc_url( home_url( '/ai-book/verify/' ) ); ?>"><label for="verify-id">شناسه یا SHA-256</label><div><input id="verify-id" name="<?php echo esc_attr( KBK_AI_Book::VERIFY_QUERY_VAR ); ?>" dir="ltr" value="<?php echo esc_attr( (string) $verification['query'] ); ?>" placeholder="KDJ-<?php echo esc_attr( $book_code ); ?>-<?php echo esc_attr( $edition ); ?>-P01-C01-S01" maxlength="80" autocomplete="off" spellcheck="false"><button class="ab-button ab-button-primary" type="submit">بررسی</button></div></form>
		<?php if ( 'READY' !== $verification['status'] ) : ?>
		<section class="ab-section"><div class="ab-fallback"><p>اعتبارسنجی موقتاً در دسترس نیست؛ اعتبار داده‌های کتاب تأیید نشد و هیچ نتیجهٔ حدسی نمایش داده نمی‌شود.</p><p><bdi><?php echo esc_html( $verification['status'] ); ?></bdi></p></div></section>
		<?php else : ?>
		<?php if ( $verification['submitted'] && null === $verification['query'] ) : ?>
		<section class="ab-verification" aria-live="polite"><div class="ab-status ab-status-miss"><span>مقدار واردشده شناسه یا اثر انگشت معتبری نیست؛ فقط حروف لاتین، رقم و خط تیره پذیرفته می‌شود.</span><b>INVALID</b></div></section>
		<?php elseif ( null !== $verification['query'] && null === $match ) : ?>
		<section class="ab-verification" aria-live="polite"><div class="ab-status ab-status-miss"><span>این شناسه یا اثر انگشت در نسخهٔ منتشرشده ثبت نشده است.</span><b>NOT FOUND</b></div><dl><div><dt>مقدار بررسی‌شده</dt><dd><bdi dir="ltr"><?php echo esc_html( $verification['query'] ); ?></bdi></dd></div><div><dt>ویرایش</dt><dd><bdi><?php echo esc_html( $edition ); ?></bdi></dd></div></dl></section>
		<?php elseif ( null !== $match && 'content' === $match['kind'] ) :
			$match_section = $match['section'];
			?>
		<section class="ab-verification" aria-live="polite"><div class="ab-status"><span>این محتوا در فهرست محتوای نسخهٔ منتشرشده ثبت است.</span><b>VERIFIED</b></div><dl>
			<div><dt>ویرایش</dt><dd><bdi><?php echo esc_html( $edition ); ?></bdi></dd></div>
			<div><dt>شناسهٔ محتوا</dt><dd><bdi dir="ltr"><?php echo esc_html( $match['content_id'] ); ?></bdi></dd></div>
			<div><dt>شناسهٔ ساختاری</dt><dd><bdi dir="ltr"><?php echo esc_html( $match['structural_id'] ); ?></bdi></dd></div>
			<div><dt>لایهٔ محتوا</dt><dd><?php echo esc_html( $origin_map[ $match['origin'] ]['label'] ?? $match['origin'] ); ?></dd></div>
			<div><dt>اثر انگشت SHA-256</dt><dd><bdi dir="ltr"><?php echo esc_html( $match['sha256'] ); ?></bdi></dd></div>
			<?php if ( $match_section ) : ?><div><dt>جایگاه در کتاب</dt><dd><a href="<?php echo esc_url( $read_link( $match_section['part_id'], $match_section['chapter_id'], $match_section['structural_id'] ) ); ?>"><?php echo esc_html( KBK_AI_Book::reader_section_title( $match_section ) ); ?></a></dd></div><?php endif; ?>
			<?php if ( $release ) : ?><div><dt>ریشهٔ مرکل</dt><dd><bdi dir="ltr"><?php echo esc_html( $release['merkle_root'] ); ?></bdi></dd></div><?php endif; ?>
			<div><dt>وضعیت امضا</dt><dd><?php echo esc_html( $signing_note ); ?></dd></div>
		</dl></section>
		<?php elseif ( null !== $match ) : ?>
		<section class="ab-verification" aria-live="polite"><div class="ab-status"><span>این اثر انگشت با یکی از فایل‌های نسخهٔ منتشرشده یکسان است.</span><b>VERIFIED</b></div><dl>
			<div><dt>فایل</dt><dd><bdi dir="ltr"><?php echo esc_html( $match['name'] ); ?></bdi></dd></div>
			<div><dt>حجم</dt><dd><?php echo esc_html( $fa( (int) $match['bytes'] ) ); ?> بایت</dd></div>
			<div><dt>اثر انگشت SHA-256</dt><dd><bdi dir="ltr"><?php echo esc_html( $match['sha256'] ); ?></bdi></dd></div>
			<div><dt>ویرایش</dt><dd><bdi><?php echo esc_html( $edition ); ?></bdi></dd></div>
			<div><dt>ریشهٔ مرکل</dt><dd><bdi dir="ltr"><?php echo esc_html( $release['merkle_root'] ); ?></bdi></dd></div>
			<div><dt>وضعیت امضا</dt><dd><?php echo esc_html( $signing_note ); ?></dd></div>
		</dl></section>
		<?php elseif ( $release ) : ?>
		<section class="ab-verification"><div class="ab-status"><span>مشخصات نسخهٔ منتشرشده</span><b><?php echo esc_html( $release['edition_id'] ); ?></b></div><dl>
			<div><dt>شناسهٔ ویرایش</dt><dd><bdi><?php echo esc_html( $release['edition_id'] ); ?></bdi></dd></div>
			<?php if ( '' !== $release['release_id'] ) : ?><div><dt>شناسهٔ انتشار</dt><dd><bdi><?php echo esc_html( $release['release_id'] ); ?></bdi></dd></div><?php endif; ?>
			<div><dt>تاریخ ساخت</dt><dd><bdi dir="ltr"><?php echo esc_html( $release['build_date'] ); ?></bdi></dd></div>
			<div><dt>ناشر</dt><dd><?php echo esc_html( $release['publisher'] ); ?></dd></div>
			<div><dt>ریشهٔ مرکل</dt><dd><bdi dir="ltr"><?php echo esc_html( $release['merkle_root'] ); ?></bdi></dd></div>
			<div><dt>وضعیت امضا</dt><dd><?php echo esc_html( $signing_note ); ?></dd></div>
		</dl></section>
		<?php endif; ?>
		<?php if ( $release ) : ?>
		<section class="ab-section ab-verify-artifacts"><div class="ab-section-head"><div><p class="ab-kicker">فایل‌های نسخه</p><h2>اثر انگشت فایل‌های منتشرشده</h2></div></div><p class="ab-lead">اثر انگشت SHA-256 فایلی را که در دست دارید محاسبه کنید و با این فهرست یا فرم بالا مقایسه کنید.</p><div class="ab-table-wrap"><table><thead><tr><th>فایل</th><th>حجم (بایت)</th><th>SHA-256</th></tr></thead><tbody>
			<?php foreach ( $release['artifacts'] as $artifact_name => $artifact ) : ?>
			<tr><td><bdi dir="ltr"><?php echo esc_html( $artifact_name ); ?></bdi></td><td><?php echo esc_html( $fa( (int) $artifact['bytes'] ) ); ?></td><td class="ab-verify-hash"><bdi dir="ltr"><?php echo esc_html( $artifact['sha256'] ); ?></bdi></td></tr>
			<?php endforeach; ?>
		</tbody></table></div></section>
		<?php endif; ?>
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
		<header class="ab-page-hero"><p class="ab-kicker">یافت نشد</p><h1>این بخش از کتاب موجود نیست</h1><p class="ab-lead">شناسهٔ درخواستی با هیچ‌یک از <?php echo $legacy ? 'هفت' : esc_html( $fa( count( $parts ) ) ); ?> بخش اصلی کتاب مطابقت ندارد.</p><div class="ab-actions"><a class="ab-button ab-button-primary" href="<?php echo esc_url( home_url( '/ai-book/' ) ); ?>">بازگشت به فهرست بخش‌ها</a></div></header>
	<?php elseif ( $part && $chapter ) :
		$current_page  = $reader_page['page'];
		$page_count    = $reader_page['pages'];
		$section_pages = KBK_AI_Book::chapter_section_pages( $chapter );
		$page_sections = array();
		foreach ( $reader_page['indexes'] as $section_index ) {
			$page_sections[] = $chapter['sections'][ $section_index ];
		}
		// A section longer than the page budget is read in parts; part k > 1
		// is anchored as ID-pk and listed in the TOC under its own page.
		$part_pages = array();
		foreach ( $reader_page['all'] as $slice_page => $page_slices ) {
			foreach ( $page_slices as $page_slice ) {
				$part_pages[ $chapter['sections'][ $page_slice['index'] ]['structural_id'] ][ $page_slice['part'] ] = $slice_page + 1;
			}
		}
		$slice_anchor = static function ( array $slice ) use ( $chapter ): string {
			$sid = $chapter['sections'][ $slice['index'] ]['structural_id'];
			return $slice['part'] > 1 ? $sid . '-p' . $slice['part'] : $sid;
		};
		$part_label = static function ( int $part, int $parts ) use ( $fa ): string {
			return 'بخش ' . $fa( $part ) . ' از ' . $fa( $parts );
		};
		$visible_sections = array_values( array_filter( $chapter['sections'], static function ( array $toc_section ): bool { return ! KBK_AI_Book::is_omitted_section( $toc_section ); } ) );
		$related_map  = array();
		$graph_engine = KBK_AI_Book::graph();
		if ( null !== $graph_engine ) {
			$related_map = $graph_engine->related_map( array_column( array_filter( $page_sections, static function ( array $page_section ): bool { return ! KBK_AI_Book::is_omitted_section( $page_section ); } ), 'content_id' ) );
		}
		$chapter_kicker = KBK_AI_Book::location_label( $part['part_id'], $chapter['chapter_id'] ) . ( $page_count > 1 ? ' — صفحهٔ ' . $fa( $current_page ) . ' از ' . $fa( $page_count ) : '' );
		$current_sid    = null !== $selection['section'] ? $selection['section']['structural_id'] : null;
		if ( null === $current_sid ) {
			foreach ( $reader_page['slices'] as $page_slice ) {
				if ( ! KBK_AI_Book::is_omitted_section( $chapter['sections'][ $page_slice['index'] ] ) ) {
					$current_sid = $slice_anchor( $page_slice );
					break;
				}
			}
		}
		$toc_href = static function ( array $toc_section ) use ( $section_pages, $current_page, $part, $chapter ): string {
			$target_page = $section_pages[ $toc_section['structural_id'] ] ?? 1;
			return $target_page === $current_page ? '#' . rawurlencode( $toc_section['structural_id'] ) : KBK_AI_Book::section_url( $part['part_id'], $chapter['chapter_id'], $toc_section['structural_id'] );
		};
		$toc_links = static function () use ( $visible_sections, $section_pages, $part_pages, $page_count, $current_page, $current_sid, $toc_href, $part_label, $fa, $part, $chapter ): string {
			$html = '';
			$last = 0;
			foreach ( $visible_sections as $toc_section ) {
				$sid      = $toc_section['structural_id'];
				$entries  = array( array( $sid, $section_pages[ $sid ] ?? 1, KBK_AI_Book::bidi_html( KBK_AI_Book::reader_section_title( $toc_section ) ), $toc_href( $toc_section ), '' ) );
				$parts    = $part_pages[ $sid ] ?? array();
				foreach ( $parts as $part_number => $part_page ) {
					if ( $part_number > 1 ) {
						$anchor    = $sid . '-p' . $part_number;
						$entries[] = array( $anchor, $part_page, 'ادامه — ' . esc_html( $part_label( $part_number, count( $parts ) ) ), $part_page === $current_page ? '#' . rawurlencode( $anchor ) : KBK_AI_Book::reader_url( $part['part_id'], $chapter['chapter_id'], $part_page ) . '#' . rawurlencode( $anchor ), 'ab-toc-cont' );
					}
				}
				foreach ( $entries as $entry ) {
					list( $anchor, $toc_page, $label, $href, $class ) = $entry;
					if ( $page_count > 1 && $toc_page !== $last ) {
						$html .= '<p class="ab-toc-page">صفحهٔ ' . esc_html( $fa( $toc_page ) ) . '</p>';
						$last  = $toc_page;
					}
					$classes = trim( $class . ( $current_sid === $anchor ? ' is-current' : '' ) );
					$html   .= '<a' . ( '' !== $classes ? ' class="' . esc_attr( $classes ) . '"' : '' ) . ( $current_sid === $anchor ? ' aria-current="location"' : '' ) . ' href="' . esc_url( $href ) . '">' . $label . '</a>';
				}
			}
			return $html;
		};
		$first_title = static function ( int $page_number ) use ( $reader_page, $chapter ): string {
			foreach ( $reader_page['all'][ $page_number - 1 ] ?? array() as $page_slice ) {
				$slice_section = $chapter['sections'][ $page_slice['index'] ];
				if ( ! KBK_AI_Book::is_omitted_section( $slice_section ) ) {
					return ( $page_slice['part'] > 1 ? 'ادامهٔ ' : '' ) . KBK_AI_Book::reader_section_title( $slice_section );
				}
			}
			return $chapter['title_fa'];
		};
		$pager = '';
		if ( $page_count > 1 ) {
			$pager = '<nav class="ab-pager" aria-label="صفحه‌های این فصل"><span class="ab-pager-label">صفحه‌ها</span>';
			for ( $page_number = 1; $page_number <= $page_count; $page_number++ ) {
				$pager .= $page_number === $current_page
					? '<a aria-current="page" href="' . esc_url( KBK_AI_Book::reader_url( $part['part_id'], $chapter['chapter_id'], $page_number ) ) . '">' . esc_html( $fa( $page_number ) ) . '</a>'
					: '<a href="' . esc_url( KBK_AI_Book::reader_url( $part['part_id'], $chapter['chapter_id'], $page_number ) ) . '" title="' . esc_attr( $first_title( $page_number ) ) . '">' . esc_html( $fa( $page_number ) ) . '</a>';
			}
			$pager .= '</nav>';
		}
		$prev_card = null;
		$next_card = null;
		if ( $current_page > 1 ) {
			$prev_card = array( 'label' => 'صفحهٔ قبلی', 'title' => $first_title( $current_page - 1 ), 'href' => KBK_AI_Book::reader_url( $part['part_id'], $chapter['chapter_id'], $current_page - 1 ) );
		} elseif ( $adjacent['prev'] ) {
			$prev_card = array( 'label' => 'فصل قبلی', 'title' => $adjacent['prev']['title_fa'], 'href' => KBK_AI_Book::reader_url( $adjacent['prev']['part_id'] ?? $part['part_id'], $adjacent['prev']['chapter_id'] ) );
		}
		if ( $current_page < $page_count ) {
			$next_card = array( 'label' => 'صفحهٔ بعدی', 'title' => $first_title( $current_page + 1 ), 'href' => KBK_AI_Book::reader_url( $part['part_id'], $chapter['chapter_id'], $current_page + 1 ) );
		} elseif ( $adjacent['next'] ) {
			$next_card = array( 'label' => 'فصل بعدی', 'title' => $adjacent['next']['title_fa'], 'href' => KBK_AI_Book::reader_url( $adjacent['next']['part_id'] ?? $part['part_id'], $adjacent['next']['chapter_id'] ) );
		}
		$page_urls = array();
		for ( $page_number = 1; $page_number <= $page_count; $page_number++ ) {
			$page_urls[] = KBK_AI_Book::reader_url( $part['part_id'], $chapter['chapter_id'], $page_number );
		}
		$reader_defaults = KBK_AI_Book::reader_settings();
		$views_on        = ! empty( $reader_defaults['views_show'] ) && class_exists( 'KBK_AI_Book_Views' );
		$views_counts    = $views_on ? KBK_AI_Book_Views::chapter_counts( $book_slug, $part['part_id'], $chapter ) : array( 'sections' => array(), 'chapter' => 0 );
		$views_badge     = static function ( int $count, string $attrs, string $extra_class = '' ) use ( $reader_defaults, $fa ): string {
			$hidden = ( 0 === $count && ! empty( $reader_defaults['views_hide_zero'] ) ) ? ' hidden' : '';
			return '<span class="ab-views' . $extra_class . '" ' . $attrs . ' title="بازدید"' . $hidden . '><span class="ab-views-n">' . esc_html( $fa( $count ) ) . '</span> بازدید</span>';
		};
		$omit_note       = 'این بخش فقط فهرست یا صفحات آغازین سند منبع را داشت و در نسخهٔ وب نمایش داده نمی‌شود؛ متن کامل در نسخهٔ PDF کتاب آمده است.';
		?>
		<nav class="ab-breadcrumb" aria-label="مسیر"><a href="<?php echo esc_url( home_url( '/ai-book/' ) ); ?>">کتاب</a><a href="<?php echo esc_url( KBK_AI_Book::reader_url( $part['part_id'] ) ); ?>"><?php echo KBK_AI_Book::bidi_html( $part['title_fa'] ); ?></a><span aria-current="page"><?php echo KBK_AI_Book::bidi_html( $chapter['title_fa'] ); ?></span></nav>
		<script type="application/json" id="ab-chapter-map"><?php echo wp_json_encode( array( 'book' => $book_slug, 'part' => $part['part_id'], 'chapter' => $chapter['chapter_id'], 'title' => $chapter['title_fa'], 'page' => $current_page, 'pages' => $page_count, 'urls' => $page_urls, 'sections' => $section_pages, 'views' => $views_on, 'hideZero' => ! empty( $reader_defaults['views_hide_zero'] ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
		<div class="ab-reader" data-default-wide="<?php echo $reader_defaults['default_wide'] ? '1' : '0'; ?>" data-default-focus="<?php echo $reader_defaults['default_focus'] ? '1' : '0'; ?>"><aside class="ab-toc" aria-label="فهرست فصل"><p class="ab-kicker"><?php echo esc_html( $chapter_kicker ); ?></p><h2><?php echo KBK_AI_Book::bidi_html( $chapter['title_fa'] ); ?></h2><?php echo $toc_links(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped while built. ?></aside>
		<article class="ab-prose"><?php if ( $selection['chapter_not_found'] ) : ?><p class="ab-lead">فصل درخواستی در این بخش یافت نشد؛ نخستین فصل بخش <bdi><?php echo esc_html( $part['title_fa'] ); ?></bdi> نمایش داده می‌شود.</p><?php endif; ?>
		<details class="ab-reader-index"><summary><span>فهرست این فصل</span><span class="ab-reader-index-loc"><?php echo esc_html( $chapter_kicker ); ?></span></summary><nav aria-label="فهرست فصل در نمایش فشرده"><?php echo $toc_links(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped while built. ?></nav></details>
		<header class="ab-page-hero ab-reader-hero"><p class="ab-kicker"><?php echo esc_html( $chapter_kicker ); ?><?php echo $views_on ? ' ' . $views_badge( (int) $views_counts['chapter'], 'data-chapter', ' ab-views-chapter' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped while built. ?></p><h1><?php echo KBK_AI_Book::bidi_html( $chapter['title_fa'] ); ?></h1><p class="ab-lead">متن اصلی فارسی با منشأ، شناسه و پیوند استناد پایدار.</p><p class="ab-resume" data-ab-resume="chapter" hidden></p></header>
		<?php echo $pager; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped while built. ?>
		<?php foreach ( $reader_page['slices'] as $slice ) :
			$section = $chapter['sections'][ $slice['index'] ];
			if ( KBK_AI_Book::is_omitted_section( $section ) ) : ?>
		<section id="<?php echo esc_attr( $section['structural_id'] ); ?>" class="ab-section-omitted" aria-label="بخش حذف‌شده از نسخهٔ وب"><p class="ab-omit-note"><?php echo esc_html( $omit_note ); ?></p></section>
			<?php continue; endif;
			$origin   = $origin_map[ $section['origin'] ];
			$related  = $related_map[ $section['content_id'] ] ?? null;
			$citation = $repository->find_citation( $section['structural_id'] );
			$permalink = KBK_AI_Book::section_url( $part['part_id'], $chapter['chapter_id'], $section['structural_id'] );
			$is_split  = $slice['parts'] > 1;
			$is_last   = $slice['part'] === $slice['parts'];
			$next_part = $is_last ? null : ( $part_pages[ $section['structural_id'] ][ $slice['part'] + 1 ] ?? null );
			?>
		<?php if ( $slice['part'] > 1 ) : ?>
		<section id="<?php echo esc_attr( $slice_anchor( $slice ) ); ?>" class="ab-section-cont" data-ab-section="<?php echo esc_attr( $section['structural_id'] ); ?>"><h2 class="ab-cont-label"><span>ادامهٔ بخش «<?php echo KBK_AI_Book::bidi_html( KBK_AI_Book::reader_section_title( $section ) ); ?>»</span> <span class="ab-cont-part">— <?php echo esc_html( $part_label( $slice['part'], $slice['parts'] ) ); ?></span></h2>
		<?php else : ?>
		<section id="<?php echo esc_attr( $section['structural_id'] ); ?>"><h2><?php echo KBK_AI_Book::bidi_html( KBK_AI_Book::reader_section_title( $section ) ); ?></h2><?php echo $views_on ? $views_badge( (int) ( $views_counts['sections'][ $section['structural_id'] ] ?? 0 ), 'data-section="' . esc_attr( $section['structural_id'] ) . '"' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped while built. ?><?php if ( $is_split ) : ?><p class="ab-part-note"><?php echo esc_html( $part_label( 1, $slice['parts'] ) ); ?> — این بخش طولانی است و در چند صفحه آمده است.</p><?php endif; ?>
		<?php endif; ?>
		<div class="ab-layer <?php echo esc_attr( $origin['class'] ); ?>"><?php if ( 1 === $slice['part'] ) : ?><span><?php echo esc_html( $origin['label'] ); ?></span><?php endif; ?><div class="ab-reader-content"><?php echo wp_kses_post( KBK_AI_Book::reader_section_html( $section, $slice['range'], $slice['part'] > 1 ) ); ?></div></div>
		<?php if ( null !== $next_part ) : ?><p class="ab-part-next"><a href="<?php echo esc_url( KBK_AI_Book::reader_url( $part['part_id'], $chapter['chapter_id'], $next_part ) . '#' . rawurlencode( $section['structural_id'] . '-p' . ( $slice['part'] + 1 ) ) ); ?>">ادامهٔ این بخش در صفحهٔ <?php echo esc_html( $fa( $next_part ) ); ?><span aria-hidden="true"> ←</span></a></p><?php endif; ?>
		<?php if ( $is_last || $next_part !== $current_page ) : // every page that holds part of a section can cite it ?>
		<details class="ab-cite-box"><summary>استناد</summary><div class="ab-cite-body"><?php if ( $citation && ! empty( $citation['citation_fa'] ) ) : ?><p class="ab-cite-text"><?php echo KBK_AI_Book::bidi_html( (string) $citation['citation_fa'] ); ?></p><?php endif; ?><p class="ab-cite-id"><span>شناسهٔ محتوا</span> <span dir="ltr"><?php echo esc_html( $section['content_id'] ); ?></span></p><p class="ab-cite-actions"><a href="<?php echo esc_url( $permalink ); ?>">پیوند مستقیم این بخش</a><button type="button" class="ab-copy" data-ab-copy="<?php echo esc_url( $permalink ); ?>" hidden>کپی پیوند</button></p></div></details>
		<?php endif; ?>
		<?php if ( $is_last && $related ) : ?>
		<nav class="ab-related-inline" aria-label="مطالعهٔ مرتبط"><div><h3>مفاهیم مرتبط</h3><ul class="ab-concept-links"><?php foreach ( $related['concepts'] as $related_concept ) : ?><li><a href="<?php echo esc_url( home_url( '/ai-book/concepts/?' . KBK_AI_Book::ENTITY_QUERY_VAR . '=' . rawurlencode( $related_concept['entity_id'] ) ) ); ?>"><?php echo KBK_AI_Book::bidi_html( '' !== $related_concept['label_fa'] ? $related_concept['label_fa'] : $related_concept['label_en'] ); ?></a></li><?php endforeach; ?></ul></div>
		<?php if ( $related['sections'] ) : ?><div><h3>ادامهٔ مطالعه</h3><ul class="ab-section-links"><?php foreach ( $related['sections'] as $related_section ) : ?><li><a href="<?php echo esc_url( $read_link( $related_section['part_id'], $related_section['chapter_id'], $related_section['structural_id'] ) ); ?>"><span><?php echo KBK_AI_Book::bidi_html( $related_section['title_fa'] ); ?></span><span aria-hidden="true">←</span></a></li><?php endforeach; ?></ul></div><?php endif; ?>
		</nav>
		<?php endif; ?>
		</section>
		<?php endforeach; ?>
		<?php echo $pager; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped while built. ?>
		<nav class="ab-prev-next" aria-label="<?php echo $page_count > 1 ? 'صفحه یا فصل قبل و بعد' : 'فصل قبل و بعد'; ?>">
		<?php if ( $prev_card ) : ?><a class="ab-pn ab-pn-prev" rel="prev" href="<?php echo esc_url( $prev_card['href'] ); ?>"><span class="ab-pn-label"><span aria-hidden="true">→ </span><?php echo esc_html( $prev_card['label'] ); ?></span><span class="ab-pn-title"><?php echo KBK_AI_Book::bidi_html( $prev_card['title'] ); ?></span></a><?php else : ?><span class="ab-pn-empty"></span><?php endif; ?>
		<?php if ( $next_card ) : ?><a class="ab-pn ab-pn-next" rel="next" href="<?php echo esc_url( $next_card['href'] ); ?>"><span class="ab-pn-label"><?php echo esc_html( $next_card['label'] ); ?><span aria-hidden="true"> ←</span></span><span class="ab-pn-title"><?php echo KBK_AI_Book::bidi_html( $next_card['title'] ); ?></span></a><?php endif; ?>
		</nav>
		</article></div>
	<?php endif; ?>
</main>
<?php if ( $legacy || 'library' === $view ) : ?><footer class="ab-footer"><div><b><?php echo esc_html( $summary['title_fa'] ?? 'کتاب مرجع هوش مصنوعی' ); ?></b><p>ترجمه، تدوین و بازآرایی فارسی: محمدعلی کهن‌دژ</p></div><p class="ab-disclaimer">این اثر ترجمهٔ رسمی NIST نمی‌باشد و توسط محمدعلی کهن‌دژ جهت آموزش و آگاهی‌رسانی حاکمیت هوش مصنوعی طراحی و توسعه داده شده است.</p></footer>
<?php else : ?><footer class="ab-footer"><div><b><?php echo esc_html( '' !== $book['title_fa'] ? $book['title_fa'] : $book['name'] ); ?></b><?php if ( '' !== $book['attribution'] ) : ?><p><?php echo esc_html( $book['attribution'] ); ?></p><?php elseif ( '' !== $book['compiler'] ) : ?><p><?php echo esc_html( $book['compiler'] ); ?></p><?php endif; ?></div></footer>
<?php endif; ?>
<footer class="ab-site-footer blog-footer" aria-label="پاورقی سایت"></footer>
<?php wp_footer(); ?>
</body>
</html>
