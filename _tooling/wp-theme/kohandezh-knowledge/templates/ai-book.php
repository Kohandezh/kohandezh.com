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
$page_title = 'home' === $view ? 'کتاب مرجع هوش مصنوعی کهن‌دژ' : ( $chapter['title_fa'] ?? 'مطالعه آنلاین کتاب هوش مصنوعی' );
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
	<?php elseif ( $selection['part_not_found'] ) : ?>
		<header class="ab-page-hero"><p class="ab-kicker">یافت نشد</p><h1>این بخش از کتاب موجود نیست</h1><p class="ab-lead">شناسهٔ درخواستی با هیچ‌یک از هفت بخش canonical کتاب مطابقت ندارد.</p><div class="ab-actions"><a class="ab-button ab-button-primary" href="<?php echo esc_url( home_url( '/ai-book/' ) ); ?>">بازگشت به فهرست بخش‌ها</a></div></header>
	<?php elseif ( $part && $chapter ) : ?>
		<nav class="ab-breadcrumb" aria-label="مسیر"><a href="<?php echo esc_url( home_url( '/ai-book/' ) ); ?>">کتاب</a><a href="<?php echo esc_url( home_url( '/ai-book/read/?' . KBK_AI_Book::PART_QUERY_VAR . '=' . rawurlencode( $part['part_id'] ) ) ); ?>"><?php echo esc_html( $part['title_fa'] ); ?></a><span aria-current="page"><?php echo esc_html( $chapter['title_fa'] ); ?></span></nav>
		<div class="ab-reader"><aside class="ab-toc" aria-label="فهرست فصل"><p class="ab-kicker"><?php echo esc_html( $part['part_id'] . ' · ' . $chapter['chapter_id'] ); ?></p><h2><?php echo esc_html( $chapter['title_fa'] ); ?></h2><?php foreach ( $chapter['sections'] as $index => $section ) : ?><a<?php echo 0 === $index ? ' class="is-current"' : ''; ?> href="#<?php echo esc_attr( $section['structural_id'] ); ?>"><?php echo esc_html( $section['title_fa'] ); ?></a><?php endforeach; ?></aside>
		<article class="ab-prose"><?php if ( $selection['chapter_not_found'] ) : ?><p class="ab-lead">فصل درخواستی در این بخش یافت نشد؛ نخستین فصل بخش <bdi><?php echo esc_html( $part['title_fa'] ); ?></bdi> نمایش داده می‌شود.</p><?php endif; ?>
		<header class="ab-page-hero"><p class="ab-kicker"><?php echo esc_html( $part['part_id'] . ' · ' . $chapter['chapter_id'] ); ?></p><h1><?php echo esc_html( $chapter['title_fa'] ); ?></h1><p class="ab-lead">متن canonical فارسی با منشأ، شناسه و پیوند استناد پایدار.</p></header>
		<?php foreach ( $chapter['sections'] as $section ) : $origin = $origin_map[ $section['origin'] ]; ?>
		<section id="<?php echo esc_attr( $section['structural_id'] ); ?>"><h2><?php echo esc_html( $section['title_fa'] ); ?></h2><div class="ab-layer <?php echo esc_attr( $origin['class'] ); ?>"><span><?php echo esc_html( $origin['label'] ); ?></span><?php echo wp_kses_post( wpautop( esc_html( $section['fa_text'] ) ) ); ?></div><p class="ab-card-meta"><bdi><?php echo esc_html( $section['content_id'] ); ?></bdi></p><a href="<?php echo esc_url( $section['canonical_url'] ); ?>">پیوند canonical این بخش</a></section>
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
