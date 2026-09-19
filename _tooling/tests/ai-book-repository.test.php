<?php
define( 'KBK_TESTING', true );
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-artifacts.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-repository.php';

$root = $argv[1] ?? '';
if ( '' === $root ) {
	fwrite( STDERR, "Usage: php ai-book-repository.test.php /path/to/AiBook\n" );
	exit( 2 );
}

$repository = new KBK_AI_Book_Repository( $root );
$summary    = $repository->summary();
$expected   = array( 'edition_id' => '2026E1', 'locale' => 'fa', 'parts' => 7, 'chapters' => 53, 'sections' => 479 );
foreach ( $expected as $key => $value ) {
	if ( $summary[ $key ] !== $value ) {
		fwrite( STDERR, 'FAIL summary.' . $key . "\n" );
		exit( 1 );
	}
}

$structural_id = 'KDJ-AI-2026E1-P01-C01-S01';
$content_id    = 'KDJ-AI-2026E1-P01-C01-S01-04D3A306';
$by_structure  = $repository->find_section( $structural_id );
$by_content    = $repository->find_section( $content_id );
if ( null === $by_structure || $by_structure !== $by_content || $content_id !== $by_structure['content_id'] ) {
	fwrite( STDERR, "FAIL section lookup\n" );
	exit( 1 );
}

$citation = $repository->find_citation( $content_id );
if ( null === $citation || $citation['content_id'] !== $content_id ) {
	fwrite( STDERR, "FAIL citation lookup\n" );
	exit( 1 );
}

if ( null !== $repository->find_part( 'missing' ) || null !== $repository->find_chapter( 'P01', 'missing' ) || null !== $repository->find_section( 'missing' ) ) {
	fwrite( STDERR, "FAIL unknown lookup must return null\n" );
	exit( 1 );
}

// P06 has three chapters (C43, C44, C45) that share one truncated slug. A
// stable chapter_id must still resolve; the ambiguous slug must fail closed
// to null rather than silently returning one of the three chapters.
$p06_by_id = $repository->find_chapter( 'P06', 'C44' );
if ( null === $p06_by_id || 'C44' !== $p06_by_id['chapter_id'] ) {
	fwrite( STDERR, "FAIL P06 chapter_id lookup must resolve\n" );
	exit( 1 );
}
$p06_slug         = $p06_by_id['slug'];
$p06_by_ambiguous = $repository->find_chapter( 'P06', $p06_slug );
if ( null !== $p06_by_ambiguous ) {
	fwrite( STDERR, "FAIL ambiguous P06 slug must fail closed to null\n" );
	exit( 1 );
}

$adjacent = $repository->adjacent_chapters( 'P06', 'C44' );
if ( null === $adjacent['prev'] || 'C43' !== $adjacent['prev']['chapter_id'] || null === $adjacent['next'] || 'C45' !== $adjacent['next']['chapter_id'] ) {
	fwrite( STDERR, "FAIL adjacent_chapters must return the true document-order neighbors\n" );
	exit( 1 );
}
$first_chapter_adjacent = $repository->adjacent_chapters( 'P01', 'C01' );
if ( null !== $first_chapter_adjacent['prev'] ) {
	fwrite( STDERR, "FAIL first chapter in a part must have no previous neighbor\n" );
	exit( 1 );
}
if ( null !== $repository->adjacent_chapters( 'missing', 'C01' )['next'] ) {
	fwrite( STDERR, "FAIL adjacent_chapters on an unknown part must return nulls\n" );
	exit( 1 );
}

echo "PASS ai-book-repository: validated bundle, summary, structural/content lookup, citation lookup, P06 slug-collision fail-closed and adjacent_chapters\n";
