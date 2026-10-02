<?php
/**
 * KBK_AI_Book_Registry — one Reader, many books.
 *
 *   php _tooling/tests/ai-book-registry.test.php
 *
 * Sources merge in order (KBK_AI_BOOK_ROOT → KBK_BOOKS → option `kbk_books`),
 * the first row per slug wins, slugs are validated, announced rows never
 * route, option rows route only with a real bundle, the ID code is derived
 * from the bundle, and the Reader resolves links, verification, sitemap and
 * llms.txt per book. Self-contained: bundles are built from the fixtures.
 */
define( 'ABSPATH', __DIR__ );
define( 'KBK_FEATURE_AI_BOOK', true );
define( 'KBK_PLUGIN_FILE', __DIR__ . '/../wp-theme/kohandezh-knowledge/kohandezh-knowledge.php' );
define( 'KBK_VERSION', 'test' );
define( 'KBK_AI_BOOK_INDEXABLE', true );

require_once __DIR__ . '/fixtures/ai-book/bundle.php';
$legacy_root = kbk_test_bundle( 'AI', array( 'built_at' => '2026-09-21T10:34:09-0400', 'compiler' => 'محمدعلی کهن‌دژ' ) );
$ics_root    = kbk_test_bundle( 'ICS', array( 'title_fa' => 'امنیت سایبری صنعتی', 'title_en' => 'Industrial Cybersecurity', 'attribution' => 'تألیف: محمدعلی کهن‌دژ', 'built_at' => '2026-10-01T08:00:00+0330' ) );
define( 'KBK_AI_BOOK_ROOT', $legacy_root );
define( 'KBK_BOOKS', json_encode( array(
	array( 'slug' => 'ics-cybersecurity', 'root' => $ics_root ),
	array( 'slug' => 'Bad Slug', 'root' => $ics_root ),
	array( 'slug' => 'ai-governance', 'root' => '/nowhere' ),
) ) );

$GLOBALS['kbk_test_query']   = array();
$GLOBALS['kbk_test_options'] = array();
function add_filter( $name, $callback, $priority = 10 ) {}
function add_action( $name, $callback, $priority = 10 ) {}
function add_rewrite_rule( $regex, $query, $position ) {}
function get_query_var( $name ) { return $GLOBALS['kbk_test_query'][ $name ] ?? ''; }
function get_option( $name, $default = false ) { return array_key_exists( $name, $GLOBALS['kbk_test_options'] ) ? $GLOBALS['kbk_test_options'][ $name ] : $default; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function plugins_url( $path, $file ) { return 'https://example.test/plugin/' . $path; }
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : ''; }
function esc_url_raw( $url, $protocols = null ) { return 1 === preg_match( '#^https?://#i', (string) $url ) ? (string) $url : ''; }
function add_settings_error( $setting, $code, $message ) { $GLOBALS['kbk_test_notices'][] = $code; }

require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-artifacts.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-repository.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-catalog.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-registry.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book.php';

$checks = 0;
function check( bool $ok, string $label ): void {
	global $checks;
	$checks++;
	if ( ! $ok ) {
		fwrite( STDERR, "FAIL {$label}\n" );
		exit( 1 );
	}
}
function slugs( array $rows ): array {
	return array_column( $rows, 'slug' );
}

// --- Slug grammar ---------------------------------------------------------------
foreach ( array( 'ai-governance' => true, 'a' => true, 'ics-62443' => true, str_repeat( 'a', 40 ) => true, str_repeat( 'a', 41 ) => false, 'Bad' => false, '-a' => false, 'a-' => false, 'a--b' => false, 'a_b' => false, '' => false, '../x' => false ) as $slug => $want ) {
	check( $want === KBK_AI_Book_Registry::valid_slug( (string) $slug ), "slug grammar for '{$slug}'" );
}

// --- Merge order: implicit row, constant rows, option defaults ------------------
$rows = KBK_AI_Book_Registry::rows();
check( array( 'ai-governance', 'ics-cybersecurity' ) === slugs( $rows ), 'implicit ai-governance row first, valid KBK_BOOKS rows next; bad slug and duplicate dropped; option default ics row shadowed by the constant' );
check( $rows[0]['locked'] && 'KBK_AI_BOOK_ROOT' === $rows[0]['source'] && $legacy_root === $rows[0]['root'], 'the implicit row is locked and points at KBK_AI_BOOK_ROOT (the duplicate KBK_BOOKS row never replaces it)' );
check( $rows[1]['locked'] && 'KBK_BOOKS' === $rows[1]['source'] && $rows[1]['routable'], 'KBK_BOOKS rows are locked and routed' );
check( 'ai-governance' === KBK_AI_Book_Registry::default_slug(), 'the first routed book is the default' );

