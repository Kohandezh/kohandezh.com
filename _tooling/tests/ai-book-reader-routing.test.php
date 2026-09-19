<?php
/**
 * Isolated test for KBK_AI_Book::current_reader_selection() — the allowlisted
 * dynamic part/chapter/section routing added in Phase 9 (Reader Data
 * Integration). Exercises real canonical data so the P06 slug-collision
 * fail-closed path is proven against the actual book, not a fixture.
 */
define( 'ABSPATH', __DIR__ );
define( 'KBK_FEATURE_AI_BOOK', true );
define( 'KBK_PLUGIN_FILE', __FILE__ );
define( 'KBK_VERSION', 'test' );

$root = $argv[1] ?? '';
if ( '' === $root ) {
	fwrite( STDERR, "Usage: php ai-book-reader-routing.test.php /path/to/AiBook\n" );
	exit( 2 );
}
define( 'KBK_AI_BOOK_ROOT', $root );

$GLOBALS['kbk_test_query'] = array();

function add_filter( $name, $callback, $priority = 10 ) {}
function add_action( $name, $callback, $priority = 10 ) {}
function add_rewrite_rule( $regex, $query, $position ) {}
function get_query_var( $name ) { return $GLOBALS['kbk_test_query'][ $name ] ?? ''; }

require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-artifacts.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-repository.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-search.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book.php';

function reset_query() {
	$GLOBALS['kbk_test_query'] = array();
}

// 1. No part/chapter requested: default to the first canonical part/chapter.
reset_query();
$selection = KBK_AI_Book::current_reader_selection();
if ( 'P01' !== ( $selection['part']['part_id'] ?? null ) || 'C01' !== ( $selection['chapter']['chapter_id'] ?? null ) ) {
	fwrite( STDERR, "FAIL default selection must be P01/C01\n" );
	exit( 1 );
}
if ( $selection['part_not_found'] || $selection['chapter_not_found'] ) {
	fwrite( STDERR, "FAIL default selection must not report not-found\n" );
	exit( 1 );
}

// 2. Explicit valid part + chapter.
reset_query();
$GLOBALS['kbk_test_query'][ KBK_AI_Book::PART_QUERY_VAR ]    = 'P03';
$GLOBALS['kbk_test_query'][ KBK_AI_Book::CHAPTER_QUERY_VAR ] = 'C18';
$selection = KBK_AI_Book::current_reader_selection();
if ( 'P03' !== ( $selection['part']['part_id'] ?? null ) || 'C18' !== ( $selection['chapter']['chapter_id'] ?? null ) ) {
	fwrite( STDERR, "FAIL explicit P03/C18 selection failed\n" );
	exit( 1 );
}

// 3. Unknown part fails closed; no chapter guessed.
reset_query();
$GLOBALS['kbk_test_query'][ KBK_AI_Book::PART_QUERY_VAR ] = 'P99';
$selection = KBK_AI_Book::current_reader_selection();
if ( ! $selection['part_not_found'] || null !== $selection['part'] || null !== $selection['chapter'] ) {
	fwrite( STDERR, "FAIL unknown part must fail closed with no guessed chapter\n" );
	exit( 1 );
}

// 4. Known part, unknown chapter: chapter_not_found is set, part is still known.
reset_query();
$GLOBALS['kbk_test_query'][ KBK_AI_Book::PART_QUERY_VAR ]    = 'P02';
$GLOBALS['kbk_test_query'][ KBK_AI_Book::CHAPTER_QUERY_VAR ] = 'C99';
$selection = KBK_AI_Book::current_reader_selection();
if ( 'P02' !== ( $selection['part']['part_id'] ?? null ) || ! $selection['chapter_not_found'] || null !== $selection['chapter'] ) {
	fwrite( STDERR, "FAIL unknown chapter in a known part must fail closed\n" );
	exit( 1 );
}

// 5. P06's ambiguous truncated slug must fail closed even through the public
//    query-var path, not just the repository's own unit test.
reset_query();
$repository = new KBK_AI_Book_Repository( $root );
$p06_chapter = $repository->find_chapter( 'P06', 'C44' );
$GLOBALS['kbk_test_query'][ KBK_AI_Book::PART_QUERY_VAR ]    = 'P06';
$GLOBALS['kbk_test_query'][ KBK_AI_Book::CHAPTER_QUERY_VAR ] = $p06_chapter['slug'];
$selection = KBK_AI_Book::current_reader_selection();
if ( ! $selection['chapter_not_found'] || null !== $selection['chapter'] ) {
	fwrite( STDERR, "FAIL P06 ambiguous slug must fail closed through the query-var route too\n" );
	exit( 1 );
}

