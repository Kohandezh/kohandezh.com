<?php
/**
 * KBK_AI_Book_Views — per-section / per-chapter view counters.
 *
 *   php _tooling/tests/ai-book-views.test.php
 *
 * $wpdb is an in-memory table that understands exactly the statements the
 * class issues. Covers: lazy table creation, atomic increment, chapter
 * aggregation in one read, ID grammar, unknown book / chapter / section
 * rejection (never counted), the settings switches and the admin top list.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'KBK_FEATURE_AI_BOOK', true );
define( 'KBK_PLUGIN_FILE', __DIR__ . '/../wp-theme/kohandezh-knowledge/kohandezh-knowledge.php' );
define( 'KBK_VERSION', 'test' );
define( 'KBK_REST_NAMESPACE', 'kohandezh/v1' );
define( 'ARRAY_A', 'ARRAY_A' );

require_once __DIR__ . '/fixtures/ai-book/bundle.php';
define( 'KBK_AI_BOOK_ROOT', kbk_test_bundle( 'AI' ) );
define( 'KBK_BOOKS', json_encode( array( array( 'slug' => 'ics-cybersecurity', 'root' => kbk_test_bundle( 'ICS' ) ) ) ) );

$GLOBALS['kbk_test_options'] = array();
function add_filter( $name, $callback, $priority = 10 ) {}
function add_action( $name, $callback, $priority = 10 ) {}
function add_rewrite_rule( $regex, $query, $position ) {}
function get_query_var( $name ) { return ''; }
function get_option( $name, $default = false ) { return array_key_exists( $name, $GLOBALS['kbk_test_options'] ) ? $GLOBALS['kbk_test_options'][ $name ] : $default; }
function update_option( $name, $value ) { $GLOBALS['kbk_test_options'][ $name ] = $value; return true; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function plugins_url( $path, $file ) { return 'https://example.test/plugin/' . $path; }
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( $value ) { return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : ''; }
function esc_url_raw( $url, $protocols = null ) { return (string) $url; }

/** In-memory stand-in for the kbk_views table. */
final class Mock_WPDB {
	public $prefix = 'wp_';
	public $exists = false;
	public $rows   = array();
	public $log    = array();
	public function esc_like( $s ) { return addcslashes( $s, '_%\\' ); }
	public function get_charset_collate() { return ''; }
	public function prepare( $sql, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$i = 0;
		return preg_replace_callback( '/%[sd]/', function ( $m ) use ( &$i, $args ) {
			$v = $args[ $i++ ];
			return '%d' === $m[0] ? (string) (int) $v : "'" . addslashes( (string) $v ) . "'";
		}, $sql );
	}
	private function strings( $sql ) {
		preg_match_all( "/'((?:[^'\\\\]|\\\\.)*)'/", $sql, $m );
		return array_map( 'stripslashes', $m[1] );
	}
	public function query( $sql ) {
		$this->log[] = $sql;
		if ( 0 === strpos( $sql, 'CREATE TABLE' ) ) { $this->exists = true; return true; }
		if ( 0 === strpos( $sql, 'TRUNCATE' ) ) { $this->rows = array(); return true; }
		if ( 0 === strpos( $sql, 'INSERT INTO wp_kbk_views' ) && false !== strpos( $sql, 'ON DUPLICATE KEY UPDATE views = views + 1' ) ) {
			$key = $this->strings( $sql )[0];
			$this->rows[ $key ] = ( $this->rows[ $key ] ?? 0 ) + 1;
			return 1;
		}
		return false;
	}
	public function get_var( $sql ) {
		$this->log[] = $sql;
		if ( 0 === strpos( $sql, 'SHOW TABLES' ) ) { return $this->exists ? 'wp_kbk_views' : null; }
		if ( 0 === strpos( $sql, 'SELECT SUM(views)' ) ) {
			$prefix = stripslashes( str_replace( array( '\\_', '\\%' ), array( '_', '%' ), rtrim( $this->strings( $sql )[0], '%' ) ) );
			$sum = 0;
			foreach ( $this->rows as $k => $v ) { if ( 0 === strpos( $k, $prefix ) ) { $sum += $v; } }
			return (string) $sum;
		}
		return null;
	}
	public function get_results( $sql, $output = null ) {
		$this->log[] = $sql;
		$out = array();
		if ( false !== strpos( $sql, 'WHERE view_key IN' ) ) {
			foreach ( $this->strings( $sql ) as $k ) { if ( isset( $this->rows[ $k ] ) ) { $out[] = array( 'view_key' => $k, 'views' => (string) $this->rows[ $k ] ); } }
		} elseif ( false !== strpos( $sql, "LIKE '%|KDJ-%'" ) ) {
			$rows = array_filter( $this->rows, function ( $k ) { return false !== strpos( $k, '|KDJ-' ); }, ARRAY_FILTER_USE_KEY );
			uksort( $rows, function ( $a, $b ) use ( $rows ) { return $rows[ $b ] <=> $rows[ $a ] ?: strcmp( $a, $b ); } );
			preg_match( '/LIMIT (\d+)/', $sql, $m );
			foreach ( array_slice( $rows, 0, (int) $m[1], true ) as $k => $v ) { $out[] = array( 'view_key' => $k, 'views' => (string) $v ); }
		}
		return $out;
	}
}
$GLOBALS['wpdb'] = new Mock_WPDB();

