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

	/** The validated absolute canonical root. */
	public function root(): string {
		return $this->root;
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
	 * Find a chapter within one part by stable ID, or by slug when unambiguous.
	 *
	 * A stable `chapter_id` match always wins and is returned immediately: IDs
	 * are unique within a part by construction. A slug match is only returned
	 * when exactly one chapter in the part carries it; some parts (e.g. P06)
	 * contain chapters that share a truncated slug, and guessing among them
	 * would silently serve the wrong chapter, so an ambiguous slug fails
	 * closed to null instead. All generated navigation in this project links
	 * by `chapter_id`, so this only affects hand-typed/legacy slug URLs.
	 *
	 * @return array<string,mixed>|null
	 */
	public function find_chapter( string $part_id_or_slug, string $chapter_id_or_slug ): ?array {
		$part = $this->find_part( $part_id_or_slug );
		if ( null === $part ) {
			return null;
		}
		foreach ( $part['chapters'] as $chapter ) {
			if ( $chapter['chapter_id'] === $chapter_id_or_slug ) {
				return $chapter;
			}
		}
		$slug_matches = array();
		foreach ( $part['chapters'] as $chapter ) {
			if ( $chapter['slug'] === $chapter_id_or_slug ) {
				$slug_matches[] = $chapter;
			}
		}
		return 1 === count( $slug_matches ) ? $slug_matches[0] : null;
	}

	/**
	 * The chapter immediately before/after the given chapter in whole-book
	 * document order. Chapter numbers run across the book (C01..C53), so the
	 * last chapter of a part leads into the first chapter of the next part
	 * instead of stopping at the boundary. Each neighbour carries the
	 * 'part_id' it belongs to, which the reader needs to build its link.
	 *
	 * @return array{prev:?array<string,mixed>,next:?array<string,mixed>}
	 */
	public function adjacent_chapters( string $part_id, string $chapter_id ): array {
		$part = $this->find_part( $part_id );
		if ( null === $part ) {
			return array( 'prev' => null, 'next' => null );
		}
		$sequence = array();
		$index    = null;
		foreach ( $this->parts() as $book_part ) {
			foreach ( $book_part['chapters'] as $book_chapter ) {
				if ( $book_part['part_id'] === $part['part_id'] && $book_chapter['chapter_id'] === $chapter_id ) {
					$index = count( $sequence );
				}
				$sequence[] = $book_chapter + array( 'part_id' => $book_part['part_id'] );
			}
		}
		if ( null === $index ) {
			return array( 'prev' => null, 'next' => null );
		}
		return array(
			'prev' => $sequence[ $index - 1 ] ?? null,
			'next' => $sequence[ $index + 1 ] ?? null,
		);
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
