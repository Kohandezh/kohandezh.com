<?php
/**
 * Isolated test for the Knowledge Graph subsystem (KBK_AI_Book_Graph +
 * validate_graph): real canonical data for the bounded explorer and the
 * mention-based Reader wiring, inline fixtures for the validator boundary,
 * and corrupted-artifact fail-closed paths on a temporary artifact root.
 */
define( 'ABSPATH', __DIR__ );
define( 'KBK_FEATURE_AI_BOOK', true );
define( 'KBK_PLUGIN_FILE', __FILE__ );
define( 'KBK_VERSION', 'test' );

$root = $argv[1] ?? '';
if ( '' === $root ) {
	fwrite( STDERR, "Usage: php ai-book-graph.test.php /path/to/AiBook\n" );
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
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book.php';

function fail( string $message ) {
	fwrite( STDERR, "FAIL {$message}\n" );
	exit( 1 );
}

$repository = new KBK_AI_Book_Repository( $root );
$catalog    = new KBK_AI_Book_Catalog( $repository );
$graph      = new KBK_AI_Book_Graph( $repository, $catalog );
if ( KBK_AI_Book_Graph::STATUS_READY !== $graph->status() ) {
	fail( 'graph must load the real canonical artifact cleanly' );
}

// 1. Overview stats: honest totals matching the canonical artifact.
$stats = $graph->stats();
if ( null === $stats || 3671 !== $stats['nodes'] || 10474 !== $stats['edges'] || 17 !== $stats['relations'] ) {
	fail( 'graph stats must match the canonical artifact totals' );
}
$relations = $graph->relation_counts();
if ( 17 !== count( $relations ) || ( $relations['REFERENCES'] ?? 0 ) !== 5291 || ( $relations['PART_OF'] ?? 0 ) !== 743 ) {
	fail( 'relation counts must be complete and correct' );
}

// 2. Node lookup: known node resolves with the safe identity model; unknown
//    IDs and hostile shapes fail closed.
$graph_data = json_decode( (string) file_get_contents( $root . '/knowledge/graph.json' ), true );
$some_node  = null;
foreach ( $graph_data['nodes'] as $candidate ) {
	if ( 'Concept' === $candidate['type'] && count( $candidate['mentions'] ) >= 5 ) {
		$some_node = $candidate;
		break;
	}
}
if ( null === $some_node ) {
	fail( 'fixture: graph must contain a Concept with several mentions' );
}
$node = $graph->node( $some_node['entity_id'] );
if ( null === $node || $node['entity_id'] !== $some_node['entity_id'] || '' === $node['label_en'] ) {
	fail( 'known node must resolve with its identity model' );
}
if ( array_keys( $node ) !== array( 'entity_id', 'type', 'label_fa', 'label_en', 'origin', 'documents', 'definition_en' ) ) {
	fail( 'node model must carry exactly the safe key set' );
}
if ( null !== $graph->node( 'E:Concept:ghost' ) || null !== $graph->node( '' ) ) {
	fail( 'unknown node IDs must fail closed to null' );
}

// 3. Neighborhood: bounded, stable, every endpoint resolved, anchors resolve
//    through the repository, quotes bounded.
$hood = $graph->neighborhood( $some_node['entity_id'] );
if ( null === $hood || array() === $hood['edges'] ) {
	fail( 'a multi-mention concept must have a non-empty neighborhood' );
}
if ( count( $hood['edges'] ) > KBK_AI_Book_Graph::MAX_NEIGHBORHOOD_EDGES ) {
	fail( 'neighborhood must respect the hard cap' );
}
$relations_seen = array();
foreach ( $hood['edges'] as $edge ) {
	if ( ! isset( $edge['relation'], $edge['direction_out'], $edge['origin'], $edge['quote'] ) ) {
		fail( 'neighborhood edges must carry the safe key set' );
	}
	$relations_seen[] = $edge['relation'];
	if ( ! in_array( $edge['origin'], array( 'source', 'editorial' ), true ) ) {
		fail( 'edge origin allowlist' );
	}
	if ( mb_strlen( $edge['quote'] ) > KBK_AI_Book_Graph::MAX_EVIDENCE_QUOTES ) {
		fail( 'evidence quotes must stay bounded' );
	}
	if ( null !== $edge['other'] ) {
		if ( $edge['other']['entity_id'] === $some_node['entity_id'] ) {
			fail( 'the other endpoint must not be the node itself' );
		}
		if ( null === $graph->node( $edge['other']['entity_id'] ) ) {
			fail( 'neighbor endpoints must resolve as nodes' );
		}
	}
	if ( null !== $edge['anchor_section'] ) {
		$resolved = $repository->find_section( $edge['anchor_section']['structural_id'] );
		if ( null === $resolved || $resolved['content_id'] !== $edge['anchor_section']['content_id'] ) {
			fail( 'anchor sections must resolve to the same Reader section' );
		}
	}
}

// 4. Reader wiring: related_map over a real chapter with concept coverage.
$chapter_sections = null;
$content_ids      = array();
foreach ( $repository->parts() as $fixture_part ) {
	foreach ( $fixture_part['chapters'] as $fixture_chapter ) {
		$fixture_ids = array_column( $fixture_chapter['sections'], 'content_id' );
		if ( count( $fixture_ids ) < 2 ) {
			continue;
		}
		$covered = 0;
		foreach ( $catalog->all_entities() as $entity ) {
			if ( array() !== array_intersect( $entity['mentions'], $fixture_ids ) ) {
				$covered++;
			}
		}
		if ( $covered >= 5 ) {
			$chapter_sections = $fixture_chapter['sections'];
			$content_ids      = $fixture_ids;
			break 2;
		}
	}
}
if ( null === $chapter_sections ) {
	fail( 'fixture: corpus must contain a chapter with concept coverage' );
}
$section_ids      = array_column( $chapter_sections, 'structural_id', 'content_id' );
$related_map      = $graph->related_map( $content_ids );
if ( array() === $related_map ) {
	fail( 'related_map must produce results for a real chapter' );
}
foreach ( $related_map as $content_id => $related ) {
	if ( count( $related['concepts'] ) > KBK_AI_Book_Graph::MAX_RELATED_CONCEPTS || count( $related['sections'] ) > KBK_AI_Book_Graph::MAX_RELATED_SECTIONS ) {
		fail( 'related wiring must respect its caps' );
	}
	foreach ( $related['concepts'] as $concept ) {
		if ( ! isset( $concept['entity_id'], $concept['type'], $concept['label_fa'], $concept['label_en'] ) ) {
			fail( 'related concepts must carry the safe key set' );
		}
		if ( null === $catalog->entity( $concept['entity_id'] ) ) {
			fail( 'related concepts must resolve in the catalog' );
		}
	}
	foreach ( $related['sections'] as $related_section ) {
		if ( $related_section['structural_id'] === $section_ids[ $content_id ] ) {
			fail( 'related sections must never include the section itself' );
		}
		if ( in_array( $related_section['structural_id'], $section_ids, true ) ) {
			fail( 'related sections must not suggest sibling sections of the same view' );
		}
		if ( null === $repository->find_section( $related_section['structural_id'] ) ) {
			fail( 'related sections must resolve through the repository' );
		}
	}
}
$empty_map = $graph->related_map( array( 'KDJ-AI-2026E1-P99-C99-S99-00000000' ) );
if ( array() !== $empty_map ) {
	fail( 'related_map over unknown content IDs must be an honest empty map' );
}

// 5. Validator fixtures: valid minimal graph passes; tampered variants throw.
$edition       = '2026E1';
$valid_entity  = array(
	'entity_id' => 'E:Concept:ai_system',
	'type'      => 'Concept',
	'labels'    => array( 'en' => 'AI system', 'fa' => 'سامانهٔ هوش مصنوعی' ),
	'origin'    => 'source',
	'documents' => array( 'NIST-AI-100-1' ),
	'mentions'  => array( 'KDJ-AI-2026E1-P01-C01-S01-04D3A306' ),
);
$valid_node_two = $valid_entity;
$valid_node_two['entity_id'] = 'E:Risk:supply_chain';
$valid_node_two['type']      = 'Risk';
$valid_graph = array(
	'edition'        => $edition,
	'nodes'          => array( $valid_entity, $valid_node_two ),
	'edges'          => array( array(
		'source'     => 'E:Concept:ai_system',
		'relation'   => 'AFFECTS',
		'target'     => 'E:Risk:supply_chain',
		'content_id' => 'KDJ-AI-2026E1-P01-C01-S01-04D3A306',
		'origin'     => 'editorial',
		'evidence'   => array( 'quote' => 'شاهد کوتاه.', 'block_id' => null ),
	) ),
	'entity_types'   => array( 'Concept', 'Risk' ),
	'relation_types' => array( 'AFFECTS' ),
);
KBK_AI_Book_Artifacts::validate_graph( $valid_graph, $edition );

$invalid_cases = array();
$case = $valid_graph; $case['edges'][0]['target'] = 'E:Concept:ghost';            $invalid_cases[] = $case;
$case = $valid_graph; $case['edges'][0]['relation'] = 'TELEPORTS';                $invalid_cases[] = $case;
$case = $valid_graph; $case['edges'][0]['content_id'] = 'KDJ-AI-2025E1-P01-C01-S01-04D3A306'; $invalid_cases[] = $case;
$case = $valid_graph; $case['edges'][0]['origin'] = 'guessed';                    $invalid_cases[] = $case;
$case = $valid_graph; $case['edges'][0]['evidence']['quote'] = str_repeat( 'x', 201 ); $invalid_cases[] = $case;
$case = $valid_graph; $case['edition'] = '2025E1';                                $invalid_cases[] = $case;
$case = $valid_graph; $case['nodes'] = array();                                   $invalid_cases[] = $case;
$case = $valid_graph; $case['relation_types'] = array( 'bad name' );              $invalid_cases[] = $case;
foreach ( $invalid_cases as $i => $graph_case ) {
	try {
		KBK_AI_Book_Artifacts::validate_graph( $graph_case, $edition );
		fail( "tampered graph fixture #{$i} must be rejected by the validator" );
	} catch ( UnexpectedValueException $expected ) {
		// fail closed as designed
	}
}

// 6. Corrupted artifacts on a real root fail the graph closed without
//    disturbing the catalog.
$tmp_root = sys_get_temp_dir() . '/kbk-graph-fixture-' . getmypid();
foreach ( array( 'manifests', 'master', 'provenance', 'release', 'templates', 'knowledge', 'html/search' ) as $dir ) {
	mkdir( $tmp_root . '/' . $dir, 0777, true );
}
foreach ( array(
	'master/book.json'                  => 'master/book.json',
	'master/bibliography.json'          => 'master/bibliography.json',
	'master/source_map.json'            => 'master/source_map.json',
	'provenance/content_ids.json'       => 'provenance/content_ids.json',
	'provenance/citation-registry.json' => 'provenance/citation-registry.json',
	'manifests/sources.json'            => 'manifests/sources.json',
	'release/09_glossary.json'          => 'release/09_glossary.json',
	'knowledge/entities.jsonl'          => 'knowledge/entities.jsonl',
	'knowledge/graph.json'              => 'knowledge/graph.json',
) as $target => $origin ) {
	copy( $root . '/' . $origin, $tmp_root . '/' . $target );
}
foreach ( glob( $root . '/templates/TPL-*.json' ) as $template_file ) {
	copy( $template_file, $tmp_root . '/templates/' . basename( $template_file ) );
}
$tampered = array(
	'dangling edge' => function ( $data ) { $data['edges'][0]['target'] = 'E:Concept:ghost'; return $data; },
	'bad relation'  => function ( $data ) { $data['edges'][0]['relation'] = 'MAGIC'; return $data; },
	'bad edition'   => function ( $data ) { $data['edition'] = '1999E9'; return $data; },
	'not json'      => null,
);
foreach ( $tampered as $label => $mutator ) {
	if ( null === $mutator ) {
		file_put_contents( $tmp_root . '/knowledge/graph.json', '[broken' );
	} else {
		$data = json_decode( (string) file_get_contents( $tmp_root . '/knowledge/graph.json' ), true );
		file_put_contents( $tmp_root . '/knowledge/graph.json', (string) wp_json_encode( $mutator( $data ) ) );
	}
	$fixture_repository = new KBK_AI_Book_Repository( $tmp_root );
	$fixture_catalog    = new KBK_AI_Book_Catalog( $fixture_repository );
	$broken_graph       = new KBK_AI_Book_Graph( $fixture_repository, $fixture_catalog );
	if ( KBK_AI_Book_Graph::STATUS_ARTIFACT_INVALID !== $broken_graph->status() ) {
		fail( "corrupted graph ({$label}) must fail closed" );
	}
	if ( null !== $broken_graph->stats() || array() !== $broken_graph->relation_counts() || null !== $broken_graph->node( 'E:Concept:ai_system' ) ) {
		fail( "corrupted graph ({$label}) must expose no partial data" );
	}
	if ( KBK_AI_Book_Catalog::STATUS_READY !== $fixture_catalog->status() ) {
		fail( 'corrupted graph must not corrupt the catalog' );
	}
	copy( $root . '/knowledge/graph.json', $tmp_root . '/knowledge/graph.json' );
}

// 7. Route layer: the graph view and its node query var stay strict.
$GLOBALS['kbk_test_query'] = array( 'kbk_ai_book' => 'graph' );
if ( 'graph' !== KBK_AI_Book::current_view() || ! KBK_AI_Book::is_request() ) {
	fail( 'graph route detection' );
}
$GLOBALS['kbk_test_query'] = array( 'kbk_ai_book' => 'graph', KBK_AI_Book::ENTITY_QUERY_VAR => 'E:Risk:supply_chain' );
if ( 'E:Risk:supply_chain' !== KBK_AI_Book::requested_entity() ) {
	fail( 'graph node query var allowlist' );
}
$GLOBALS['kbk_test_query'] = array( 'kbk_ai_book' => 'graph', KBK_AI_Book::ENTITY_QUERY_VAR => "E:Risk:x' OR 1=1" );
if ( null !== KBK_AI_Book::requested_entity() ) {
	fail( 'graph node query var must reject hostile shapes' );
}

// Cleanup fixture root.
$cleanup = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $tmp_root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
foreach ( $cleanup as $file ) {
	$file->isDir() ? @rmdir( $file->getPathname() ) : @unlink( $file->getPathname() );
}
@rmdir( $tmp_root );

echo "PASS ai-book-graph: honest stats (3,671/10,474/17), safe node model, bounded repository-resolvable neighborhoods, capped deterministic related wiring with sibling exclusion, validator fixtures, four corrupted-graph fail-closed paths and route-layer hygiene\n";