$plugin = __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/';
foreach ( array( 'artifacts', 'repository', 'catalog', 'registry', '' ) as $name ) {
	require_once $plugin . 'class-kbk-ai-book' . ( '' === $name ? '' : '-' . $name ) . '.php';
}
require_once $plugin . 'class-kbk-ai-book-views.php';

$checks = 0;
function check( bool $ok, string $label ): void {
	global $checks;
	$checks++;
	if ( ! $ok ) {
		fwrite( STDERR, "FAIL {$label}\n" );
		exit( 1 );
	}
}

$repo = KBK_AI_Book_Registry::repository( 'ai-governance' );
check( null !== $repo, 'fixture book is routed' );
$part    = $repo->parts()[0];
$chapter = $part['chapters'][0];
$ids     = KBK_AI_Book_Views::chapter_section_ids( $chapter );
check( count( $ids ) >= 1, 'fixture chapter has sections' );
$p = $part['part_id'];
$c = $chapter['chapter_id'];
$s = $ids[0];
$ics = KBK_AI_Book_Registry::repository( 'ics-cybersecurity' )->parts()[0]['chapters'][0]['sections'][0]['structural_id'];

// --- ID grammar --------------------------------------------------------------
foreach ( array( 'KDJ-AI-2026E1-P01-C03-S08' => 1, 'KDJ-ICS62443-2026E12-P01-C03-S08' => 1, 'KDJ-ai-2026E1-P01-C03-S08' => 0, 'KDJ-AI-2026E1-P01-C03-S08x' => 0, "KDJ-AI-2026E1-P01-C03-S08'--" => 0, '' => 0 ) as $id => $want ) {
	check( (bool) $want === ( 1 === preg_match( KBK_AI_Book_Views::SECTION_RE, (string) $id ) ), "section grammar {$id}" );
}

// --- Lazy table creation + increment ---------------------------------------------
check( false === $wpdb->exists, 'table starts missing' );
list( $status, $body ) = KBK_AI_Book_Views::handle_post( 'ai-governance', $p, $c, $s );
check( 200 === $status && 1 === $body['views'] && 1 === $body['chapter_views'], 'first POST counts 1 / chapter 1' );
check( $wpdb->exists && 1 === get_option( KBK_AI_Book_Views::SCHEMA_OPTION ), 'table created lazily and schema option stamped' );
list( $status, $body ) = KBK_AI_Book_Views::handle_post( 'ai-governance', $p, $c, $s );
check( 2 === $body['views'] && 2 === $body['chapter_views'], 'second POST increments both keys' );
list( , $body ) = KBK_AI_Book_Views::handle_post( 'ics-cybersecurity', $p, $c, $ics );
check( 1 === $body['views'] && 1 === $body['chapter_views'], 'a second book counts separately (multi-book keys)' );
check( 2 === $wpdb->rows[ 'ai-governance|' . $s ] && 2 === $wpdb->rows[ "ai-governance|{$p}|{$c}" ] && 1 === $wpdb->rows[ "ics-cybersecurity|{$p}|{$c}" ], 'keys are {slug}|{id} and {slug}|{part}|{chapter}' );

