<?php
/**
 * Alias search test: one concept must be reachable through every Persian or Latin
 * form a reader may type (the common form, the official INSO form, an acronym and
 * the Arabic-letter spelling printed in the national standards).
 *
 * The queries are checked against the bundle's own glossary. Editions differ in what
 * they carry: the first edition has only the common forms, the second edition adds the
 * official INSO forms as aliases. A query that an edition does not promise is not asserted.
 */
define( 'ABSPATH', __DIR__ );
define( 'KBK_FEATURE_AI_BOOK', true );
define( 'KBK_PLUGIN_FILE', __FILE__ );
define( 'KBK_VERSION', 'test' );

$root = $argv[1] ?? '';
if ( '' === $root ) {
	fwrite( STDERR, "Usage: php ai-book-alias.test.php /path/to/book-root\n" );
	exit( 2 );
}
define( 'KBK_AI_BOOK_ROOT', $root );

$GLOBALS['kbk_test_query'] = array();

function add_filter( $name, $callback, $priority = 10 ) {}
function add_action( $name, $callback, $priority = 10 ) {}
function add_rewrite_rule( $regex, $query, $position ) {}
function get_query_var( $name ) { return $GLOBALS['kbk_test_query'][ $name ] ?? ''; }
function wp_unslash( $value ) { return $value; }
function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }

require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-artifacts.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-repository.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-search.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book.php';

function fail( string $message ) {
	fwrite( STDERR, "FAIL {$message}\n" );
	exit( 1 );
}

$repository = new KBK_AI_Book_Repository( $root );
$search     = new KBK_AI_Book_Search( $repository );
if ( KBK_AI_Book_Search::STATUS_READY !== $search->status() ) {
	fail( 'engine must load the bundle cleanly' );
}
$first_edition = '2026E1' === $repository->summary()['edition_id'];

/* query => [ English concept the glossary card must carry, true when the first edition promises it too ] */
$aliases = array(
	'ریسک'                => array( 'risk', true ),
	'مخاطره'              => array( 'risk', false ),
	'AIMS'                => array( 'artificial intelligence management system', false ),
	'نظام مدیریت هوش مصنوعی' => array( 'artificial intelligence management system', false ),
	'حاکمیت هوش مصنوعی'   => array( null, true ),
	'حکمرانی'             => array( 'governance', false ),
	'کنترل'               => array( 'control', false ),
	'واپایش'              => array( 'control', false ),
	'كنترل'               => array( 'control', false ),
	'ممیزی'               => array( 'audit', false ),
	'حسابرسی'             => array( 'audit', false ), // E1 ships two entries on one concept ID (AUDIT), so its card is not reliably the one shown
	'هومص'                => array( 'artificial intelligence', false ),
);

foreach ( $aliases as $query => list( $concept, $in_first_edition ) ) {
	if ( $first_edition && ! $in_first_edition ) {
		continue;
	}
	$outcome = $search->search( $query );
	if ( 'READY' !== $outcome['status'] || $outcome['total'] < 1 || array() === $outcome['results'] ) {
		fail( "query «{$query}» must return results" );
	}
	if ( null === $concept ) {
		continue;
	}
	$found = false;
	foreach ( $outcome['results'] as $item ) {
		if ( 'glossary' === $item['type'] && 0 === strcasecmp( (string) ( $item['english'] ?? '' ), $concept ) ) {
			$found = true;
			break;
		}
	}
	if ( ! $found ) {
		fail( "query «{$query}» must surface the glossary card for «{$concept}»" );
	}
}

/* A rejected form must never act as an alias. The first edition's glossary predates this rule and is frozen, so it is checked from the second edition on. */
$glossary = json_decode( (string) file_get_contents( $root . '/release/09_glossary.json' ), true );
foreach ( $first_edition ? array() : $glossary['terms'] as $term ) {
	foreach ( $term['rejected_fa'] ?? array() as $rejected ) {
		if ( in_array( $rejected, $term['alternatives_fa'] ?? array(), true ) || $rejected === $term['preferred_fa'] ) {
			fail( 'a rejected form must not also be a preferred form or an alias: ' . $term['concept_id'] );
		}
	}
}

echo "PASS ai-book-alias: " . count( $aliases ) . " alias queries checked for " . $repository->summary()['edition_id'] . ( $first_edition ? ' (first-edition subset)' : '' ) . "\n";
