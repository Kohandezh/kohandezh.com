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
function get_option( $name, $default = false ) { return array_key_exists( $name, $GLOBALS['kbk_test_options'] ?? array() ) ? $GLOBALS['kbk_test_options'][ $name ] : $default; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }

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
$hook_names = array_map( static function ( array $hook ): string { return $hook[1] . '@' . $hook[2]; }, $GLOBALS['kbk_test_hooks'] );
if ( 10 !== count( $GLOBALS['kbk_test_hooks'] ) || ! in_array( 'robots_txt@20', $hook_names, true ) || 2 !== count( array_keys( $hook_names, 'template_redirect@1', true ) ) ) {
	fwrite( STDERR, "FAIL hook registration (10 hooks incl. the book feeds at template_redirect@1 and robots_txt@20)\n" ); exit( 1 );
}
KBK_AI_Book::rewrite_rules();
$rules = $GLOBALS['kbk_test_rewrites'];
$book  = '([a-z0-9]+(?:-[a-z0-9]+)*)';
$views = '(read|search|glossary|sources|templates|concepts|graph|ask|pdf|request-pdf)';
$want_rules = array(
	0  => array( '^books/?$', 'index.php?kbk_ai_book=library' ),
	1  => array( '^fa/books/?$', 'index.php?kbk_ai_book=library' ),
	2  => array( '^ai-book/?$', 'index.php?kbk_ai_book=home' ),
	3  => array( '^ai-book/read/?$', 'index.php?kbk_ai_book=read' ),
	12 => array( '^ai-book/request-pdf/?$', 'index.php?kbk_ai_book=request-pdf' ),
	13 => array( '^ai-book/pdf/file/?$', 'index.php?kbk_ai_book=pdf&kbk_pdf_file=1' ),
	14 => array( '^books/' . $book . '/?$', 'index.php?kbk_ai_book=governance&kbk_book=$matches[1]' ),
	15 => array( '^books/' . $book . '/news/?$', 'index.php?kbk_ai_book=governance-news&kbk_book=$matches[1]' ),
	16 => array( '^books/' . $book . '/' . $views . '/?$', 'index.php?kbk_ai_book=$matches[2]&kbk_book=$matches[1]' ),
	17 => array( '^books/' . $book . '/pdf/file/?$', 'index.php?kbk_ai_book=pdf&kbk_pdf_file=1&kbk_book=$matches[1]' ),
	18 => array( '^fa/books/' . $book . '/?$', 'index.php?kbk_ai_book=governance&kbk_book=$matches[1]' ),
	20 => array( '^fa/books/' . $book . '/' . $views . '/?$', 'index.php?kbk_ai_book=$matches[2]&kbk_book=$matches[1]' ),
	22 => array( '^fa/books/' . $book . '/sitemap\.xml$', 'index.php?kbk_ai_book=sitemap&kbk_book=$matches[1]' ),
	23 => array( '^fa/books/' . $book . '/llms\.txt$', 'index.php?kbk_ai_book=llms&kbk_book=$matches[1]' ),
	24 => array( '^ai-book/verify/?$', 'index.php?kbk_ai_book=verify' ),
);
if ( 25 !== count( $rules ) ) {
	fwrite( STDERR, "FAIL rewrite rule count\n" ); exit( 1 );
}
foreach ( $want_rules as $index => list( $regex, $query ) ) {
	if ( $regex !== $rules[ $index ]['regex'] || $query !== $rules[ $index ]['query'] || 'top' !== $rules[ $index ]['position'] ) {
		fwrite( STDERR, "FAIL rewrite rule {$index}\n" ); exit( 1 );
	}
}
// WordPress matches a request path against the rules in order (WP::parse_request)
// and substitutes $matches[n]; emulate that for the generic book routes.
$route = static function ( string $path ) use ( $rules ): ?string {
	foreach ( $rules as $rule ) {
		if ( 1 === preg_match( '#^' . $rule['regex'] . '#', $path, $matches ) ) {
			return preg_replace_callback( '/\$matches\[(\d+)\]/', static function ( array $m ) use ( $matches ): string { return $matches[ (int) $m[1] ] ?? ''; }, $rule['query'] );
		}
	}
	return null;
};
foreach ( array(
	'books'                                  => 'index.php?kbk_ai_book=library',
	'fa/books/'                              => 'index.php?kbk_ai_book=library',
	'fa/books/ai-governance/'                => 'index.php?kbk_ai_book=governance&kbk_book=ai-governance',
	'fa/books/ai-governance/read/'           => 'index.php?kbk_ai_book=read&kbk_book=ai-governance',
	'fa/books/other-slug/read/'              => 'index.php?kbk_ai_book=read&kbk_book=other-slug',
	'fa/books/other-slug/request-pdf'        => 'index.php?kbk_ai_book=request-pdf&kbk_book=other-slug',
	'fa/books/other-slug/news/'              => 'index.php?kbk_ai_book=governance-news&kbk_book=other-slug',
	'fa/books/other-slug/pdf/'               => 'index.php?kbk_ai_book=pdf&kbk_book=other-slug',
	'fa/books/other-slug/pdf/file/'          => 'index.php?kbk_ai_book=pdf&kbk_pdf_file=1&kbk_book=other-slug',
	'fa/books/other-slug/sitemap.xml'        => 'index.php?kbk_ai_book=sitemap&kbk_book=other-slug',
	'fa/books/other-slug/llms.txt'           => 'index.php?kbk_ai_book=llms&kbk_book=other-slug',
	'books/other-slug/glossary/'             => 'index.php?kbk_ai_book=glossary&kbk_book=other-slug',
	'ai-book/verify/'                        => 'index.php?kbk_ai_book=verify',
	'fa/books/Other_Slug/read/'              => null,
	'fa/books/other-slug/unknown-view/'      => null,
	'fa/books/other-slug/sitemapXxml'        => null,
	'fa/books/other-slug/part-a/chapter-b/'  => null,
) as $path => $want ) {
	if ( $want !== $route( $path ) ) {
		fwrite( STDERR, "FAIL route for {$path}\n" ); exit( 1 );
	}
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
if ( false === strpos( $reader_html, '<h3>پیشگفتار</h3>' ) || false === strpos( $reader_html, '<ul><li dir="auto">مورد نخست</li><li dir="auto">مورد دوم</li></ul>' ) || false === strpos( $reader_html, '<thead>' ) || false !== strpos( $reader_html, '<script>' ) || false === strpos( $reader_html, '&lt;<span dir="ltr">script</span>&gt;' ) ) {
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

// The PDF viewer is an iframe of the same-origin stream: CSP object-src 'none'
// blocks <object>/<embed>; frame-src 'self' allows the frame. A visible
// open/download link stays for browsers that cannot show a PDF inline.
if ( false !== strpos( $template_source, '<object' ) || false !== strpos( $template_source, '<embed' ) || 1 !== preg_match( '#<iframe class="ab-pdf-embed" src="<\?php echo esc_url\( \$pdf_stream \); \?>" title="[^"]+"#u', $template_source ) || false === strpos( $template_source, 'class="ab-pdf-fallback"' ) || false === strpos( $template_source, ' download>' ) ) {
	fwrite( STDERR, "FAIL PDF viewer must be a titled iframe with a visible link fallback, never <object>/<embed>\n" ); exit( 1 );
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
if ( 'verify' !== ( function () { $GLOBALS['kbk_test_query']['kbk_ai_book'] = 'verify'; return KBK_AI_Book::current_view(); } )() ) {
	fwrite( STDERR, "FAIL verify view allowlist\n" ); exit( 1 );
}
// The verify field takes identifiers only: ASCII letters, digits, hyphens.
foreach ( array( 'KDJ-AI-2026E1-P01-C01-S01' => 'KDJ-AI-2026E1-P01-C01-S01', '  abc-123  ' => 'abc-123', '' => null, '<script>' => null, 'شناسه' => null, 'a b' => null ) as $input => $want ) {
	$GLOBALS['kbk_test_query']['kbk_verify'] = $input;
	if ( $want !== KBK_AI_Book::requested_verify_id() ) {
		fwrite( STDERR, "FAIL requested_verify_id for '$input'\n" ); exit( 1 );
	}
}
$GLOBALS['kbk_test_query']['kbk_verify'] = array( 'x' );
if ( null !== KBK_AI_Book::requested_verify_id() ) {
	fwrite( STDERR, "FAIL requested_verify_id must reject arrays\n" ); exit( 1 );
}
unset( $GLOBALS['kbk_test_query']['kbk_verify'] );

// --- More than one book: the same views under /fa/books/{slug}/ ---------------------
require_once __DIR__ . '/fixtures/ai-book/bundle.php';
$other_root = kbk_test_bundle( 'OT', array( 'title_fa' => 'کتاب دیگر', 'built_at' => '2026-10-02T09:00:00+0000' ) );
$fixture    = json_decode( (string) file_get_contents( $other_root . '/master/book.json' ), true );
$part_slug    = $fixture['parts'][0]['slug'];
$chapter_slug = $fixture['parts'][0]['chapters'][0]['slug'];
$GLOBALS['kbk_test_options']['kbk_books'] = array( array( 'slug' => 'other-slug', 'root' => $other_root, 'status' => 'published' ) );
KBK_AI_Book_Registry::reset();
$GLOBALS['kbk_test_query'] = array( 'kbk_ai_book' => 'read', 'kbk_book' => 'other-slug' );
if ( 'read' !== KBK_AI_Book::current_view() || ! KBK_AI_Book::is_request() || null === KBK_AI_Book::repository() || 'READY' !== KBK_AI_Book::repository_status() ) {
	fwrite( STDERR, "FAIL /fa/books/other-slug/read/ must be a Reader page of that book\n" ); exit( 1 );
}
if ( 'https://example.test/fa/books/other-slug/read/?kbk_part=P01&kbk_chapter=C01&kbk_page=2' !== KBK_AI_Book::reader_url( 'P01', 'C01', 2 ) || 'https://example.test/fa/books/other-slug/glossary/' !== KBK_AI_Book::preferred_internal_url( 'https://example.test/ai-book/glossary/' ) ) {
	fwrite( STDERR, "FAIL links inside another book must stay in its namespace\n" ); exit( 1 );
}
$selection = KBK_AI_Book::current_reader_selection();
if ( 'P01' !== ( $selection['part']['part_id'] ?? null ) || 'C01' !== ( $selection['chapter']['chapter_id'] ?? null ) ) {
	fwrite( STDERR, "FAIL another book reads from its own bundle\n" ); exit( 1 );
}
foreach ( array( 'nope', 'Other-Slug', '../x' ) as $unknown ) {
	$GLOBALS['kbk_test_query'] = array( 'kbk_ai_book' => 'read', 'kbk_book' => $unknown );
	if ( '' !== KBK_AI_Book::current_view() || KBK_AI_Book::is_request() || null !== KBK_AI_Book::repository() ) {
		fwrite( STDERR, "FAIL unknown book '{$unknown}' must not be a Reader page (404)\n" ); exit( 1 );
	}
}
foreach ( array(
	'/books/other-slug/'                      => '/fa/books/other-slug/',
	'/books/other-slug/read/?kbk_part=P01'    => '/fa/books/other-slug/read/?kbk_part=P01',
	'/books/other-slug/pdf/file/'             => '/fa/books/other-slug/pdf/file/',
	'/books/nope/read/'                       => null,
	'/books/'                                 => null,
	'/fa/books/other-slug/read/'              => null,
) as $from => $want ) {
	if ( $want !== KBK_AI_Book::localized_book_path( $from ) ) {
		fwrite( STDERR, "FAIL 301 of {$from}\n" ); exit( 1 );
	}
}
// The frozen citation address /fa/books/{slug}/{part}/{chapter}/ → the Reader.
foreach ( array(
	'/fa/books/other-slug/' . $part_slug . '/' . $chapter_slug . '/'  => '/fa/books/other-slug/read/?kbk_part=P01&kbk_chapter=C01',
	'/fa/books/other-slug/' . $part_slug . '/' . $chapter_slug       => '/fa/books/other-slug/read/?kbk_part=P01&kbk_chapter=C01',
	'/fa/books/other-slug/' . $part_slug . '/no-such-chapter/'         => '',
	'/fa/books/other-slug/no-such-part/' . $chapter_slug . '/'         => '',
	'/fa/books/other-slug/pdf/file/'                                   => null,
	'/fa/books/other-slug/read/anything/'                              => null,
	'/fa/books/nope/' . $part_slug . '/' . $chapter_slug . '/'         => null,
	'/fa/books/other-slug/read/'                                       => null,
) as $from => $want ) {
	if ( $want !== KBK_AI_Book::book_chapter_path( $from ) ) {
		fwrite( STDERR, "FAIL chapter address {$from}\n" ); exit( 1 );
	}
}
foreach ( array( 'sitemap', 'llms' ) as $feed ) {
	$GLOBALS['kbk_test_query'] = array( 'kbk_ai_book' => $feed, 'kbk_book' => 'other-slug' );
	if ( $feed !== KBK_AI_Book::current_view() ) {
		fwrite( STDERR, "FAIL {$feed} endpoint detection\n" ); exit( 1 );
	}
	$GLOBALS['kbk_test_query']['kbk_book'] = 'nope';
	if ( '' !== KBK_AI_Book::current_view() ) {
		fwrite( STDERR, "FAIL {$feed} of an unknown book must 404\n" ); exit( 1 );
	}
}
$sitemap = simplexml_load_string( KBK_AI_Book::sitemap_xml( KBK_AI_Book::sitemap_entries( 'other-slug' ) ) );
if ( false === $sitemap || 9 !== count( $sitemap->url ) || 'https://example.test/fa/books/other-slug/' !== (string) $sitemap->url[0]->loc || '2026-10-02' !== (string) $sitemap->url[0]->lastmod ) {
	fwrite( STDERR, "FAIL sitemap.xml of another book\n" ); exit( 1 );
}
$llms = KBK_AI_Book::llms_txt( 'other-slug' );
if ( 0 !== strpos( $llms, "# کتاب دیگر\n" ) || false === strpos( $llms, '`KDJ-OT-2026E1-Pnn-Cnn-Snn`' ) || false === strpos( $llms, 'https://example.test/fa/books/other-slug/read/?kbk_part=P01&kbk_chapter=C01' ) ) {
	fwrite( STDERR, "FAIL llms.txt of another book\n" ); exit( 1 );
}
if ( false === strpos( $template_source, "'book' => \$book_slug" ) || false !== strpos( $template_source, "'book' => 'ai-governance'" ) || false === strpos( $reader_script, 'chapterMap.book' ) || false !== strpos( $reader_script, 'Resume("ai-governance"' ) ) {
	fwrite( STDERR, "FAIL the chapter map and the resume key must carry the current book\n" ); exit( 1 );
}
$GLOBALS['kbk_test_query'] = array();
unset( $GLOBALS['kbk_test_options']['kbk_books'] );
KBK_AI_Book_Registry::reset();

echo "PASS ai-book-wordpress-route: hooks, rewrites (25 generic, incl. /books/ library, /fa/books/{slug}/ views, sitemap.xml, llms.txt and /ai-book/verify/), other books (routing, 404, /books/{slug}/ 301, frozen chapter address, sitemap, llms.txt, chapter map), retired /ai-book/ redirects, locale-first Persian AI Governance namespace, full-bleed Reader theme contract, CSP-safe PDF iframe, meaningful Governance titles, safe reader markup, allowlist, noindex, config state, assets, template and search/glossary/catalog/graph/ask/pdf/request request hygiene\n";
