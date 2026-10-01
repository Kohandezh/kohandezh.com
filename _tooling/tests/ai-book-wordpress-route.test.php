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
function sanitize_text_field( $value ) { return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : ''; }
function sanitize_textarea_field( $value ) { return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : ''; }
function status_header( $code ) { $GLOBALS['kbk_test_status'] = $code; }
function nocache_headers() {}
function wp_create_nonce( $action ) { return 'test-nonce-' . $action; }
function wp_verify_nonce( $nonce, $action ) { return 'test-nonce-' . $action === $nonce ? 1 : false; }
function wp_nonce_field( $action, $name ) {}
function plugins_url( $path, $file ) { return 'https://example.test/plugin/' . $path; }
function get_template_directory_uri() { return 'https://example.test/theme'; }
function trailingslashit( $value ) { return rtrim( $value, '/\\' ) . '/'; }
function wp_enqueue_style( $handle, $src, $deps, $version ) { $GLOBALS['kbk_test_assets'][] = array( 'style', $handle, $src, $version ); }
function wp_enqueue_script( $handle, $src, $deps, $version, $footer ) { $GLOBALS['kbk_test_assets'][] = array( 'script', $handle, $src, $version, $footer ); }

require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-artifacts.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-repository.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-search.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-catalog.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-graph.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-ask.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-pdf.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-request.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book.php';
function is_post_type_archive( $types = array() ) { return false; }
function is_tax( $taxonomies = array() ) { return false; }
function is_single() { return false; }
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-routes.php';