// Option absent → the announced ICS card is seeded; an explicit empty option removes it.
$GLOBALS['kbk_test_options'] = array();
KBK_AI_Book_Registry::reset();
$defaults = KBK_AI_Book_Registry::option_rows();
check( 1 === count( $defaults ) && 'ics-cybersecurity' === $defaults[0]['slug'] && 'announced' === $defaults[0]['status'] && 'امنیت سایبری در سیستم‌های اتوماسیون صنعتی' === $defaults[0]['title_fa'], 'an unsaved option seeds the announced ICS card' );
$GLOBALS['kbk_test_options']['kbk_books'] = array();
KBK_AI_Book_Registry::reset();
check( array() === KBK_AI_Book_Registry::option_rows(), 'a saved empty option stays empty (an admin can remove the seed)' );

// Option rows: valid bundle routes, broken root is kept but not routed, announced never routes.
$GLOBALS['kbk_test_options']['kbk_books'] = array(
	array( 'slug' => 'second-book', 'root' => $ics_root, 'status' => 'published', 'cover' => '/covers/second.webp' ),
	array( 'slug' => 'broken-book', 'root' => '/definitely/missing', 'status' => 'published' ),
	array( 'slug' => 'coming-soon', 'status' => 'announced', 'title_fa' => 'کتاب آینده', 'subtitle_fa' => 'زیرعنوان', 'meta_fa' => 'نویسنده', 'note_fa' => 'به‌زودی' ),
	array( 'slug' => 'ai-governance', 'root' => $ics_root ),
	array( 'slug' => 'no-root-default' ),
	'not-a-row',
);
KBK_AI_Book_Registry::reset();
$rows = KBK_AI_Book_Registry::rows();
check( array( 'ai-governance', 'ics-cybersecurity', 'second-book', 'broken-book', 'coming-soon', 'no-root-default' ) === slugs( $rows ), 'option rows follow the locked rows; a locked slug is never overridden' );
$by = array_column( $rows, null, 'slug' );
check( $by['second-book']['routable'] && ! $by['second-book']['locked'], 'an option row with a real bundle is routed' );
check( ! $by['broken-book']['routable'] && 'published' === $by['broken-book']['status'] && ! $by['broken-book']['root_ok'], 'an option row with a broken root is kept, flagged and not routed' );
check( ! $by['coming-soon']['routable'] && 'announced' === $by['coming-soon']['status'], 'announced rows never route' );
check( 'announced' === $by['no-root-default']['status'], 'a row without root defaults to announced' );
check( array( 'ai-governance', 'ics-cybersecurity', 'second-book' ) === KBK_AI_Book_Registry::routable_slugs(), 'routable slugs in registry order' );
check( KBK_AI_Book_Registry::is_routable( 'second-book' ) && ! KBK_AI_Book_Registry::is_routable( 'broken-book' ) && ! KBK_AI_Book_Registry::is_routable( 'nope' ) && ! KBK_AI_Book_Registry::is_routable( 'Bad Slug' ), 'is_routable' );

// A locked row with a broken root still routes, so the Reader can show its degraded state.
check( null === KBK_AI_Book_Registry::normalize_row( array( 'slug' => 'x y' ), true, 'test' ), 'normalize rejects bad slugs' );
$locked_broken = KBK_AI_Book_Registry::normalize_row( array( 'slug' => 'locked-broken', 'root' => '/missing' ), true, 'KBK_BOOKS' );
check( $locked_broken['routable'] && ! $locked_broken['root_ok'], 'a broken wp-config root still routes (honest degraded page, never a silent 404)' );
check( '' === KBK_AI_Book_Registry::normalize_row( array( 'slug' => 'c', 'cover' => 'javascript:alert(1)' ), false, 'kbk_books' )['cover'], 'cover URLs must be http(s) or site paths' );

