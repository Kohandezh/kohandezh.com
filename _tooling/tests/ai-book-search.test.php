<?php
/**
 * Isolated test for the Search subsystem (KBK_AI_Book_Search + validators).
 * Exercises real canonical data for the query paths, and small inline
 * fixtures for the validator boundary (valid + rejected shapes).
 */
define( 'ABSPATH', __DIR__ );
define( 'KBK_FEATURE_AI_BOOK', true );
define( 'KBK_PLUGIN_FILE', __FILE__ );
define( 'KBK_VERSION', 'test' );

$root = $argv[1] ?? '';
if ( '' === $root ) {
	fwrite( STDERR, "Usage: php ai-book-search.test.php /path/to/AiBook\n" );
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
	fail( 'engine must load real canonical artifacts cleanly' );
}

// ---------------------------------------------------------------------------
// 1. Persian query (body/keyword term) returns ranked section results with
//    bounded highlighted snippets, hierarchy, provenance and IDs.
// ---------------------------------------------------------------------------
$result = $search->search( 'سوگیری' );
if ( 'READY' !== $result['status'] || 0 === $result['total'] || empty( $result['results'] ) ) {
	fail( 'Persian query سوگیری must return results' );
}
$section_results = 0;
foreach ( $result['results'] as $item ) {
	if ( 'section' !== $item['type'] ) {
		continue;
	}
	$section_results++;
	if ( ! isset( $item['part']['id'], $item['chapter']['id'], $item['section']['structural_id'], $item['section']['content_id'] ) ) {
		fail( 'section result must carry part/chapter/section identifiers' );
	}
	if ( ! preg_match( '/^KDJ-AI-\d{4}E\d+-P\d{2}-C\d{2}-S\d{2}$/', $item['section']['structural_id'] ) ) {
		fail( 'section result structural ID shape' );
	}
	if ( ! in_array( $item['origin'], KBK_AI_Book_Artifacts::ALLOWED_ORIGINS, true ) ) {
		fail( 'section result must preserve its origin label' );
	}
	$has_mark = false;
	foreach ( $item['snippet_segments'] as $segment ) {
		if ( ! isset( $segment['text'], $segment['mark'] ) ) {
			fail( 'snippet segments must be text/mark pairs' );
		}
		if ( $segment['mark'] ) {
			$has_mark = true;
		}
	}
	if ( ! $has_mark ) {
		fail( 'Persian query must produce a highlighted match inside the snippet' );
	}
	break;
}
if ( 0 === $section_results ) {
	fail( 'Persian query must return at least one section result' );
}

// 2. Persian multi-token AND query («مدیریت ریسک»).
$multi = $search->search( 'مدیریت ریسک' );
if ( 0 === $multi['total'] ) {
	fail( 'Persian multi-token query must return results' );
}
if ( 2 !== $multi['tokens'] ) {
	fail( 'multi-token query must report two tokens' );
}

// 3. English term query.
$english = $search->search( 'Artificial Intelligence' );
if ( 0 === $english['total'] ) {
	fail( 'English term query must return results' );
}

// 4. Acronym query — the matched section/card evidence must reference RMF.
$acronym = $search->search( 'RMF' );
if ( 0 === $acronym['total'] ) {
	fail( 'acronym query RMF must return results' );
}
$rmf_evidence = false;
foreach ( $acronym['results'] as $item ) {
	$serialized = wp_json_encode( $item );
	if ( false !== stripos( (string) $serialized, 'rmf' ) ) {
		$rmf_evidence = true;
		break;
	}
}
if ( ! $rmf_evidence ) {
	fail( 'acronym results must visibly reference RMF (docs/acronym/title/glossary)' );
}