KBK_AI_Book::hooks();
if ( 8 !== count( $GLOBALS['kbk_test_hooks'] ) ) {
	fwrite( STDERR, "FAIL hook registration\n" ); exit( 1 );
}
KBK_AI_Book::rewrite_rules();
if ( 40 !== count( $GLOBALS['kbk_test_rewrites'] ) || '^books/?$' !== $GLOBALS['kbk_test_rewrites'][0]['regex'] || 'index.php?kbk_ai_book=library' !== $GLOBALS['kbk_test_rewrites'][0]['query'] || '^fa/books/?$' !== $GLOBALS['kbk_test_rewrites'][1]['regex'] || '^ai-book/?$' !== $GLOBALS['kbk_test_rewrites'][2]['regex'] || '^ai-book/read/?$' !== $GLOBALS['kbk_test_rewrites'][3]['regex'] || '^ai-book/search/?$' !== $GLOBALS['kbk_test_rewrites'][4]['regex'] || '^ai-book/glossary/?$' !== $GLOBALS['kbk_test_rewrites'][5]['regex'] || '^ai-book/sources/?$' !== $GLOBALS['kbk_test_rewrites'][6]['regex'] || '^ai-book/templates/?$' !== $GLOBALS['kbk_test_rewrites'][7]['regex'] || '^ai-book/concepts/?$' !== $GLOBALS['kbk_test_rewrites'][8]['regex'] || '^ai-book/graph/?$' !== $GLOBALS['kbk_test_rewrites'][9]['regex'] || '^ai-book/ask/?$' !== $GLOBALS['kbk_test_rewrites'][10]['regex'] || '^ai-book/pdf/?$' !== $GLOBALS['kbk_test_rewrites'][11]['regex'] || '^ai-book/request-pdf/?$' !== $GLOBALS['kbk_test_rewrites'][12]['regex'] || '^ai-book/pdf/file/?$' !== $GLOBALS['kbk_test_rewrites'][13]['regex'] || '^books/ai-governance/?$' !== $GLOBALS['kbk_test_rewrites'][14]['regex'] || '^books/ai-governance/news/?$' !== $GLOBALS['kbk_test_rewrites'][15]['regex'] || '^books/ai-governance/read/?$' !== $GLOBALS['kbk_test_rewrites'][16]['regex'] || '^books/ai-governance/pdf/file/?$' !== $GLOBALS['kbk_test_rewrites'][26]['regex'] || '^fa/books/ai-governance/?$' !== $GLOBALS['kbk_test_rewrites'][27]['regex'] || '^fa/books/ai-governance/news/?$' !== $GLOBALS['kbk_test_rewrites'][28]['regex'] || '^fa/books/ai-governance/read/?$' !== $GLOBALS['kbk_test_rewrites'][29]['regex'] || '^fa/books/ai-governance/pdf/file/?$' !== $GLOBALS['kbk_test_rewrites'][39]['regex'] ) {
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
if ( 4 !== count( $GLOBALS['kbk_test_assets'] ) ) {
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

foreach ( array( 'governance', 'governance-news', 'search', 'glossary', 'sources', 'templates', 'concepts', 'graph', 'ask', 'pdf', 'request-pdf' ) as $view ) {
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
$GLOBALS['kbk_test_query']['kbk_ai_book'] = 'sources';
if ( 'CONFIG_REQUIRED' !== KBK_AI_Book::catalog_status() ) {
	fwrite( STDERR, "FAIL catalog without root must be CONFIG_REQUIRED\n" ); exit( 1 );
}
if ( null !== KBK_AI_Book::catalog() ) {
	fwrite( STDERR, "FAIL catalog without root must fail closed\n" ); exit( 1 );
}
$GLOBALS['kbk_test_query']['kbk_ai_book'] = 'graph';
if ( 'CONFIG_REQUIRED' !== KBK_AI_Book::graph_status() ) {
	fwrite( STDERR, "FAIL graph without root must be CONFIG_REQUIRED\n" ); exit( 1 );
}
if ( null !== KBK_AI_Book::graph() ) {
	fwrite( STDERR, "FAIL graph without root must fail closed\n" ); exit( 1 );
}
$GLOBALS['kbk_test_query']['kbk_ai_book'] = 'ask';
if ( 'CONFIG_REQUIRED' !== KBK_AI_Book::ask_status() ) {
	fwrite( STDERR, "FAIL ask without root must be CONFIG_REQUIRED\n" ); exit( 1 );
}
$ask_state = KBK_AI_Book::current_ask();
if ( 'CONFIG_REQUIRED' !== $ask_state['status'] || array() !== $ask_state['retrieval'] || null !== $ask_state['answer'] ) {
	fwrite( STDERR, "FAIL ask without root must degrade to a safe empty state\n" ); exit( 1 );
}
$GLOBALS['kbk_test_query']['kbk_ai_book'] = 'pdf';
if ( 'CONFIG_REQUIRED' !== KBK_AI_Book::pdf_status() ) {
	fwrite( STDERR, "FAIL pdf without root must be CONFIG_REQUIRED\n" ); exit( 1 );
}
if ( null !== KBK_AI_Book::pdf() ) {
	fwrite( STDERR, "FAIL pdf without root must fail closed\n" ); exit( 1 );
}
$GLOBALS['kbk_test_query']['kbk_ai_book'] = 'request-pdf';
if ( 'CONFIG_REQUIRED' !== KBK_AI_Book::request_status() ) {
	fwrite( STDERR, "FAIL request without provider must be CONFIG_REQUIRED\n" ); exit( 1 );
}
$request_state = KBK_AI_Book::current_request();
if ( 'CONFIG_REQUIRED' !== $request_state['status'] || false !== $request_state['provider'] || array() !== $request_state['errors'] || null !== $request_state['reference'] ) {
	fwrite( STDERR, "FAIL request without provider must degrade to a safe empty state\n" ); exit( 1 );
}
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST                     = array( 'kbk_nonce' => 'forged' );
$forged = KBK_AI_Book::current_request();
if ( 'INPUT_INVALID' !== $forged['status'] || array( array( 'field' => 'session', 'code' => 'EXPIRED' ) ) !== $forged['errors'] ) {
	fwrite( STDERR, "FAIL forged nonce must be rejected before any provider call\n" ); exit( 1 );
}
$_POST = array(
	KBK_AI_Book_Request::NONCE_FIELD => wp_create_nonce( KBK_AI_Book_Request::NONCE_ACTION ),
	'name'                           => 'سارا',
	'email'                          => 'sara@example.com',
	'use'                            => 'personal',
	'reason'                         => '',
	'consent'                        => '1',
);
$valid_nonce = KBK_AI_Book::current_request();
if ( 'CONFIG_REQUIRED' !== $valid_nonce['status'] || array() !== $valid_nonce['errors'] ) {
	fwrite( STDERR, "FAIL valid nonce without provider must degrade honestly\n" ); exit( 1 );
}
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST                     = array();
$GLOBALS['kbk_test_query']['kbk_ai_book'] = 'pdf';
$GLOBALS['kbk_test_query'][ KBK_AI_Book::PDF_FILE_QUERY_VAR ] = '1';
if ( '' === KBK_AI_Book::current_view() ) {
	fwrite( STDERR, "FAIL pdf file stream must stay inside the ai-book view boundary\n" ); exit( 1 );
}
$GLOBALS['kbk_test_query'][ KBK_AI_Book::PDF_FILE_QUERY_VAR ] = 'yes';
if ( '' === KBK_AI_Book::current_view() ) {
	fwrite( STDERR, "FAIL pdf file view detection is view-level, not var-level\n" ); exit( 1 );
}
$GLOBALS['kbk_test_query']['kbk_ai_book'] = 'graph';
$GLOBALS['kbk_test_query']['kbk_entity']  = 'E:Risk:supply_chain';
if ( KBK_Routes::is_layer_b() || KBK_Routes::is_entity_request() ) {
	fwrite( STDERR, "FAIL ai-book views must never enter the Layer B gate (shared kbk_entity var)\n" ); exit( 1 );
}
$GLOBALS['kbk_test_query']['kbk_ai_book'] = '';
if ( ! KBK_Routes::is_entity_request() ) {
	fwrite( STDERR, "FAIL entity route must stay Layer B outside ai-book views\n" ); exit( 1 );
}
$GLOBALS['kbk_test_query'] = array();

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
$GLOBALS['kbk_test_query'][ KBK_AI_Book::TEMPLATE_QUERY_VAR ] = 'TPL-P01-2';
if ( 'TPL-P01-2' !== KBK_AI_Book::requested_template() ) {
	fwrite( STDERR, "FAIL template allowlist must accept valid template IDs\n" ); exit( 1 );
}
$GLOBALS['kbk_test_query'][ KBK_AI_Book::TEMPLATE_QUERY_VAR ] = 'TPL-P1-2';
if ( null !== KBK_AI_Book::requested_template() ) {
	fwrite( STDERR, "FAIL template allowlist must reject malformed template IDs\n" ); exit( 1 );
}
$GLOBALS['kbk_test_query'][ KBK_AI_Book::ENTITY_QUERY_VAR ] = 'E:Risk:supply_chain';
if ( 'E:Risk:supply_chain' !== KBK_AI_Book::requested_entity() ) {
	fwrite( STDERR, "FAIL entity allowlist must accept valid entity IDs\n" ); exit( 1 );
}
$GLOBALS['kbk_test_query'][ KBK_AI_Book::ENTITY_QUERY_VAR ] = "E:Risk:x'; DROP";
if ( null !== KBK_AI_Book::requested_entity() ) {
	fwrite( STDERR, "FAIL entity allowlist must reject hostile entity IDs\n" ); exit( 1 );
}
$GLOBALS['kbk_test_query'][ KBK_AI_Book::TYPE_QUERY_VAR ] = 'EvaluationMethod';
if ( 'EvaluationMethod' !== KBK_AI_Book::requested_entity_type() ) {
	fwrite( STDERR, "FAIL type allowlist must accept valid type names\n" ); exit( 1 );
}
$GLOBALS['kbk_test_query'][ KBK_AI_Book::TYPE_QUERY_VAR ] = 'Bad Type!';
if ( null !== KBK_AI_Book::requested_entity_type() ) {
	fwrite( STDERR, "FAIL type allowlist must reject hostile type names\n" ); exit( 1 );
}
$GLOBALS['kbk_test_query'][ KBK_AI_Book::PAGE_QUERY_VAR ] = '3';
if ( 3 !== KBK_AI_Book::requested_page() ) {
	fwrite( STDERR, "FAIL page allowlist must accept valid page numbers\n" ); exit( 1 );
}
$GLOBALS['kbk_test_query'][ KBK_AI_Book::PAGE_QUERY_VAR ] = '9x';
if ( 1 !== KBK_AI_Book::requested_page() ) {
	fwrite( STDERR, "FAIL page allowlist must fall back to page 1\n" ); exit( 1 );
}
$GLOBALS['kbk_test_query'] = array();

$reader_html = KBK_AI_Book::render_reader_markdown( "## پیشگفتار\n\n- مورد نخست\n- مورد دوم\n\n| عنوان | مقدار |\n| --- | --- |\n| ایمن | <script>alert(1)</script> |" );
if ( false === strpos( $reader_html, '<h3>پیشگفتار</h3>' ) || false === strpos( $reader_html, '<ul><li dir="auto">مورد نخست</li><li dir="auto">مورد دوم</li></ul>' ) || false === strpos( $reader_html, '<thead>' ) || false !== strpos( $reader_html, '<script>' ) || false === strpos( $reader_html, '&lt;script&gt;' ) ) {
	fwrite( STDERR, "FAIL reader Markdown must become safe semantic HTML\n" ); exit( 1 );
}
$governance_fixture = array(
	'part_id'    => 'P02',
	'chapter_id' => 'C09',
	'section_id' => 'S02',
	'title_fa'   => 'AI Governance 1.1 — حاکمیت هوش مصنوعی ۱.۱',
	'fa_text'    => "# حاکمیت\n\nبیانیهٔ موضوعی.\n\n## GOVERN 1.1\n\n### درباره",
);
if ( 'AI Governance 1.1 — الزامات حقوقی و مقرراتی هوش مصنوعی' !== KBK_AI_Book::reader_section_title( $governance_fixture ) ) {
	fwrite( STDERR, "FAIL Governance reader title must describe the section subject\n" ); exit( 1 );
}
$governance_body = KBK_AI_Book::reader_section_body( $governance_fixture );
if ( false !== strpos( $governance_body, '# حاکمیت' ) || false !== strpos( $governance_body, 'GOVERN 1.1' ) || false === strpos( $governance_body, 'بیانیهٔ موضوعی.' ) || false === strpos( $governance_body, '### درباره' ) ) {
	fwrite( STDERR, "FAIL Governance reading body must remove only duplicated code headings\n" ); exit( 1 );
}
$template_source = file_get_contents( __DIR__ . '/../wp-theme/kohandezh-knowledge/templates/ai-book.php' );
$reader_script   = file_get_contents( __DIR__ . '/../wp-theme/kohandezh-knowledge/assets/ai-book.js' );
if ( false === $template_source || false === $reader_script || false === strpos( $template_source, 'ab-theme-toggle' ) || false === strpos( $template_source, 'ab-view-<?php echo esc_attr( $view ); ?>' ) || false === strpos( $reader_script, 'darkMode' ) || false === strpos( $reader_script, 'prefers-color-scheme: light' ) ) {
	fwrite( STDERR, "FAIL Reader must expose the shared persistent light/dark mode contract\n" ); exit( 1 );
}

// /ai-book/ retired: its landing is the /books/ library, every other view lives
// under the book's own namespace, verification URLs are signed and stay served.
foreach ( array(
	'/ai-book'                         => '/books/',
	'/ai-book/'                        => '/books/',
	'/ai-book/read/?kbk_part=P02'      => '/fa/books/ai-governance/read/?kbk_part=P02',
	'/ai-book/search/?kbk_q=risk'      => '/fa/books/ai-governance/search/?kbk_q=risk',
	'/ai-book/pdf/file/'               => '/fa/books/ai-governance/pdf/file/',
	'/ai-book/verify/'                 => null,
	'/ai-book/verify/abc/'             => null,
	'/books/'                          => null,
	'/fa/books/ai-governance/read/'    => null,
	'/ai-bookish/'                     => null,
) as $from => $want ) {
	if ( $want !== KBK_AI_Book::retired_ai_book_path( $from ) ) {
		fwrite( STDERR, "FAIL retired /ai-book/ path $from\n" ); exit( 1 );
	}
}
if ( 'library' !== ( function () { $GLOBALS['kbk_test_query']['kbk_ai_book'] = 'library'; return KBK_AI_Book::current_view(); } )() ) {
	fwrite( STDERR, "FAIL library view allowlist\n" ); exit( 1 );
}

echo "PASS ai-book-wordpress-route: hooks, rewrites (40, incl. /books/ library), retired /ai-book/ redirects, locale-first Persian AI Governance namespace, full-bleed Reader theme contract, meaningful Governance titles, safe reader markup, allowlist, noindex, config state, assets, template and search/glossary/catalog/graph/ask/pdf/request request hygiene\n";
