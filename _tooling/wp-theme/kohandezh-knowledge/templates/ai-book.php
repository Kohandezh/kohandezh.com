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
$page_title = 'home' === $view ? 'کتاب مرجع هوش مصنوعی کهن‌دژ' : ( 'search' === $view ? 'جست‌وجو — کتاب هوش مصنوعی کهن‌دژ' : ( 'glossary' === $view ? 'واژه‌نامه — کتاب هوش مصنوعی کهن‌دژ' : ( $chapter['title_fa'] ?? 'مطالعه آنلاین کتاب هوش مصنوعی' ) ) );
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
	<?php wp_head(); ?>
</head>
<body class="kbk-ai-book-page">
<?php wp_body_open(); ?>
<a class="ab-skip" href="#main">رفتن به محتوای اصلی</a>
<header class="ab-header">
	<a class="ab-brand" href="<?php echo esc_url( home_url( '/ai-book/' ) ); ?>"><span class="ab-brand-mark">ک</span><span><b>کهن‌دژ</b><small>کتاب مرجع هوش مصنوعی</small></span></a>
	<nav class="ab-primary" aria-label="ناوبری اصلی کتاب"><a href="<?php echo esc_url( home_url( '/ai-book/read/' ) ); ?>"<?php echo 'read' === $view ? ' aria-current="page"' : ''; ?>>مطالعه</a><a href="<?php echo esc_url( home_url( '/ai-book/search/' ) ); ?>">جست‌وجو</a><a href="<?php echo esc_url( home_url( '/ai-book/ask/' ) ); ?>">پرسش</a><a href="<?php echo esc_url( home_url( '/ai-book/concepts/' ) ); ?>">مفاهیم</a></nav>
	<div class="ab-tools"><a href="<?php echo esc_url( home_url( '/ai-book/sources/' ) ); ?>">منابع</a><a href="<?php echo esc_url( home_url( '/ai-book/verify/' ) ); ?>">اعتبارسنجی</a><button class="ab-menu-button" type="button" aria-expanded="false" aria-controls="mobile-nav">فهرست</button></div>
