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

echo "PASS ai-book-repository: validated bundle, summary, structural/content lookup and citation lookup\n";
