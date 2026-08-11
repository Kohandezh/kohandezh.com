<?php
/**
 * Layer B renderer — full HTML document for all Kohandezh Knowledge routes.
 * Loaded ONLY via KBK_Routes::template_include() when is_layer_b() is true.
 * Layer A is never rendered through this file. Standalone (doctype/head/body)
 * like the theme's single.php; uses wp_head/wp_footer so KBK_Schema JSON-LD and
 * any future conditional assets fire. Zero external assets — tiny scoped inline
 * CSS only, for full isolation.
 *
 * @package Kohandezh_Knowledge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$body_class = 'kbk-layer-b';
$title      = KBK_Routes::current_hub_label() ?: ( get_the_archive_title() ?? 'Knowledge' );
$entity     = KBK_Routes::is_entity_request() ? KBK_Routes::current_entity_post() : null;
if ( $entity ) {
	$title = get_the_title( $entity );
} elseif ( is_single() ) {
	$title = get_the_title();
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="canonical" href="<?php echo esc_url( KBK_Schema::current_url_public() ); ?>">
	<title><?php echo esc_html( $title ); ?> — <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
	<?php wp_head(); ?>
	<style>
		.kbk-layer-b{--bg:#0b1113;--fg:#e8f1ea;--muted:#8a9a90;--accent:#00de51;--card:#10181a;--border:#1d2a25;max-width:960px;margin:0 auto;padding:32px 20px 80px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Inter,sans-serif;color:var(--fg);background:var(--bg);line-height:1.6}
		.kbk-layer-b a{color:var(--accent);text-decoration:none}
		.kbk-layer-b a:hover{text-decoration:underline}
		.kbk-layer-b header.kbk-mast{display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);padding-bottom:16px;margin-bottom:28px}
		.kbk-layer-b .kbk-brand{font-weight:700;letter-spacing:.04em}
		.kbk-layer-b nav.kbk-crumbs{font-size:13px;color:var(--muted);margin-bottom:24px}
		.kbk-layer-b nav.kbk-crumbs a{color:var(--muted)}
		.kbk-layer-b h1{font-size:clamp(28px,4vw,44px);margin:0 0 8px;line-height:1.15}
		.kbk-layer-b .kbk-lead{font-size:18px;color:var(--muted);margin:0 0 24px}
		.kbk-layer-b .kbk-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px}
		.kbk-layer-b article.kbk-card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:18px}
		.kbk-layer-b article.kbk-card h3{margin:0 0 6px;font-size:17px}
		.kbk-layer-b article.kbk-card p{margin:0;color:var(--muted);font-size:14px}
		.kbk-layer-b .kbk-tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
		.kbk-layer-b .kbk-tag{font-size:11px;color:var(--muted);border:1px solid var(--border);padding:2px 8px;border-radius:999px}
		.kbk-layer-b .kbk-meta{font-size:13px;color:var(--muted);margin:8px 0}
		.kbk-layer-b .kbk-body{font-size:16px}
		.kbk-layer-b .kbk-empty{color:var(--muted);padding:40px;text-align:center;border:1px dashed var(--border);border-radius:12px}
		.kbk-layer-b footer.kbk-foot{margin-top:48px;padding-top:16px;border-top:1px solid var(--border);font-size:13px;color:var(--muted)}
	</style>
</head>
<body class="<?php echo esc_attr( $body_class ); ?>">
<?php wp_body_open(); ?>
<div class="kbk-layer-b">
	<header class="kbk-mast">
		<a class="kbk-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a>
		<nav aria-label="Knowledge hubs">
			<a href="<?php echo esc_url( home_url( '/enterprise-ai/' ) ); ?>">Enterprise AI</a> ·
			<a href="<?php echo esc_url( home_url( '/quantum/' ) ); ?>">Quantum</a> ·
			<a href="<?php echo esc_url( home_url( '/knowledge/' ) ); ?>">Knowledge</a> ·
			<a href="<?php echo esc_url( home_url( '/glossary/' ) ); ?>">Glossary</a> ·
			<a href="<?php echo esc_url( home_url( '/news/' ) ); ?>">News</a>
		</nav>
	</header>

	<nav class="kbk-crumbs" aria-label="Breadcrumb">
		<?php
		$crumbs = KBK_Routes::breadcrumbs();
		$parts  = array();
		foreach ( $crumbs as $c ) {
			$parts[] = '<a href="' . esc_url( $c['url'] ) . '">' . esc_html( $c['name'] ) . '</a>';
		}
		echo wp_kses_post( implode( ' / ', $parts ) );
		?>
	</nav>

	<?php
	if ( $entity ) {
		require __DIR__ . '/partial-entity.php';
	} elseif ( KBK_Routes::is_hub_request() ) {
		require __DIR__ . '/partial-hub.php';
	} else {
		require __DIR__ . '/partial-archive.php';
	}
	?>

	<footer class="kbk-foot">
		© <?php echo esc_html( date_i18n( 'Y' ) ); ?> Mohammad Ali Kohandezh · Knowledge Platform (Layer B)
	</footer>
</div>
<?php wp_footer(); ?>
</body>
</html>