</header>
<nav id="mobile-nav" class="ab-mobile-nav" aria-label="فهرست همراه" hidden><a href="<?php echo esc_url( home_url( '/ai-book/read/' ) ); ?>">مطالعه</a><a href="<?php echo esc_url( home_url( '/ai-book/search/' ) ); ?>">جست‌وجو</a><a href="<?php echo esc_url( home_url( '/ai-book/graph/' ) ); ?>">نقشه دانش</a></nav>
<main id="main" class="ab-main" tabindex="-1">
	<?php if ( ! $repository ) : ?>
		<header class="ab-page-hero"><p class="ab-kicker">وضعیت داده</p><h1>کتاب موقتاً در دسترس نیست</h1><p class="ab-lead">پیکربندی منبع canonical کامل نشده یا اعتبار داده تأیید نشده است. هیچ محتوای حدسی نمایش داده نمی‌شود.</p><p><bdi><?php echo esc_html( KBK_AI_Book::repository_status() ); ?></bdi></p></header>
	<?php elseif ( 'home' === $view ) : ?>
		<header class="ab-page-hero"><p class="ab-kicker">نسخهٔ فارسی · <?php echo esc_html( $summary['edition_id'] ); ?></p><h1>دانش فنی، با مسیر روشن تا منبع</h1><p class="ab-lead"><?php echo esc_html( $summary['title_fa'] ); ?>؛ هر بخش با شناسه، منشأ و مسیر بازگشت به شاهد.</p><div class="ab-actions"><a class="ab-button ab-button-primary" href="<?php echo esc_url( home_url( '/ai-book/read/' ) ); ?>">مطالعه آنلاین</a></div></header>
		<section class="ab-stat-grid" aria-label="مشخصات نسخه"><div><b><?php echo esc_html( $summary['parts'] ); ?></b><span>بخش</span></div><div><b><?php echo esc_html( $summary['chapters'] ); ?></b><span>فصل</span></div><div><b><?php echo esc_html( $summary['sections'] ); ?></b><span>بخش محتوایی</span></div><div><b>۱۸</b><span>منبع اصلی</span></div></section>
		<section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker">ساختار کتاب</p><h2>هفت بخش canonical</h2></div></div><div class="ab-card-grid">
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
		<section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker">وضعیت داده</p><h2>جست‌وجو موقتاً در دسترس نیست</h2></div></div><p class="ab-lead">پیکربندی منبع canonical کامل نشده یا اعتبار داده تأیید نشده است. هیچ محتوای حدسی نمایش داده نمی‌شود.</p><p><bdi><?php echo esc_html( $search['status'] ); ?></bdi></p></section>
		<?php elseif ( null === $search['query'] ) : ?>
		<section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker">راهنما</p><h2>عبارتی برای جست‌وجو وارد کنید</h2></div></div><p class="ab-lead">می‌توانید متن فارسی، اصطلاح یا سرواژهٔ انگلیسی (مانند <bdi>RMF</bdi>)، نام سند (مانند <bdi>NIST AI 100-1</bdi>) یا شناسهٔ محتوا (مانند <bdi>KDJ-AI-2026E1-P01-C01-S01</bdi>) را جست‌وجو کنید.</p></section>
		<?php elseif ( 0 === $search['total'] ) : ?>
		<section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker">نتیجه‌ای یافت نشد</p><h2>نتیجه‌ای برای «<?php echo esc_html( $search['query'] ); ?>» وجود ندارد</h2></div></div><p class="ab-lead">جست‌وجو دقیق است و نتیجهٔ حدسی تولید نمی‌کند؛ عبارت کوتاه‌تر یا دیگری را امتحان کنید.</p></section>
		<?php else : ?>
		<section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker"><?php echo esc_html( $search['total'] ); ?> نتیجه<?php echo $search['truncated'] ? ' · نمایش اولین ' . esc_html( $search['limit'] ) . ' نتیجه' : ''; ?></p><h2>نتایج برای «<?php echo esc_html( $search['query'] ); ?>»</h2></div></div><div class="ab-results">
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
		</div></section>
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
			<header class="ab-page-hero"><p class="ab-kicker">وضعیت داده</p><h1>واژه‌نامه موقتاً در دسترس نیست</h1><p class="ab-lead">پیکربندی منبع canonical کامل نشده یا اعتبار داده تأیید نشده است.</p><p><bdi><?php echo esc_html( KBK_AI_Book::catalog_status() ); ?></bdi></p></header>
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
		<header class="ab-page-hero"><p class="ab-kicker">وضعیت داده</p><h1>فهرست منابع موقتاً در دسترس نیست</h1><p class="ab-lead">پیکربندی منبع canonical کامل نشده یا اعتبار داده تأیید نشده است.</p><p><bdi><?php echo esc_html( KBK_AI_Book::catalog_status() ); ?></bdi></p></header>
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
		<header class="ab-page-hero"><p class="ab-kicker">وضعیت داده</p><h1>قالب‌ها موقتاً در دسترس نیستند</h1><p class="ab-lead">پیکربندی منبع canonical کامل نشده یا اعتبار داده تأیید نشده است.</p><p><bdi><?php echo esc_html( KBK_AI_Book::catalog_status() ); ?></bdi></p></header>
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
		<header class="ab-page-hero"><p class="ab-kicker">وضعیت داده</p><h1>مفاهیم موقتاً در دسترس نیستند</h1><p class="ab-lead">پیکربندی منبع canonical کامل نشده یا اعتبار داده تأیید نشده است.</p><p><bdi><?php echo esc_html( KBK_AI_Book::catalog_status() ); ?></bdi></p></header>
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
		<header class="ab-page-hero"><p class="ab-kicker">وضعیت داده</p><h1>نگارهٔ مفاهیم موقتاً در دسترس نیست</h1><p class="ab-lead">پیکربندی منبع canonical کامل نشده یا اعتبار داده تأیید نشده است.</p><p><bdi><?php echo esc_html( KBK_AI_Book::graph_status() ); ?></bdi></p></header>
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
		<header class="ab-page-hero"><p class="ab-kicker">پرسش با استناد</p><h1>از کتاب بپرسید</h1><p class="ab-lead">پاسخ‌ها فقط از متن canonical همین کتاب ساخته می‌شوند و به بخش‌های کتاب ارجاع می‌دهند.</p></header>
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
			<div class="ab-card"><span class="ab-card-meta"><?php echo esc_html( $ask_item['part_id'] . ' · ' . $ask_item['chapter_id'] ); ?> · <?php echo esc_html( $origin_map[ $ask_item['origin'] ]['label'] ); ?></span><h3><?php echo esc_html( $ask_item['section_fa'] ); ?></h3><p><?php echo $render_segments( $ask_item['excerpt_segments'] ); ?><?php echo $ask_item['reader_resolvable'] ? '' : ' <bdi>(ارجاع در دسترس نیست)</bdi>'; ?></p><?php if ( $ask_item['reader_resolvable'] ) : ?><a class="ab-card-link" href="<?php echo esc_url( $read_link( $ask_item['part_id'], $ask_item['chapter_id'], $ask_item['structural_id'] ) ); ?>">مطالعهٔ بخش مستند</a><?php endif; ?><span class="ab-card-meta"><bdi><a href="<?php echo esc_url( $ask_item['citation_url'] ); ?>"><?php echo esc_html( $ask_item['content_id'] ); ?></a></bdi></span></div>
			<?php endforeach; ?>
			</div>
			<?php else : ?>
			<div class="ab-fallback"><p>برای این پرسش گذرواژهٔ مستند مرتبطی یافت نشد.</p></div>
			<?php endif; ?>
		</section>
		<?php elseif ( 'PROVIDER_REQUIRED' === $ask_status || 'READY' === $ask_status ) : ?>
		<section class="ab-section"><div class="ab-fallback"><p>برای شروع، پرسش خود را بنویسید؛ پاسخ‌ها همیشه به بخش‌های کتاب استناد می‌دهند.</p></div></section>
		<?php endif; ?>
	<?php elseif ( $selection['part_not_found'] ) : ?>
		<header class="ab-page-hero"><p class="ab-kicker">یافت نشد</p><h1>این بخش از کتاب موجود نیست</h1><p class="ab-lead">شناسهٔ درخواستی با هیچ‌یک از هفت بخش canonical کتاب مطابقت ندارد.</p><div class="ab-actions"><a class="ab-button ab-button-primary" href="<?php echo esc_url( home_url( '/ai-book/' ) ); ?>">بازگشت به فهرست بخش‌ها</a></div></header>
	<?php elseif ( $part && $chapter ) :
		$related_map = array();
		$graph_engine = KBK_AI_Book::graph();
		if ( null !== $graph_engine ) {
			$related_map = $graph_engine->related_map( array_column( $chapter['sections'], 'content_id' ) );
		}
		?>
		<nav class="ab-breadcrumb" aria-label="مسیر"><a href="<?php echo esc_url( home_url( '/ai-book/' ) ); ?>">کتاب</a><a href="<?php echo esc_url( home_url( '/ai-book/read/?' . KBK_AI_Book::PART_QUERY_VAR . '=' . rawurlencode( $part['part_id'] ) ) ); ?>"><?php echo esc_html( $part['title_fa'] ); ?></a><span aria-current="page"><?php echo esc_html( $chapter['title_fa'] ); ?></span></nav>
		<div class="ab-reader"><aside class="ab-toc" aria-label="فهرست فصل"><p class="ab-kicker"><?php echo esc_html( $part['part_id'] . ' · ' . $chapter['chapter_id'] ); ?></p><h2><?php echo esc_html( $chapter['title_fa'] ); ?></h2><?php foreach ( $chapter['sections'] as $index => $section ) : ?><a<?php echo 0 === $index ? ' class="is-current"' : ''; ?> href="#<?php echo esc_attr( $section['structural_id'] ); ?>"><?php echo esc_html( $section['title_fa'] ); ?></a><?php endforeach; ?></aside>
		<article class="ab-prose"><?php if ( $selection['chapter_not_found'] ) : ?><p class="ab-lead">فصل درخواستی در این بخش یافت نشد؛ نخستین فصل بخش <bdi><?php echo esc_html( $part['title_fa'] ); ?></bdi> نمایش داده می‌شود.</p><?php endif; ?>
		<header class="ab-page-hero"><p class="ab-kicker"><?php echo esc_html( $part['part_id'] . ' · ' . $chapter['chapter_id'] ); ?></p><h1><?php echo esc_html( $chapter['title_fa'] ); ?></h1><p class="ab-lead">متن canonical فارسی با منشأ، شناسه و پیوند استناد پایدار.</p></header>
		<?php foreach ( $chapter['sections'] as $section ) : $origin = $origin_map[ $section['origin'] ]; $related = $related_map[ $section['content_id'] ] ?? null; ?>
		<section id="<?php echo esc_attr( $section['structural_id'] ); ?>"><h2><?php echo esc_html( $section['title_fa'] ); ?></h2><div class="ab-layer <?php echo esc_attr( $origin['class'] ); ?>"><span><?php echo esc_html( $origin['label'] ); ?></span><?php echo wp_kses_post( wpautop( esc_html( $section['fa_text'] ) ) ); ?></div><p class="ab-card-meta"><bdi><?php echo esc_html( $section['content_id'] ); ?></bdi></p><a href="<?php echo esc_url( $section['canonical_url'] ); ?>">پیوند canonical این بخش</a>
		<?php if ( $related ) : ?>
		<div class="ab-related-inline"><div><span>مفاهیم مرتبط</span><p><?php $related_concept_links = array(); foreach ( $related['concepts'] as $related_concept ) : $related_concept_links[] = '<a href="' . esc_url( home_url( '/ai-book/concepts/?' . KBK_AI_Book::ENTITY_QUERY_VAR . '=' . rawurlencode( $related_concept['entity_id'] ) ) ) . '">' . esc_html( '' !== $related_concept['label_fa'] ? $related_concept['label_fa'] : $related_concept['label_en'] ) . '</a>'; endforeach; echo implode( '، ', $related_concept_links ); ?></p></div>
		<?php if ( $related['sections'] ) : ?><div><span>بخش‌های مرتبط</span><p><?php $related_section_links = array(); foreach ( $related['sections'] as $related_section ) : $related_section_links[] = '<a href="' . esc_url( $read_link( $related_section['part_id'], $related_section['chapter_id'], $related_section['structural_id'] ) ) . '">' . esc_html( $related_section['title_fa'] ) . '</a>'; endforeach; echo implode( '، ', $related_section_links ); ?></p></div><?php endif; ?>
		</div>
		<?php endif; ?>
		</section>
		<?php endforeach; ?>
		<nav class="ab-prev-next" aria-label="فصل قبل و بعد">
		<?php if ( $adjacent['prev'] ) : ?><a href="<?php echo esc_url( home_url( '/ai-book/read/?' . KBK_AI_Book::PART_QUERY_VAR . '=' . rawurlencode( $part['part_id'] ) . '&' . KBK_AI_Book::CHAPTER_QUERY_VAR . '=' . rawurlencode( $adjacent['prev']['chapter_id'] ) ) ); ?>">« <?php echo esc_html( $adjacent['prev']['title_fa'] ); ?></a><?php else : ?><span></span><?php endif; ?>
		<?php if ( $adjacent['next'] ) : ?><a href="<?php echo esc_url( home_url( '/ai-book/read/?' . KBK_AI_Book::PART_QUERY_VAR . '=' . rawurlencode( $part['part_id'] ) . '&' . KBK_AI_Book::CHAPTER_QUERY_VAR . '=' . rawurlencode( $adjacent['next']['chapter_id'] ) ) ); ?>"><?php echo esc_html( $adjacent['next']['title_fa'] ); ?> »</a><?php endif; ?>
		</nav>
		</article></div>
	<?php endif; ?>
</main>
<footer class="ab-footer"><div><b><?php echo esc_html( $summary['title_fa'] ?? 'کتاب مرجع هوش مصنوعی' ); ?></b><p>ترجمه، تدوین و بازآرایی فارسی: محمدعلی کهن‌دژ</p></div><p class="ab-disclaimer">این اثر ترجمهٔ رسمی NIST نیست و NIST آن را تأیید نکرده است.</p></footer>
<?php wp_footer(); ?>
</body>
</html>
