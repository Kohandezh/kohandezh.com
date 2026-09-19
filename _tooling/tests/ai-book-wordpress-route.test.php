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
function wp_unslash( $value ) { return $value; }
function plugins_url( $path, $file ) { return 'https://example.test/plugin/' . $path; }
function wp_enqueue_style( $handle, $src, $deps, $version ) { $GLOBALS['kbk_test_assets'][] = array( 'style', $handle, $src, $version ); }
function wp_enqueue_script( $handle, $src, $deps, $version, $footer ) { $GLOBALS['kbk_test_assets'][] = array( 'script', $handle, $src, $version, $footer ); }

require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-artifacts.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-repository.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-search.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book.php';

KBK_AI_Book::hooks();
if ( 5 !== count( $GLOBALS['kbk_test_hooks'] ) ) {
	fwrite( STDERR, "FAIL hook registration\n" ); exit( 1 );
}
KBK_AI_Book::rewrite_rules();
if ( 4 !== count( $GLOBALS['kbk_test_rewrites'] ) || '^ai-book/?$' !== $GLOBALS['kbk_test_rewrites'][0]['regex'] || '^ai-book/read/?$' !== $GLOBALS['kbk_test_rewrites'][1]['regex'] || '^ai-book/search/?$' !== $GLOBALS['kbk_test_rewrites'][2]['regex'] || '^ai-book/glossary/?$' !== $GLOBALS['kbk_test_rewrites'][3]['regex'] ) {
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

foreach ( array( 'search', 'glossary' ) as $view ) {
	$GLOBALS['kbk_test_query']['kbk_ai_book'] = $view;
	if ( $view !== KBK_AI_Book::current_view() || ! KBK_AI_Book::is_request() ) {
		fwrite( STDERR, "FAIL {$view} route detection\n" ); exit( 1 );
	}
}
$GLOBALS['kbk_test_query']['kbk_ai_book'] = 'search';
if ( 'CONFIG_REQUIRED' !== KBK_AI_Book::search_status() ) {
	fwrite( STDERR, "FAIL search without root must be CONFIG_REQUIRED\n" ); exit( 1 );
}
$search_state = KBK_AI_Book::current_search();
if ( 'CONFIG_REQUIRED' !== $search_state['status'] || array() !== $search_state['results'] ) {
	fwrite( STDERR, "FAIL search degrades to a safe empty state without configuration\n" ); exit( 1 );
}

$GLOBALS['kbk_test_query'][ KBK_AI_Book::SEARCH_QUERY_VAR ] = "  سوگیری\x00\x07  " ;
$query = KBK_AI_Book::requested_search_query();
if ( null === $query || false !== strpos( $query, "\x00" ) || false !== strpos( $query, "\x07" ) || 'سوگیری' !== trim( str_replace( "\n", ' ', $query ) ) ) {
	fwrite( STDERR, "FAIL search query hygiene must strip control characters and trim\n" ); exit( 1 );
}
$GLOBALS['kbk_test_query'][ KBK_AI_Book::SEARCH_QUERY_VAR ] = str_repeat( 'ریسک', 100 );
if ( null === KBK_AI_Book::requested_search_query() || strlen( KBK_AI_Book::requested_search_query() ) > 480 ) {
	fwrite( STDERR, "FAIL oversized search query must be capped, not rejected into an error\n" ); exit( 1 );
}
$GLOBALS['kbk_test_query'][ KBK_AI_Book::SEARCH_QUERY_VAR ] = array( 'bad' );
if ( null !== KBK_AI_Book::requested_search_query() ) {
	fwrite( STDERR, "FAIL array-shaped query var must be rejected\n" ); exit( 1 );
}
$GLOBALS['kbk_test_query'][ KBK_AI_Book::CONCEPT_QUERY_VAR ] = 'good_CONCEPT_2';
if ( 'good_CONCEPT_2' !== KBK_AI_Book::requested_concept() ) {
	fwrite( STDERR, "FAIL concept allowlist must accept valid identifiers\n" ); exit( 1 );
}
$GLOBALS['kbk_test_query'][ KBK_AI_Book::CONCEPT_QUERY_VAR ] = 'bad concept; DROP';
if ( null !== KBK_AI_Book::requested_concept() ) {
	fwrite( STDERR, "FAIL concept allowlist must reject invalid identifiers\n" ); exit( 1 );
}
$GLOBALS['kbk_test_query'] = array();

echo "PASS ai-book-wordpress-route: hooks, rewrites, allowlist, noindex, config state, assets, template and search/glossary request hygiene\n";
