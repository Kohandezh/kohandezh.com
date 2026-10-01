<?php
/**
 * Isolated test for the Ask/RAG subsystem (KBK_AI_Book_Ask +
 * validate_rag_chunk + provider response contract): real canonical corpus
 * for retrieval and citations, inline fixtures for validators, corrupted
 * corpus fail-closed paths, and honest provider degradation.
 */
define( 'ABSPATH', __DIR__ );
define( 'KBK_FEATURE_AI_BOOK', true );
define( 'KBK_PLUGIN_FILE', __FILE__ );
define( 'KBK_VERSION', 'test' );

$root = $argv[1] ?? '';
if ( '' === $root ) {
	fwrite( STDERR, "Usage: php ai-book-ask.test.php /path/to/AiBook\n" );
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
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-catalog.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-graph.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-ask.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book.php';

function fail( string $message ) {
	fwrite( STDERR, "FAIL {$message}\n" );
	exit( 1 );
}

$repository = new KBK_AI_Book_Repository( $root );
$ask_no_provider = new KBK_AI_Book_Ask( $repository );
if ( KBK_AI_Book_Ask::STATUS_READY !== $ask_no_provider->status() ) {
	fail( 'ask corpus must load the real canonical artifact cleanly' );
}
if ( $ask_no_provider->provider_configured() ) {
	fail( 'no-provider construction must report the provider as unconfigured' );
}

// ---------------------------------------------------------------------------
// 1. Retrieval: Persian query returns bounded cited passages that resolve
//    through the repository, without any full-body leakage.
// ---------------------------------------------------------------------------
$result = $ask_no_provider->ask( 'مدیریت ریسک هوش مصنوعی' );
if ( 'READY' !== $result['status'] || array() === $result['retrieval'] ) {
	fail( 'Persian ask query must return retrieved passages' );
}
if ( 'PROVIDER_REQUIRED' !== $result['provider'] || null !== $result['answer'] ) {
	fail( 'without provider config the state must be an honest PROVIDER_REQUIRED' );
}
if ( 4 !== $result['tokens'] ) {
	fail( 'ask must report its token count' );
}
$allowed_keys = array( 'chunk_id', 'content_id', 'structural_id', 'label_fa', 'section_fa', 'part_id', 'chapter_id', 'origin', 'excerpt_segments', 'citation_url', 'source_documents', 'reader_resolvable' );
foreach ( $result['retrieval'] as $item ) {
	if ( array_keys( $item ) !== $allowed_keys ) {
		fail( 'retrieval models must carry exactly the safe key set' );
	}
	if ( mb_strlen( implode( '', array_map( static function ( $segment ) { return $segment['text']; }, $item['excerpt_segments'] ) ) ) > KBK_AI_Book_Ask::MAX_EXCERPT_CHARS + 3 ) {
		fail( 'excerpts must stay bounded' );
	}
	if ( ! in_array( $item['origin'], KBK_AI_Book_Artifacts::ALLOWED_ORIGINS, true ) ) {
		fail( 'retrieval must preserve content origin labels' );
	}
	if ( false === strpos( $item['citation_url'], '/fa/ai-book/' ) || false === strpos( $item['citation_url'], '#' . $item['structural_id'] ) ) {
		fail( 'citations must stay in the canonical namespace with their section fragment' );
	}
	if ( $item['reader_resolvable'] ) {
		$resolved = $repository->find_section( $item['structural_id'] );
		if ( null === $resolved || $resolved['content_id'] !== $item['content_id'] ) {
			fail( 'resolvable citations must resolve to the same section' );
		}
	}
	$serialized = (string) wp_json_encode( $item );
	if ( false !== strpos( $serialized, '"fa_text"' ) ) {
		fail( 'retrieval models must never carry the raw body text' );
	}
}
if ( count( $result['retrieval'] ) > KBK_AI_Book_Ask::MAX_RETRIEVAL ) {
	fail( 'retrieval must respect its cap' );
}

// 2. Honest empty states: too-short query and no-match query.
$short = $ask_no_provider->ask( 'ریسک' );
if ( array() !== $short['retrieval'] || null !== $short['answer'] ) {
	fail( 'single-token query must be an honest empty state' );
}
$none = $ask_no_provider->ask( 'غغبغبغب غچپژچپژ' );
if ( array() !== $none['retrieval'] ) {
	fail( 'no-match query must be an honest empty state' );
}
$empty = $ask_no_provider->ask( '   ' );
if ( null !== $empty['query'] || array() !== $empty['retrieval'] ) {
	fail( 'blank query must be an honest empty state' );
}

// 3. Hostile inputs stay safe.
foreach ( array( "\x00\x01ریسک\x02مدیریت", '<script>alert(1)</script> <b>x</b>', str_repeat( 'ریسک ', 100 ) ) as $hostile ) {
	$outcome = $ask_no_provider->ask( $hostile );
	if ( ! isset( $outcome['status'], $outcome['retrieval'] ) || count( $outcome['retrieval'] ) > KBK_AI_Book_Ask::MAX_RETRIEVAL ) {
		fail( 'hostile query must never break the ask contract' );
	}
	if ( false !== strpos( (string) wp_json_encode( $outcome ), "\x00" ) ) {
		fail( 'hostile query must never leak control characters' );
	}
}

// 4. Provider response contract: valid answers pass; every violation throws.
$allowed = array( 'KDJ-AI-2026E1-P01-C01-S01-04D3A306', 'KDJ-AI-2026E1-P01-C01-S02-9D7D396F' );
$valid_response = array(
	'answer' => 'بر پایهٔ متن کتاب، ریسک باید مدیریت شود.',
	'used'   => array( 'KDJ-AI-2026E1-P01-C01-S01-04D3A306' ),
	'model'  => 'test-model',
);
$normalized = KBK_AI_Book_Ask::validate_provider_response( $valid_response, $allowed );
if ( $normalized['text'] !== $valid_response['answer'] || array( 'KDJ-AI-2026E1-P01-C01-S01-04D3A306' ) !== $normalized['used'] ) {
	fail( 'valid provider response must normalize unchanged' );
}
$invalid_responses = array();
$case = $valid_response; $case['answer'] = '';                                            $invalid_responses[] = $case;
$case = $valid_response; $case['answer'] = str_repeat( 'م', 4001 );                       $invalid_responses[] = $case;
$case = $valid_response; $case['answer'] = "hidden\x00payload";                           $invalid_responses[] = $case;
$case = $valid_response; $case['used'] = array( 'KDJ-AI-2026E1-P99-C99-S99-00000000' );   $invalid_responses[] = $case;
$case = $valid_response; $case['used'] = 'not-a-list';                                    $invalid_responses[] = $case;
$case = $valid_response; $case['model'] = str_repeat( 'm', 81 );                          $invalid_responses[] = $case;
$case = $valid_response; unset( $case['used'] );                                          $invalid_responses[] = $case;
foreach ( $invalid_responses as $i => $response_case ) {
	try {
		KBK_AI_Book_Ask::validate_provider_response( $response_case, $allowed );
		fail( "invalid provider response #{$i} must be rejected" );
	} catch ( UnexpectedValueException $expected ) {
		// fail closed as designed
	}
}

// 5. RAG chunk validator fixtures.
$valid_chunk = array(
	'chunk_id'         => 'KDJ-AI-2026E1-P01-C01-S01-04D3A306#r01',
	'content_id'       => 'KDJ-AI-2026E1-P01-C01-S01-04D3A306',
	'structural_id'    => 'KDJ-AI-2026E1-P01-C01-S01',
	'language'         => 'fa',
	'origin'           => 'editorial_synthesis',
	'label_fa'         => 'برچسب',
	'fa_text'          => 'متن کوتاه.',
	'part_id'          => 'P01',
	'chapter_id'       => 'C01',
	'section_id'       => 'S01',
	'keywords_fa'      => array( 'واژه' ),
	'keywords_en'      => array(),
	'source_documents' => array( 'NIST-AI-100-1' ),
	'canonical_url'    => 'https://kohandezh.com/fa/ai-book/x/y/#KDJ-AI-2026E1-P01-C01-S01',
);
KBK_AI_Book_Artifacts::validate_rag_chunk( $valid_chunk, '2026E1' );
$invalid_chunks = array();
$case = $valid_chunk; $case['chunk_id'] = 'KDJ-AI-2026E1-P01-C01-S01-04D3A306';            $invalid_chunks[] = $case;
$case = $valid_chunk; $case['language'] = 'en';                                            $invalid_chunks[] = $case;
$case = $valid_chunk; $case['origin'] = 'mystery';                                         $invalid_chunks[] = $case;
$case = $valid_chunk; $case['fa_text'] = str_repeat( 'م', 5001 );                          $invalid_chunks[] = $case;
$case = $valid_chunk; $case['content_id'] = 'KDJ-AI-2025E1-P01-C01-S01-04D3A306';          $invalid_chunks[] = $case;
$case = $valid_chunk; $case['canonical_url'] = 'https://evil.example/fa/ai-book/#KDJ-AI-2026E1-P01-C01-S01'; $invalid_chunks[] = $case;
$case = $valid_chunk; $case['part_id'] = 'PX1';                                            $invalid_chunks[] = $case;
$case = $valid_chunk; $case['keywords_fa'] = array( 12 );                                  $invalid_chunks[] = $case;
foreach ( $invalid_chunks as $i => $chunk_case ) {
	try {
		KBK_AI_Book_Artifacts::validate_rag_chunk( $chunk_case, '2026E1' );
		fail( "tampered rag chunk #{$i} must be rejected" );
	} catch ( UnexpectedValueException $expected ) {
		// fail closed as designed
	}
}

// 6. Configured provider path: unreachable endpoint degrades honestly; an
//    invalid provider body is discarded without rendering.
$GLOBALS['kbk_ask_stub_responses'] = array();
function wp_remote_post( $url, $args ) {
	return array_shift( $GLOBALS['kbk_ask_stub_responses'] );
}
function wp_remote_retrieve_response_code( $response ) { return is_array( $response ) ? ( $response['code'] ?? 0 ) : 0; }
function wp_remote_retrieve_body( $response ) { return is_array( $response ) ? ( $response['body'] ?? '' ) : ''; }
function is_wp_error( $thing ) { return false; }

$ask_with_provider = new KBK_AI_Book_Ask( $repository, null, array(
	'endpoint' => 'https://ask.example.invalid/v1/chat',
	'api_key'  => 'test-only-key',
	'model'    => 'test-model',
) );
$GLOBALS['kbk_ask_stub_responses'] = array( array( 'code' => 503, 'body' => '' ) );
$unreachable = $ask_with_provider->ask( 'مدیریت ریسک هوش مصنوعی' );
if ( null !== $unreachable['answer'] || 'PROVIDER_UNAVAILABLE' !== $unreachable['provider_error'] || array() === $unreachable['retrieval'] ) {
	fail( 'unreachable provider must degrade honestly while retrieval still works' );
}
$GLOBALS['kbk_ask_stub_responses'] = array( array( 'code' => 200, 'body' => '{"answer":"پاسخ نامعتبر با استناد بیرونی","used":["KDJ-AI-2025E1-P01-C01-S01-AAAAAAAA"]}' ) );
$invalid_provider = $ask_with_provider->ask( 'مدیریت ریسک هوش مصنوعی' );
if ( null !== $invalid_provider['answer'] || 'PROVIDER_INVALID' !== $invalid_provider['provider_error'] ) {
	fail( 'provider answers citing non-offered IDs must be discarded' );
}
$GLOBALS['kbk_ask_stub_responses'] = array( array( 'code' => 200, 'body' => (string) wp_json_encode( array(
	'answer' => 'پاسخ معتبر بر پایهٔ متن.',
	'used'   => array( $unreachable['retrieval'][0]['content_id'] ),
	'model'  => 'test-model',
) ) ) );
$GLOBALS['kbk_test_query'] = array();
$valid_provider = $ask_with_provider->ask( 'مدیریت ریسک هوش مصنوعی' );
if ( null === $valid_provider['answer'] || 'پاسخ معتبر بر پایهٔ متن.' !== $valid_provider['answer']['text'] ) {
	fail( 'valid provider answers must render with citations' );
}
if ( array( $unreachable['retrieval'][0]['content_id'] ) !== $valid_provider['answer']['used'] ) {
	fail( 'valid provider answers must report their used citations' );
}

// 7. Corrupted corpus fails closed.
$tmp_dir = sys_get_temp_dir() . '/kbk-ask-fixture-' . getmypid();
mkdir( $tmp_dir, 0777, true );
file_put_contents( $tmp_dir . '/corpus.jsonl', "{broken line}\n" );
$broken_ask = new KBK_AI_Book_Ask( $repository, $tmp_dir . '/corpus.jsonl' );
$broken_outcome = $broken_ask->ask( 'مدیریت ریسک هوش مصنوعی' );
if ( KBK_AI_Book_Ask::STATUS_ARTIFACT_INVALID !== $broken_outcome['status'] || array() !== $broken_outcome['retrieval'] ) {
	fail( 'corrupted corpus must fail closed with ARTIFACT_INVALID' );
}
file_put_contents( $tmp_dir . '/corpus.jsonl', "\n\n" );
$empty_ask = new KBK_AI_Book_Ask( $repository, $tmp_dir . '/corpus.jsonl' );
if ( KBK_AI_Book_Ask::STATUS_ARTIFACT_INVALID !== $empty_ask->status() ) {
	fail( 'empty corpus must fail closed' );
}
@unlink( $tmp_dir . '/corpus.jsonl' );
@rmdir( $tmp_dir );

// 8. Route layer: ask view detection and honest no-config states.
$GLOBALS['kbk_test_query'] = array( 'kbk_ai_book' => 'ask' );
if ( 'ask' !== KBK_AI_Book::current_view() || ! KBK_AI_Book::is_request() ) {
	fail( 'ask route detection' );
}
if ( 'PROVIDER_REQUIRED' !== KBK_AI_Book::ask_status() ) {
	fail( 'ask with root but no provider must be PROVIDER_REQUIRED' );
}
$ask_state = KBK_AI_Book::current_ask();
if ( array() !== $ask_state['retrieval'] || null !== $ask_state['query'] ) {
	fail( 'route ask state without a query must be an honest empty state' );
}
$GLOBALS['kbk_test_query'] = array( 'kbk_ai_book' => 'ask', KBK_AI_Book::SEARCH_QUERY_VAR => 'مدیریت ریسک' );
$route_ask = KBK_AI_Book::current_ask();
if ( 'مدیریت ریسک' !== $route_ask['query'] || array() === $route_ask['retrieval'] ) {
	fail( 'route ask with a query must retrieve cited passages' );
}

echo "PASS ai-book-ask: bounded cited retrieval with repository resolution, honest provider degradation (unreachable/invalid/valid), provider response contract, rag chunk validator fixtures, corrupted corpus fail-closed and route-layer hygiene\n";
