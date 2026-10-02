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
$by_structure  = $repository->find_section( $structural_id );
$content_id    = $by_structure['content_id'] ?? '';
$by_content    = $repository->find_section( $content_id );
if ( null === $by_structure || '' === $content_id || $by_structure !== $by_content || 0 !== strpos( $content_id, $structural_id . '-' ) ) {
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

// Chapter navigation crosses part boundaries in whole-book order.
$boundary = $repository->adjacent_chapters( 'P01', 'C06' );
if ( null === $boundary['next'] || 'C07' !== $boundary['next']['chapter_id'] || 'P02' !== $boundary['next']['part_id'] || 'C05' !== $boundary['prev']['chapter_id'] || 'P01' !== $boundary['prev']['part_id'] ) {
	fwrite( STDERR, "FAIL adjacent_chapters must cross from the end of P01 into P02\n" );
	exit( 1 );
}
$back = $repository->adjacent_chapters( 'P02', 'C07' );
if ( null === $back['prev'] || 'C06' !== $back['prev']['chapter_id'] || 'P01' !== $back['prev']['part_id'] ) {
	fwrite( STDERR, "FAIL adjacent_chapters must step back from P02 into the end of P01\n" );
	exit( 1 );
}
$all   = $repository->parts();
$lastP = end( $all );
$lastC = end( $lastP['chapters'] );
if ( null !== $repository->adjacent_chapters( $lastP['part_id'], $lastC['chapter_id'] )['next'] ) {
	fwrite( STDERR, "FAIL the last chapter of the book has no next\n" );
	exit( 1 );
}

// /ai-book/verify/ answers from the release manifest and the content registry,
// exact matches only.
$release = $repository->release();
if ( null === $release || '2026E1' !== $release['edition_id'] || 1 !== preg_match( '/^[0-9a-f]{64}$/', $release['merkle_root'] ) || ! isset( $release['artifacts']['06_book_fa.pdf'] ) ) {
	fwrite( STDERR, "FAIL release() must expose the validated manifest facts\n" );
	exit( 1 );
}
$hit = $repository->verify( strtolower( $content_id ) );
if ( null === $hit || 'content' !== $hit['kind'] || $content_id !== $hit['content_id'] || $structural_id !== $hit['structural_id'] || null === $hit['section'] ) {
	fwrite( STDERR, "FAIL verify() must find a content ID regardless of case\n" );
	exit( 1 );
}
$by_struct = $repository->verify( $structural_id );
$by_hash   = $repository->verify( strtoupper( $hit['sha256'] ) );
if ( null === $by_struct || $content_id !== $by_struct['content_id'] || null === $by_hash || $content_id !== $by_hash['content_id'] ) {
	fwrite( STDERR, "FAIL verify() must find the same entry by structural ID and by its SHA-256\n" );
	exit( 1 );
}
$pdf_hash = $release['artifacts']['06_book_fa.pdf']['sha256'];
$file_hit = $repository->verify( $pdf_hash );
if ( null === $file_hit || 'artifact' !== $file_hit['kind'] || '06_book_fa.pdf' !== $file_hit['name'] ) {
	fwrite( STDERR, "FAIL verify() must match a release file by SHA-256\n" );
	exit( 1 );
}
foreach ( array( '', 'KDJ-AI-2026E1-P01-C01-S01-00000000', 'KDJ-AI-2026E1-P01-C01', str_repeat( '0', 64 ), substr( $pdf_hash, 0, 63 ), str_repeat( 'A', 81 ) ) as $miss ) {
	if ( null !== $repository->verify( $miss ) ) {
		fwrite( STDERR, "FAIL verify() must not report a near miss as found: $miss\n" );
		exit( 1 );
	}
}

echo "PASS ai-book-repository: validated bundle, summary, structural/content lookup, citation lookup, P06 slug-collision fail-closed, adjacent_chapters and release/verify lookup\n";
