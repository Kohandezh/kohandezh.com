<?php
define( 'ABSPATH', __DIR__ );
define( 'KBK_FEATURE_AI_BOOK', true );
define( 'KBK_PLUGIN_FILE', __FILE__ );
define( 'KBK_VERSION', 'test' );

$GLOBALS['kbk_test_hooks']    = array();
$GLOBALS['kbk_test_rewrites'] = array();
$GLOBALS['kbk_test_query']    = array();
$GLOBALS['kbk_test_assets']   = array();

function add_filter( $name, $callback, $priority = 10 ) { $GLOBALS['kbk_test_hooks'][] = array( 'filter', $name, $priority ); }
function add_action( $name, $callback, $priority = 10 ) { $GLOBALS['kbk_test_hooks'][] = array( 'action', $name, $priority ); }
function add_rewrite_rule( $regex, $query, $position ) { $GLOBALS['kbk_test_rewrites'][] = compact( 'regex', 'query', 'position' ); }
function get_query_var( $name ) { return $GLOBALS['kbk_test_query'][ $name ] ?? ''; }
function plugins_url( $path, $file ) { return 'https://example.test/plugin/' . $path; }
function wp_enqueue_style( $handle, $src, $deps, $version ) { $GLOBALS['kbk_test_assets'][] = array( 'style', $handle, $src, $version ); }
function wp_unslash( $value ) { return $value; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
class KBK_Test_Redirect extends Exception {}
function wp_safe_redirect( $location, $status ) { throw new KBK_Test_Redirect( $status . ' ' . $location ); }
function wp_enqueue_script( $handle, $src, $deps, $version, $footer ) { $GLOBALS['kbk_test_assets'][] = array( 'script', $handle, $src, $version, $footer ); }

require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-artifacts.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-repository.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book.php';

KBK_AI_Book::hooks();
if ( 6 !== count( $GLOBALS['kbk_test_hooks'] ) ) {
	fwrite( STDERR, "FAIL hook registration\n" ); exit( 1 );
}
KBK_AI_Book::rewrite_rules();
if ( 2 !== count( $GLOBALS['kbk_test_rewrites'] ) || '^books/?$' !== $GLOBALS['kbk_test_rewrites'][0]['regex'] || '^books/read/?$' !== $GLOBALS['kbk_test_rewrites'][1]['regex'] ) {
	fwrite( STDERR, "FAIL rewrite rules\n" ); exit( 1 );
}

$GLOBALS['kbk_test_query']['kbk_ai_book'] = 'home';
if ( 'home' !== KBK_AI_Book::current_view() || ! KBK_AI_Book::is_request() ) {
	fwrite( STDERR, "FAIL home route detection\n" ); exit( 1 );
}
$robots = KBK_AI_Book::robots( array() );
if ( empty( $robots['noindex'] ) || empty( $robots['nofollow'] ) ) {
	fwrite( STDERR, "FAIL safe robots default\n" ); exit( 1 );
}
if ( 'CONFIG_REQUIRED' !== KBK_AI_Book::repository_status() ) {
	fwrite( STDERR, "FAIL missing root status\n" ); exit( 1 );
}
KBK_AI_Book::enqueue_assets();
if ( 2 !== count( $GLOBALS['kbk_test_assets'] ) ) {
	fwrite( STDERR, "FAIL conditional assets\n" ); exit( 1 );
}
$template = KBK_AI_Book::template_include( '/theme/index.php' );
if ( 'ai-book.php' !== basename( $template ) ) {
	fwrite( STDERR, "FAIL template routing\n" ); exit( 1 );
}

$GLOBALS['kbk_test_query']['kbk_ai_book'] = 'invalid';
if ( '' !== KBK_AI_Book::current_view() || KBK_AI_Book::is_request() ) {
	fwrite( STDERR, "FAIL invalid route allowlist\n" ); exit( 1 );
}

// /ai-book/ moved to /books/: every old link must land on the same view.
foreach ( array( '/ai-book/' => '301 https://example.test/books/', '/ai-book' => '301 https://example.test/books/', '/ai-book/read/?part=P02' => '301 https://example.test/books/read/?part=P02' ) as $from => $want ) {
	$_SERVER['REQUEST_URI'] = $from;
	$got = '';
	try { KBK_AI_Book::legacy_redirect(); } catch ( KBK_Test_Redirect $redirect ) { $got = $redirect->getMessage(); }
	if ( $want !== $got ) { fwrite( STDERR, "FAIL legacy redirect $from -> $got\n" ); exit( 1 ); }
}
$_SERVER['REQUEST_URI'] = '/books/';
try { KBK_AI_Book::legacy_redirect(); } catch ( KBK_Test_Redirect $redirect ) { fwrite( STDERR, "FAIL /books/ must not redirect\n" ); exit( 1 ); }

echo "PASS ai-book-wordpress-route: hooks, rewrites, allowlist, noindex, config state, assets, template and /ai-book/ -> /books/ redirect\n";