// --- Repositories and ID codes ------------------------------------------------------
$legacy_repo = KBK_AI_Book_Registry::repository( 'ai-governance' );
$ics_repo    = KBK_AI_Book_Registry::repository( 'ics-cybersecurity' );
check( null !== $legacy_repo && null !== $ics_repo && $legacy_repo !== $ics_repo && $legacy_repo === KBK_AI_Book_Registry::repository( 'ai-governance' ), 'one cached repository per book' );
check( 'READY' === KBK_AI_Book_Registry::repository_status( 'second-book' ) && 'CONFIG_REQUIRED' === KBK_AI_Book_Registry::repository_status( 'broken-book' ), 'repository status per book' );
check( 'AI' === KBK_AI_Book_Registry::code( 'ai-governance' ) && 'ICS' === KBK_AI_Book_Registry::code( 'ics-cybersecurity' ), 'the ID code is derived from the bundle' );
check( 'X1' === KBK_AI_Book_Registry::normalize_row( array( 'slug' => 'c', 'code' => 'x1' ), false, 'kbk_books' )['code'], 'an explicit row code wins (upper-cased)' );
check( null !== $ics_repo->find_section( 'KDJ-ICS-2026E1-P01-C01-S01' ), 'a KDJ-ICS-… bundle validates under the generic ID grammar' );

// --- The Reader follows the requested book ------------------------------------------
$GLOBALS['kbk_test_query'] = array( 'kbk_ai_book' => 'read', 'kbk_book' => 'ics-cybersecurity' );
check( 'ics-cybersecurity' === KBK_AI_Book::current_book_slug() && $ics_repo === KBK_AI_Book::repository(), 'repository() is the requested book' );
check( 'https://example.test/fa/books/ics-cybersecurity/read/?kbk_part=P01&kbk_chapter=C01' === KBK_AI_Book::reader_url( 'P01', 'C01' ), 'reader_url uses the current slug' );
check( 0 === strpos( KBK_AI_Book::section_url( 'P01', 'C01', 'KDJ-ICS-2026E1-P01-C01-S01' ), 'https://example.test/fa/books/ics-cybersecurity/read/?kbk_part=P01&kbk_chapter=C01&kbk_section=' ), 'section_url uses the current slug' );
check( 'https://example.test/fa/books/ics-cybersecurity/search/' === KBK_AI_Book::preferred_internal_url( 'https://example.test/ai-book/search/' ), 'legacy /ai-book/ links inside a book point at that book' );
check( 'https://example.test/ai-book/verify/' === KBK_AI_Book::preferred_internal_url( 'https://example.test/ai-book/verify/' ), 'the verify address is never rewritten' );
check( ! KBK_AI_Book::is_legacy_book(), 'the first book\'s hand-written fix-ups stay with the first book' );
$governance_title = array( 'part_id' => 'P02', 'chapter_id' => 'C09', 'section_id' => 'S02', 'title_fa' => 'AI Governance 1.1 — حاکمیت', 'fa_text' => "## GOVERN 1.1\n\nمتن" );
check( 'AI Governance 1.1 — حاکمیت' === KBK_AI_Book::reader_section_title( $governance_title ) && false !== strpos( KBK_AI_Book::reader_section_body( $governance_title ), 'GOVERN 1.1' ), 'another book\'s P02/C09 is rendered as written' );
$display = KBK_AI_Book::book_display();
check( 'امنیت سایبری صنعتی' === $display['name'] && 'امنیت سایبری صنعتی' === $display['short'] && '2026-10-01' === $display['built_date'] && 'Industrial Cybersecurity' === $display['title_en'], 'another book is named by its own book.json' );
check( null !== $display['cover'] && false !== strpos( $display['cover']['w640'], 'ics-cybersecurity-cover-w640.webp' ), 'a shipped plugin cover is found by slug' );
$GLOBALS['kbk_test_query'] = array( 'kbk_ai_book' => 'read', 'kbk_book' => 'second-book' );
check( array( 'w320' => 'https://example.test/covers/second.webp', 'w640' => 'https://example.test/covers/second.webp' ) === KBK_AI_Book::book_display()['cover'], 'a registry cover path wins over the plugin default' );
$GLOBALS['kbk_test_query'] = array( 'kbk_ai_book' => 'read', 'kbk_book' => 'broken-book' );
check( null === KBK_AI_Book::current_book_slug() && '' === KBK_AI_Book::current_view() && null === KBK_AI_Book::repository(), 'an unrouted slug is not a Reader page' );
$GLOBALS['kbk_test_query'] = array( 'kbk_ai_book' => 'library' );
check( 'ai-governance' === KBK_AI_Book::current_book_slug() && KBK_AI_Book::is_legacy_book(), 'pages without a slug belong to the first book' );
$legacy_display = KBK_AI_Book::book_display( 'ai-governance' );
check( 'AI Governance — حاکمیت هوش مصنوعی' === $legacy_display['name'] && 'حاکمیت هوش مصنوعی' === $legacy_display['short'] && 'محمدعلی کهن‌دژ' === $legacy_display['meta_fa'], 'the first book keeps its hand-written names' );
$soon = KBK_AI_Book::book_display( 'coming-soon' );
check( ! $soon['routable'] && 'کتاب آینده' === $soon['short'] && 'زیرعنوان' === $soon['subtitle_fa'] && 'به‌زودی' === $soon['note_fa'], 'announced cards are described by their row' );

