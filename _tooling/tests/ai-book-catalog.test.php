<?php
/**
 * Isolated test for the Catalog subsystem (KBK_AI_Book_Catalog + validators).
 * Exercises real canonical data for the read models, and small inline
 * fixtures for the validator boundary (valid + rejected shapes) plus a
 * corrupted-artifact fail-closed path on a temporary artifact root.
 */
define( 'ABSPATH', __DIR__ );
define( 'KBK_FEATURE_AI_BOOK', true );
define( 'KBK_PLUGIN_FILE', __FILE__ );
define( 'KBK_VERSION', 'test' );

$root = $argv[1] ?? '';
if ( '' === $root ) {
	fwrite( STDERR, "Usage: php ai-book-catalog.test.php /path/to/AiBook\n" );
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
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book.php';

function fail( string $message ) {
	fwrite( STDERR, "FAIL {$message}\n" );
	exit( 1 );
}

$repository = new KBK_AI_Book_Repository( $root );
$catalog    = new KBK_AI_Book_Catalog( $repository );
if ( KBK_AI_Book_Catalog::STATUS_READY !== $catalog->status() ) {
	fail( 'catalog must load real canonical artifacts cleanly' );
}

// ---------------------------------------------------------------------------
// 1. Glossary terms: sorted by preferred_fa, safe key set only, uppercase IDs,
//    and never any reviewer metadata.
// ---------------------------------------------------------------------------
$terms = $catalog->glossary_terms();
if ( count( $terms ) < 50 ) {
	fail( 'glossary must expose the full approved term list' );
}
$safe_term_keys = array( 'concept_id', 'preferred_fa', 'english', 'acronym', 'status', 'domain' );
$previous_fa    = null;
foreach ( $terms as $term ) {
	if ( array_keys( $term ) !== $safe_term_keys ) {
		fail( 'glossary term must carry exactly the safe key set' );
	}
	if ( $term['concept_id'] !== strtoupper( $term['concept_id'] ) ) {
		fail( 'glossary concept IDs must be uppercase' );
	}
	if ( null !== $previous_fa && strcmp( $previous_fa, $term['preferred_fa'] ) > 0 ) {
		fail( 'glossary terms must be sorted by preferred_fa' );
	}
	$previous_fa = $term['preferred_fa'];
}
$serialized_terms = (string) wp_json_encode( $terms );
foreach ( array( 'reviewer', 'review_mode', 'rationale', 'rejected_fa' ) as $secret ) {
	if ( false !== strpos( $serialized_terms, '"' . $secret . '"' ) ) {
		fail( "glossary list must never expose {$secret}" );
	}
}

// 2. Sources: 18 validated documents, bibliography merged, real section counts.
$sources = $catalog->sources();
if ( 18 !== count( $sources ) ) {
	fail( 'sources must expose exactly the 18 audited documents' );
}
$total_sections = 0;
$known_keys     = array( 'doc_key', 'title', 'title_fa', 'subtitle_fa', 'subtitle', 'series_identifier', 'organization', 'publication_type', 'publication_status', 'publication_date', 'doi_url', 'pages', 'sha256', 'sections_count' );
$has_ai_100_1   = false;
foreach ( $sources as $source ) {
	if ( array_keys( $source ) !== $known_keys ) {
		fail( 'source model must carry exactly the merged key set' );
	}
	if ( ! preg_match( '/^[a-f0-9]{64}$/', $source['sha256'] ) || $source['pages'] < 1 ) {
		fail( 'source must carry a valid sha256 and page count' );
	}
	if ( '' !== $source['doi_url'] && 0 !== strpos( $source['doi_url'], 'https://' ) ) {
		fail( 'source DOI URLs must be https or empty' );
	}
	$total_sections += $source['sections_count'];
	if ( 'NIST-AI-100-1' === $source['doc_key'] ) {
		$has_ai_100_1 = true;
	}
}
if ( ! $has_ai_100_1 || 660 !== $total_sections ) {
	fail( 'source section counts must sum to the 660 mapped sections' );
}

// 2b. Persian publication titles: optional taxonomy/doc_titles_fa.json.
$titles_path = rtrim( $root, '/' ) . '/taxonomy/doc_titles_fa.json';
$titles_fa   = $repository->doc_titles_fa();
if ( is_file( $titles_path ) ) {
	$titles_raw = json_decode( (string) file_get_contents( $titles_path ), true );
	foreach ( $sources as $source ) {
		$want = trim( (string) ( $titles_raw[ $source['doc_key'] ]['title_fa'] ?? '' ) );
		if ( $want !== $source['title_fa'] ) {
			fail( 'source ' . $source['doc_key'] . ' must carry its Persian title from doc_titles_fa.json' );
		}
	}
	$publication = $catalog->entity( 'E:Publication:nist_ai_100_1' );
	if ( null === $publication || $publication['label_fa'] !== $titles_fa['NIST-AI-100-1']['title_fa'] || 'Artificial Intelligence Risk Management Framework (AI RMF 1.0)' !== $publication['label_en'] ) {
		fail( 'a Publication entity naming a source document must take its Persian title as label_fa, keeping the English label' );
	}
} elseif ( array() !== $titles_fa || '' !== $sources[0]['title_fa'] ) {
	fail( 'without doc_titles_fa.json every source keeps its English title only' );
}
$tmp_titles = sys_get_temp_dir() . '/kbk-doc-titles-' . getmypid();
@mkdir( $tmp_titles . '/taxonomy', 0777, true );
foreach ( array( 'master', 'provenance' ) as $dir ) {
	@mkdir( $tmp_titles . '/' . $dir, 0777, true );
}
foreach ( array( 'master/book.json', 'provenance/content_ids.json', 'provenance/citation-registry.json' ) as $file ) {
	copy( rtrim( $root, '/' ) . '/' . $file, $tmp_titles . '/' . $file );
}
file_put_contents( $tmp_titles . '/taxonomy/doc_titles_fa.json', '{"NIST-AI-100-1":{"title_fa":"عنوان"},"bad key":{"title_fa":"x"},"NIST-X":{"title_fa":""},"NIST-Y":"str"}' );
$probe = ( new KBK_AI_Book_Repository( $tmp_titles ) )->doc_titles_fa();
if ( array( 'NIST-AI-100-1' => array( 'title_fa' => 'عنوان', 'subtitle_fa' => '' ) ) !== $probe ) {
	fail( 'doc_titles_fa keeps only well-formed doc_key → non-empty title_fa entries' );
}
file_put_contents( $tmp_titles . '/taxonomy/doc_titles_fa.json', '{broken' );
if ( array() !== ( new KBK_AI_Book_Repository( $tmp_titles ) )->doc_titles_fa() ) {
	fail( 'a malformed doc_titles_fa.json is ignored, never fatal' );
}
foreach ( array( 'taxonomy/doc_titles_fa.json', 'master/book.json', 'provenance/content_ids.json', 'provenance/citation-registry.json' ) as $file ) {
	@unlink( $tmp_titles . '/' . $file );
}
foreach ( array( 'taxonomy', 'master', 'provenance', '' ) as $dir ) {
	@rmdir( rtrim( $tmp_titles . '/' . $dir, '/' ) );
}

// 3. Templates: 20 cards sorted by ID; detail lookup works, unknown fails closed.
$templates = $catalog->templates();
if ( 20 !== count( $templates ) ) {
	fail( 'catalog must expose all 20 editorial templates' );
}
$previous_id = null;
foreach ( $templates as $template_card ) {
	if ( null !== $previous_id && strcmp( $previous_id, $template_card['template_id'] ) >= 0 ) {
		fail( 'template cards must be sorted by template ID' );
	}
	$previous_id = $template_card['template_id'];
	if ( count( $template_card['fields'] ) < 1 || count( $template_card['derived_from'] ) > 3 ) {
		fail( 'template cards must carry fields and bounded derived_from list' );
	}
}
$detail = $catalog->template( 'TPL-P06-1' );
if ( null === $detail || $detail['template_id'] !== 'TPL-P06-1' || array() === $detail['fields'] ) {
	fail( 'template detail must resolve a known template ID with fields' );
}
foreach ( $detail['fields'] as $field ) {
	if ( ! isset( $field['name_fa'], $field['name_en'], $field['description_fa'], $field['example_fa'] ) ) {
		fail( 'template fields must carry the safe field key set' );
	}
}
if ( null !== $catalog->template( 'TPL-NOPE-99' ) ) {
	fail( 'unknown template ID must fail closed to null' );
}

// 4. Entity types + pagination: totals match the corpus; pages clamp.
$types = $catalog->entity_types();
if ( ( $types['Concept'] ?? 0 ) !== 696 ) {
	fail( 'entity type counts must match the corpus (Concept=696)' );
}
$entities = $catalog->entities();
if ( 3671 !== $entities['total'] || 60 !== count( $entities['items'] ) || $entities['page'] !== 1 ) {
	fail( 'unfiltered entity list must paginate the full corpus' );
}
if ( 60 !== KBK_AI_Book_Catalog::ENTITIES_PER_PAGE ) {
	fail( 'entities page size contract' );
}
$clamped = $catalog->entities( null, 9999 );
if ( $clamped['page'] !== $clamped['pages'] || array() === $clamped['items'] ) {
	fail( 'out-of-range page must clamp to the last honest page' );
}
$by_type = $catalog->entities( 'Concept' );
if ( 696 !== $by_type['total'] ) {
	fail( 'entity type filter must return the Concept subset' );
}
$empty_type = $catalog->entities( 'NoSuchType' );
if ( 0 !== $empty_type['total'] || array() !== $empty_type['items'] ) {
	fail( 'unknown entity type must be an honest empty page' );
}
if ( KBK_AI_Book_Catalog::STATUS_READY !== $catalog->status() ) {
	fail( 'empty filter pages must not corrupt the catalog status' );
}

// 5. Entity detail: bounded sections that resolve through the repository,
//    no raw mention list, honest totals.
$entity_line = null;
$handle = fopen( $root . '/knowledge/entities.jsonl', 'r' );
while ( false !== ( $line = fgets( $handle ) ) ) {
	$decoded = json_decode( $line, true );
	if ( isset( $decoded['type'], $decoded['entity_id'], $decoded['mentions'] ) && 'Concept' === $decoded['type'] && count( $decoded['mentions'] ) >= 15 ) {
		$entity_line = $decoded;
		break;
	}
}
fclose( $handle );
if ( null === $entity_line ) {
	fail( 'fixture: corpus must contain a Concept with many mentions' );
}
$detail_entity = $catalog->entity( $entity_line['entity_id'] );
if ( null === $detail_entity ) {
	fail( 'entity detail must resolve a real entity ID' );
}
if ( count( $detail_entity['sections'] ) > KBK_AI_Book_Catalog::MAX_ENTITY_MENTIONS ) {
	fail( 'entity detail must cap resolved sections' );
}
if ( count( $entity_line['mentions'] ) < 15 && $detail_entity['mentions_total'] !== count( $entity_line['mentions'] ) ) {
	fail( 'entity detail must report the honest total' );
}
if ( $detail_entity['mentions_total'] < 15 ) {
	fail( 'entity detail must report the full mention total beyond the cap' );
}
if ( array_key_exists( 'mentions', $detail_entity ) ) {
	fail( 'entity detail must never carry the raw mention list' );
}
foreach ( $detail_entity['sections'] as $mention ) {
	$resolved = $repository->find_section( $mention['structural_id'] );
	if ( null === $resolved || $resolved['content_id'] !== $mention['content_id'] || $resolved['title_fa'] !== $mention['title_fa'] ) {
		fail( 'entity mention links must resolve to the same Reader section' );
	}
}
if ( null !== $catalog->entity( 'E:Concept:does_not_exist' ) ) {
	fail( 'unknown entity must fail closed to null' );
}

// ---------------------------------------------------------------------------
// 6. Validator fixtures: valid minimal artifacts pass; tampered variants throw.
// ---------------------------------------------------------------------------
$edition = '2026E1';
$valid_sources = array(
	'unique_source_document_count' => 1,
	'documents'                    => array( array(
		'doc_key'   => 'NIST-AI-100-1',
		'filename'  => 'ai1001.pdf',
		'sha256'    => str_repeat( 'a', 64 ),
		'pages'     => 48,
		'title'     => 'Title',
	) ),
);
$valid_bibliography = array(
	'entries' => array( array(
		'doc_key'            => 'NIST-AI-100-1',
		'title'              => 'Title',
		'organization'       => 'NIST',
		'publication_type'   => 'report',
		'publication_status' => 'Final',
		'sha256'             => str_repeat( 'a', 64 ),
	) ),
);
$valid_source_map = array( array(
	'content_id'     => 'KDJ-AI-2026E1-P01-C01-S01-04D3A306',
	'structural_id'  => 'KDJ-AI-2026E1-P01-C01-S01',
	'origin'         => 'source_translation',
	'source_doc'     => 'NIST-AI-100-1',
) );
$valid_template = array(
	'template_id' => 'TPL-P06-01',
	'title_fa'    => 'عنوان',
	'origin'      => 'editorial_template_ir',
	'fields'      => array( array( 'name_fa' => 'ف', 'name_en' => 'F', 'description_fa' => 'ت', 'example_fa' => 'ن' ) ),
);
$valid_entity = array(
	'entity_id' => 'E:Concept:ai_system',
	'type'      => 'Concept',
	'labels'    => array( 'en' => 'AI system', 'fa' => 'سامانهٔ هوش مصنوعی' ),
	'origin'    => 'source',
	'documents' => array( 'NIST-AI-100-1' ),
	'mentions'  => array( 'KDJ-AI-2026E1-P01-C01-S01-04D3A306' ),
);
KBK_AI_Book_Artifacts::validate_sources_manifest( $valid_sources );
KBK_AI_Book_Artifacts::validate_bibliography( $valid_bibliography );
KBK_AI_Book_Artifacts::validate_source_map( $valid_source_map, $edition );
KBK_AI_Book_Artifacts::validate_template( $valid_template );
KBK_AI_Book_Artifacts::validate_entity( $valid_entity, $edition );

$invalid_cases = array(
	'sources duplicate doc_key' => function () use ( $valid_sources ) {
		$case = $valid_sources;
		$case['documents'][] = $case['documents'][0];
		$case['unique_source_document_count'] = 2;
		return $case;
	},
	'sources bad sha256' => function () use ( $valid_sources ) {
		$case = $valid_sources;
		$case['documents'][0]['sha256'] = 'zz';
		return $case;
	},
	'source map bad origin' => function () use ( $valid_source_map ) {
		$case = $valid_source_map;
		$case[0]['origin'] = 'mystery';
		return $case;
	},
	'source map wrong edition' => function () use ( $valid_source_map ) {
		$case = $valid_source_map;
		$case[0]['content_id'] = 'KDJ-AI-2025E1-P01-C01-S01-04D3A306';
		return $case;
	},
	'template bad origin' => function () use ( $valid_template ) {
		$case = $valid_template;
		$case['origin'] = 'random_blog';
		return $case;
	},
	'template bad id shape' => function () use ( $valid_template ) {
		$case = $valid_template;
		$case['template_id'] = 'TPL-P6-1';
		return $case;
	},
	'entity bad mention' => function () use ( $valid_entity ) {
		$case = $valid_entity;
		$case['mentions'] = array( 'NOT-A-CONTENT-ID' );
		return $case;
	},
	'entity bad origin' => function () use ( $valid_entity ) {
		$case = $valid_entity;
		$case['origin'] = 'guessed';
		return $case;
	},
	'entity bad id shape' => function () use ( $valid_entity ) {
		$case = $valid_entity;
		$case['entity_id'] = 'concept-ai-system';
		return $case;
	},
);
foreach ( $invalid_cases as $label => $builder ) {
	try {
		$case = $builder();
		if ( 0 === strpos( $label, 'sources' ) ) {
			KBK_AI_Book_Artifacts::validate_sources_manifest( $case );
		} elseif ( 0 === strpos( $label, 'bibliography' ) ) {
			KBK_AI_Book_Artifacts::validate_bibliography( $case );
		} elseif ( 0 === strpos( $label, 'source map' ) ) {
			KBK_AI_Book_Artifacts::validate_source_map( $case, $edition );
		} elseif ( 0 === strpos( $label, 'template' ) ) {
			KBK_AI_Book_Artifacts::validate_template( $case );
		} else {
			KBK_AI_Book_Artifacts::validate_entity( $case, $edition );
		}
		fail( "tampered fixture '{$label}' must be rejected by the validator" );
	} catch ( UnexpectedValueException $expected ) {
		// fail closed as designed
	}
}

// 7. Corrupted artifacts on a real root fail the whole catalog closed.
$tmp_root = sys_get_temp_dir() . '/kbk-catalog-fixture-' . getmypid();
foreach ( array( 'manifests', 'master', 'provenance', 'release', 'templates', 'knowledge', 'html/search' ) as $dir ) {
	mkdir( $tmp_root . '/' . $dir, 0777, true );
}
foreach ( array(
	'master/book.json'            => 'master/book.json',
	'master/bibliography.json'    => 'master/bibliography.json',
	'master/source_map.json'      => 'master/source_map.json',
	'provenance/content_ids.json' => 'provenance/content_ids.json',
	'provenance/citation-registry.json' => 'provenance/citation-registry.json',
	'manifests/sources.json'      => 'manifests/sources.json',
	'release/09_glossary.json'    => 'release/09_glossary.json',
	'knowledge/entities.jsonl'    => 'knowledge/entities.jsonl',
) as $target => $origin ) {
	copy( $root . '/' . $origin, $tmp_root . '/' . $target );
}
foreach ( glob( $root . '/templates/TPL-*.json' ) as $template_file ) {
	copy( $template_file, $tmp_root . '/templates/' . basename( $template_file ) );
}
$fixture_repository = new KBK_AI_Book_Repository( $tmp_root );
$tampered = array(
	'sources manifest'   => array( 'manifests/sources.json', function ( $data ) { $data['documents'][0]['sha256'] = 'nothex'; return $data; } ),
	'bibliography'       => array( 'master/bibliography.json', function ( $data ) { $data['entries'][0]['doc_key'] = 'GHOST-DOC-1'; return $data; } ),
	'source map'         => array( 'master/source_map.json', function ( $data ) { $data[0]['source_doc'] = 'GHOST-DOC'; return $data; } ),
	'glossary'           => array( 'release/09_glossary.json', function ( $data ) { $first_key = array_key_first( $data['terms'] ); $data['terms'][ $first_key ]['status'] = 'secret'; return $data; } ),
	'entities'           => array( 'knowledge/entities.jsonl', null ),
	'templates'          => array( 'templates/' . basename( glob( $root . '/templates/TPL-*.json' )[0] ), function ( $data ) { $data['origin'] = 'bogus'; return $data; } ),
);
foreach ( $tampered as $label => list( $path, $mutator ) ) {
	if ( null === $mutator ) {
		file_put_contents( $tmp_root . '/' . $path, "{not json}\n" );
	} else {
		$data   = json_decode( (string) file_get_contents( $tmp_root . '/' . $path ), true );
		file_put_contents( $tmp_root . '/' . $path, (string) wp_json_encode( $mutator( $data ) ) );
	}
	$broken_catalog = new KBK_AI_Book_Catalog( new KBK_AI_Book_Repository( $tmp_root ) );
	if ( KBK_AI_Book_Catalog::STATUS_ARTIFACT_INVALID !== $broken_catalog->status() ) {
		fail( "corrupted {$label} must fail the catalog closed" );
	}
	if ( array() !== $broken_catalog->glossary_terms() || array() !== $broken_catalog->sources() || array() !== $broken_catalog->templates() ) {
		fail( "corrupted {$label} must expose no partial data" );
	}
	// restore for the next iteration
	copy( $root . '/' . $path, $tmp_root . '/' . $path );
}

// 8. Route layer: new allowlisted query vars stay strict.
$GLOBALS['kbk_test_query'] = array( KBK_AI_Book::TEMPLATE_QUERY_VAR => 'TPL-P06-01' );
if ( 'TPL-P06-01' !== KBK_AI_Book::requested_template() ) {
	fail( 'template allowlist must accept valid template IDs' );
}
$GLOBALS['kbk_test_query'] = array( KBK_AI_Book::TEMPLATE_QUERY_VAR => 'tpl-p06-01' );
if ( null !== KBK_AI_Book::requested_template() ) {
	fail( 'template allowlist must reject wrong-case IDs' );
}
$GLOBALS['kbk_test_query'] = array( KBK_AI_Book::ENTITY_QUERY_VAR => "E:Risk:x); DROP TABLE" );
if ( null !== KBK_AI_Book::requested_entity() ) {
	fail( 'entity allowlist must reject hostile shapes' );
}
$GLOBALS['kbk_test_query'] = array( KBK_AI_Book::ENTITY_QUERY_VAR => 'E:Risk:supply_chain' );
if ( 'E:Risk:supply_chain' !== KBK_AI_Book::requested_entity() ) {
	fail( 'entity allowlist must accept valid entity IDs' );
}
$GLOBALS['kbk_test_query'] = array( KBK_AI_Book::TYPE_QUERY_VAR => 'Risk9' );
if ( 'Risk9' !== KBK_AI_Book::requested_entity_type() ) {
	fail( 'type allowlist must accept alnum shapes' );
}
$GLOBALS['kbk_test_query'] = array( KBK_AI_Book::TYPE_QUERY_VAR => '9Risk' );
if ( null !== KBK_AI_Book::requested_entity_type() ) {
	fail( 'type allowlist must reject leading digits' );
}
foreach ( array( 'abc' => 1, '0' => 1, '12' => 12 ) as $raw => $expected ) {
	$GLOBALS['kbk_test_query'] = array( KBK_AI_Book::PAGE_QUERY_VAR => $raw );
	if ( $expected !== KBK_AI_Book::requested_page() ) {
		fail( "page allowlist must normalize '{$raw}' to {$expected}" );
	}
}

// Cleanup fixture root.
$cleanup = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $tmp_root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
foreach ( $cleanup as $file ) {
	$file->isDir() ? @rmdir( $file->getPathname() ) : @unlink( $file->getPathname() );
}
@rmdir( $tmp_root );

echo "PASS ai-book-catalog: sorted safe glossary list, 18 merged sources with 660 section counts and optional Persian titles (Publication labels too), 20 templates with fail-closed detail, entity types/pagination/clamping, bounded entity detail with repository-resolved mentions, validator fixtures, six corrupted-artifact fail-closed paths and route-layer query var hygiene\n";