// --- Rejections never count -------------------------------------------------------
$before = $wpdb->rows;
foreach ( array(
	array( 'no-such-book', $p, $c, $s, 'unknown book' ),
	array( 'Bad Slug', $p, $c, $s, 'invalid slug' ),
	array( 'ai-governance', 'P99', $c, $s, 'unknown part' ),
	array( 'ai-governance', $p, 'C99', $s, 'unknown chapter' ),
	array( 'ai-governance', $p, $chapter['slug'] ?? 'x', $s, 'chapter slug instead of ID' ),
	array( 'ai-governance', $p, $c, 'KDJ-AI-2026E1-P01-C01-S99', 'unknown section' ),
	array( 'ai-governance', $p, $c, 'garbage', 'malformed section' ),
	array( 'ai-governance', $p, $c, str_replace( '-C01-', '-C02-', $s ), 'well-formed section of another chapter' ),
	array( 'ics-cybersecurity', $p, $c, $s, 'section ID of another book' ),
	array( 'ai-governance', $p, $c, $ics, 'other book section in this book' ),
) as $bad ) {
	list( $status ) = KBK_AI_Book_Views::handle_post( $bad[0], $bad[1], $bad[2], $bad[3] );
	check( 400 === $status, 'reject ' . $bad[4] );
}
check( $before === $wpdb->rows, 'rejected requests wrote nothing' );
list( $status ) = KBK_AI_Book_Views::handle_get( 'no-such-book', $p, $c );
check( 400 === $status, 'GET rejects unknown book' );

// --- Chapter read: one query, every section present -------------------------------
$reads = count( $wpdb->log );
list( $status, $body ) = KBK_AI_Book_Views::handle_get( 'ai-governance', $p, $c );
check( 200 === $status && 2 === $body['chapter'] && 2 === $body['sections'][ $s ] && count( $ids ) === count( $body['sections'] ), 'GET returns every section + chapter total' );
check( 1 === count( $wpdb->log ) - $reads, 'chapter counts cost exactly one query' );
$fresh = KBK_AI_Book_Views::counts( array( 'ai-governance|never-seen' ) );
check( array( 'ai-governance|never-seen' => 0 ) === $fresh, 'unviewed keys report 0' );
check( 2 === KBK_AI_Book_Views::book_total( 'ai-governance' ), 'book total sums chapter keys only' );
check( 1 === KBK_AI_Book_Views::book_total( 'ics-cybersecurity' ), 'book totals are per book' );

// --- Settings switches -----------------------------------------------------------
$settings = KBK_AI_Book::reader_settings();
check( $settings['views_show'] && $settings['views_hide_zero'], 'counters on and hide-zero on by default' );
$GLOBALS['kbk_test_options']['kbk_reader_settings'] = array( 'views_show' => 1, 'views_hide_zero' => 0 );
check( ! KBK_AI_Book_Views::hide_zero() && KBK_AI_Book_Views::enabled(), 'hide-zero can be switched off' );
$GLOBALS['kbk_test_options']['kbk_reader_settings'] = array( 'views_show' => 0 );
$before = $wpdb->rows;
list( $status ) = KBK_AI_Book_Views::handle_post( 'ai-governance', $p, $c, $s );
check( 404 === $status && $before === $wpdb->rows, 'counters off: POST counts nothing' );
list( $status ) = KBK_AI_Book_Views::handle_get( 'ai-governance', $p, $c );
check( 404 === $status, 'counters off: GET disabled' );
unset( $GLOBALS['kbk_test_options']['kbk_reader_settings'] );

// --- Admin report + reset -------------------------------------------------------
$top = KBK_AI_Book_Views::top_rows( 30 );
check( 2 === count( $top ) && 2 === $top[0]['views'] && 1 === $top[1]['views'] && '' !== $top[0]['chapter'] && $top[0]['section'] !== $s && 'امنیت سایبری در سیستم‌های اتوماسیون صنعتی' !== $top[0]['book'], 'top list: sections only, most viewed first, titles resolved' );
KBK_AI_Book_Views::reset_all();
check( array() === $wpdb->rows && array() === KBK_AI_Book_Views::top_rows(), 'reset truncates the table' );

echo "ai-book-views: {$checks} checks passed\n";
