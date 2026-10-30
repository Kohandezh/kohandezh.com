<?php
/**
 * Previous-edition links and the "what's new" facts, against a real book root.
 *
 * Every section ID carries its edition, so a new edition renames all of them.
 * IDs printed by the previous edition (PDF, DOCX, shared links) must still reach
 * the same section, a forged previous-edition content ID must not, and the
 * landing page's counts must come from the shipped map, not from hand-typed copy.
 *
 * Run: php _tooling/tests/ai-book-edition.test.php /path/to/AiBook
 */
define( 'KBK_TESTING', true );
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-artifacts.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-repository.php';

$root = $argv[1] ?? '';
if ( '' === $root ) {
	fwrite( STDERR, "Usage: php ai-book-edition.test.php /path/to/AiBook\n" );
	exit( 2 );
}
$checks = 0;
function check( bool $ok, string $label ): void {
	global $checks;
	++$checks;
	if ( ! $ok ) {
		fwrite( STDERR, 'FAIL ' . $label . "\n" );
		exit( 1 );
	}
}

$repository = new KBK_AI_Book_Repository( $root );
$book       = $repository->book();
$base       = $repository->base_edition_id();
if ( '' === $base ) {
	check( null === $repository->edition_changes(), 'a first edition reports no changes' );
	check( null === $repository->resolve_previous_edition( 'KDJ-AI-2025E1-P01-C01-S01' ), 'a first edition resolves no previous IDs' );
	echo "PASS ai-book-edition: first edition ({$book['edition_id']}) — no previous-edition links, no what's-new block ({$checks} checks)\n";
	exit( 0 );
}

$map = $repository->previous_edition_map();
check( array() !== $map, 'provenance/e1-e2-map.json loads for ' . $base . ' -> ' . $book['edition_id'] );

// Every previous-edition section resolves, by structural and by content ID, to a section of this edition at the same position.
$statuses = array();
foreach ( $map as $base_sid => $row ) {
	$by_sid = $repository->resolve_previous_edition( $base_sid );
	check( null !== $by_sid && $by_sid['structural_id'] === $row['structural_id'], $base_sid . ' resolves to ' . $row['structural_id'] );
	check( null !== $repository->find_section( $by_sid['structural_id'] ), $row['structural_id'] . ' exists' );
	$by_cid = $repository->resolve_previous_edition( strtolower( $row['base_content_id'] ) );
	check( null !== $by_cid && true === $by_cid['content_match'], $row['base_content_id'] . ' (any case) matches the published hash' );
	$statuses[ $row['status'] ] = ( $statuses[ $row['status'] ] ?? 0 ) + 1;
}

// Forged and foreign IDs fail closed.
$first = array_key_first( $map );
check( null === $repository->resolve_previous_edition( $first . '-00000000' ), 'a previous-edition content ID with the wrong hash is rejected' );
check( null === $repository->resolve_previous_edition( str_replace( $base, '2019E9', $first ) ), 'an edition that is neither current nor previous is rejected' );
check( null === $repository->resolve_previous_edition( preg_replace( '/S\d{2}$/', 'S99', $first ) ), 'a position this edition does not have is rejected' );
check( null === $repository->resolve_previous_edition( 'KDJ-AI-' . $base . '-P01-C01' ), 'a part/chapter prefix is not a section' );
check( null === $repository->resolve_previous_edition( '../../etc/passwd' ), 'garbage is rejected' );
$current_first = $map[ $first ]['structural_id'];
check( null === $repository->resolve_previous_edition( $current_first ), 'a current-edition ID is not a previous-edition ID' );

// The what's-new facts add up: every section is either carried over or new.
$changes  = $repository->edition_changes();
$sections = 0;
foreach ( $repository->parts() as $part ) {
	foreach ( $part['chapters'] as $chapter ) {
		$sections += count( $chapter['sections'] );
	}
}
check( null !== $changes && $base === $changes['base_edition_id'], 'edition_changes names the previous edition' );
check( $changes['added'] + $changes['corrected'] + $changes['unchanged'] === $sections, 'added + corrected + unchanged = all sections' );
check( count( $map ) === $changes['corrected'] + $changes['unchanged'], 'every mapped section is counted once' );
check( ( $statuses['corrected_against_supplied_standard'] ?? 0 ) === $changes['corrected'], 'corrected count comes from the map' );
check( array() !== $changes['new_chapters'], 'the edition has new chapters' );
$mapped_targets = array_flip( array_column( $map, 'structural_id' ) );
foreach ( $changes['new_chapters'] as $new ) {
	$chapter = $repository->find_chapter( $new['part_id'], $new['chapter_id'] );
	check( null !== $chapter, $new['chapter_id'] . ' exists in ' . $new['part_id'] );
	foreach ( $chapter['sections'] as $section ) {
		check( ! isset( $mapped_targets[ $section['structural_id'] ] ), $new['chapter_id'] . ' has no carried-over section' );
	}
}

echo 'PASS ai-book-edition: ' . count( $map ) . " previous-edition sections ({$base}) resolve by structural and content ID, forged/foreign/garbage IDs rejected, what's new = " . count( $changes['new_chapters'] ) . ' new chapters, ' . $changes['added'] . ' new and ' . $changes['corrected'] . " corrected sections ({$checks} checks)\n";
