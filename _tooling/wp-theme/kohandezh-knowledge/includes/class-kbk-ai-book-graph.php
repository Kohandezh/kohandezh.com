<?php
/**
 * KBK_AI_Book_Graph — bounded Knowledge Graph explorer plus the mention-based
 * Related Concepts/Related Sections wiring for the Reader.
 *
 * The explorer reads knowledge/graph.json (validated, fail-closed). Reader
 * wiring intentionally does NOT load the 4.8MB graph artifact: related items
 * derive from the catalog's validated entity mentions in one bounded scan
 * (ADR-AB-0012). All outputs are capped, deterministic, hand-built read
 * models; raw JSONL/JSON never reaches templates.
 *
 * @package Kohandezh_Knowledge
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'KBK_TESTING' ) ) {
	exit;
}

final class KBK_AI_Book_Graph {

	const STATUS_READY            = 'READY';
	const STATUS_ARTIFACT_INVALID = 'ARTIFACT_INVALID';
	const MAX_NEIGHBORHOOD_EDGES  = 24;
	const MAX_VIZ_NODES           = 12;
	const MAX_RELATED_CONCEPTS    = 6;
	const MAX_RELATED_SECTIONS    = 4;
	const MAX_EVIDENCE_QUOTES     = 160;

	/** @var KBK_AI_Book_Repository */
	private $repository;

	/** @var KBK_AI_Book_Catalog */
	private $catalog;

	/** @var string|null */
	private $path_override;

	/** @var string */
	private $status = self::STATUS_READY;

	/** @var bool */
	private $loaded = false;

	/** @var array<string,array<string,mixed>> */
	private $nodes = array();

	/** @var array<int,array<string,mixed>> */
	private $edges = array();

	/** @var array<string,int> */
	private $relation_counts = array();

	/** @var array<string,array<int,int>> node id => edge indexes */
	private $incident = array();

	public function __construct( KBK_AI_Book_Repository $repository, KBK_AI_Book_Catalog $catalog, ?string $path_override = null ) {
		$this->repository     = $repository;
		$this->catalog        = $catalog;
		$this->path_override  = $path_override;
	}

	public function status(): string {
		$this->load();
		return $this->status;
	}

	/** Bounded overview stats. @return array<string,int>|null */
	public function stats(): ?array {
		if ( ! $this->load() ) {
			return null;
		}
		return array(
			'nodes'     => count( $this->nodes ),
			'edges'     => count( $this->edges ),
			'relations' => count( $this->relation_counts ),
		);
	}

	/** Relation name => edge count, ascending by name. @return array<string,int> */
	public function relation_counts(): array {
		return $this->load() ? $this->relation_counts : array();
	}

	/**
	 * One node's identity model; unknown IDs fail closed to null.
	 *
	 * @return array<string,mixed>|null
	 */
	public function node( string $entity_id ): ?array {
		if ( ! $this->load() || ! isset( $this->nodes[ $entity_id ] ) ) {
			return null;
		}
		$node = $this->nodes[ $entity_id ];
		return array(
			'entity_id'     => $node['entity_id'],
			'type'          => $node['type'],
			'label_fa'      => $node['labels']['fa'],
			'label_en'      => $node['labels']['en'],
			'origin'        => $node['origin'],
			'documents'     => array_slice( $node['documents'], 0, KBK_AI_Book_Catalog::MAX_LISTED_DOCS ),
			'definition_en' => (string) ( $node['definition_en'] ?? '' ),
			'definition_fa' => (string) ( $node['definition_fa'] ?? '' ),
		);
	}

	/**
	 * The capped, stable neighborhood of one node: each edge becomes a model
	 * with the other endpoint resolved (or null when that node was validated
	 * away — cannot happen today, but the template fails safe either way),
	 * plus bounded evidence and a Reader-resolvable anchor section.
	 *
	 * @return array{total:int,edges:array<int,array<string,mixed>>}|null
	 */
	public function neighborhood( string $entity_id ): ?array {
		if ( ! $this->load() || ! isset( $this->nodes[ $entity_id ] ) ) {
			return null;
		}
		$out   = array( 'total' => count( $this->incident[ $entity_id ] ?? array() ), 'edges' => array() );
		$index = 0;
		foreach ( $this->incident[ $entity_id ] ?? array() as $edge_index ) {
			if ( $index >= self::MAX_NEIGHBORHOOD_EDGES ) {
				break;
			}
			$edge     = $this->edges[ $edge_index ];
			$other_id = $entity_id === $edge['source'] ? $edge['target'] : $edge['source'];
			$other    = $this->node( $other_id );
			$anchor   = $this->repository->find_section( $edge['content_id'] );
			$quote    = (string) ( $edge['evidence']['quote'] ?? '' );
			$out['edges'][] = array(
				'relation'       => $edge['relation'],
				'direction_out'  => $entity_id === $edge['source'],
				'origin'         => $edge['origin'],
				'other'          => $other,
				'quote'          => mb_substr( $quote, 0, self::MAX_EVIDENCE_QUOTES ),
				'anchor_section' => null === $anchor ? null : array(
					'structural_id' => $anchor['structural_id'],
					'content_id'    => $anchor['content_id'],
					'title_fa'      => $anchor['title_fa'],
					'part_id'       => $anchor['part_id'],
					'chapter_id'    => $anchor['chapter_id'],
				),
			);
			$index++;
		}
		return $out;
	}

	/**
	 * Reader wiring: for the given section content IDs (one chapter's worth),
	 * build per-section Related Concepts (most-mentioned concepts first) and
	 * Related Sections (co-mention frequency, self excluded), all resolved
	 * through the repository into stable Reader links. One bounded scan.
	 *
	 * @param array<int,string> $content_ids Section content IDs.
	 * @return array<string,array{concepts:array<int,array<string,mixed>>,sections:array<int,array<string,mixed>>>}>
	 */
	public function related_map( array $content_ids ): array {
		$out = array();
		if ( array() === $content_ids || KBK_AI_Book_Catalog::STATUS_READY !== $this->catalog->status() ) {
			return $out;
		}
		$wanted = array_fill_keys( $content_ids, true );
		$by_section = array_fill_keys( $content_ids, array() );
		foreach ( $this->catalog->all_entities() as $entity ) {
			foreach ( $entity['mentions'] as $mention ) {
				if ( isset( $wanted[ $mention ] ) ) {
					$by_section[ $mention ][] = $entity;
				}
			}
		}
		foreach ( $by_section as $content_id => $candidates ) {
			if ( array() === $candidates ) {
				continue;
			}
			usort( $candidates, static function ( array $a, array $b ): int {
				return count( $b['mentions'] ) <=> count( $a['mentions'] )
					?: strcmp( $a['entity_id'], $b['entity_id'] );
			} );
			$concepts = array();
			foreach ( array_slice( $candidates, 0, self::MAX_RELATED_CONCEPTS ) as $entity ) {
				$concepts[] = array(
					'entity_id' => $entity['entity_id'],
					'type'      => $entity['type'],
					'label_fa'  => $entity['labels']['fa'],
					'label_en'  => $entity['labels']['en'],
				);
			}
			$frequency = array();
			foreach ( array_slice( $candidates, 0, 25 ) as $entity ) {
				foreach ( $entity['mentions'] as $mention ) {
					if ( isset( $wanted[ $mention ] ) ) {
						continue; // another section of the same chapter view
					}
					$frequency[ $mention ] = ( $frequency[ $mention ] ?? 0 ) + 1;
				}
			}
			arsort( $frequency );
			$sections = array();
			foreach ( array_slice( array_keys( $frequency ), 0, self::MAX_RELATED_SECTIONS * 4 ) as $mention ) {
				$section = $this->repository->find_section( $mention );
				if ( null === $section || $section['structural_id'] === $this->structural_for( $content_id ) ) {
					continue;
				}
				$sections[] = array(
					'structural_id' => $section['structural_id'],
					'title_fa'      => $section['title_fa'],
					'part_id'       => $section['part_id'],
					'chapter_id'    => $section['chapter_id'],
					'count'         => $frequency[ $mention ],
				);
				if ( count( $sections ) >= self::MAX_RELATED_SECTIONS ) {
					break;
				}
			}
			$out[ $content_id ] = array(
				'concepts' => $concepts,
				'sections' => $sections,
			);
		}
		return $out;
	}

	/** Load and validate the graph artifact once. Fails closed. */
	private function load(): bool {
		if ( $this->loaded ) {
			return self::STATUS_READY === $this->status;
		}
		$this->loaded = true;
		try {
			$path    = $this->path_override ?? ( $this->repository->root() . DIRECTORY_SEPARATOR . 'knowledge' . DIRECTORY_SEPARATOR . 'graph.json' );
			$graph   = KBK_AI_Book_Artifacts::load_json_file( $path );
			$edition = $this->repository->bundle()['book']['edition_id'];
			KBK_AI_Book_Artifacts::validate_graph( $graph, $edition );
			$titles_fa = $this->repository->doc_titles_fa();
			foreach ( $graph['nodes'] as $node ) {
				$node = KBK_AI_Book_Catalog::with_publication_title( $node, $titles_fa );
				$this->nodes[ (string) $node['entity_id'] ] = array(
					'entity_id'     => (string) $node['entity_id'],
					'type'          => (string) $node['type'],
					'labels'        => array(
						'en' => (string) ( $node['labels']['en'] ?? '' ),
						'fa' => (string) ( $node['labels']['fa'] ?? '' ),
					),
					'origin'        => (string) $node['origin'],
					'documents'     => array_values( (array) $node['documents'] ),
					'definition_en' => (string) ( $node['definition_en'] ?? '' ),
					'definition_fa' => is_string( $node['definition_fa'] ?? null ) ? $node['definition_fa'] : '',
				);
			}
			foreach ( $graph['edges'] as $index => $edge ) {
				$model = array(
					'source'    => (string) $edge['source'],
					'relation'  => (string) $edge['relation'],
					'target'    => (string) $edge['target'],
					'content_id' => (string) $edge['content_id'],
					'origin'    => (string) $edge['origin'],
					'evidence'  => (array) ( $edge['evidence'] ?? array() ),
				);
				$this->edges[] = $model;
				$this->incident[ $model['source'] ][] = $index;
				if ( $model['target'] !== $model['source'] ) {
					$this->incident[ $model['target'] ][] = $index;
				}
			}
			foreach ( $graph['relation_types'] as $relation ) {
				$this->relation_counts[ (string) $relation ] = 0;
			}
			foreach ( $this->edges as $edge ) {
				$this->relation_counts[ $edge['relation'] ]++;
			}
			ksort( $this->relation_counts );
		} catch ( UnexpectedValueException | InvalidArgumentException $error ) {
			$this->status = self::STATUS_ARTIFACT_INVALID;
			return false;
		}
		return true;
	}

	/** Structural ID for a content ID, via the repository bundle index. */
	private function structural_for( string $content_id ): string {
		$section = $this->repository->find_section( $content_id );
		return null === $section ? '' : (string) $section['structural_id'];
	}
}
