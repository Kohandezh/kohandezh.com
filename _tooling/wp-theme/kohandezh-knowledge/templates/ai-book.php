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
		<?php // Library shelf -- mirrors shelf() in _tooling/ai-book/build_visual_shell.py; change both together. ?>
		<?php $kbk_img = plugins_url( 'assets/books/', KBK_PLUGIN_FILE ); ?>
		<section class="ab-section ab-shelf" aria-labelledby="ab-shelf-title"><div class="ab-section-head"><div><p class="ab-kicker">کتابخانه</p><h2 id="ab-shelf-title">کتاب‌های کهن‌دژ</h2></div></div><div class="ab-shelf-grid">
			<article class="ab-book"><img class="ab-book-cover" src="<?php echo esc_url( $kbk_img . 'ai-governance-cover-w320.webp' ); ?>" srcset="<?php echo esc_url( $kbk_img . 'ai-governance-cover-w320.webp' ); ?> 320w, <?php echo esc_url( $kbk_img . 'ai-governance-cover-w640.webp' ); ?> 640w" sizes="(max-width: 420px) 110px, 200px" width="320" height="512" alt="جلد کتاب AI Governance — حاکمیت هوش مصنوعی، نوشتهٔ محمدعلی کهن‌دژ" loading="lazy" decoding="async"><div class="ab-book-body"><span class="ab-book-status ab-book-status-live">نسخهٔ آنلاین در دسترس</span><h3>حاکمیت هوش مصنوعی</h3><p class="ab-book-sub">راهنمای جامع حاکمیت، امنیت و مدیریت ریسک هوش مصنوعی</p><p class="ab-book-meta">محمدعلی کهن‌دژ</p><a class="ab-button ab-button-primary" href="<?php echo esc_url( home_url( '/ai-book/read/' ) ); ?>">مطالعه آنلاین</a></div></article>
			<article class="ab-book"><img class="ab-book-cover" src="<?php echo esc_url( $kbk_img . 'ics-cybersecurity-cover-w320.webp' ); ?>" srcset="<?php echo esc_url( $kbk_img . 'ics-cybersecurity-cover-w320.webp' ); ?> 320w, <?php echo esc_url( $kbk_img . 'ics-cybersecurity-cover-w640.webp' ); ?> 640w" sizes="(max-width: 420px) 110px, 200px" width="320" height="512" alt="جلد کتاب امنیت سایبری در سیستم‌های اتوماسیون صنعتی" loading="lazy" decoding="async"><div class="ab-book-body"><span class="ab-book-status ab-book-status-soon">انتشار دیجیتال به‌زودی</span><h3>امنیت سایبری در سیستم‌های اتوماسیون صنعتی</h3><p class="ab-book-sub">راهنمای جامع بر اساس استانداردهای IEC 62443</p><p class="ab-book-meta">محمدعلی کهن‌دژ، روزبه بابازاده، نیلوفر کریمی آذر · تابستان ۱۴۰۴</p><p class="ab-book-note">نسخهٔ دیجیتال این کتاب به‌زودی در همین بخش منتشر می‌شود.</p></div></article>
		</div></section>
		<section class="ab-section"><div class="ab-section-head"><div><p class="ab-kicker">ساختار کتاب</p><h2>هفت بخش canonical</h2></div></div><div class="ab-card-grid">
		<?php foreach ( $parts as $part ) : ?>
			<a class="ab-card" href="<?php echo esc_url( home_url( '/ai-book/read/?part=' . rawurlencode( $part['part_id'] ) ) ); ?>"><span class="ab-card-meta"><?php echo esc_html( $part['part_id'] ); ?></span><h3><?php echo esc_html( $part['title_fa'] ); ?></h3><p><?php echo esc_html( count( $part['chapters'] ) ); ?> فصل</p><span class="ab-card-link">مطالعه</span></a>
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
