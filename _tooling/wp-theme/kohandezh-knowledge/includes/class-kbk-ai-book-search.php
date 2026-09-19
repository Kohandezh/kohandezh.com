<?php
/**
 * KBK_AI_Book_Search — bounded Persian/English search over validated canonical artifacts.
 *
 * Consumes the pipeline's purpose-built search index (`html/search/index.json`,
 * one bounded entry per canonical section) plus the approved glossary
 * (`release/09_glossary.json`). Section results carry only the pipeline-authored
 * bounded snippet — never a section's full body text — and glossary results
 * never expose reviewer metadata (reviewer, review_mode, rationale) or
 * rejected term aliases. The glossary's approved alternatives participate in
 * matching; rejected aliases never do.
 *
 * No Elasticsearch/OpenSearch/external service: the whole corpus is 479
 * bounded entries + 435 terms, so an in-memory normalized linear scan is the
 * simplest solution compatible with the existing PHP/WordPress architecture.
 *
 * @package Kohandezh_Knowledge
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'KBK_TESTING' ) ) {
	exit;
}

final class KBK_AI_Book_Search {

	const MIN_QUERY_CHARS    = 2;
	const MAX_QUERY_CHARS    = 80;
	const MAX_QUERY_TOKENS   = 12;
	const DEFAULT_LIMIT      = 20;
	const MAX_LIMIT          = 50;
	const MAX_LISTED_DOCS    = 3;

	const STATUS_READY            = 'READY';
	const STATUS_ARTIFACT_INVALID = 'ARTIFACT_INVALID';

	const STRUCTURAL_ID_PATTERN = '/^KDJ-AI-\d{4}E\d+-(P\d{2})-(C\d{2})-S\d{2}$/';

	/** @var KBK_AI_Book_Repository */
	private $repository;

	/** @var string|null */
	private $index_path;

	/** @var string|null */
	private $glossary_path;

	/** @var string */
	private $status;

	/** @var array<int,array<string,mixed>>|null */
	private $entries;

	/** @var array<string,array<string,mixed>>|null */
	private $terms;

	/** @var array<string,array<string,mixed>> */
	private $parts = array();

	/** @var array<string,array<string,mixed>> */
	private $chapters = array();

	public function __construct( KBK_AI_Book_Repository $repository, ?string $index_path = null, ?string $glossary_path = null ) {
		$this->repository    = $repository;
		$this->index_path    = $index_path;
		$this->glossary_path = $glossary_path;
		$this->status        = self::STATUS_READY;
	}

	public function status(): string {
		return $this->status;
	}

	/**
	 * Run a bounded search. All failures fail closed to an empty result set
	 * plus a status code; they never throw past this method.
	 *
	 * @return array{status:string,query:?string,tokens:int,results:array<int,array<string,mixed>>,total:int,limit:int,truncated:bool}
	 */
	public function search( string $raw_query, int $limit = self::DEFAULT_LIMIT ): array {
		$out = array(
			'status'    => self::STATUS_READY,
			'query'     => null,
			'tokens'    => 0,
			'results'   => array(),
			'total'     => 0,
			'limit'     => self::DEFAULT_LIMIT,
			'truncated' => false,
		);
		if ( ! $this->load() ) {
			$out['status'] = $this->status;
			return $out;
		}
		$limit        = max( 1, min( self::MAX_LIMIT, $limit ) );
		$out['limit'] = $limit;

		$parsed = self::parse_query( $raw_query );
		if ( '' === $parsed['display'] || empty( $parsed['tokens'] ) ) {
			return $out;
		}
		$out['query']  = $parsed['display'];
		$out['tokens'] = count( $parsed['tokens'] );

		// Exact-ID lookup mode: a content/structural ID (or ID prefix)
		// short-circuits free-text scoring entirely.
		$total           = 0;
		$id_results      = $this->search_by_identifier( $parsed['display'], $limit, $total );
		if ( null !== $id_results ) {
			$out['results']   = $id_results;
			$out['total']     = $total;
			$out['truncated'] = $total > count( $id_results );
			return $out;
		}

		$matches = array();
		$this->score_parts( $parsed, $matches );
		$this->score_chapters( $parsed, $matches );
		$this->score_sections( $parsed, $matches );
		$this->score_glossary( $parsed, $matches );

		$out['total']     = count( $matches );
		uasort( $matches, array( $this, 'rank' ) );
		$sorted  = array_values( $matches );
		$results = array_slice( $sorted, 0, $limit );
		// Glossary representation rule: when matches overflow the page and no
		// glossary card survived the slice, the best glossary match takes the
		// last slot — the dictionary view must never be crowded out entirely
		// by section text matches. When everything fits, no slot is displaced.
		if ( $out['total'] > $limit ) {
			$has_glossary = false;
			foreach ( $results as $result ) {
				if ( 'glossary' === $result['type'] ) {
					$has_glossary = true;
					break;
				}
			}
			if ( ! $has_glossary ) {
				foreach ( $sorted as $result ) {
					if ( 'glossary' === $result['type'] ) {
						$results[ count( $results ) - 1 ] = $result;
						break;
					}
				}
			}
		}
		$out['results']   = $results;
		$out['truncated'] = $out['total'] > count( $results );
		return $out;
	}

	/**
	 * Safe public view model for one glossary term — the deep-link target of
	 * glossary search results. Reviewer metadata is never included.
	 *
	 * @return array<string,mixed>|null
	 */
	public function find_term( string $concept_id ): ?array {
		$concept_id = strtoupper( $concept_id );
		if ( ! $this->load() || 1 !== preg_match( '/^[A-Z][A-Z0-9_]{0,63}$/', $concept_id ) || ! isset( $this->terms[ $concept_id ] ) ) {
			return null;
		}
		return $this->public_term( $this->terms[ $concept_id ], array() );
	}

	/**
	 * Parse and hygienize a raw query into a display string, match tokens and
	 * the normalized phrase. Never throws on malformed input.
	 *
	 * @return array{display:string,tokens:string[],phrase:string}
	 */
	public static function parse_query( string $raw_query ): array {
		$q = str_replace( "\0", '', $raw_query );
		$clean = (string) preg_replace( '/[\x00-\x1F\x7F]/u', ' ', $q );
		if ( null === $clean ) {
			// Invalid UTF-8 input: fall back to printable ASCII only.
			$clean = (string) preg_replace( '/[^\x20-\x7E]/', '', $q );
		}
		$q = trim( $clean );
		if ( 1 === preg_match( '/^.{0,' . self::MAX_QUERY_CHARS . '}/us', $q, $m ) ) {
			$q = rtrim( $m[0] );
		} else {
			$q = substr( (string) preg_replace( '/[^\x20-\x7E]/', '', $q ), 0, self::MAX_QUERY_CHARS );
		}
		$normalized = self::normalize( $q );
		$tokens     = self::tokenize( $normalized );
		if ( mb_strlen( $normalized ) < self::MIN_QUERY_CHARS ) {
			$tokens = array();
		}
		return array(
			'display' => $q,
			'tokens'  => array_slice( array_values( array_unique( $tokens ) ), 0, self::MAX_QUERY_TOKENS ),
			'phrase'  => $normalized,
		);
	}

	/** Persian/English-aware normalization shared by queries and indexed text. */
	public static function normalize( string $text ): string {
		$text = strtr( $text, array(
			'ي' => 'ی', 'ك' => 'ک', 'ة' => 'ه', 'أ' => 'ا', 'إ' => 'ا', 'ٱ' => 'ا',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
			'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
			'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		) );
		// Arabic diacritics, superscript alef, tatweel, zero-width marks/joiners,
		// and Unicode directional isolates.
		$text = (string) preg_replace( '/[\x{064B}-\x{0652}\x{0670}\x{0640}\x{200C}-\x{200F}\x{2066}-\x{2069}]/u', '', $text );
		return strtolower( trim( $text ) );
	}

	/** @return string[] */
	private static function tokenize( string $normalized ): array {
		if ( '' === $normalized ) {
			return array();
		}
		$parts = preg_split( '/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $parts ) ? $parts : array();
	}

	/**
	 * Identifier lookup mode. Returns null when the query is not an ID shape
	 * (normal scoring runs); returns an array (possibly empty) when it is.
	 *
	 * @param int $total Receives the unbounded match count.
	 * @return array<int,array<string,mixed>>|null
	 */
	private function search_by_identifier( string $display, int $limit, &$total ) {
		$id = strtoupper( str_replace( ' ', '', $display ) );
		$is_content    = 1 === preg_match( '/^KDJ-AI-\d{4}E\d+-P\d{2}-C\d{2}-S\d{2}-[A-F0-9]{8}$/', $id );
		$is_structural = 1 === preg_match( self::STRUCTURAL_ID_PATTERN, $id );
		$is_prefix     = 1 === preg_match( '/^KDJ-AI-\d{4}E\d+(-P\d{2}(-C\d{2})?)?$/', $id );
		if ( ! $is_content && ! $is_structural && ! $is_prefix ) {
			return null;
		}
		$results = array();
		foreach ( $this->entries as $entry ) {
			$structural = strtoupper( $entry['entry']['structural_id'] );
			$content    = strtoupper( $entry['entry']['id'] );
			$hit = ( $is_content && $content === $id )
				|| ( ( $is_structural || $is_content ) && $structural === $id )
				|| ( $is_prefix && 0 === strpos( $structural, $id ) );
			if ( $hit ) {
				$results[] = $this->section_result( $entry, 100, array() );
			}
		}
		$total = count( $results );
		return array_slice( $results, 0, $limit );
	}

	/**
	 * @param array<string,mixed>                $parsed
	 * @param array<string,array<string,mixed>>  $matches
	 */
	private function score_parts( array $parsed, array &$matches ): void {
		foreach ( $this->parts as $part_id => $part ) {
			// High phrase bonus: an exact part-title query must outrank chapter
			// cards whose own titles merely contain the part title (P01/C01).
			$score = $this->title_score( $parsed, $part['haystacks'], 2, 70 );
			if ( $score > 0 ) {
				$matches[ 'part:' . $part_id ] = array(
					'type'             => 'part',
					'order'            => 0,
					'score'            => $score,
					'part'             => array( 'id' => $part['part_id'], 'title_fa' => $part['title_fa'] ),
					'title'            => $part['title_fa'],
					'chapters'         => $part['chapters'],
					'snippet_segments' => array(),
				);
			}
		}
	}

	/**
	 * @param array<string,mixed>                $parsed
	 * @param array<string,array<string,mixed>>  $matches
	 */
	private function score_chapters( array $parsed, array &$matches ): void {
		foreach ( $this->chapters as $key => $chapter ) {
			$score = $this->title_score( $parsed, $chapter['haystacks'], 3, 55 );
			if ( $score > 0 ) {
				$matches[ 'chapter:' . $key ] = array(
					'type'             => 'chapter',
					'order'            => 1,
					'score'            => $score,
					'part'             => array( 'id' => $chapter['part_id'], 'title_fa' => $chapter['part_title'] ),
					'chapter'          => array( 'id' => $chapter['chapter_id'], 'title_fa' => $chapter['title_fa'], 'origin' => $chapter['origin'] ),
					'title'            => $chapter['title_fa'],
					'snippet_segments' => array(),
				);
			}
		}
	}

	/**
	 * @param array<string,mixed>                $parsed
	 * @param array<string,array<string,mixed>>  $matches
	 */
	private function score_sections( array $parsed, array &$matches ): void {
		foreach ( $this->entries as $index => $entry ) {
			$score = 0;
			foreach ( $parsed['tokens'] as $token ) {
				$token_score = 0;
				if ( false !== strpos( $entry['haystacks']['title'], $token ) ) {
					$token_score = 10;
				}
				if ( false !== strpos( $entry['haystacks']['title_en'], $token ) ) {
					$token_score = max( $token_score, 7 );
				}
				if ( false !== strpos( $entry['haystacks']['acronyms'], ' ' . $token . ' ' ) ) {
					$token_score = max( $token_score, 8 );
				}
				if ( false !== strpos( $entry['haystacks']['keywords'], $token ) ) {
					$token_score = max( $token_score, 4 );
				}
				if ( false !== strpos( $entry['haystacks']['docs'], $token ) ) {
					$token_score = max( $token_score, 4 );
				}
				if ( false !== strpos( $entry['haystacks']['chapter'], $token ) ) {
					$token_score = max( $token_score, 3 );
				}
				if ( false !== strpos( $entry['haystacks']['part'], $token ) ) {
					$token_score = max( $token_score, 2 );
				}
				if ( 0 === $token_score && false !== strpos( $entry['haystacks']['body'], $token ) ) {
					$token_score = min( 3, max( 1, substr_count( $entry['haystacks']['body'], $token ) ) );
				}
				if ( 0 === $token_score ) {
					$score = 0;
					break;
				}
				$score += $token_score;
			}
			if ( 0 === $score ) {
				continue;
			}
			if ( '' !== $parsed['phrase'] && false !== strpos( $entry['haystacks']['title'], $parsed['phrase'] ) ) {
				$score += 4;
			}
			$matches[ 'section:' . $index ] = $this->section_result( $entry, $score, $parsed['tokens'] );
		}
	}

	/**
	 * @param array<string,mixed>                $parsed
	 * @param array<string,array<string,mixed>>  $matches
	 */
	private function score_glossary( array $parsed, array &$matches ): void {
		foreach ( $this->terms as $term ) {
			$score = 0;
			foreach ( $parsed['tokens'] as $token ) {
				$token_score = 0;
				// Exact preferred-term equality outranks substring containment
				// («ریسک» = RISK beats «ارزیابی ریسک …»), so the dictionary
				// entry the user literally asked for wins the tie.
				$haystack = $term['haystacks']['preferred_fa'];
				if ( $haystack === $token ) {
					$token_score = 14;
				} elseif ( '' !== $haystack && false !== strpos( $haystack, $token ) ) {
					$token_score = 12;
				}
				if ( false !== strpos( $term['haystacks']['english'], $token ) ) {
					$token_score = max( $token_score, 10 );
				}
				if ( false !== strpos( $term['haystacks']['acronyms'], ' ' . $token . ' ' ) ) {
					$token_score = max( $token_score, 12 );
				}
				if ( false !== strpos( $term['haystacks']['alternatives'], $token ) ) {
					$token_score = max( $token_score, 7 );
				}
				if ( 0 === $token_score && false !== strpos( $term['haystacks']['definition'], $token ) ) {
					$token_score = 2;
				}
				if ( 0 === $token_score ) {
					$score = 0;
					break;
				}
				$score += $token_score;
			}
			// A bare concept-ID token (RISK, ARTIFICIAL_INTELLIGENCE) is an ID query too.
			if ( 0 === $score && 1 === count( $parsed['tokens'] ) && strtoupper( $term['concept_id'] ) === strtoupper( $parsed['tokens'][0] ) ) {
				$score = 20;
			}
			if ( 0 === $score ) {
				continue;
			}
			$matches[ 'glossary:' . $term['concept_id'] ] = $this->public_term( $term, $parsed['tokens'] ) + array(
				'type'  => 'glossary',
				'order' => 2,
				'score' => $score,
			);
		}
	}

	/**
	 * Weighted title-field scoring shared by part/chapter results: every token
	 * must occur in at least one haystack (AND semantics); full-phrase
	 * presence adds a bonus.
	 *
	 * @param array<string,mixed>   $parsed
	 * @param array<string,string>  $haystacks
	 */
	private function title_score( array $parsed, array $haystacks, int $weight, int $phrase_bonus ): int {
		$score = 0;
		foreach ( $parsed['tokens'] as $token ) {
			$token_score = 0;
			foreach ( $haystacks as $haystack ) {
				if ( '' !== $haystack && false !== strpos( $haystack, $token ) ) {
					$token_score = $weight;
					break;
				}
			}
			if ( 0 === $token_score ) {
				return 0;
			}
			$score += $token_score;
		}
		if ( '' !== $parsed['phrase'] ) {
			foreach ( $haystacks as $haystack ) {
				if ( '' !== $haystack && false !== strpos( $haystack, $parsed['phrase'] ) ) {
					$score += $phrase_bonus;
					break;
				}
			}
		}
		return $score;
	}

	/** @param array<string,mixed> $a @param array<string,mixed> $b */
	private function rank( array $a, array $b ): int {
		if ( $a['score'] !== $b['score'] ) {
			return $a['score'] > $b['score'] ? -1 : 1;
		}
		if ( $a['order'] !== $b['order'] ) {
			return $a['order'] < $b['order'] ? -1 : 1;
		}
		return strcmp( (string) $a['title'], (string) $b['title'] );
	}

	/**
	 * Safe section result view model. Only the pipeline-authored bounded
	 * snippet is ever emitted — never a section's full body text.
	 *
	 * @param string[] $tokens
	 * @return array<string,mixed>
	 */
	private function section_result( array $entry, int $score, array $tokens ): array {
		$record = $entry['entry'];
		return array(
			'type'             => 'section',
			'order'            => 3,
			'score'            => $score,
			'title'            => $record['title_fa'],
			'title_en'         => (string) ( $record['title_en'] ?? '' ),
			'part'             => array( 'id' => $entry['part_id'], 'title_fa' => $entry['part_title'] ),
			'chapter'          => array( 'id' => $entry['chapter_id'], 'title_fa' => $entry['chapter_title'] ),
			'section'          => array( 'structural_id' => $record['structural_id'], 'content_id' => $record['id'] ),
			'origin'           => $record['origin'],
			'docs'             => array_slice( $record['docs'], 0, self::MAX_LISTED_DOCS ),
			'snippet_segments' => self::highlight( (string) $record['snippet'], $tokens ),
			'canonical_url'    => $record['url'],
		);
	}

	/**
	 * Safe public glossary view model. Reviewer fields (reviewer, review_mode,
	 * rationale, rejected aliases) are dropped here by construction.
	 *
	 * @param string[] $tokens
	 * @return array<string,mixed>
	 */
	private function public_term( array $term, array $tokens ): array {
		$body = $term['definition_fa'];
		if ( '' === $body ) {
			$body = $term['definition_en'];
		}
		return array(
			'title'            => $term['preferred_fa'],
			'english'          => $term['english'],
			'acronym'          => $term['acronym'],
			'domain'           => $term['domain'],
			'status'           => $term['status'],
			'concept_id'       => $term['concept_id'],
			'alternatives_fa'  => $term['alternatives_fa'],
			'source_documents' => array_slice( $term['source_documents'], 0, self::MAX_LISTED_DOCS ),
			'snippet_segments' => self::highlight( $body, $tokens ),
		);
	}

	/**
	 * Split text into segments, marking tokens that match the query tokens.
	 * Matching compares normalized forms and additionally accepts token
	 * prefixes (Persian affix morphology) for query tokens of 3+ characters.
	 *
	 * @param string[] $tokens
	 * @return array<int,array{text:string,mark:bool}>
	 */
	public static function highlight( string $text, array $tokens ): array {
		if ( '' === $text ) {
			return array();
		}
		$exact    = array();
		$prefixes = array();
		foreach ( $tokens as $token ) {
			$normalized = self::normalize( $token );
			if ( '' === $normalized ) {
				continue;
			}
			$exact[ $normalized ] = true;
			if ( mb_strlen( $normalized ) >= 3 ) {
				$prefixes[] = $normalized;
			}
		}
		$pieces = preg_split( '/([^\p{L}\p{N}]+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $pieces ) ) {
			return array( array( 'text' => $text, 'mark' => false ) );
		}
		$segments = array();
		foreach ( $pieces as $piece ) {
			if ( '' === $piece ) {
				continue;
			}
			$mark = false;
			$normalized = self::normalize( $piece );
			if ( '' !== $normalized && isset( $exact[ $normalized ] ) ) {
				$mark = true;
			} elseif ( '' !== $normalized && mb_strlen( $normalized ) >= 3 ) {
				foreach ( $prefixes as $prefix ) {
					if ( 0 === strpos( $normalized, $prefix ) ) {
						$mark = true;
						break;
					}
				}
			}
			$segments[] = array( 'text' => $piece, 'mark' => $mark );
		}
		return $segments;
	}

	/** Load, validate and prepare both artifacts once. Fails closed to false. */
	private function load(): bool {
		if ( null !== $this->entries ) {
			return self::STATUS_READY === $this->status;
		}
		$this->entries = array();
		$this->terms   = array();
		try {
			$root     = $this->repository->root();
			$bundle   = $this->repository->bundle();
			$index    = KBK_AI_Book_Artifacts::load_json_file( $this->index_path ?? ( $root . DIRECTORY_SEPARATOR . 'html' . DIRECTORY_SEPARATOR . 'search' . DIRECTORY_SEPARATOR . 'index.json' ) );
			$glossary = KBK_AI_Book_Artifacts::load_json_file( $this->glossary_path ?? ( $root . DIRECTORY_SEPARATOR . 'release' . DIRECTORY_SEPARATOR . '09_glossary.json' ) );
			KBK_AI_Book_Artifacts::validate_search_index( $index );
			KBK_AI_Book_Artifacts::validate_glossary( $glossary );
			if ( $bundle['book']['units'] !== count( $index['entries'] ) ) {
				throw new UnexpectedValueException( 'Search index and book disagree on the section count' );
			}
			$this->prepare_index( $index );
			$this->prepare_glossary( $glossary );
			$this->prepare_structure();
		} catch ( UnexpectedValueException | InvalidArgumentException $error ) {
			$this->status = self::STATUS_ARTIFACT_INVALID;
			return false;
		}
		return true;
	}

	/** @param array<string,mixed> $index */
	private function prepare_index( array $index ): void {
		foreach ( $index['entries'] as $entry ) {
			$structural = (string) $entry['structural_id'];
			if ( ! preg_match( self::STRUCTURAL_ID_PATTERN, $structural, $m ) ) {
				throw new UnexpectedValueException( 'Search index entry has an unusable structural ID: ' . $structural );
			}
			if ( null === $this->repository->find_section( $structural ) ) {
				throw new UnexpectedValueException( 'Search index references an unknown section: ' . $structural );
			}
			$this->entries[] = array(
				'entry'         => $entry,
				'part_id'       => $m[1],
				'chapter_id'    => $m[2],
				'part_title'    => (string) $entry['part'],
				'chapter_title' => (string) $entry['chapter'],
				'haystacks'     => array(
					'title'    => self::normalize( (string) $entry['title_fa'] ),
					'title_en' => self::normalize( (string) ( $entry['title_en'] ?? '' ) ),
					'keywords' => self::normalize( implode( ' ', array_merge( $entry['keywords_fa'], $entry['keywords_en'] ) ) ),
					'acronyms' => ' ' . self::normalize( implode( ' ', $entry['acronyms'] ) ) . ' ',
					'docs'     => self::normalize( strtolower( implode( ' ', $entry['docs'] ) ) ),
					'chapter'  => self::normalize( (string) $entry['chapter'] ),
					'part'     => self::normalize( (string) $entry['part'] ),
					'body'     => self::normalize( (string) $entry['snippet'] ),
				),
			);
		}
	}

	/** @param array<string,mixed> $glossary */
	private function prepare_glossary( array $glossary ): void {
		foreach ( $glossary['terms'] as $key => $term ) {
			$concept_id = strtoupper( (string) $term['concept_id'] );
			if ( 1 !== preg_match( '/^[A-Z][A-Z0-9_]{0,63}$/', $concept_id ) ) {
				throw new UnexpectedValueException( 'Glossary term has an unusable concept ID: ' . $key );
			}
			$this->terms[ $concept_id ] = array(
				'concept_id'       => $concept_id,
				'preferred_fa'     => (string) $term['preferred_fa'],
				'english'          => (string) $term['english'],
				'acronym'          => (string) $term['acronym'],
				'alternatives_fa'  => $term['alternatives_fa'],
				'definition_fa'    => (string) $term['definition_fa'],
				'definition_en'    => (string) $term['definition_en'],
				'domain'           => (string) $term['domain'],
				'status'           => (string) $term['status'],
				'source_documents' => $term['source_documents'],
				'haystacks'        => array(
					'preferred_fa' => self::normalize( (string) $term['preferred_fa'] ),
					'english'      => self::normalize( (string) $term['english'] ),
					'acronyms'     => ' ' . self::normalize( (string) $term['acronym'] ) . ' ',
					'alternatives' => self::normalize( implode( ' ', $term['alternatives_fa'] ) ),
					'definition'   => self::normalize( (string) $term['definition_fa'] . ' ' . (string) $term['definition_en'] ),
				),
			);
		}
	}

	private function prepare_structure(): void {
		foreach ( $this->repository->parts() as $part ) {
			$this->parts[ $part['part_id'] ] = array(
				'part_id'   => $part['part_id'],
				'title_fa'  => $part['title_fa'],
				'chapters'  => count( $part['chapters'] ),
				'haystacks' => array(
					'title'    => self::normalize( (string) $part['title_fa'] ),
					'title_en' => self::normalize( (string) ( $part['title_en'] ?? '' ) ),
				),
			);
			foreach ( $part['chapters'] as $chapter ) {
				$this->chapters[ $part['part_id'] . '/' . $chapter['chapter_id'] ] = array(
					'part_id'    => $part['part_id'],
					'part_title' => $part['title_fa'],
					'chapter_id' => $chapter['chapter_id'],
					'title_fa'   => $chapter['title_fa'],
					'origin'     => $chapter['origin'],
					'haystacks'  => array(
						'title'    => self::normalize( (string) $chapter['title_fa'] ),
						'title_en' => self::normalize( (string) ( $chapter['title_en'] ?? '' ) ),
					),
				);
			}
		}
	}
}
