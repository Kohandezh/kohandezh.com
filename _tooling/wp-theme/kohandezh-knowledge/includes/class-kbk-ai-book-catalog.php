<?php
/**
 * KBK_AI_Book_Catalog — bounded, validated read models for glossary, sources,
 * templates and concepts over the canonical AiBook artifacts.
 *
 * Everything crosses the KBK_AI_Book_Artifacts boundary first. Public models
 * are hand-built field by field: glossary reviewer metadata, template
 * internals beyond the defined fields, and raw JSONL never leave this class.
 * A single corrupted artifact fails the whole catalog closed
 * (ADR-AB-0007/0010 philosophy); callers render an honest error state.
 *
 * @package Kohandezh_Knowledge
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'KBK_TESTING' ) ) {
	exit;
}

final class KBK_AI_Book_Catalog {

	const STATUS_READY            = 'READY';
	const STATUS_ARTIFACT_INVALID = 'ARTIFACT_INVALID';
	const ENTITIES_PER_PAGE       = 60;
	const MAX_ENTITY_MENTIONS     = 12;
	const MAX_LISTED_DOCS         = 3;

	/** @var KBK_AI_Book_Repository */
	private $repository;

	/** @var array{entities:?string,templates:?string}|null Path overrides for tests. */
	private $paths;

	/** @var string */
	private $status = self::STATUS_READY;

	/** @var bool */
	private $loaded = false;

	/** @var array<int,array<string,mixed>> */
	private $glossary_terms = array();

	/** @var array<int,array<string,mixed>> */
	private $sources = array();

	/** @var array<string,array<string,mixed>> */
	private $templates = array();

	/** @var array<string,array<string,mixed>> */
	private $entities = array();

	/** @var array<string,int> */
	private $entity_type_counts = array();

	public function __construct( KBK_AI_Book_Repository $repository, ?array $paths = null ) {
		$this->repository = $repository;
		$this->paths      = $paths;
	}

	public function status(): string {
		$this->load();
		return $this->status;
	}

	/** Safe glossary list sorted by Persian preferred term. @return array<int,array<string,mixed>> */
	public function glossary_terms(): array {
		return $this->load() ? $this->glossary_terms : array();
	}

	/** Validated sources with per-source section counts. @return array<int,array<string,mixed>> */
	public function sources(): array {
		return $this->load() ? $this->sources : array();
	}

	/** Template cards sorted by template ID. @return array<int,array<string,mixed>> */
	public function templates(): array {
		return $this->load() ? array_values( $this->templates ) : array();
	}

	/**
	 * One full template, fail-closed to null on unknown IDs.
	 *
	 * @return array<string,mixed>|null
	 */
	public function template( string $template_id ): ?array {
		$this->load();
		return $this->templates[ $template_id ] ?? null;
	}

	/** Entity type => count, for the filter UI. @return array<string,int> */
	public function entity_types(): array {
		return $this->load() ? $this->entity_type_counts : array();
	}

	/**
	 * One paginated entities page. Unknown type → empty honest page; page
	 * numbers are clamped into range.
	 *
	 * @return array{total:int,page:int,pages:int,per_page:int,items:array<int,array<string,mixed>>}
	 */
	public function entities( ?string $type = null, int $page = 1 ): array {
		$out = array( 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => self::ENTITIES_PER_PAGE, 'items' => array() );
		if ( ! $this->load() ) {
			return $out;
		}
		$all = array();
		foreach ( $this->entities as $entity ) {
			if ( null === $type || $entity['type'] === $type ) {
				$all[] = $entity;
			}
		}
		$out['total'] = count( $all );
		$out['pages'] = max( 1, (int) ceil( $out['total'] / self::ENTITIES_PER_PAGE ) );
		$out['page']  = max( 1, min( $out['pages'], $page ) );
		$out['items'] = array();
		foreach ( array_slice( $all, ( $out['page'] - 1 ) * self::ENTITIES_PER_PAGE, self::ENTITIES_PER_PAGE ) as $entity ) {
			$out['items'][] = array(
				'entity_id'      => $entity['entity_id'],
				'type'           => $entity['type'],
				'labels'         => $entity['labels'],
				'origin'         => $entity['origin'],
				'mentions_count' => count( $entity['mentions'] ),
			);
		}
		return $out;
	}

	/**
	 * One concept (entity) detail; mentions are resolved into bounded section
	 * references the template can turn into stable Reader links.
	 *
	 * @return array<string,mixed>|null
	 */
	public function entity( string $entity_id ): ?array {
		if ( ! $this->load() || ! isset( $this->entities[ $entity_id ] ) ) {
			return null;
		}
		$entity = $this->entities[ $entity_id ];
		$sections = array();
		foreach ( array_slice( $entity['mentions'], 0, self::MAX_ENTITY_MENTIONS ) as $content_id ) {
			$section = $this->repository->find_section( $content_id );
			if ( null !== $section ) {
				$sections[] = array(
					'structural_id' => $section['structural_id'],
					'content_id'    => $section['content_id'],
					'title_fa'      => $section['title_fa'],
					'part_id'       => $section['part_id'],
					'chapter_id'    => $section['chapter_id'],
					'origin'        => $section['origin'],
				);
			}
		}
		return array(
			'entity_id'        => $entity['entity_id'],
			'type'             => $entity['type'],
			'label_fa'         => $entity['labels']['fa'] ?? '',
			'label_en'         => $entity['labels']['en'] ?? '',
			'origin'           => $entity['origin'],
			'documents'        => array_slice( $entity['documents'], 0, self::MAX_LISTED_DOCS ),
			'mentions_total'   => count( $entity['mentions'] ),
			'sections'         => $sections,
			'definition_en'    => (string) ( $entity['definition_en'] ?? '' ),
		);
	}

	/** Load and validate every catalog artifact once. Fails closed. */
	private function load(): bool {
		if ( $this->loaded ) {
			return self::STATUS_READY === $this->status;
		}
		$this->loaded = true;
		try {
			$root    = $this->repository->root();
			$bundle  = $this->repository->bundle();
			$edition = $bundle['book']['edition_id'];
			$this->load_glossary( $root );
			$this->load_sources( $root, $edition );
			$this->load_templates( $this->paths['templates'] ?? ( $root . DIRECTORY_SEPARATOR . 'templates' ) );
			$this->load_entities( $this->paths['entities'] ?? ( $root . DIRECTORY_SEPARATOR . 'knowledge' . DIRECTORY_SEPARATOR . 'entities.jsonl' ), $edition );
		} catch ( UnexpectedValueException | InvalidArgumentException $error ) {
			$this->status = self::STATUS_ARTIFACT_INVALID;
			return false;
		}
		return true;
	}

	private function load_glossary( string $root ): void {
		$glossary = KBK_AI_Book_Artifacts::load_json_file( $root . DIRECTORY_SEPARATOR . 'release' . DIRECTORY_SEPARATOR . '09_glossary.json' );
		KBK_AI_Book_Artifacts::validate_glossary( $glossary );
		foreach ( $glossary['terms'] as $term ) {
			$this->glossary_terms[] = array(
				'concept_id'   => strtoupper( (string) $term['concept_id'] ),
				'preferred_fa' => (string) $term['preferred_fa'],
				'english'      => (string) $term['english'],
				'acronym'      => (string) $term['acronym'],
				'status'       => (string) $term['status'],
				'domain'       => (string) $term['domain'],
			);
		}
		usort( $this->glossary_terms, static function ( array $a, array $b ): int {
			return strcmp( $a['preferred_fa'], $b['preferred_fa'] );
		} );
	}

	private function load_sources( string $root, string $edition ): void {
		$manifest     = KBK_AI_Book_Artifacts::load_json_file( $root . DIRECTORY_SEPARATOR . 'manifests' . DIRECTORY_SEPARATOR . 'sources.json' );
		$bibliography = KBK_AI_Book_Artifacts::load_json_file( $root . DIRECTORY_SEPARATOR . 'master' . DIRECTORY_SEPARATOR . 'bibliography.json' );
		$source_map   = KBK_AI_Book_Artifacts::load_json_file( $root . DIRECTORY_SEPARATOR . 'master' . DIRECTORY_SEPARATOR . 'source_map.json' );
		KBK_AI_Book_Artifacts::validate_sources_manifest( $manifest );
		KBK_AI_Book_Artifacts::validate_bibliography( $bibliography );
		KBK_AI_Book_Artifacts::validate_source_map( $source_map, $edition );
		$citations = array();
		foreach ( $bibliography['entries'] as $entry ) {
			$citations[ $entry['doc_key'] ] = $entry;
		}
		$unknown_bib = array_diff( array_keys( $citations ), array_column( $manifest['documents'], 'doc_key' ) );
		if ( array() !== $unknown_bib ) {
			throw new UnexpectedValueException( 'Bibliography references a document missing from the manifest: ' . reset( $unknown_bib ) );
		}
		$known = array();
		foreach ( $manifest['documents'] as $document ) {
			$known[ $document['doc_key'] ] = true;
		}
		$counts = array_fill_keys( array_keys( $known ), 0 );
		foreach ( $source_map as $entry ) {
			if ( ! isset( $counts[ $entry['source_doc'] ] ) ) {
				throw new UnexpectedValueException( 'Source map references an unknown document: ' . $entry['source_doc'] );
			}
			$counts[ $entry['source_doc'] ]++;
			if ( null === $this->repository->find_section( $entry['structural_id'] ) ) {
				throw new UnexpectedValueException( 'Source map references an unknown section: ' . $entry['structural_id'] );
			}
		}
		foreach ( $manifest['documents'] as $document ) {
			$doc_key   = $document['doc_key'];
			$citation  = $citations[ $doc_key ] ?? array();
			$this->sources[] = array(
				'doc_key'            => $doc_key,
				'title'              => (string) $document['title'],
				'subtitle'           => (string) ( $document['subtitle'] ?? '' ),
				'series_identifier'  => (string) ( $document['series_identifier'] ?? '' ),
				'organization'       => (string) ( $citation['organization'] ?? '' ),
				'publication_type'   => (string) ( $citation['publication_type'] ?? '' ),
				'publication_status' => (string) ( $citation['publication_status'] ?? '' ),
				'publication_date'   => (string) ( $citation['publication_date'] ?? '' ),
				'doi_url'            => (string) ( $citation['doi_url'] ?? '' ),
				'pages'              => (int) $document['pages'],
				'sha256'             => (string) $document['sha256'],
				'sections_count'     => $counts[ $doc_key ],
			);
		}
	}

	private function load_templates( string $templates_dir ): void {
		if ( ! is_dir( $templates_dir ) || ! is_readable( $templates_dir ) ) {
			throw new UnexpectedValueException( 'Templates directory is not readable' );
		}
		$files = glob( $templates_dir . DIRECTORY_SEPARATOR . 'TPL-*.json' );
		if ( false === $files || array() === $files ) {
			throw new UnexpectedValueException( 'No template artifacts found' );
		}
		sort( $files );
		foreach ( $files as $file ) {
			$template = KBK_AI_Book_Artifacts::load_json_file( $file );
			KBK_AI_Book_Artifacts::validate_template( $template );
			$fields = array();
			foreach ( $template['fields'] as $field ) {
				$fields[] = array(
					'name_fa'       => (string) $field['name_fa'],
					'name_en'       => (string) $field['name_en'],
					'description_fa' => (string) $field['description_fa'],
					'example_fa'    => (string) ( $field['example_fa'] ?? '' ),
				);
			}
			$this->templates[ (string) $template['template_id'] ] = array(
				'template_id'  => (string) $template['template_id'],
				'title_fa'     => (string) $template['title_fa'],
				'note_fa'      => (string) ( $template['note_fa'] ?? '' ),
				'origin'       => (string) $template['origin'],
				'derived_from' => array_slice( array_map( 'strval', (array) ( $template['derived_from'] ?? array() ) ), 0, self::MAX_LISTED_DOCS ),
				'fields'       => $fields,
			);
		}
		ksort( $this->templates );
	}

	private function load_entities( string $entities_path, string $edition ): void {
		if ( ! is_file( $entities_path ) || ! is_readable( $entities_path ) ) {
			throw new UnexpectedValueException( 'Entities artifact is not a readable file' );
		}
		$handle = fopen( $entities_path, 'r' );
		if ( false === $handle ) {
			throw new UnexpectedValueException( 'Entities artifact could not be opened' );
		}
		try {
			while ( false !== ( $line = fgets( $handle ) ) ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				$entity = json_decode( $line, true );
				if ( ! is_array( $entity ) ) {
					throw new UnexpectedValueException( 'Entities artifact contains an invalid JSON line' );
				}
				KBK_AI_Book_Artifacts::validate_entity( $entity, $edition );
				$model = array(
					'entity_id' => (string) $entity['entity_id'],
					'type'      => (string) $entity['type'],
					'labels'    => array(
						'en' => (string) ( $entity['labels']['en'] ?? '' ),
						'fa' => (string) ( $entity['labels']['fa'] ?? '' ),
					),
					'origin'         => (string) $entity['origin'],
					'documents'      => (array) $entity['documents'],
					'mentions'       => array_values( (array) ( $entity['mentions'] ?? array() ) ),
					'definition_en'  => (string) ( $entity['definition_en'] ?? '' ),
				);
				$this->entities[ $model['entity_id'] ] = $model;
				$this->entity_type_counts[ $model['type'] ] = ( $this->entity_type_counts[ $model['type'] ] ?? 0 ) + 1;
			}
		} finally {
			fclose( $handle );
		}
		if ( array() === $this->entities ) {
			throw new UnexpectedValueException( 'Entities artifact is empty' );
		}
		ksort( $this->entity_type_counts );
	}
}