// 5. Glossary query «ریسک» returns a glossary card with approved aliases and
//    WITHOUT any reviewer metadata or rejected aliases.
$gloss = $search->search( 'ریسک' );
$glossary_hit = null;
foreach ( $gloss['results'] as $item ) {
	if ( 'glossary' === $item['type'] && 'RISK' === $item['concept_id'] ) {
		$glossary_hit = $item;
		break;
	}
}
if ( null === $glossary_hit ) {
	fail( 'glossary query ریسک must surface the RISK term' );
}
foreach ( array( 'reviewer', 'review_mode', 'rationale', 'rejected_fa' ) as $secret ) {
	if ( array_key_exists( $secret, $glossary_hit ) || false !== strpos( (string) wp_json_encode( $glossary_hit ), '"' . $secret . '"' ) ) {
		fail( "glossary result must never expose {$secret}" );
	}
}

// 6. Source-document-ID query.
$docs = $search->search( 'AI-100-1' );
if ( 0 === $docs['total'] ) {
	fail( 'source document ID query must return results' );
}

// 7. Content ID query: full structural ID resolves exactly; content ID (with
//    hash) resolves exactly; case-insensitive; prefix returns bounded P06 set.
$sample = $repository->find_part( 'P03' )['chapters'][2]['sections'][0];
$by_structural = $search->search( $sample['structural_id'] );
if ( 1 !== $by_structural['total'] || $by_structural['results'][0]['section']['structural_id'] !== $sample['structural_id'] ) {
	fail( 'full structural ID query must resolve exactly one section' );
}
$by_content = $search->search( strtolower( $sample['content_id'] ) );
if ( 1 !== $by_content['total'] || $by_content['results'][0]['section']['content_id'] !== $sample['content_id'] ) {
	fail( 'full content ID query must resolve exactly one section, case-insensitively' );
}
$by_prefix = $search->search( 'kdj-ai-2026e1-p06' );
if ( 0 === $by_prefix['total'] ) {
	fail( 'ID prefix query must return the P06 sections' );
}
foreach ( $by_prefix['results'] as $item ) {
	if ( 0 !== strpos( $item['section']['structural_id'], 'KDJ-AI-2026E1-P06' ) ) {
		fail( 'ID prefix results must all belong to the requested part' );
	}
}
if ( $by_prefix['total'] < 3 ) {
	fail( 'P06 prefix should cover the collision cluster chapters at least' );
}

// 8. Part and chapter title queries produce dedicated navigational results.
$part_title = $search->search( 'مبانی و زبان مشترک هوش مصنوعی' );
$types = array_column( $part_title['results'], 'type' );
if ( ! in_array( 'part', $types, true ) || 'part' !== $part_title['results'][0]['type'] ) {
	fail( 'part title query must rank the part card first' );
}
if ( 'P01' !== $part_title['results'][0]['part']['id'] ) {
	fail( 'part card must reference the matching part ID' );
}
$chapter_title = $search->search( 'بخش نخست — مبانی و زبان مشترک هوش مصنوعی' );
$types = array_column( $chapter_title['results'], 'type' );
if ( ! in_array( 'chapter', $types, true ) ) {
	fail( 'chapter title query must surface a chapter card' );
}

// 9. No-result query: honest zero, no guessing, no exception.
$none = $search->search( 'غغبغبغب غچپژچپژ' );
if ( 0 !== $none['total'] || array() !== $none['results'] || null === $none['query'] ) {
	fail( 'no-result query must be an honest empty state' );
}