// Verification looks across every routed book; the first match wins.
$GLOBALS['kbk_test_query'] = array( 'kbk_ai_book' => 'verify', 'kbk_verify' => 'KDJ-ICS-2026E1-P01-C01-S01' );
check( 'ics-cybersecurity' === KBK_AI_Book::current_book_slug(), 'a verify ID resolves to the book that holds it' );
$verification = KBK_AI_Book::current_verification();
check( 'READY' === $verification['status'] && null !== $verification['match'] && 'KDJ-ICS-2026E1-P01-C01-S01' === $verification['match']['structural_id'], 'the verify page finds IDs of any book' );
$GLOBALS['kbk_test_query'] = array( 'kbk_ai_book' => 'verify', 'kbk_verify' => 'KDJ-AI-2026E1-P01-C01-S01' );
check( 'ai-governance' === KBK_AI_Book::current_book_slug(), 'first book IDs stay with the first book' );
$GLOBALS['kbk_test_query'] = array( 'kbk_ai_book' => 'verify', 'kbk_verify' => 'KDJ-ZZ-2026E1-P01-C01-S01' );
check( 'ai-governance' === KBK_AI_Book::current_book_slug() && null === KBK_AI_Book::current_verification()['match'], 'an unknown ID falls back to the first book and matches nothing' );

// --- /books/{slug}/ aliases ------------------------------------------------------------
foreach ( array(
	'/books/ics-cybersecurity/'                  => '/fa/books/ics-cybersecurity/',
	'/books/ics-cybersecurity/glossary/?kbk_concept=RISK' => '/fa/books/ics-cybersecurity/glossary/?kbk_concept=RISK',
	'/books/ai-governance'                       => '/fa/books/ai-governance',
	'/books/coming-soon/'                        => null,
	'/books/nope/read/'                          => null,
	'/books/'                                    => null,
	'/fa/books/ics-cybersecurity/'               => null,
) as $from => $want ) {
	check( $want === KBK_AI_Book::localized_book_path( $from ), "unlocalized alias {$from}" );
}

// --- Sitemap, llms.txt and robots.txt per book -----------------------------------------
$entries = KBK_AI_Book::sitemap_entries( 'ics-cybersecurity' );
$locs    = array_column( $entries, 'loc' );
check( 9 === count( $entries ) && 'https://example.test/fa/books/ics-cybersecurity/' === $locs[0] && in_array( 'https://example.test/fa/books/ics-cybersecurity/read/?kbk_part=P01&kbk_chapter=C01', $locs, true ), 'the sitemap lists the landing, the tool pages and every chapter page of its own book' );
check( array( '2026-10-01' ) === array_values( array_unique( array_column( $entries, 'lastmod' ) ) ), 'lastmod is the bundle build date' );
check( array() === array_filter( $locs, static function ( string $loc ): bool { return 1 === preg_match( '#/(search|ask|request-pdf|pdf/file)/#', $loc ); } ), 'search, ask, the request form and the PDF file are never listed' );
$xml = simplexml_load_string( KBK_AI_Book::sitemap_xml( $entries ) );
check( false !== $xml && 9 === count( $xml->url ) && 'https://example.test/fa/books/ics-cybersecurity/read/?kbk_part=P01&kbk_chapter=C01' === (string) $xml->url[8]->loc, 'the sitemap is valid XML with escaped query strings' );
check( array() === KBK_AI_Book::sitemap_entries( 'broken-book' ) && array() === KBK_AI_Book::sitemap_entries( 'nope' ), 'unrouted books have no sitemap' );
$llms = KBK_AI_Book::llms_txt( 'ics-cybersecurity' );
check( 0 === strpos( $llms, "# امنیت سایبری صنعتی\n" ) && false !== strpos( $llms, '> Industrial Cybersecurity — تألیف: محمدعلی کهن‌دژ' ) && false !== strpos( $llms, "## ساختار" ) && false !== strpos( $llms, '(https://example.test/fa/books/ics-cybersecurity/read/?kbk_part=P01&kbk_chapter=C01)' ) && false !== strpos( $llms, '`KDJ-ICS-2026E1-Pnn-Cnn-Snn`' ) && false !== strpos( $llms, 'https://example.test/ai-book/verify/?kbk_verify={id}' ), 'llms.txt: title, summary, structure with URLs, ID grammar and verify URL' );
check( '' === KBK_AI_Book::llms_txt( 'nope' ), 'unrouted books have no llms.txt' );
$robots = KBK_AI_Book::robots_txt( "User-agent: *\n", true );
check( false !== strpos( $robots, "Sitemap: https://example.test/fa/books/ai-governance/sitemap.xml\n" ) && false !== strpos( $robots, "Sitemap: https://example.test/fa/books/ics-cybersecurity/sitemap.xml\n" ) && false !== strpos( $robots, "Sitemap: https://example.test/fa/books/second-book/sitemap.xml\n" ) && false === strpos( $robots, 'broken-book' ) && false === strpos( $robots, 'coming-soon' ), 'robots.txt advertises one sitemap per routed, valid book' );
check( "User-agent: *\n" === KBK_AI_Book::robots_txt( "User-agent: *\n", false ), 'a private site advertises nothing' );

