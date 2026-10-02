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

	/** @var array<string,mixed>|false|null False once the manifest failed to load. */
	private $release;

	/** @var array{applied:bool,chapters:int,sections:int,omitted:int} */
	private $overlay_state = array( 'applied' => false, 'chapters' => 0, 'sections' => 0, 'omitted' => 0 );

	/** Schema tag of the optional display overlay (master/reader-overlay.json). */
	const OVERLAY_SCHEMA = 'kbk-reader-overlay/1';

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
			$this->apply_reader_overlay();
			$this->clean_display_titles();
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
	 * The published release's provenance facts, or null when the manifest is
	 * absent or fails validation. Artifacts are name => {sha256, bytes}.
	 *
	 * @return array{edition_id:string,release_id:string,hash_spec:string,build_date:string,publisher:string,merkle_root:string,artifacts:array<string,array{sha256:string,bytes:int}>}|null
	 */
	public function release(): ?array {
		if ( null === $this->release ) {
			try {
				$manifest  = KBK_AI_Book_Artifacts::load_json_file( $this->artifact_path( 'provenance/release-manifest.json' ) );
				$facts     = KBK_AI_Book_Artifacts::validate_release_manifest( $manifest, $this->book()['edition_id'] );
				$artifacts = array();
				foreach ( $manifest['artifacts'] as $name => $entry ) {
					$artifacts[ $name ] = array( 'sha256' => $entry['sha256'], 'bytes' => (int) $entry['bytes'] );
				}
				$label = static function ( $value ): string {
					return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9._-]{1,40}$/', $value ) ? $value : '';
				};
				$this->release = array(
					'edition_id'  => $facts['edition_id'],
					'release_id'  => $label( $manifest['release_id'] ?? null ),
					'hash_spec'   => $label( $manifest['hash_spec'] ?? null ),
					'build_date'  => $facts['build_date'],
					'publisher'   => $facts['publisher'],
					'merkle_root' => $facts['merkle_root'],
					'artifacts'   => $artifacts,
				);
			} catch ( InvalidArgumentException | UnexpectedValueException $error ) {
				$this->release = false;
			}
		}
		return false === $this->release ? null : $this->release;
	}

	/**
	 * Look a content ID, structural ID or SHA-256 up in the published edition.
	 * Only exact matches count; anything else is null, never a near miss.
	 *
	 * @return array<string,mixed>|null kind "content" (registry entry and its
	 *         section) or kind "artifact" (a release file).
	 */
	public function verify( string $needle ): ?array {
		$needle = trim( $needle );
		if ( '' === $needle || strlen( $needle ) > 80 ) {
			return null;
		}
		$ids = $this->bundle()['content_ids']['ids'];
		if ( 1 === preg_match( '/^[0-9a-fA-F]{64}$/', $needle ) ) {
			$hash = strtolower( $needle );
			foreach ( $ids as $content_id => $entry ) {
				if ( $hash === $entry['hash'] ) {
					return $this->content_match( (string) $content_id, $entry );
				}
			}
			foreach ( ( $this->release()['artifacts'] ?? array() ) as $name => $artifact ) {
				if ( $hash === $artifact['sha256'] ) {
					return array( 'kind' => 'artifact', 'name' => (string) $name, 'sha256' => $artifact['sha256'], 'bytes' => $artifact['bytes'] );
				}
			}
			return null;
		}
		$id = strtoupper( $needle );
		if ( isset( $ids[ $id ] ) ) {
			return $this->content_match( $id, $ids[ $id ] );
		}
		foreach ( $ids as $content_id => $entry ) {
			if ( $id === $entry['structural_id'] ) {
				return $this->content_match( (string) $content_id, $entry );
			}
		}
		return null;
	}

	/**
	 * @param array<string,mixed> $entry Validated content_ids entry.
	 * @return array<string,mixed>
	 */
	private function content_match( string $content_id, array $entry ): array {
		return array(
			'kind'          => 'content',
			'content_id'    => $content_id,
			'structural_id' => $entry['structural_id'],
			'origin'        => $entry['origin'],
			'sha256'        => $entry['hash'],
			'section'       => $this->find_section( $entry['structural_id'] ),
		);
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

	/**
	 * What the reader overlay changed in this request, for diagnostics/tests.
	 *
	 * @return array{applied:bool,chapters:int,sections:int,omitted:int}
	 */
	public function overlay_state(): array {
		$this->bundle();
		return $this->overlay_state;
	}

	/**
	 * Merge the optional display overlay into the in-memory book.
	 *
	 * The overlay only replaces display text (chapter/section title_fa and
	 * section fa_text) and can flag a section as source-PDF debris (omit). It
	 * is applied only when its schema and edition match and its book_sha256 is
	 * the hash of the exact book.json bytes on disk; anything else is ignored
	 * and the canonical text is served (fail closed, never fatal). Content IDs,
	 * structural IDs, content hashes, citations and content_ids.json are never
	 * touched, so verification keeps proving the canonical text.
	 */
	private function apply_reader_overlay(): void {
		$path = $this->artifact_path( 'master/reader-overlay.json' );
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return;
		}
		try {
			$overlay = KBK_AI_Book_Artifacts::load_json_file( $path );
		} catch ( UnexpectedValueException $error ) {
			return;
		}
		if ( self::OVERLAY_SCHEMA !== ( $overlay['schema'] ?? null ) || ! is_string( $overlay['edition_id'] ?? null ) || $overlay['edition_id'] !== ( $this->book['edition_id'] ?? null ) ) {
			return;
		}
		$expected = $overlay['book_sha256'] ?? null;
		if ( ! is_string( $expected ) || 1 !== preg_match( '/^[0-9a-f]{64}$/', $expected ) ) {
			return;
		}
		$actual = hash_file( 'sha256', $this->artifact_path( 'master/book.json' ) );
		if ( ! is_string( $actual ) || ! hash_equals( $actual, $expected ) ) {
			return;
		}
		$chapters = is_array( $overlay['chapters'] ?? null ) ? $overlay['chapters'] : array();
		$sections = is_array( $overlay['sections'] ?? null ) ? $overlay['sections'] : array();
		$text = static function ( $value ): ?string {
			return is_string( $value ) && '' !== trim( $value ) ? $value : null;
		};
		foreach ( $this->book['parts'] as $part_index => $part ) {
			foreach ( $part['chapters'] as $chapter_index => $chapter ) {
				$chapter_patch = $chapters[ $chapter['chapter_id'] ] ?? null;
				if ( is_array( $chapter_patch ) && null !== $text( $chapter_patch['title_fa'] ?? null ) ) {
					$this->book['parts'][ $part_index ]['chapters'][ $chapter_index ]['title_fa'] = $text( $chapter_patch['title_fa'] );
					$this->overlay_state['chapters']++;
				}
				foreach ( $chapter['sections'] as $section_index => $section ) {
					$patch = $sections[ $section['structural_id'] ] ?? null;
					if ( ! is_array( $patch ) ) {
						continue;
					}
					$target  = &$this->book['parts'][ $part_index ]['chapters'][ $chapter_index ]['sections'][ $section_index ];
					$changed = false;
					if ( null !== $text( $patch['title_fa'] ?? null ) ) {
						$target['title_fa']             = $text( $patch['title_fa'] );
						$target['reader_overlay_title'] = true;
						$changed = true;
					}
					if ( null !== $text( $patch['fa_text'] ?? null ) ) {
						$target['fa_text'] = $text( $patch['fa_text'] );
						$changed = true;
					}
					if ( true === ( $patch['omit'] ?? null ) ) {
						$target['reader_omit'] = true;
						$this->overlay_state['omitted']++;
						$changed = true;
					}
					unset( $target );
					if ( $changed ) {
						$this->overlay_state['sections']++;
					}
				}
			}
		}
		$this->overlay_state['applied'] = true;
	}

	/** @var array<string,array{title_fa:string,subtitle_fa:string}>|null */
	private $doc_titles_fa;

	/**
	 * Persian publication titles from the optional taxonomy/doc_titles_fa.json
	 * (doc_key → {title_fa, subtitle_fa, …}). Missing, unreadable or malformed
	 * → an empty map, and every caller keeps showing the source title.
	 *
	 * @return array<string,array{title_fa:string,subtitle_fa:string}>
	 */
	public function doc_titles_fa(): array {
		if ( null !== $this->doc_titles_fa ) {
			return $this->doc_titles_fa;
		}
		$this->doc_titles_fa = array();
		$path = $this->artifact_path( 'taxonomy/doc_titles_fa.json' );
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return $this->doc_titles_fa;
		}
		try {
			$titles = KBK_AI_Book_Artifacts::load_json_file( $path );
		} catch ( UnexpectedValueException $error ) {
			return $this->doc_titles_fa;
		}
		foreach ( $titles as $doc_key => $entry ) {
			$title = is_array( $entry ) && is_string( $entry['title_fa'] ?? null ) ? trim( $entry['title_fa'] ) : '';
			if ( ! is_string( $doc_key ) || 1 !== preg_match( '/^[A-Z0-9][A-Z0-9-]{0,79}$/', $doc_key ) || '' === $title || mb_strlen( $title, 'UTF-8' ) > 300 ) {
				continue;
			}
			$subtitle = is_string( $entry['subtitle_fa'] ?? null ) ? trim( $entry['subtitle_fa'] ) : '';
			$this->doc_titles_fa[ $doc_key ] = array(
				'title_fa'    => $title,
				'subtitle_fa' => mb_strlen( $subtitle, 'UTF-8' ) <= 300 ? $subtitle : '',
			);
		}
		return $this->doc_titles_fa;
	}

	/**
	 * Footnote reference marks (`[^38]`) belong to body text; in a part,
	 * chapter or section title they would print raw in headings, the TOC and
	 * search. Display titles drop them; hashes and IDs are untouched.
	 */
	private function clean_display_titles(): void {
		$clean = static function ( $title ) {
			return is_string( $title ) && false !== strpos( $title, '[^' ) ? trim( (string) preg_replace( '/\s*\[\^[^\]]*\]/u', '', $title ) ) : $title;
		};
		foreach ( $this->book['parts'] as $p => $part ) {
			$this->book['parts'][ $p ]['title_fa'] = $clean( $part['title_fa'] ?? '' );
			foreach ( $part['chapters'] as $c => $chapter ) {
				$this->book['parts'][ $p ]['chapters'][ $c ]['title_fa'] = $clean( $chapter['title_fa'] ?? '' );
				foreach ( $chapter['sections'] as $s => $section ) {
					$this->book['parts'][ $p ]['chapters'][ $c ]['sections'][ $s ]['title_fa'] = $clean( $section['title_fa'] ?? '' );
				}
			}
		}
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