// 10. Safe malformed inputs: control characters, tags, oversized, too short.
$hostile = array(
	"\x00\x01\x02control",
	'<script>alert(1)</script>',
	str_repeat( 'ریسک', 500 ),
	'ر',
	'   ',
	"ریسک\x7F",
);
foreach ( $hostile as $query ) {
	$outcome = $search->search( $query );
	if ( ! isset( $outcome['results'], $outcome['total'], $outcome['status'] ) || count( $outcome['results'] ) > $outcome['limit'] ) {
		fail( 'malformed query must never break the result contract' );
	}
	if ( false !== strpos( (string) wp_json_encode( $outcome['results'] ), "\x00" ) ) {
		fail( 'malformed query must never leak control payloads into results' );
	}
}
if ( 0 !== $search->search( '<script>alert(1)</script>' )['total'] ) {
	fail( 'script-tag query must not match anything' );
}
$parsed = KBK_AI_Book_Search::parse_query( "  \xE2\x80\x8C\x01ریسک\x00 " );
if ( false !== strpos( $parsed['display'], "\0" ) || false !== strpos( $parsed['display'], "\x01" ) ) {
	fail( 'parse_query must strip control characters from the display string' );
}

// 11. Bounded output: limit respected; results stay bounded snippets (no
//     full-corpus leakage through section results).
$bounded = $search->search( 'هوش مصنوعی', 5 );
// All pages must preserve every ranked match, including the promoted glossary.
foreach ( array( 'هوش مصنوعی', 'KDJ-AI-2026E1-P02' ) as $paged_query ) {
	$first_page = $search->search( $paged_query, 5 );
	$seen = array();
	for ( $page = 1; $page <= $first_page['pages']; $page++ ) {
		$batch = $search->search( $paged_query, 5, $page );
		foreach ( $batch['results'] as $item ) {
			$key = hash( 'sha256', json_encode( $item ) );
			if ( isset( $seen[ $key ] ) ) { fail( 'pagination must not repeat results' ); }
			$seen[ $key ] = true;
		}
	}
	if ( count( $seen ) !== $first_page['total'] ) { fail( 'pagination must not lose results' ); }
	if ( $search->search( $paged_query, 5, PHP_INT_MAX )['page'] !== $first_page['pages'] || 1 !== $search->search( $paged_query, 5, -1 )['page'] ) {
		fail( 'out-of-range pages must clamp safely' );
	}
}
if ( count( $bounded['results'] ) > 5 || ! $bounded['truncated'] ) {
	fail( 'limit must bound results and set the truncated flag' );
}
foreach ( $bounded['results'] as $item ) {
	if ( 'section' !== $item['type'] ) {
		continue;
	}
	$plain = '';
	foreach ( $item['snippet_segments'] as $segment ) {
		$plain .= $segment['text'];
	}
	if ( mb_strlen( $plain ) > 2000 ) {
		fail( 'section snippets must stay bounded (pipeline snippet, not body text)' );
	}
	if ( array_key_exists( 'fa_text', $item ) ) {
		fail( 'section results must never carry the full body text' );
	}
}

// 12. Stable deep links: every result's identifiers resolve through the
//     repository, exactly like Reader routing does.
foreach ( $bounded['results'] as $item ) {
	if ( 'section' !== $item['type'] ) {
		continue;
	}
	$linked_part = $repository->find_part( $item['part']['id'] );
	if ( null === $linked_part ) {
		fail( 'deep link part must resolve' );
	}
	$linked_chapter = $repository->find_chapter( $item['part']['id'], $item['chapter']['id'] );
	if ( null === $linked_chapter || $linked_chapter['title_fa'] !== $item['chapter']['title_fa'] ) {
		fail( 'deep link chapter must resolve with the displayed title' );
	}
	$linked_section = $repository->find_section( $item['section']['structural_id'] );
	if ( null === $linked_section || $linked_section['content_id'] !== $item['section']['content_id'] ) {
		fail( 'deep link section must resolve to the same content ID' );
	}
	if ( false === strpos( $item['canonical_url'], '/fa/ai-book/' ) ) {
		fail( 'canonical citation URL namespace must be preserved' );
	}
}

// 13. Glossary deep-link target resolves via find_term with the same safe shape.
$term = $search->find_term( 'risk' );
if ( null === $term || 'ریسک' !== $term['title'] || array_key_exists( 'reviewer', $term ) ) {
	fail( 'find_term must resolve case-insensitively with the safe view model' );
}
if ( null !== $search->find_term( 'NOT_A_REAL_CONCEPT' ) ) {
	fail( 'unknown concept must fail closed' );
}

