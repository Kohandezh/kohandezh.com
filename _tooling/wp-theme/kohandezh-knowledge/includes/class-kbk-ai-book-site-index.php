<?php
/**
 * KBK_AI_Book_Site_Index — every book section, offered to the site assistant.
 *
 * The Kohandezh AI Hub plugin answers visitor questions from one site-wide
 * index and lets other plugins add documents through the
 * `kdcv_ai_site_documents` filter. This class adds the release RAG corpus of
 * every routable book (release/14_rag_corpus_fa.jsonl): one document per
 * chunk, titled with the reader's section title and linked to the section's
 * canonical reader URL, so the avatar can cite any section of the book.
 *
 * It also extends `kdcv_ai_site_index_signature` with each corpus file's
 * size and mtime: uploading a new edition re-indexes on the next question.
 * Without the AI Hub plugin both filters are simply never applied.
 *
 * @package Kohandezh_Knowledge
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'KBK_TESTING' ) ) {
	exit;
}

final class KBK_AI_Book_Site_Index {

	const TEXT_CHARS = 1200;

	public static function hooks(): void {
		add_filter( 'kdcv_ai_site_documents', array( __CLASS__, 'documents' ) );
		add_filter( 'kdcv_ai_site_index_signature', array( __CLASS__, 'signature' ) );
	}

	/**
	 * @return array<string,array{0:KBK_AI_Book_Repository,1:string}>
	 */
	private static function corpora(): array {
		$out = array();
		foreach ( KBK_AI_Book_Registry::routable_slugs() as $slug ) {
			$repository = KBK_AI_Book_Registry::repository( $slug );
			if ( null === $repository ) {
				continue;
			}
			$path = $repository->root() . DIRECTORY_SEPARATOR . 'release' . DIRECTORY_SEPARATOR . '14_rag_corpus_fa.jsonl';
			if ( is_file( $path ) && is_readable( $path ) ) {
				$out[ $slug ] = array( $repository, $path );
			}
		}
		return $out;
	}

	/**
	 * @param mixed $signature Signature so far.
	 */
	public static function signature( $signature ): string {
		$signature = (string) $signature;
		foreach ( self::corpora() as $slug => $corpus ) {
			$signature .= '|' . $slug . ':' . (int) filesize( $corpus[1] ) . ':' . (int) filemtime( $corpus[1] );
		}
		return $signature;
	}

	/**
	 * @param mixed $documents Documents so far.
	 * @return array<int,array<string,string>>
	 */
	public static function documents( $documents ): array {
		$documents = is_array( $documents ) ? $documents : array();
		foreach ( self::corpora() as $corpus ) {
			list( $repository, $path ) = $corpus;
			$urls   = array();
			$handle = fopen( $path, 'r' );
			if ( false === $handle ) {
				continue;
			}
			try {
				while ( false !== ( $line = fgets( $handle ) ) ) {
					$chunk = json_decode( trim( $line ), true );
					if ( ! is_array( $chunk ) || empty( $chunk['structural_id'] ) || ! isset( $chunk['fa_text'] ) ) {
						continue;
					}
					$sid     = (string) $chunk['structural_id'];
					$section = $repository->find_section( $sid );
					if ( null === $section || KBK_AI_Book::is_omitted_section( $section ) ) {
						continue;
					}
					if ( ! isset( $urls[ $sid ] ) ) {
						$urls[ $sid ] = KBK_AI_Book::section_url( (string) $section['part_id'], (string) $section['chapter_id'], $sid );
					}
					$title = KBK_AI_Book::reader_section_title( $section );
					$label = isset( $chunk['label_fa'] ) ? trim( (string) $chunk['label_fa'] ) : '';
					// Add the chunk's own label only when it says something the
					// section title does not already say.
					if ( '' !== $label && ( '' === $title || ( false === mb_stripos( $title, $label ) && false === mb_stripos( $label, $title ) ) ) ) {
						$title .= ' — ' . $label;
					}
					// Markdown punctuation carries no meaning for retrieval.
					$text        = preg_replace( '/[#*_`>|]+/u', ' ', (string) $chunk['fa_text'] );
					$documents[] = array(
						'title' => $title,
						'url'   => $urls[ $sid ],
						'text'  => mb_substr( (string) $text, 0, self::TEXT_CHARS ),
						'kind'  => 'book',
						'lang'  => 'fa',
					);
				}
			} finally {
				fclose( $handle );
			}
		}
		return $documents;
	}
}
