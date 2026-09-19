<?php
define( 'KBK_TESTING', true );
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-artifacts.php';

$fixtures   = __DIR__ . '/fixtures/ai-book/';
$book       = KBK_AI_Book_Artifacts::load_json_file( $fixtures . 'book.json' );
$content_ids = KBK_AI_Book_Artifacts::load_json_file( $fixtures . 'content_ids.json' );
$citations  = KBK_AI_Book_Artifacts::load_json_file( $fixtures . 'citations.json' );

if ( true !== KBK_AI_Book_Artifacts::validate_bundle( $book, $content_ids, $citations ) ) {
	fwrite( STDERR, "FAIL: valid fixture bundle was rejected\n" );
	exit( 1 );
}

$failures = array(
	'unknown origin' => static function () use ( $book ) {
		$invalid = $book;
		$invalid['parts'][0]['chapters'][0]['sections'][0]['origin'] = 'generated_guess';
		KBK_AI_Book_Artifacts::validate_book( $invalid );
	},
	'unit count mismatch' => static function () use ( $book ) {
		$invalid = $book;
		$invalid['units'] = 2;
		KBK_AI_Book_Artifacts::validate_book( $invalid );
	},
	'foreign canonical URL' => static function () use ( $citations ) {
		$invalid = $citations;
		$invalid['registry']['KDJ-AI-2026E1-P01-C01-S01']['canonical_url'] = 'https://example.com/fa/ai-book/#KDJ-AI-2026E1-P01-C01-S01';
		KBK_AI_Book_Artifacts::validate_citations( $invalid );
	},
	'unknown citation content ID' => static function () use ( $book, $content_ids, $citations ) {
		$invalid = $citations;
		$invalid['registry']['KDJ-AI-2026E1-P01-C01-S01']['content_id'] = 'KDJ-AI-2026E1-P01-C01-S01-FFFFFFFF';
		KBK_AI_Book_Artifacts::validate_bundle( $book, $content_ids, $invalid );
	},
);

foreach ( $failures as $label => $case ) {
	$rejected = false;
	try {
		$case();
	} catch ( UnexpectedValueException $error ) {
		$rejected = true;
	}
	if ( ! $rejected ) {
		fwrite( STDERR, 'FAIL: invalid case accepted: ' . $label . "\n" );
		exit( 1 );
	}
}

echo 'PASS ai-book-artifacts: valid bundle accepted; ' . count( $failures ) . " invalid contracts rejected\n";