// 14. Corrupted artifacts fail closed with ARTIFACT_INVALID (fixture-based).
$tmp_dir = sys_get_temp_dir() . '/kbk-search-fixture-' . getmypid();
mkdir( $tmp_dir . '/html/search', 0777, true );
mkdir( $tmp_dir . '/release', 0777, true );
$bad_index = array(
	'edition' => '2026E1',
	'count'   => 1,
	'entries' => array( array( 'id' => 'not-an-id', 'structural_id' => 'nope', 'url' => 'nope', 'title_fa' => 'x', 'part' => 'p', 'chapter' => 'c', 'origin' => 'source_translation', 'docs' => array(), 'keywords_fa' => array(), 'keywords_en' => array(), 'acronyms' => array(), 'snippet' => 's' ) ),
);
file_put_contents( $tmp_dir . '/html/search/index.json', (string) wp_json_encode( $bad_index ) );
file_put_contents( $tmp_dir . '/release/09_glossary.json', '{"glossary_version":"fa-v1","terms":{}}' );
$broken = new KBK_AI_Book_Search( $repository, $tmp_dir . '/html/search/index.json', $tmp_dir . '/release/09_glossary.json' );
$broken_outcome = $broken->search( 'ریسک' );
if ( KBK_AI_Book_Search::STATUS_ARTIFACT_INVALID !== $broken_outcome['status'] || array() !== $broken_outcome['results'] ) {
	fail( 'corrupted index must fail closed with ARTIFACT_INVALID and no results' );
}

// Validator fixtures: valid minimal pair passes; each tampered variant throws.
$valid_index = array(
	'edition' => '2026E1',
	'count'   => 1,
	'entries' => array( array(
		'id'             => 'KDJ-AI-2026E1-P01-C01-S01-04D3A306',
		'structural_id'  => 'KDJ-AI-2026E1-P01-C01-S01',
		'url'            => 'https://kohandezh.com/fa/ai-book/foundations/foundations-reading-guide/#KDJ-AI-2026E1-P01-C01-S01',
		'title_fa'       => 'عنوان',
		'title_en'       => 'Title',
		'part'           => 'بخش',
		'chapter'        => 'فصل',
		'origin'         => 'source_translation',
		'docs'           => array( 'NIST-AI-100-1' ),
		'keywords_fa'    => array( 'واژه' ),
		'keywords_en'    => array(),
		'acronyms'       => array( 'AI' ),
		'snippet'        => 'گزارهٔ کوتاه.',
	) ),
);
$valid_glossary = array(
	'glossary_version' => 'fa-v1',
	'terms'            => array( 'risk' => array(
		'concept_id'       => 'RISK',
		'english'          => 'Risk',
		'preferred_fa'     => 'ریسک',
		'acronym'          => '',
		'alternatives_fa'  => array( 'خطر' ),
		'definition_fa'    => 'تعریف.',
		'definition_en'    => 'Definition.',
		'domain'           => 'risk',
		'status'           => 'approved',
		'source_documents' => array( 'NIST-AI-100-1' ),
	) ),
);
KBK_AI_Book_Artifacts::validate_search_index( $valid_index );
KBK_AI_Book_Artifacts::validate_glossary( $valid_glossary );