// 6. A section query var that resolves within the selected chapter is honored.
reset_query();
$GLOBALS['kbk_test_query'][ KBK_AI_Book::PART_QUERY_VAR ]    = 'P01';
$GLOBALS['kbk_test_query'][ KBK_AI_Book::CHAPTER_QUERY_VAR ] = 'C01';
$GLOBALS['kbk_test_query'][ KBK_AI_Book::SECTION_QUERY_VAR ] = 'KDJ-AI-2026E1-P01-C01-S01';
$selection = KBK_AI_Book::current_reader_selection();
if ( 'KDJ-AI-2026E1-P01-C01-S01' !== ( $selection['section']['structural_id'] ?? null ) ) {
	fwrite( STDERR, "FAIL matching section query var must resolve\n" );
	exit( 1 );
}

// 7. A section query var from a different chapter is silently ignored, not an error.
reset_query();
$GLOBALS['kbk_test_query'][ KBK_AI_Book::PART_QUERY_VAR ]    = 'P01';
$GLOBALS['kbk_test_query'][ KBK_AI_Book::CHAPTER_QUERY_VAR ] = 'C01';
$GLOBALS['kbk_test_query'][ KBK_AI_Book::SECTION_QUERY_VAR ] = 'KDJ-AI-2026E1-P03-C02-S01';
$selection = KBK_AI_Book::current_reader_selection();
if ( null !== $selection['section'] || 'C01' !== ( $selection['chapter']['chapter_id'] ?? null ) ) {
	fwrite( STDERR, "FAIL cross-chapter section query var must be ignored, not error\n" );
	exit( 1 );
}

// 8. A malformed/injection-shaped identifier never reaches the repository as
//    a lookup value: requested_identifier() rejects it before find_part() is
//    called, so it is treated the same as "no part requested" (safe default)
//    rather than being passed through for string comparison.
reset_query();
$GLOBALS['kbk_test_query'][ KBK_AI_Book::PART_QUERY_VAR ] = "P01' OR '1'='1";
if ( null !== KBK_AI_Book::requested_identifier( KBK_AI_Book::PART_QUERY_VAR ) ) {
	fwrite( STDERR, "FAIL malformed identifier must be rejected by the allowlist pattern\n" );
	exit( 1 );
}
$selection = KBK_AI_Book::current_reader_selection();
if ( $selection['part_not_found'] || 'P01' !== ( $selection['part']['part_id'] ?? null ) ) {
	fwrite( STDERR, "FAIL malformed identifier must fall back to the safe default part, not error\n" );
	exit( 1 );
}
$overlong = str_repeat( 'a', 200 );
$GLOBALS['kbk_test_query'][ KBK_AI_Book::PART_QUERY_VAR ] = $overlong;
if ( null !== KBK_AI_Book::requested_identifier( KBK_AI_Book::PART_QUERY_VAR ) ) {
	fwrite( STDERR, "FAIL oversized identifier must be rejected by the allowlist pattern\n" );
	exit( 1 );
}

// 9. adjacent_chapters is reachable and correct through the resolved selection.
reset_query();
$GLOBALS['kbk_test_query'][ KBK_AI_Book::PART_QUERY_VAR ]    = 'P01';
$GLOBALS['kbk_test_query'][ KBK_AI_Book::CHAPTER_QUERY_VAR ] = 'C02';
$selection = KBK_AI_Book::current_reader_selection();
$adjacent  = $repository->adjacent_chapters( $selection['part']['part_id'], $selection['chapter']['chapter_id'] );
if ( 'C01' !== ( $adjacent['prev']['chapter_id'] ?? null ) ) {
	fwrite( STDERR, "FAIL adjacent_chapters from a resolved selection failed\n" );
	exit( 1 );
}

echo "PASS ai-book-reader-routing: default/explicit/unknown part+chapter, P06 fail-closed, section deep link, malformed-input allowlist and adjacent_chapters\n";