// --- Titles and descriptions ------------------------------------------------------------
check( 'فصل — کتاب' === KBK_AI_Book::fitted_title( 'فصل', 'کتاب' ) && str_repeat( 'ب', 69 ) === KBK_AI_Book::fitted_title( str_repeat( 'ب', 69 ), 'کتاب' ), 'a title keeps its book suffix only within 70 characters' );
$excerpt = KBK_AI_Book::plain_excerpt( "## عنوان\n\n**متن** با [پیوند](https://x.test) و پانویس[^1] [FIGURE 2]\n\n| a | b |\n\n[^1]: تعریف" );
check( 'متن با پیوند و پانویس' === $excerpt, 'descriptions drop headings, markup, links, footnotes, figures and tables' );
$long = KBK_AI_Book::plain_excerpt( str_repeat( 'واژه ', 60 ) );
check( mb_strlen( $long ) <= 156 && '…' === mb_substr( $long, -1 ) && 'واژه…' === mb_substr( $long, -5 ), 'long descriptions are cut at a word and closed with an ellipsis' );

// --- Settings sanitizer -----------------------------------------------------------------------
$GLOBALS['kbk_test_notices'] = array();
$clean = KBK_AI_Book_Registry::sanitize_option( array(
	array( 'slug' => 'New-Book', 'root' => $ics_root, 'status' => 'published', 'cover' => 'https://cdn.example/c.webp', 'title_fa' => '<b>کتاب</b>' ),
	array( 'slug' => 'Bad Slug!', 'root' => '/x' ),
	array( 'slug' => 'broken', 'root' => '/nowhere', 'status' => 'published' ),
	array( 'slug' => '', 'root' => '', 'title_fa' => '', 'cover' => '' ),
	array( 'slug' => 'gone', 'remove' => '1' ),
	array( 'slug' => 'new-book' ),
	array( 'slug' => 'ics-cybersecurity', 'root' => $ics_root ),
	array( 'slug' => 'soon', 'cover' => 'javascript:alert(1)', 'status' => 'weird' ),
) );
check( array( 'new-book', 'broken', 'soon' ) === slugs( $clean ), 'sanitize keeps valid rows only: bad, blank, removed, duplicate and wp-config slugs are dropped' );
check( 'کتاب' === $clean[0]['title_fa'] && 'https://cdn.example/c.webp' === $clean[0]['cover'] && 'published' === $clean[0]['status'], 'text is sanitized, http(s) covers kept' );
check( ! isset( $clean[2]['cover'] ) && 'announced' === $clean[2]['status'], 'unsafe covers and unknown statuses are not stored' );
check( array( 'kbk_books_slug', 'kbk_books_root', 'kbk_books_duplicate', 'kbk_books_duplicate' ) === $GLOBALS['kbk_test_notices'], 'every dropped or unrouted row is reported to the admin' );

echo "PASS ai-book-registry: {$checks} checks — slug grammar, KBK_AI_BOOK_ROOT → KBK_BOOKS → option merge (first slug wins, locked rows), seeded/removable announced card, routed vs broken vs announced rows, per-book repositories and derived ID codes, per-book links/verify/aliases, sitemap + llms.txt + robots.txt, fitted titles, plain descriptions, settings sanitizer\n";