$invalid_cases = array();
$case = $valid_index; $case['entries'][0]['id'] = 'WRONG';                       $invalid_cases[] = array( $case, $valid_glossary );
$case = $valid_index; $case['entries'][0]['origin'] = 'mystery';                 $invalid_cases[] = array( $case, $valid_glossary );
$case = $valid_index; $case['count'] = 2;                                        $invalid_cases[] = array( $case, $valid_glossary );
$case = $valid_index; $case['entries'][0]['snippet'] = str_repeat( 'x', 3000 );  $invalid_cases[] = array( $case, $valid_glossary );
$case = $valid_index; unset( $case['entries'][0]['keywords_fa'] );               $invalid_cases[] = array( $case, $valid_glossary );
$case = $valid_index; $case['entries'][0]['url'] = 'https://evil.example/#KDJ-AI-2026E1-P01-C01-S01'; $invalid_cases[] = array( $case, $valid_glossary );
$case = $valid_glossary; $case['terms']['risk']['status'] = 'secret';            $invalid_cases[] = array( $valid_index, $case );
$case = $valid_glossary; $case['terms']['risk']['preferred_fa'] = '';            $invalid_cases[] = array( $valid_index, $case );
foreach ( $invalid_cases as $i => list( $index_case, $glossary_case ) ) {
	try {
		KBK_AI_Book_Artifacts::validate_search_index( $index_case );
		KBK_AI_Book_Artifacts::validate_glossary( $glossary_case );
		fail( "tampered fixture #{$i} must be rejected by the validator" );
	} catch ( UnexpectedValueException $expected ) {
		// fail closed as designed
	}
}

// 15. WordPress route layer: current_search() honors the allowlisted query var
//     and stays safe without configuration.
$GLOBALS['kbk_test_query'] = array( KBK_AI_Book::SEARCH_QUERY_VAR => '  سوگیری  ' );
$route_search = KBK_AI_Book::current_search();
if ( 'ریسک' === $route_search['query'] || null === $route_search['query'] || 'سوگیری' !== $route_search['query'] ) {
	fail( 'route layer must pass the trimmed query through and resolve it' );
}
$GLOBALS['kbk_test_query'] = array( KBK_AI_Book::SEARCH_QUERY_VAR => 'حاکمیت', KBK_AI_Book::PAGE_QUERY_VAR => '2' );
if ( 2 !== KBK_AI_Book::current_search()['page'] ) { fail( 'search route must honor page query' ); }
$GLOBALS['kbk_test_query'][ KBK_AI_Book::PAGE_QUERY_VAR ] = '2<script>';
if ( 1 !== KBK_AI_Book::current_search()['page'] ) { fail( 'search route must reject malformed page query' ); }
$GLOBALS['kbk_test_query'] = array( KBK_AI_Book::SEARCH_QUERY_VAR => "<script>x\x00</script>" );
$hostile_search = KBK_AI_Book::current_search();
if ( false !== strpos( (string) wp_json_encode( $hostile_search ), "\x00" ) ) {
	fail( 'route layer must strip control characters before search' );
}
$GLOBALS['kbk_test_query'] = array( KBK_AI_Book::CONCEPT_QUERY_VAR => 'risk); DROP TABLE x' );
if ( null !== KBK_AI_Book::requested_concept() ) {
	fail( 'concept allowlist must reject non-identifier shapes' );
}
$GLOBALS['kbk_test_query'] = array( KBK_AI_Book::CONCEPT_QUERY_VAR => 'artificial_intelligence' );
if ( 'ARTIFICIAL_INTELLIGENCE' !== strtoupper( (string) KBK_AI_Book::requested_concept() ) ) {
	fail( 'concept allowlist must accept identifier shapes' );
}

if ( is_dir( $tmp_dir ) ) {
	@unlink( $tmp_dir . '/html/search/index.json' );
	@unlink( $tmp_dir . '/release/09_glossary.json' );
	@rmdir( $tmp_dir . '/html/search' );
	@rmdir( $tmp_dir . '/html' );
	@rmdir( $tmp_dir . '/release' );
	@rmdir( $tmp_dir );
}

echo "PASS ai-book-search: Persian/English/acronym/glossary/docs queries, exact+prefix Content IDs, part/chapter cards, no-result and malformed safety, bounded snippets, repository-resolvable deep links, validator fixtures and route-layer hygiene\n";
