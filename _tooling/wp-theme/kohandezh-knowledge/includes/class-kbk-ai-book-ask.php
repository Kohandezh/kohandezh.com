<?php
/**
 * KBK_AI_Book_Ask — cited Ask/RAG over the release RAG corpus.
 *
 * Retrieval is local and always available: a bounded normalized scan over
 * `release/14_rag_corpus_fa.jsonl` (validated, fail-closed) that returns
 * capped chunk excerpts with repository-resolvable citations. Answer
 * generation is strictly configuration-required: without a provider
 * endpoint/key the view honestly shows the cited passages only; with one,
 * the provider response must validate (bounded text, used IDs ⊆ offered
 * citations) or it is discarded — unvalidated text never renders
 * (ADR-AB-0013).
 *
 * @package Kohandezh_Knowledge
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'KBK_TESTING' ) ) {
	exit;
}

final class KBK_AI_Book_Ask {

	const STATUS_READY            = 'READY';
	const STATUS_ARTIFACT_INVALID = 'ARTIFACT_INVALID';
	const MAX_RETRIEVAL           = 6;
	const MAX_EXCERPT_CHARS       = 360;
	const MAX_ANSWER_CHARS        = 4000;
	const MIN_QUERY_TOKENS        = 2;

	/** @var KBK_AI_Book_Repository */
	private $repository;

	/** @var string|null */
	private $corpus_path;

	/** @var string */
	private $status = self::STATUS_READY;

	/** @var bool */
	private $loaded = false;

	/** @var array<int,array<string,mixed>> */
	private $chunks = array();

	/** @var array{endpoint:string,api_key:string,model:string}|null */
	private $provider;

	public function __construct( KBK_AI_Book_Repository $repository, ?string $corpus_path = null, ?array $provider = null ) {
		$this->repository = $repository;
		$this->corpus_path = $corpus_path;
		$this->provider    = $provider;
	}

	public function status(): string {
		$this->load();
		return $this->status;
	}

	public function provider_configured(): bool {
		return null !== $this->provider && '' !== $this->provider['endpoint'] && '' !== $this->provider['api_key'];
	}

	/**
	 * One bounded ask: sanitized query → retrieval → (optional) validated
	 * provider answer. Every state is honest; nothing is guessed.
	 *
	 * @return array<string,mixed>
	 */
	public function ask( string $raw_query ): array {
		$parsed = KBK_AI_Book_Search::parse_query( $raw_query );
		$out    = array(
			'status'         => 'READY',
			'query'          => '' === $parsed['display'] ? null : $parsed['display'],
			'tokens'         => 0,
			'provider'       => $this->provider_configured() ? 'configured' : 'PROVIDER_REQUIRED',
			'provider_error' => null,
			'retrieval'      => array(),
			'answer'         => null,
		);
		if ( ! $this->load() ) {
			$out['status'] = self::STATUS_ARTIFACT_INVALID;
			$out['provider'] = null;
			return $out;
		}
		$out['tokens'] = count( $parsed['tokens'] );
		if ( $out['tokens'] < self::MIN_QUERY_TOKENS ) {
			return $out; // honest empty state for too-short queries
		}
		$out['retrieval'] = $this->retrieve( $parsed['tokens'], $parsed['phrase'] );
		if ( ! $this->provider_configured() || array() === $out['retrieval'] ) {
			return $out;
		}
		$offered = array();
		foreach ( $out['retrieval'] as $item ) {
			$offered[ $item['content_id'] ] = true;
		}
		$response = $this->call_provider( $parsed['display'], $out['retrieval'] );
		if ( ! is_array( $response ) ) {
			$out['provider_error'] = is_string( $response ) ? $response : 'PROVIDER_UNAVAILABLE';
			return $out;
		}
		try {
			$out['answer'] = self::validate_provider_response( $response, array_keys( $offered ) );
		} catch ( UnexpectedValueException $error ) {
			$out['provider_error'] = 'PROVIDER_INVALID';
		}
		return $out;
	}

	/**
	 * Validate a provider response into the safe answer model. The used-ID
	 * rule is the citation contract: the provider may only reference chunks
	 * that were actually offered.
	 *
	 * @param array<string,mixed> $response   Decoded provider body.
	 * @param array<int,string>   $allowed_ids Content IDs offered as context.
	 * @return array<string,mixed>
	 * @throws UnexpectedValueException On any contract violation.
	 */
	public static function validate_provider_response( array $response, array $allowed_ids ): array {
		$path = 'ask.response';
		if ( ! isset( $response['answer'] ) || ! is_string( $response['answer'] ) ) {
			throw new UnexpectedValueException( $path . '.answer must be a string' );
		}
		$answer = trim( $response['answer'] );
		if ( '' === $answer || mb_strlen( $answer ) > self::MAX_ANSWER_CHARS ) {
			throw new UnexpectedValueException( $path . '.answer must be a bounded non-empty string' );
		}
		if ( 1 === preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $answer ) ) {
			throw new UnexpectedValueException( $path . '.answer must not contain control characters' );
		}
		if ( ! isset( $response['used'] ) || ! is_array( $response['used'] ) ) {
			throw new UnexpectedValueException( $path . '.used must be a list' );
		}
		$allowed = array_fill_keys( $allowed_ids, true );
		$used    = array();
		foreach ( $response['used'] as $content_id ) {
			if ( ! is_string( $content_id ) || ! isset( $allowed[ $content_id ] ) ) {
				throw new UnexpectedValueException( $path . '.used references an ID outside the offered citations' );
			}
			if ( ! isset( $used[ $content_id ] ) ) {
				$used[ $content_id ] = true;
			}
		}
		if ( isset( $response['model'] ) && ( ! is_string( $response['model'] ) || mb_strlen( $response['model'] ) > 80 ) ) {
			throw new UnexpectedValueException( $path . '.model must be a bounded string' );
		}
		return array(
			'text'  => $answer,
			'used'  => array_keys( $used ),
			'model' => (string) ( $response['model'] ?? '' ),
		);
	}

	/**
	 * Score and cap the corpus for one query. AND token semantics; weights:
	 * label/section 8, keywords 4, body hits ≤3, label phrase bonus +6.
	 *
	 * @param array<int,string> $tokens    Normalized query tokens.
	 * @param string            $normalized Full normalized query.
	 * @return array<int,array<string,mixed>>
	 */
	private function retrieve( array $tokens, string $normalized ): array {
		$scored = array();
		foreach ( $this->chunks as $chunk ) {
			$label_hits    = 0;
			$keyword_hits  = 0;
			$body_hits     = 0;
			$satisfied     = true;
			foreach ( $tokens as $token ) {
				$hit = false;
				if ( false !== strpos( $chunk['label_norm'], $token ) ) {
					$label_hits++;
					$hit = true;
				}
				if ( false !== strpos( $chunk['keywords_norm'], $token ) ) {
					$keyword_hits++;
					$hit = true;
				}
				if ( false !== strpos( $chunk['body_norm'], $token ) ) {
					$body_hits++;
					$hit = true;
				}
				if ( ! $hit ) {
					$satisfied = false;
					break;
				}
			}
			if ( ! $satisfied ) {
				continue;
			}
			$score = $label_hits * 8 + $keyword_hits * 4 + min( 3, $body_hits );
			if ( '' !== $normalized && false !== strpos( $chunk['label_norm'], $normalized ) ) {
				$score += 6;
			}
			$scored[] = array( $score, $chunk['order'], $chunk );
		}
		if ( array() === $scored ) {
			return array();
		}
		usort( $scored, static function ( array $a, array $b ): int {
			return $b[0] <=> $a[0] ?: $a[1] <=> $b[1];
		} );
		$results = array();
		foreach ( array_slice( $scored, 0, self::MAX_RETRIEVAL ) as $entry ) {
			$chunk   = $entry[2];
			$section = $this->repository->find_section( $chunk['content_id'] );
			$excerpt = mb_substr( $chunk['fa_text'], 0, self::MAX_EXCERPT_CHARS );
			$results[] = array(
				'chunk_id'          => $chunk['chunk_id'],
				'content_id'        => $chunk['content_id'],
				'structural_id'     => $chunk['structural_id'],
				'label_fa'          => $chunk['label_fa'],
				'section_fa'        => $chunk['section_fa'],
				'part_id'           => $chunk['part_id'],
				'chapter_id'        => $chunk['chapter_id'],
				'origin'            => $chunk['origin'],
				'excerpt_segments'  => KBK_AI_Book_Search::highlight( $excerpt, $tokens ),
				'citation_url'      => $chunk['citation_url'],
				'source_documents'  => $chunk['source_documents'],
				'reader_resolvable' => null !== $section,
			);
		}
		return $results;
	}

	/**
	 * Call the configured provider. Returns the decoded body array, or a
	 * string error code. Never throws; never logs the API key.
	 *
	 * @param array<int,array<string,mixed>> $retrieval Retrieval models.
	 * @return array<string,mixed>|string
	 */
	private function call_provider( string $question, array $retrieval ) {
		$contexts = array();
		foreach ( $retrieval as $item ) {
			$plain = '';
			foreach ( $item['excerpt_segments'] as $segment ) {
				$plain .= $segment['text'];
			}
			$contexts[] = array(
				'content_id' => $item['content_id'],
				'title_fa'   => $item['section_fa'],
				'text'       => $plain,
			);
		}
		$payload = wp_json_encode( array(
			'model'    => $this->provider['model'],
			'messages' => array(
				array( 'role' => 'system', 'content' => 'You answer questions using ONLY the provided Persian book passages. Respond in Persian. Cite passages by their content_id.' ),
				array( 'role' => 'user', 'content' => wp_json_encode( array( 'question' => $question, 'passages' => $contexts ) ) ),
			),
		) );
		$response = wp_remote_post( $this->provider['endpoint'], array(
			'timeout'  => 30,
			'headers'  => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->provider['api_key'],
			),
			'body'     => (string) $payload,
		) );
		if ( is_wp_error( $response ) ) {
			return 'PROVIDER_UNAVAILABLE';
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return 'PROVIDER_UNAVAILABLE';
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return 'PROVIDER_INVALID';
		}
		return $body;
	}

	/** Load and validate the RAG corpus once. Fails closed. */
	private function load(): bool {
		if ( $this->loaded ) {
			return self::STATUS_READY === $this->status;
		}
		$this->loaded = true;
		try {
			$path    = $this->corpus_path ?? ( $this->repository->root() . DIRECTORY_SEPARATOR . 'release' . DIRECTORY_SEPARATOR . '14_rag_corpus_fa.jsonl' );
			$edition = $this->repository->bundle()['book']['edition_id'];
			if ( ! is_file( $path ) || ! is_readable( $path ) ) {
				throw new UnexpectedValueException( 'RAG corpus is not a readable file' );
			}
			$handle = fopen( $path, 'r' );
			if ( false === $handle ) {
				throw new UnexpectedValueException( 'RAG corpus could not be opened' );
			}
			$order = 0;
			try {
				while ( false !== ( $line = fgets( $handle ) ) ) {
					$line = trim( $line );
					if ( '' === $line ) {
						continue;
					}
					$chunk = json_decode( $line, true );
					if ( ! is_array( $chunk ) ) {
						throw new UnexpectedValueException( 'RAG corpus contains an invalid JSON line' );
					}
					KBK_AI_Book_Artifacts::validate_rag_chunk( $chunk, $edition );
					$this->chunks[] = array(
						'order'          => $order++,
						'chunk_id'       => (string) $chunk['chunk_id'],
						'content_id'     => (string) $chunk['content_id'],
						'structural_id'  => (string) $chunk['structural_id'],
						'label_norm'     => KBK_AI_Book_Search::normalize( (string) $chunk['label_fa'] ),
						'keywords_norm'  => KBK_AI_Book_Search::normalize( implode( ' ', array_merge( (array) $chunk['keywords_fa'], (array) $chunk['keywords_en'] ) ) ),
						'body_norm'      => KBK_AI_Book_Search::normalize( mb_substr( (string) $chunk['fa_text'], 0, 4000 ) ),
						'fa_text'        => (string) $chunk['fa_text'],
						'label_fa'       => (string) $chunk['label_fa'],
						'section_fa'     => (string) $chunk['section'],
						'part_id'        => (string) $chunk['part_id'],
						'chapter_id'     => (string) $chunk['chapter_id'],
						'origin'         => (string) $chunk['origin'],
						'citation_url'   => (string) $chunk['canonical_url'],
						'source_documents' => array_slice( array_map( 'strval', (array) $chunk['source_documents'] ), 0, 3 ),
					);
				}
			} finally {
				fclose( $handle );
			}
			if ( array() === $this->chunks ) {
				throw new UnexpectedValueException( 'RAG corpus is empty' );
			}
		} catch ( UnexpectedValueException | InvalidArgumentException $error ) {
			$this->status = self::STATUS_ARTIFACT_INVALID;
			return false;
		}
		return true;
	}
}
