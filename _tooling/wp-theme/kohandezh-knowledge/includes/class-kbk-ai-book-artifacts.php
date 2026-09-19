<?php
/**
 * KBK_AI_Book_Artifacts — strict boundary validation for canonical book data.
 *
 * The canonical AiBook repository is an external, read-only source. This class
 * converts decoded JSON into a verified PHP array contract before WordPress code
 * may read it. It has no WordPress dependency, so it can be tested in isolation.
 *
 * @package Kohandezh_Knowledge
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'KBK_TESTING' ) ) {
	exit;
}

final class KBK_AI_Book_Artifacts {

	const EDITION_PATTERN    = '/^\d{4}E\d+$/';
	const STRUCTURAL_PATTERN = '/^KDJ-AI-(\d{4}E\d+)-P\d{2}-C\d{2}-S\d{2}$/';
	const CONTENT_PATTERN    = '/^KDJ-AI-(\d{4}E\d+)-P\d{2}-C\d{2}-S\d{2}-[A-F0-9]{8}$/';
	const ALLOWED_ORIGINS    = array( 'source_translation', 'editorial_synthesis', 'editorial_localization_ir' );

	/**
	 * Load one JSON object from an explicit file path.
	 *
	 * @return array<string,mixed>
	 * @throws UnexpectedValueException When the file cannot be read or decoded.
	 */
	public static function load_json_file( string $path ): array {
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			throw new UnexpectedValueException( 'AI Book artifact is not a readable file: ' . $path );
		}
		$raw = file_get_contents( $path );
		if ( false === $raw ) {
			throw new UnexpectedValueException( 'AI Book artifact could not be read: ' . $path );
		}
		$data = json_decode( $raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			throw new UnexpectedValueException( 'AI Book artifact is not a valid JSON object: ' . json_last_error_msg() );
		}
		return $data;
	}

	/**
	 * Validate the canonical master/book.json contract.
	 *
	 * @param array<string,mixed> $book Decoded book object.
	 * @return array<string,mixed> The verified input, unchanged.
	 */
	public static function validate_book( array $book ): array {
		self::require_keys( $book, array( 'edition_id', 'locale', 'title_fa', 'hash_spec', 'parts', 'units' ), 'book' );
		$edition = self::edition( $book['edition_id'], 'book.edition_id' );
		self::same( 'fa', $book['locale'], 'book.locale' );
		self::non_empty_string( $book['title_fa'], 'book.title_fa' );
		self::non_empty_string( $book['hash_spec'], 'book.hash_spec' );
		self::positive_int( $book['units'], 'book.units' );
		self::list_value( $book['parts'], 'book.parts' );

		$section_count = 0;
		foreach ( $book['parts'] as $part_index => $part ) {
			$part_path = 'book.parts[' . $part_index . ']';
			self::object_value( $part, $part_path );
			self::require_keys( $part, array( 'part_id', 'slug', 'title_fa', 'chapters' ), $part_path );
			self::matches( '/^P\d{2}$/', $part['part_id'], $part_path . '.part_id' );
			self::slug( $part['slug'], $part_path . '.slug' );
			self::non_empty_string( $part['title_fa'], $part_path . '.title_fa' );
			self::list_value( $part['chapters'], $part_path . '.chapters' );

			foreach ( $part['chapters'] as $chapter_index => $chapter ) {
				$chapter_path = $part_path . '.chapters[' . $chapter_index . ']';
				self::object_value( $chapter, $chapter_path );
				self::require_keys( $chapter, array( 'chapter_id', 'slug', 'title_fa', 'origin', 'sections' ), $chapter_path );
				self::matches( '/^C\d{2}$/', $chapter['chapter_id'], $chapter_path . '.chapter_id' );
				self::slug( $chapter['slug'], $chapter_path . '.slug' );
				self::non_empty_string( $chapter['title_fa'], $chapter_path . '.title_fa' );
				self::origin( $chapter['origin'], $chapter_path . '.origin' );
				self::list_value( $chapter['sections'], $chapter_path . '.sections' );

				foreach ( $chapter['sections'] as $section_index => $section ) {
					$section_path = $chapter_path . '.sections[' . $section_index . ']';
					self::validate_section( $section, $section_path, $edition, $part['part_id'], $chapter['chapter_id'] );
					$section_count++;
				}
			}
		}
		self::same( $book['units'], $section_count, 'book.units must equal the number of sections' );
		return $book;
	}

	/**
	 * Validate provenance/content_ids.json.
	 *
	 * @param array<string,mixed> $artifact Decoded content ID registry.
	 * @return array<string,mixed>
	 */
	public static function validate_content_ids( array $artifact ): array {
		self::require_keys( $artifact, array( 'edition_id', 'count', 'ids' ), 'content_ids' );
		$edition = self::edition( $artifact['edition_id'], 'content_ids.edition_id' );
		self::non_negative_int( $artifact['count'], 'content_ids.count' );
		self::object_value( $artifact['ids'], 'content_ids.ids' );
		self::same( $artifact['count'], count( $artifact['ids'] ), 'content_ids.count' );
		foreach ( $artifact['ids'] as $content_id => $entry ) {
			self::content_id( $content_id, 'content_ids.ids key', $edition );
			self::object_value( $entry, 'content_ids.ids.' . $content_id );
			self::require_keys( $entry, array( 'structural_id', 'part', 'chapter', 'section', 'origin', 'hash', 'locale' ), 'content_ids.ids.' . $content_id );
			self::structural_id( $entry['structural_id'], 'content_ids structural_id', $edition );
			self::origin( $entry['origin'], 'content_ids origin' );
			self::matches( '/^[a-f0-9]{64}$/', $entry['hash'], 'content_ids hash' );
			self::same( 'fa', $entry['locale'], 'content_ids locale' );
		}
		return $artifact;
	}

	/**
	 * Validate provenance/citation-registry.json.
	 *
	 * @param array<string,mixed> $artifact Decoded citation registry.
	 * @return array<string,mixed>
	 */
	public static function validate_citations( array $artifact ): array {
		self::require_keys( $artifact, array( 'edition_id', 'count', 'registry' ), 'citations' );
		$edition = self::edition( $artifact['edition_id'], 'citations.edition_id' );
		self::non_negative_int( $artifact['count'], 'citations.count' );
		self::object_value( $artifact['registry'], 'citations.registry' );
		self::same( $artifact['count'], count( $artifact['registry'] ), 'citations.count' );
		foreach ( $artifact['registry'] as $structural_id => $entry ) {
			self::structural_id( $structural_id, 'citations.registry key', $edition );
			self::object_value( $entry, 'citations.registry.' . $structural_id );
			self::require_keys( $entry, array( 'content_id', 'title_fa', 'origin', 'canonical_url', 'citation_fa', 'source_documents' ), 'citations.registry.' . $structural_id );
			self::content_id( $entry['content_id'], 'citation content_id', $edition );
			self::non_empty_string( $entry['title_fa'], 'citation title_fa' );
			self::origin( $entry['origin'], 'citation origin' );
			self::canonical_url( $entry['canonical_url'], $structural_id );
			self::non_empty_string( $entry['citation_fa'], 'citation citation_fa' );
			self::list_value( $entry['source_documents'], 'citation source_documents' );
		}
		return $artifact;
	}

	/**
	 * Validate html/search/index.json — the pipeline's bounded per-section
	 * search index. Snippets are the only body text this artifact may carry,
	 * so the validator enforces their presence and bounded length.
	 *
	 * @param array<string,mixed> $artifact Decoded search index.
	 * @return array<string,mixed>
	 */
	public static function validate_search_index( array $artifact ): array {
		self::require_keys( $artifact, array( 'edition', 'count', 'entries' ), 'search_index' );
		$edition = self::edition( $artifact['edition'], 'search_index.edition' );
		self::non_negative_int( $artifact['count'], 'search_index.count' );
		self::list_value( $artifact['entries'], 'search_index.entries' );
		self::same( $artifact['count'], count( $artifact['entries'] ), 'search_index.count' );
		foreach ( $artifact['entries'] as $index => $entry ) {
			$path = 'search_index.entries[' . $index . ']';
			self::object_value( $entry, $path );
			self::require_keys( $entry, array( 'id', 'structural_id', 'url', 'title_fa', 'part', 'chapter', 'origin', 'docs', 'keywords_fa', 'keywords_en', 'acronyms', 'snippet' ), $path );
			self::content_id( $entry['id'], $path . '.id', $edition );
			self::structural_id( $entry['structural_id'], $path . '.structural_id', $edition );
			self::canonical_url( $entry['url'], $entry['structural_id'] );
			self::non_empty_string( $entry['title_fa'], $path . '.title_fa' );
			self::non_empty_string( $entry['part'], $path . '.part' );
			self::non_empty_string( $entry['chapter'], $path . '.chapter' );
			self::origin( $entry['origin'], $path . '.origin' );
			self::list_value( $entry['docs'], $path . '.docs' );
			self::list_value( $entry['keywords_fa'], $path . '.keywords_fa' );
			self::list_value( $entry['keywords_en'], $path . '.keywords_en' );
			self::list_value( $entry['acronyms'], $path . '.acronyms' );
			foreach ( array( 'docs', 'keywords_fa', 'keywords_en', 'acronyms' ) as $list_field ) {
				foreach ( $entry[ $list_field ] as $value ) {
					if ( ! is_string( $value ) ) {
						throw new UnexpectedValueException( $path . '.' . $list_field . ' must contain only strings' );
					}
				}
			}
			if ( ! is_string( $entry['snippet'] ) || mb_strlen( $entry['snippet'] ) > 2000 ) {
				throw new UnexpectedValueException( $path . '.snippet must be a bounded string' );
			}
		}
		return $artifact;
	}

	/**
	 * Validate release/09_glossary.json. Reviewer metadata (reviewer,
	 * review_mode, rationale) is deliberately NOT part of the validated
	 * contract the public layer may rely on; consumers must surface only
	 * approved term fields.
	 *
	 * @param array<string,mixed> $artifact Decoded glossary.
	 * @return array<string,mixed>
	 */
	public static function validate_glossary( array $artifact ): array {
		self::require_keys( $artifact, array( 'glossary_version', 'terms' ), 'glossary' );
		self::non_empty_string( $artifact['glossary_version'], 'glossary.glossary_version' );
		self::object_value( $artifact['terms'], 'glossary.terms' );
		foreach ( $artifact['terms'] as $key => $term ) {
			$path = 'glossary.terms[' . $key . ']';
			self::object_value( $term, $path );
			self::require_keys( $term, array( 'concept_id', 'english', 'preferred_fa', 'acronym', 'alternatives_fa', 'definition_fa', 'definition_en', 'domain', 'status', 'source_documents' ), $path );
			self::matches( '/^[A-Za-z][A-Za-z0-9_ ]{0,63}$/', $term['concept_id'], $path . '.concept_id' );
			self::non_empty_string( $term['english'], $path . '.english' );
			self::non_empty_string( $term['preferred_fa'], $path . '.preferred_fa' );
			if ( ! is_string( $term['acronym'] ) ) {
				throw new UnexpectedValueException( $path . '.acronym must be a string' );
			}
			foreach ( array( 'alternatives_fa', 'source_documents' ) as $list_field ) {
				self::list_value( $term[ $list_field ], $path . '.' . $list_field );
				foreach ( $term[ $list_field ] as $value ) {
					if ( ! is_string( $value ) ) {
						throw new UnexpectedValueException( $path . '.' . $list_field . ' must contain only strings' );
					}
				}
			}
			foreach ( array( 'definition_fa', 'definition_en', 'domain', 'status' ) as $string_field ) {
				if ( ! is_string( $term[ $string_field ] ) ) {
					throw new UnexpectedValueException( $path . '.' . $string_field . ' must be a string' );
				}
			}
			if ( ! in_array( $term['status'], array( 'approved', 'proposed', 'english_only' ), true ) ) {
				throw new UnexpectedValueException( $path . '.status has an unsupported value' );
			}
		}
		return $artifact;
	}

	/**
	 * Validate that the three artifact families describe the same edition/IDs.
	 *
	 * @param array<string,mixed> $book Master book.
	 * @param array<string,mixed> $content_ids Content ID registry.
	 * @param array<string,mixed> $citations Citation registry.
	 */
	public static function validate_bundle( array $book, array $content_ids, array $citations ): bool {
		self::validate_book( $book );
		self::validate_content_ids( $content_ids );
		self::validate_citations( $citations );
		self::same( $book['edition_id'], $content_ids['edition_id'], 'bundle content ID edition' );
		self::same( $book['edition_id'], $citations['edition_id'], 'bundle citation edition' );
		self::same( $book['units'], $content_ids['count'], 'bundle content ID count' );
		self::same( $book['units'], $citations['count'], 'bundle citation count' );
		foreach ( $citations['registry'] as $structural_id => $citation ) {
			if ( ! isset( $content_ids['ids'][ $citation['content_id'] ] ) ) {
				throw new UnexpectedValueException( 'Citation references an unknown content ID: ' . $structural_id );
			}
			self::same( $structural_id, $content_ids['ids'][ $citation['content_id'] ]['structural_id'], 'bundle citation structural ID' );
		}
		return true;
	}

	/** @param mixed $section */
	private static function validate_section( $section, string $path, string $edition, string $part_id, string $chapter_id ): void {
		self::object_value( $section, $path );
		self::require_keys( $section, array( 'structural_id', 'content_id', 'part_id', 'chapter_id', 'section_id', 'locale', 'title_fa', 'fa_text', 'origin', 'canonical_url', 'source_documents' ), $path );
		self::structural_id( $section['structural_id'], $path . '.structural_id', $edition );
		self::content_id( $section['content_id'], $path . '.content_id', $edition );
		self::same( $part_id, $section['part_id'], $path . '.part_id' );
		self::same( $chapter_id, $section['chapter_id'], $path . '.chapter_id' );
		self::matches( '/^S\d{2}$/', $section['section_id'], $path . '.section_id' );
		self::same( 'fa', $section['locale'], $path . '.locale' );
		self::non_empty_string( $section['title_fa'], $path . '.title_fa' );
		self::non_empty_string( $section['fa_text'], $path . '.fa_text' );
		self::origin( $section['origin'], $path . '.origin' );
		self::canonical_url( $section['canonical_url'], $section['structural_id'] );
		self::list_value( $section['source_documents'], $path . '.source_documents' );
	}

	/** @param array<string,mixed> $value @param string[] $keys */
	private static function require_keys( array $value, array $keys, string $path ): void {
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $value ) ) {
				throw new UnexpectedValueException( 'Missing required field ' . $path . '.' . $key );
			}
		}
	}

	/** @param mixed $value */
	private static function edition( $value, string $path ): string {
		self::matches( self::EDITION_PATTERN, $value, $path );
		return $value;
	}

	/** @param mixed $value */
	private static function structural_id( $value, string $path, string $edition ): void {
		self::matches( self::STRUCTURAL_PATTERN, $value, $path );
		if ( false === strpos( $value, 'KDJ-AI-' . $edition . '-' ) ) {
			throw new UnexpectedValueException( $path . ' belongs to another edition' );
		}
	}

	/** @param mixed $value */
	private static function content_id( $value, string $path, string $edition ): void {
		self::matches( self::CONTENT_PATTERN, $value, $path );
		if ( false === strpos( $value, 'KDJ-AI-' . $edition . '-' ) ) {
			throw new UnexpectedValueException( $path . ' belongs to another edition' );
		}
	}

	/** @param mixed $value */
	private static function origin( $value, string $path ): void {
		if ( ! is_string( $value ) || ! in_array( $value, self::ALLOWED_ORIGINS, true ) ) {
			throw new UnexpectedValueException( $path . ' has an unsupported content origin' );
		}
	}

	/** @param mixed $value */
	private static function canonical_url( $value, string $structural_id ): void {
		self::non_empty_string( $value, 'canonical_url' );
		$parts = parse_url( $value );
		if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || 'kohandezh.com' !== ( $parts['host'] ?? '' ) || false === strpos( $parts['path'] ?? '', '/fa/ai-book/' ) || ( $parts['fragment'] ?? '' ) !== $structural_id ) {
			throw new UnexpectedValueException( 'canonical_url must be a Kohandezh Persian book URL ending in the structural ID' );
		}
	}

	/** @param mixed $value */
	private static function slug( $value, string $path ): void {
		self::matches( '/^[a-z0-9]+(?:-[a-z0-9]+)*-?$/', $value, $path );
	}

	/** @param mixed $value */
	private static function non_empty_string( $value, string $path ): void {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			throw new UnexpectedValueException( $path . ' must be a non-empty string' );
		}
	}

	/** @param mixed $value */
	private static function positive_int( $value, string $path ): void {
		if ( ! is_int( $value ) || $value < 1 ) {
			throw new UnexpectedValueException( $path . ' must be a positive integer' );
		}
	}

	/** @param mixed $value */
	private static function non_negative_int( $value, string $path ): void {
		if ( ! is_int( $value ) || $value < 0 ) {
			throw new UnexpectedValueException( $path . ' must be a non-negative integer' );
		}
	}

	/** @param mixed $value */
	private static function list_value( $value, string $path ): void {
		if ( ! is_array( $value ) || array_values( $value ) !== $value ) {
			throw new UnexpectedValueException( $path . ' must be a JSON array/list' );
		}
	}

	/** @param mixed $value */
	private static function object_value( $value, string $path ): void {
		if ( ! is_array( $value ) ) {
			throw new UnexpectedValueException( $path . ' must be a JSON object' );
		}
	}

	/** @param mixed $value */
	private static function matches( string $pattern, $value, string $path ): void {
		if ( ! is_string( $value ) || 1 !== preg_match( $pattern, $value ) ) {
			throw new UnexpectedValueException( $path . ' has an invalid format' );
		}
	}

	/** @param mixed $expected @param mixed $actual */
	private static function same( $expected, $actual, string $path ): void {
		if ( $expected !== $actual ) {
			throw new UnexpectedValueException( $path . ' does not match its required value' );
		}
	}
}
