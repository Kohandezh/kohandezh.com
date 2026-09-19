<?php
/**
 * KBK_AI_Book_Repository — read-only access to validated canonical artifacts.
 *
 * @package Kohandezh_Knowledge
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'KBK_TESTING' ) ) {
	exit;
}

final class KBK_AI_Book_Repository {

	/** @var string */
	private $root;

	/** @var array<string,mixed>|null */
	private $book;

	/** @var array<string,mixed>|null */
	private $content_ids;

	/** @var array<string,mixed>|null */
	private $citations;

	/** @var array<string,array<string,mixed>>|null */
	private $sections_by_structural_id;

	/**
	 * @throws InvalidArgumentException When root is missing or unreadable.
	 */
	public function __construct( string $root ) {
		$resolved = realpath( $root );
		if ( false === $resolved || ! is_dir( $resolved ) || ! is_readable( $resolved ) ) {
			throw new InvalidArgumentException( 'AI Book root must be an existing readable directory.' );
		}
		$this->root = rtrim( $resolved, DIRECTORY_SEPARATOR );
	}

	/**
	 * Load and cross-validate the required reader bundle once.
	 *
	 * @return array{book:array<string,mixed>,content_ids:array<string,mixed>,citations:array<string,mixed>}
	 */
	public function bundle(): array {
		if ( null === $this->book ) {
			$this->book        = KBK_AI_Book_Artifacts::load_json_file( $this->artifact_path( 'master/book.json' ) );
			$this->content_ids = KBK_AI_Book_Artifacts::load_json_file( $this->artifact_path( 'provenance/content_ids.json' ) );
			$this->citations   = KBK_AI_Book_Artifacts::load_json_file( $this->artifact_path( 'provenance/citation-registry.json' ) );
			KBK_AI_Book_Artifacts::validate_bundle( $this->book, $this->content_ids, $this->citations );
		}
		return array(
			'book'        => $this->book,
			'content_ids' => $this->content_ids,
			'citations'   => $this->citations,
		);
	}

	/** @return array<string,mixed> */
	public function book(): array {
		return $this->bundle()['book'];
	}

	/** @return array<int,array<string,mixed>> */
	public function parts(): array {
		return $this->book()['parts'];
	}

	/**
	 * Find a part by stable ID or slug.
	 *
	 * @return array<string,mixed>|null
	 */
	public function find_part( string $id_or_slug ): ?array {
		foreach ( $this->parts() as $part ) {
			if ( $part['part_id'] === $id_or_slug || $part['slug'] === $id_or_slug ) {
				return $part;
			}
		}
		return null;
	}

	/**
	 * Find a chapter within one part by stable ID or slug.
	 *
	 * @return array<string,mixed>|null
	 */
	public function find_chapter( string $part_id_or_slug, string $chapter_id_or_slug ): ?array {
		$part = $this->find_part( $part_id_or_slug );
		if ( null === $part ) {
			return null;
		}
		foreach ( $part['chapters'] as $chapter ) {
			if ( $chapter['chapter_id'] === $chapter_id_or_slug || $chapter['slug'] === $chapter_id_or_slug ) {
				return $chapter;
			}
		}
		return null;
	}

	/**
	 * Find a section by structural ID or immutable content ID.
	 *
	 * @return array<string,mixed>|null
	 */
	public function find_section( string $id ): ?array {
		$this->index_sections();
		if ( isset( $this->sections_by_structural_id[ $id ] ) ) {
			return $this->sections_by_structural_id[ $id ];
		}
		$bundle = $this->bundle();
		if ( isset( $bundle['content_ids']['ids'][ $id ] ) ) {
			$structural_id = $bundle['content_ids']['ids'][ $id ]['structural_id'];
			return $this->sections_by_structural_id[ $structural_id ] ?? null;
		}
		return null;
	}

	/**
	 * Return the verified citation for a structural or content ID.
	 *
	 * @return array<string,mixed>|null
	 */
	public function find_citation( string $id ): ?array {
		$bundle = $this->bundle();
		$structural_id = $id;
		if ( isset( $bundle['content_ids']['ids'][ $id ] ) ) {
			$structural_id = $bundle['content_ids']['ids'][ $id ]['structural_id'];
		}
		return $bundle['citations']['registry'][ $structural_id ] ?? null;
	}

	/**
	 * Small safe view model for landing-page metadata.
	 *
	 * @return array{edition_id:string,locale:string,title_fa:string,parts:int,chapters:int,sections:int}
	 */
	public function summary(): array {
		$book = $this->book();
		$chapters = 0;
		foreach ( $book['parts'] as $part ) {
			$chapters += count( $part['chapters'] );
		}
		return array(
			'edition_id' => $book['edition_id'],
			'locale'     => $book['locale'],
			'title_fa'   => $book['title_fa'],
			'parts'      => count( $book['parts'] ),
			'chapters'   => $chapters,
			'sections'   => $book['units'],
		);
	}

	private function artifact_path( string $relative_path ): string {
		if ( false !== strpos( $relative_path, '..' ) || 1 === preg_match( '#^[/\\\\]#', $relative_path ) ) {
			throw new InvalidArgumentException( 'AI Book artifact paths must be root-relative and traversal-free.' );
		}
		return $this->root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative_path );
	}

	private function index_sections(): void {
		if ( null !== $this->sections_by_structural_id ) {
			return;
		}
		$this->sections_by_structural_id = array();
		foreach ( $this->parts() as $part ) {
			foreach ( $part['chapters'] as $chapter ) {
				foreach ( $chapter['sections'] as $section ) {
					$this->sections_by_structural_id[ $section['structural_id'] ] = $section;
				}
			}
		}
	}
}
