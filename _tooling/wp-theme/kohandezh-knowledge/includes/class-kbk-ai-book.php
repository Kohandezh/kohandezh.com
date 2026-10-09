<?php
/**
 * KBK_AI_Book — feature-flagged WordPress routes for the Persian book.
 *
 * @package Kohandezh_Knowledge
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'KBK_TESTING' ) ) {
	exit;
}

if ( ! class_exists( 'KBK_AI_Book_Registry' ) ) {
	require_once __DIR__ . '/class-kbk-ai-book-registry.php';
}

final class KBK_AI_Book {

	const QUERY_VAR         = 'kbk_ai_book';
	const PART_QUERY_VAR    = 'kbk_part';
	const CHAPTER_QUERY_VAR = 'kbk_chapter';
	const SECTION_QUERY_VAR = 'kbk_section';
	const SEARCH_QUERY_VAR  = 'kbk_q';
	const CONCEPT_QUERY_VAR = 'kbk_concept';
	const TEMPLATE_QUERY_VAR = 'kbk_template';
	const ENTITY_QUERY_VAR  = 'kbk_entity';
	const TYPE_QUERY_VAR    = 'kbk_type';
	const PAGE_QUERY_VAR    = 'kbk_page';
	const PDF_FILE_QUERY_VAR = 'kbk_pdf_file';
	const VERIFY_QUERY_VAR  = 'kbk_verify';
	const BOOK_QUERY_VAR    = 'kbk_book';

	/** Views every book has below /fa/books/{slug}/ (the landing is "governance"). */
	const BOOK_VIEWS = array( 'read', 'search', 'glossary', 'sources', 'templates', 'concepts', 'graph', 'ask', 'pdf', 'request-pdf' );

	/** Machine-readable endpoints of every book: /fa/books/{slug}/sitemap.xml and llms.txt. */
	const FEED_VIEWS = array( 'sitemap', 'llms' );

	/**
	 * Bounds any hand-typed or linked identifier before it ever reaches the
	 * repository. The repository only ever compares this value against known
	 * IDs/slugs (never uses it to build a filesystem path), but request input
	 * is validated at the boundary regardless of how it is later used.
	 */
	const IDENTIFIER_PATTERN = '/^[A-Za-z0-9-]{1,80}$/';

	/**
	 * Markup a visible section adds beyond its text (heading, citation box,
	 * related links), counted in characters when packing pages so a page of
	 * many short sections stays near the same weight as one long one.
	 */
	const SECTION_OVERHEAD_CHARS = 1000;

	/** Reader page budget in Persian characters (admin-adjustable). */
	const DEFAULT_PAGE_CHARS = 30000;
	const MIN_PAGE_CHARS     = 8000;
	const MAX_PAGE_CHARS     = 150000;

	/** @var array<string,string|null> Verify ID => slug of the book that holds it. */
	private static $verify_books = array();

	/** @var KBK_AI_Book_Search|null */
	private static $search;

	/** @var string|null */
	private static $search_error;

	/** @var KBK_AI_Book_Catalog|null */
	private static $catalog;

	/** @var string|null */
	private static $catalog_error;

	/** @var KBK_AI_Book_Graph|null */
	private static $graph;

	/** @var string|null */
	private static $graph_error;

	/** @var KBK_AI_Book_Ask|null */
	private static $ask;

	/** @var string|null */
	private static $ask_error;

	/** @var KBK_AI_Book_Pdf|null */
	private static $pdf;

	/** @var string|null */
	private static $pdf_error;

	/** @var KBK_AI_Book_Request|null */
	private static $request;

	/** @var string|null */
	private static $request_error;

	public static function hooks(): void {
		if ( ! defined( 'KBK_FEATURE_AI_BOOK' ) || ! KBK_FEATURE_AI_BOOK ) {
			return;
		}
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'init', array( __CLASS__, 'rewrite_rules' ), 20 );
		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 30 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_unlocalized_namespace' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_stream_pdf' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_filter( 'home_url', array( __CLASS__, 'preferred_internal_url' ), 10, 4 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_book_feed' ), 1 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 20, 2 );
	}

	/**
	 * Keep old shared links working while consolidating indexing and navigation
	 * on the site's locale-first Persian URL shape.
	 */
	public static function maybe_redirect_unlocalized_namespace(): void {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$target = self::retired_ai_book_path( $request_uri );
		if ( null !== $target ) {
			wp_safe_redirect( home_url( $target ), 301 );
			exit;
		}
		if ( 0 === strpos( $request_uri, '/fa/ai-book/' ) ) {
			// Corpus-era addresses all belong to the first book.
			$repository = KBK_AI_Book_Registry::repository( KBK_AI_Book_Registry::LEGACY_SLUG );
			$target = $repository ? self::legacy_reader_path( $request_uri, $repository ) : null;
			if ( null !== $target ) {
				wp_safe_redirect( home_url( $target ), 301 );
				exit;
			}
		}
		$localized_uri = self::localized_book_path( $request_uri );
		if ( null !== $localized_uri ) {
			wp_safe_redirect( home_url( $localized_uri ), 301 );
			exit;
		}
		$chapter_target = self::book_chapter_path( $request_uri );
		if ( null !== $chapter_target ) {
			if ( '' !== $chapter_target ) {
				wp_safe_redirect( home_url( $chapter_target ), 301 );
				exit;
			}
			self::send_404();
			return;
		}
		self::maybe_404_unknown_book();
	}

	/**
	 * The frozen citation address of every book,
	 * /fa/books/{slug}/{part_slug}/{chapter_slug}/ (the bundle's
	 * canonical_url, before its #structural-id), answered with the chapter in
	 * the Reader; the browser keeps the fragment across the 301.
	 *
	 * @return string|null The Reader path; '' when the address is inside a
	 *         routed book but names no chapter (404); null when it is not
	 *         such an address. View names (pdf/file …) are never parts.
	 */
	public static function book_chapter_path( string $uri ): ?string {
		$path = parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || 1 !== preg_match( '#^/fa/books/([a-z0-9]+(?:-[a-z0-9]+)*)/([a-z0-9-]{1,80})/([a-z0-9-]{1,80})/?$#', $path, $matches ) ) {
			return null;
		}
		if ( in_array( $matches[2], array_merge( self::BOOK_VIEWS, array( 'news' ) ), true ) || ! KBK_AI_Book_Registry::is_routable( $matches[1] ) ) {
			return null;
		}
		$repository = KBK_AI_Book_Registry::repository( $matches[1] );
		$part       = $repository ? $repository->find_part( $matches[2] ) : null;
		$chapter    = $part ? $repository->find_chapter( $part['part_id'], $matches[3] ) : null;
		if ( null === $chapter ) {
			return '';
		}
		return self::book_path( 'read/?' . self::PART_QUERY_VAR . '=' . rawurlencode( $part['part_id'] ) . '&' . self::CHAPTER_QUERY_VAR . '=' . rawurlencode( $chapter['chapter_id'] ), $matches[1] );
	}

	/**
	 * /books/{slug}/… is the unlocalized alias of a routed book: it answers
	 * with the same view under /fa/books/{slug}/, query string intact.
	 * Unknown slugs are not redirected (they fall through to the 404).
	 */
	public static function localized_book_path( string $uri ): ?string {
		if ( 1 !== preg_match( '#^/books/([a-z0-9]+(?:-[a-z0-9]+)*)(?=/|\?|$)#', $uri, $matches ) || ! KBK_AI_Book_Registry::is_routable( $matches[1] ) ) {
			return null;
		}
		return '/fa' . $uri;
	}

	/**
	 * A /fa/books/{slug}/ address whose slug is not a routed book is a plain
	 * WordPress 404 (the theme's 404 template), never an empty Reader.
	 */
	public static function maybe_404_unknown_book(): void {
		$requested = (string) get_query_var( self::BOOK_QUERY_VAR );
		if ( '' === $requested || KBK_AI_Book_Registry::is_routable( $requested ) ) {
			return;
		}
		self::send_404();
	}

	/** A plain WordPress 404 (the theme's 404 template), never a guessed redirect. */
	private static function send_404(): void {
		add_filter( 'do_redirect_guess_404_permalink', '__return_false' );
		global $wp_query;
		if ( is_object( $wp_query ) && method_exists( $wp_query, 'set_404' ) ) {
			$wp_query->set_404();
		}
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * /ai-book/ was the first public address. Its landing page is now the
	 * /books/ library and every view moved under the book's own namespace, so
	 * old links land on the same view with the query string intact. The
	 * verification URLs are signed into the bundle and keep being served.
	 */
	public static function retired_ai_book_path( string $uri ): ?string {
		$path = parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || 1 !== preg_match( '#^/ai-book(?:/([a-z0-9/-]*))?$#', $path, $matches ) ) {
			return null;
		}
		$rest = trim( $matches[1] ?? '', '/' );
		if ( 'verify' === $rest || 0 === strpos( $rest, 'verify/' ) ) {
			return null;
		}
		$query = parse_url( $uri, PHP_URL_QUERY );
		$base  = '' === $rest ? '/books/' : '/fa/books/ai-governance/' . $rest . '/';
		return $base . ( is_string( $query ) && '' !== $query ? '?' . $query : '' );
	}

	/** Resolve immutable corpus-era URLs only when their chapter is unambiguous. */
	public static function legacy_reader_path( string $uri, KBK_AI_Book_Repository $repository ): ?string {
		$path = parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || 1 !== preg_match( '#^/fa/ai-book/([a-z0-9-]{1,80})/([a-z0-9-]{1,80})/?$#', $path, $matches ) ) {
			return null;
		}
		$part = $repository->find_part( $matches[1] );
		$chapter = $part ? $repository->find_chapter( $part['part_id'], $matches[2] ) : null;
		if ( ! $chapter ) return null;
		// Browsers inherit the original fragment when Location has no fragment.
		return '/fa/books/ai-governance/read/?kbk_part=' . rawurlencode( $part['part_id'] ) . '&kbk_chapter=' . rawurlencode( $chapter['chapter_id'] );
	}

	/**
	 * Keep legacy rewrites functional while all links emitted by the AI Book
	 * point into the Persian locale's preferred
	 * /fa/books/ai-governance/ namespace.
	 */
	public static function preferred_internal_url( string $url, string $path = '', ?string $orig_scheme = null, ?int $blog_id = null ): string {
		unset( $path, $orig_scheme, $blog_id );
		if ( ! self::is_request() ) {
			return $url;
		}
		$marker = '/ai-book/';
		$offset = strpos( $url, $marker );
		if ( false === $offset || 0 === strpos( substr( $url, $offset ), '/ai-book/verify/' ) ) {
			return $url;
		}
		return substr( $url, 0, $offset ) . '/fa/books/' . self::current_link_slug() . '/' . substr( $url, $offset + strlen( $marker ) );
	}

	/**
	 * The book the current request belongs to:
	 * - /fa/books/{slug}/… → that slug when it is routed, else null (404);
	 * - /ai-book/verify/?kbk_verify=ID → the first routed book holding ID;
	 * - anything else (library, legacy) → the first routed book.
	 */
	public static function current_book_slug(): ?string {
		$requested = (string) get_query_var( self::BOOK_QUERY_VAR );
		if ( '' !== $requested ) {
			return KBK_AI_Book_Registry::is_routable( $requested ) ? $requested : null;
		}
		if ( 'verify' === (string) get_query_var( self::QUERY_VAR ) ) {
			$id = self::requested_verify_id();
			if ( null !== $id ) {
				return self::verify_book_slug( $id );
			}
		}
		return KBK_AI_Book_Registry::default_slug();
	}

	/** Slug used to build links; the first book when nothing resolves. */
	public static function current_link_slug(): string {
		return self::current_book_slug() ?? ( KBK_AI_Book_Registry::default_slug() ?? KBK_AI_Book_Registry::LEGACY_SLUG );
	}

	/** True while serving the first book, whose hand-written copy stays as it was. */
	public static function is_legacy_book(): bool {
		$slug = self::current_book_slug();
		return null === $slug || KBK_AI_Book_Registry::LEGACY_SLUG === $slug;
	}

	/** The first routed book whose edition holds $id (first match wins). */
	public static function verify_book_slug( string $id ): ?string {
		if ( array_key_exists( $id, self::$verify_books ) ) {
			return self::$verify_books[ $id ];
		}
		$found = KBK_AI_Book_Registry::default_slug();
		foreach ( KBK_AI_Book_Registry::routable_slugs() as $slug ) {
			$repository = KBK_AI_Book_Registry::repository( $slug );
			if ( null !== $repository && null !== $repository->verify( $id ) ) {
				$found = $slug;
				break;
			}
		}
		return self::$verify_books[ $id ] = $found;
	}

	/** Site path of the current book, plus an optional view path ("read/?…"). */
	public static function book_path( string $rest = '', ?string $slug = null ): string {
		return '/fa/books/' . ( $slug ?? self::current_link_slug() ) . '/' . ltrim( $rest, '/' );
	}

	/** @param string[] $vars @return string[] */
	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		$vars[] = self::PART_QUERY_VAR;
		$vars[] = self::CHAPTER_QUERY_VAR;
		$vars[] = self::SECTION_QUERY_VAR;
		$vars[] = self::SEARCH_QUERY_VAR;
		$vars[] = self::CONCEPT_QUERY_VAR;
		$vars[] = self::TEMPLATE_QUERY_VAR;
		$vars[] = self::ENTITY_QUERY_VAR;
		$vars[] = self::TYPE_QUERY_VAR;
		$vars[] = self::PAGE_QUERY_VAR;
		$vars[] = self::PDF_FILE_QUERY_VAR;
		$vars[] = self::VERIFY_QUERY_VAR;
		$vars[] = self::BOOK_QUERY_VAR;
		return $vars;
	}

	/**
	 * A registered query var's raw value, allowlisted to a safe identifier
	 * shape. Returns null for absent, empty or out-of-shape input rather than
	 * guessing — the caller then falls back to a documented default.
	 */
	public static function requested_identifier( string $query_var ): ?string {
		$value = (string) get_query_var( $query_var );
		if ( '' === $value || 1 !== preg_match( self::IDENTIFIER_PATTERN, $value ) ) {
			return null;
		}
		return $value;
	}

	/**
	 * Render the small Markdown subset used by the canonical Persian book.
	 *
	 * The source remains plain canonical data. Every source fragment is escaped
	 * before this method adds a fixed set of semantic reading elements.
	 *
	 * Heading levels are relative to the section: the shallowest Markdown level
	 * present becomes h3 (under the section's own h2), the next one h4, then
	 * h5/h6, and a heading never sits more than one level below the one before
	 * it. A bullet item whose text starts with a number ("- 3. …") is an
	 * ordered item, so the reader never shows a bullet and a number together.
	 * `[FIGURE …]` source markers never reach the page: a marker that matches
	 * one of the section's `figures` entries, or that carries a caption on its
	 * own line, becomes a short Persian figure note; a bare marker is dropped.
	 * Footnote references `[^n]` link to a list at the end of the section when
	 * the section defines `[^n]: …`; a reference without a definition is
	 * removed (see reader_document()).
	 *
	 * @param array<int,mixed> $figures The section's `figures` array, if any.
	 * @param string           $scope   Structural ID: prefixes heading and footnote ids.
	 */
	public static function render_reader_markdown( string $text, array $figures = array(), string $scope = '' ): string {
		$doc = self::reader_document( $text, $scope );
		return self::render_document_range( $doc, 0, count( $doc['lines'] ), $figures, false );
	}

	/**
	 * Section-wide reading model: body lines without footnote definitions,
	 * the definitions, and every heading's final level and stable id. Built
	 * once per section so a section split over several pages keeps one
	 * heading hierarchy, one id sequence and one footnote set.
	 *
	 * Definitions: `[^n]: text`, and the converter's legacy form
	 * `[^]: > n text > m text` that packs numbered notes into one line.
	 *
	 * @return array{scope:string,lines:string[],notes:array<string,array{text:string,line:int}>,referenced:array<string,bool>,headings:array<int,array{level:int,id:string}>,offsets:int[]}
	 */
	public static function reader_document( string $text, string $scope = '' ): array {
		static $cache = array();
		$key = $scope . ':' . md5( $text );
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}
		$raw   = preg_split( '/\r\n|\r|\n/', $text );
		$lines = array();
		$notes = array();
		$loose = 0;
		foreach ( false === $raw ? array() : $raw as $line ) {
			if ( 1 !== preg_match( '/^\s*\[\^([^\]]*)\]:\s*(.*)$/u', $line, $m ) ) {
				$lines[] = $line;
				continue;
			}
			$at    = count( $lines );
			$label = trim( self::latin_digits( $m[1] ) );
			$body  = trim( $m[2] );
			if ( 1 === preg_match( '/^[0-9]{1,4}$/', $label ) ) {
				if ( '' !== $body && ! isset( $notes[ (string) (int) $label ] ) ) {
					$notes[ (string) (int) $label ] = array( 'text' => $body, 'line' => $at );
				}
				continue;
			}
			$pieces = preg_split( '/(?:^|\s)>\s*([0-9۰-۹]{1,3})(?![0-9۰-۹])\s*/u', ' ' . $body, -1, PREG_SPLIT_DELIM_CAPTURE );
			if ( false === $pieces ) {
				continue;
			}
			$lead = trim( (string) preg_replace( '/^[>\s]+/u', '', (string) array_shift( $pieces ) ) );
			if ( '' !== $lead ) {
				$notes[ '~' . ( ++$loose ) ] = array( 'text' => $lead, 'line' => $at );
			}
			for ( $i = 0; $i + 1 < count( $pieces ); $i += 2 ) {
				$number = (string) (int) self::latin_digits( $pieces[ $i ] );
				$note   = trim( (string) $pieces[ $i + 1 ] );
				if ( '' !== $note && ! isset( $notes[ $number ] ) ) {
					$notes[ $number ] = array( 'text' => $note, 'line' => $at );
				}
			}
		}

		$referenced = array();
		$levels     = array();
		$offsets    = array( 0 );
		foreach ( $lines as $index => $line ) {
			$offsets[] = $offsets[ $index ] + mb_strlen( $line, 'UTF-8' ) + 1;
			if ( preg_match_all( '/\[\^([^\]]*)\]/u', $line, $refs ) ) {
				foreach ( $refs[1] as $label ) {
					foreach ( self::footnote_numbers( $label ) as $number ) {
						$referenced[ $number ] = true;
					}
				}
			}
			$trimmed = trim( $line );
			if ( 1 !== preg_match( '/^\|.*\|$/u', $trimmed ) && 1 === preg_match( '/^(#{1,6})\s+(.+)$/u', $trimmed, $hm ) ) {
				$levels[ $index ] = strlen( $hm[1] );
			}
		}
		$distinct = array_values( array_unique( $levels ) );
		sort( $distinct );
		$rank     = array_flip( $distinct );
		$headings = array();
		$previous = 2;
		$ordinal  = 0;
		foreach ( $levels as $index => $markdown_level ) {
			$level              = min( 6, 3 + $rank[ $markdown_level ], $previous + 1 );
			$previous           = $level;
			$headings[ $index ] = array(
				'level' => $level,
				'id'    => '' !== $scope ? $scope . '-h' . ( ++$ordinal ) : '',
			);
		}
		if ( count( $cache ) > 64 ) {
			$cache = array();
		}
		return $cache[ $key ] = array(
			'scope'      => $scope,
			'lines'      => $lines,
			'notes'      => $notes,
			'referenced' => $referenced,
			'headings'   => $headings,
			'offsets'    => $offsets,
		);
	}

	/**
	 * Numbers carried by one footnote label ("12", "۱۴", "9,10"); anything
	 * else (OCR debris such as "i i i") carries none and is an orphan.
	 *
	 * @return string[]
	 */
	private static function footnote_numbers( string $label ): array {
		$numbers = array();
		foreach ( preg_split( '/[\s,،]+/u', trim( self::latin_digits( $label ) ) ) ?: array() as $piece ) {
			if ( 1 !== preg_match( '/^[0-9]{1,4}$/', $piece ) ) {
				return array();
			}
			$numbers[] = (string) (int) $piece;
		}
		return $numbers;
	}

	/**
	 * Line ranges [start, end) of a section's reading parts. A body within
	 * the budget is one part. A longer one is cut at its own headings, the
	 * top-most level first; a piece still over the budget is cut again at the
	 * next level down, and the pieces are then packed greedily up to the
	 * budget. A piece with no heading left that is still over the budget
	 * (the book's largest sections are one heading over hundreds of
	 * blank-line-separated bibliography or glossary entries) is cut between
	 * blocks: only before a non-blank line that follows a blank one, and never
	 * right after a heading (it stays with its first block). A table,
	 * a list run or a blockquote never contains a blank line, so none is ever
	 * split; one block larger than the budget stays whole.
	 *
	 * @param array<string,mixed> $doc reader_document() result.
	 * @return array<int,array{0:int,1:int,2:int}> start, end, weight in characters.
	 */
	public static function section_parts( array $doc, int $budget ): array {
		$offsets = $doc['offsets'];
		$count   = count( $doc['lines'] );
		$weight  = static function ( int $start, int $end ) use ( $offsets ): int {
			return $offsets[ $end ] - $offsets[ $start ];
		};
		if ( $weight( 0, $count ) <= $budget ) {
			return array( array( 0, $count, $weight( 0, $count ) ) );
		}
		$atoms = self::split_range( $doc['headings'], $doc['lines'], $weight, 0, $count, max( 1, $budget ) );
		$parts = array();
		$open  = null;
		foreach ( $atoms as $atom ) {
			if ( null === $open ) {
				$open = $atom;
			} elseif ( $weight( $open[0], $atom[1] ) > $budget ) {
				$parts[] = $open;
				$open    = $atom;
			} else {
				$open = array( $open[0], $atom[1] );
			}
		}
		if ( null !== $open ) {
			$parts[] = $open;
		}
		return array_map(
			static function ( array $range ) use ( $weight ): array {
				return array( $range[0], $range[1], $weight( $range[0], $range[1] ) );
			},
			$parts
		);
	}

	/**
	 * @param array<int,array{level:int,id:string}> $headings
	 * @param string[]                              $lines
	 * @return array<int,array{0:int,1:int}>
	 */
	private static function split_range( array $headings, array $lines, callable $weight, int $start, int $end, int $budget ): array {
		if ( $weight( $start, $end ) <= $budget ) {
			return array( array( $start, $end ) );
		}
		$top = null;
		foreach ( $headings as $line => $heading ) {
			if ( $line > $start && $line < $end ) {
				$top = null === $top ? $heading['level'] : min( $top, $heading['level'] );
			}
		}
		if ( null === $top ) {
			$atoms = array();
			$from  = $start;
			$last_text = null;
			for ( $line = $start; $line < $end; $line++ ) {
				if ( '' === trim( $lines[ $line ] ) ) {
					continue;
				}
				// Never between a heading and the block it introduces.
				if ( $line > $start && '' === trim( $lines[ $line - 1 ] ) && null !== $last_text && ! isset( $headings[ $last_text ] ) ) {
					$atoms[] = array( $from, $line );
					$from    = $line;
				}
				$last_text = $line;
			}
			$atoms[] = array( $from, $end );
			return $atoms;
		}
		$cuts = array( $start );
		foreach ( $headings as $line => $heading ) {
			if ( $line > $start && $line < $end && $heading['level'] === $top ) {
				$cuts[] = $line;
			}
		}
		$cuts[] = $end;
		$atoms  = array();
		for ( $i = 0; $i + 1 < count( $cuts ); $i++ ) {
			foreach ( self::split_range( $headings, $lines, $weight, $cuts[ $i ], $cuts[ $i + 1 ], $budget ) as $atom ) {
				$atoms[] = $atom;
			}
		}
		return $atoms;
	}

	/**
	 * HTML for one reading part of a section (or the whole section): its
	 * lines, then the footnotes referenced in it (plus any definition placed
	 * in it that nothing references). A continuation part shifts its headings
	 * so its first one is h3 under the continuation label's h2.
	 *
	 * @param array<string,mixed>      $section
	 * @param array{0:int,1:int}|null $range
	 */
	public static function reader_section_html( array $section, ?array $range = null, bool $continuation = false ): string {
		$doc = self::reader_document( self::reader_section_body( $section ), (string) ( $section['structural_id'] ?? '' ) );
		$start = null === $range ? 0 : (int) $range[0];
		$end   = null === $range ? count( $doc['lines'] ) : (int) $range[1];
		return self::render_document_range( $doc, $start, $end, is_array( $section['figures'] ?? null ) ? $section['figures'] : array(), $continuation );
	}

	/**
	 * @param array<string,mixed> $doc
	 * @param array<int,mixed>    $figures
	 */
	private static function render_document_range( array $doc, int $start, int $end, array $figures, bool $continuation ): string {
		$shift = 0;
		if ( $continuation ) {
			foreach ( $doc['headings'] as $line => $heading ) {
				if ( $line >= $start && $line < $end ) {
					$shift = max( 0, $heading['level'] - 3 );
					break;
				}
			}
		}
		$previous_notes = self::$notes;
		self::$notes    = array(
			'prefix' => '' !== $doc['scope'] ? $doc['scope'] : 'ab',
			'notes'  => $doc['notes'],
			'order'  => array(),
			'refs'   => array(),
		);

		$html       = array();
		$paragraph  = array();
		$list_items = array();
		$list_type  = null;
		$previous   = 2;
		$flush_paragraph = static function () use ( &$html, &$paragraph ): void {
			$joined = trim( implode( ' ', $paragraph ) );
			if ( '' !== $joined ) {
				$rendered = self::render_reader_inline( $joined );
				if ( '' !== trim( $rendered ) ) {
					$html[] = '<p dir="auto">' . $rendered . '</p>';
				}
			}
			$paragraph = array();
		};
		$flush_list = static function () use ( &$html, &$list_items, &$list_type ): void {
			if ( null !== $list_type && $list_items ) {
				$items = array_map(
					static function ( array $item ): string {
						$value = null !== $item['value'] ? ' value="' . (int) $item['value'] . '"' : '';
						return '<li dir="auto"' . $value . '>' . self::render_reader_inline( $item['text'] ) . '</li>';
					},
					$list_items
				);
				$html[] = '<' . $list_type . '>' . implode( '', $items ) . '</' . $list_type . '>';
			}
			$list_items = array();
			$list_type  = null;
		};

		$lines = $doc['lines'];
		for ( $index = $start; $index < $end; $index++ ) {
			$line = trim( $lines[ $index ] );
			if ( '' === $line ) {
				$flush_paragraph();
				$flush_list();
				continue;
			}

			if ( 1 === preg_match( '/^\|.*\|$/u', $line ) ) {
				$table_lines = array();
				while ( $index < $end && 1 === preg_match( '/^\s*\|.*\|\s*$/u', $lines[ $index ] ) ) {
					$table_lines[] = trim( $lines[ $index ] );
					$index++;
				}
				$index--;
				$flush_paragraph();
				$flush_list();
				$html[] = self::render_reader_table( $table_lines );
				continue;
			}

			if ( isset( $doc['headings'][ $index ] ) && 1 === preg_match( '/^(#{1,6})\s+(.+)$/u', $line, $matches ) ) {
				$flush_paragraph();
				$flush_list();
				$heading  = $doc['headings'][ $index ];
				$level    = max( 3, min( $heading['level'] - $shift, $previous + 1 ) );
				$previous = $level;
				$id       = '' !== $heading['id'] ? ' id="' . htmlspecialchars( $heading['id'], ENT_QUOTES, 'UTF-8' ) . '"' : '';
				$html[]   = '<h' . $level . $id . '>' . self::render_reader_inline( $matches[2] ) . '</h' . $level . '>';
				continue;
			}

			if ( 1 === preg_match( '/^(?:[-*]\s+)?([0-9۰-۹]{1,4})[.)]\s+(.+)$/u', $line, $matches ) || 1 === preg_match( '/^[-*]\s+(.+)$/u', $line, $matches ) ) {
				$numbered       = isset( $matches[2] );
				$requested_type = $numbered ? 'ol' : 'ul';
				$flush_paragraph();
				if ( null !== $list_type && $requested_type !== $list_type ) {
					$flush_list();
				}
				$list_type    = $requested_type;
				$list_items[] = array(
					'text'  => $numbered ? $matches[2] : $matches[1],
					'value' => $numbered ? (int) self::latin_digits( $matches[1] ) : null,
				);
				continue;
			}

			$flush_list();
			if ( 1 === preg_match( '/^>\s*(.+)$/u', $line, $matches ) ) {
				$flush_paragraph();
				$html[] = '<blockquote><p dir="auto">' . self::render_reader_inline( $matches[1] ) . '</p></blockquote>';
				continue;
			}
			if ( 1 === preg_match( '/^\[FIGURE\b([^\]]*)\](.*)$/iu', $line, $matches ) ) {
				$flush_paragraph();
				$note = self::render_figure_note( trim( $matches[1] ), trim( $matches[2] ), $figures );
				if ( '' !== $note ) {
					$html[] = $note;
				}
				continue;
			}
			$paragraph[] = $line;
		}

		$flush_paragraph();
		$flush_list();
		$html[]       = self::render_footnotes( $doc, $start, $end );
		self::$notes = $previous_notes;
		return implode( "\n", array_filter( $html ) );
	}

	/** @var array<string,mixed>|null Footnote state of the range being rendered. */
	private static $notes = null;

	/**
	 * The footnote list closing a rendered range: notes referenced in it, in
	 * order of first reference, then definitions placed in it that nothing in
	 * the section references (kept, so no source note is lost).
	 *
	 * @param array<string,mixed> $doc
	 */
	private static function render_footnotes( array $doc, int $start, int $end ): string {
		$state = self::$notes;
		$keys  = $state['order'];
		$total = count( $doc['lines'] );
		foreach ( $doc['notes'] as $number => $note ) {
			$here = $note['line'] >= $start && ( $note['line'] < $end || ( $end === $total && $note['line'] === $total ) );
			if ( $here && empty( $doc['referenced'][ $number ] ) && ! in_array( (string) $number, $keys, true ) ) {
				$keys[] = (string) $number;
			}
		}
		if ( ! $keys ) {
			return '';
		}
		self::$notes = null; // Note text never links further notes.
		$items = '';
		foreach ( $keys as $number ) {
			$note  = $doc['notes'][ $number ];
			$loose = '~' === substr( $number, 0, 1 );
			$text  = self::render_reader_inline( (string) preg_replace( '/\s*\[\^[^\]]*\]/u', '', $note['text'] ) );
			$back  = '';
			foreach ( $state['refs'][ $number ] ?? array() as $ref_id ) {
				$back .= ' <a class="ab-fn-back" href="#' . htmlspecialchars( $ref_id, ENT_QUOTES, 'UTF-8' ) . '" aria-label="بازگشت به ارجاع ' . self::fa_digits( $number ) . ' در متن">↑</a>';
			}
			$items .= $loose
				? '<li class="ab-fn-loose" dir="auto">' . $text . '</li>'
				: '<li id="' . htmlspecialchars( $state['prefix'] . '-fn-' . $number, ENT_QUOTES, 'UTF-8' ) . '" value="' . (int) $number . '" dir="auto">' . $text . $back . '</li>';
		}
		return '<aside class="ab-footnotes" aria-label="پانوشت‌های این بخش"><p class="ab-footnotes-title">پانوشت‌ها</p><ol>' . $items . '</ol></aside>';
	}

	/** Superscript link for one footnote number, or '' when it has no definition. */
	private static function footnote_ref( string $number ): string {
		if ( null === self::$notes || ! isset( self::$notes['notes'][ $number ] ) ) {
			return '';
		}
		if ( ! in_array( $number, self::$notes['order'], true ) ) {
			self::$notes['order'][] = $number;
		}
		$ref_id = self::$notes['prefix'] . '-fnref-' . $number . '-' . ( count( self::$notes['refs'][ $number ] ?? array() ) + 1 );
		self::$notes['refs'][ $number ][] = $ref_id;
		$label = self::fa_digits( $number );
		return '<sup class="ab-fnref"><a id="' . htmlspecialchars( $ref_id, ENT_QUOTES, 'UTF-8' ) . '" href="#' . htmlspecialchars( self::$notes['prefix'] . '-fn-' . $number, ENT_QUOTES, 'UTF-8' ) . '" aria-label="پانوشت ' . $label . '">' . $label . '</a></sup>';
	}

	/** Map Persian/Arabic-Indic digits to ASCII (for list values and IDs). */
	public static function latin_digits( string $value ): string {
		return strtr( $value, array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9' ) );
	}

	/**
	 * Persian digits for UI-generated numbers only (counts, kickers, pages,
	 * progress). Never run book prose, IDs, hashes or codes through this.
	 *
	 * @param int|string $value
	 */
	public static function fa_digits( $value ): string {
		if ( is_int( $value ) && abs( $value ) >= 10000 ) {
			$value = number_format( $value, 0, '', '٬' );
		}
		return strtr( (string) $value, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
	}

	/** "P05" → 5, "C38" → 38; 0 when the ID carries no number. */
	public static function id_number( string $id ): int {
		return 1 === preg_match( '/([0-9]+)$/', $id, $matches ) ? (int) $matches[1] : 0;
	}

	/**
	 * «بخش ۵، فصل ۳۸» for a part (and optional chapter) ID. The separator is a
	 * Persian comma: a middle dot beside Persian digits reads as a zero (۰).
	 */
	public static function location_label( string $part_id, ?string $chapter_id = null ): string {
		$label = 'بخش ' . self::fa_digits( self::id_number( $part_id ) );
		if ( null !== $chapter_id && '' !== $chapter_id ) {
			$label .= '، فصل ' . self::fa_digits( self::id_number( $chapter_id ) );
		}
		return $label;
	}

	/**
	 * Escape text and isolate every Latin run, so a Persian title such as
	 * «چارچوب … (AI RMF 1.0)» keeps its parentheses in place. The isolate is a
	 * span with dir="ltr" (the UA stylesheet gives [dir] unicode-bidi:isolate)
	 * because <bdi> does not survive wp_kses_post.
	 */
	public static function bidi_html( string $text ): string {
		$pieces = preg_split( '/([A-Za-z][A-Za-z0-9]*(?:[ .\-\/:+&\'_][A-Za-z0-9]+)*)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( false === $pieces ) {
			return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		}
		$html = '';
		foreach ( $pieces as $offset => $piece ) {
			if ( '' === $piece ) {
				continue;
			}
			$safe  = htmlspecialchars( $piece, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
			$html .= 1 === $offset % 2 ? '<span dir="ltr">' . $safe . '</span>' : $safe;
		}
		return $html;
	}

	/**
	 * A figure marker becomes a compact note, or nothing. The figure's own
	 * caption/page data wins; a caption printed after the marker on the same
	 * line is used otherwise. A bare marker with no data is dropped.
	 *
	 * @param array<int,mixed> $figures
	 */
	private static function render_figure_note( string $marker, string $trailing, array $figures ): string {
		$caption = '';
		$page    = '';
		$ref     = trim( $marker );
		foreach ( $figures as $figure ) {
			if ( ! is_array( $figure ) ) {
				continue;
			}
			$ids = array();
			foreach ( array( 'figure_id', 'id', 'ref', 'marker' ) as $key ) {
				if ( isset( $figure[ $key ] ) && is_string( $figure[ $key ] ) ) {
					$ids[] = trim( $figure[ $key ] );
				}
			}
			if ( '' === $ref || ! in_array( $ref, $ids, true ) ) {
				continue;
			}
			foreach ( array( 'caption_fa', 'title_fa', 'caption', 'title' ) as $key ) {
				if ( isset( $figure[ $key ] ) && is_string( $figure[ $key ] ) && '' !== trim( $figure[ $key ] ) ) {
					$caption = trim( $figure[ $key ] );
					break;
				}
			}
			foreach ( array( 'page', 'pdf_page', 'source_page' ) as $key ) {
				if ( isset( $figure[ $key ] ) && ( is_int( $figure[ $key ] ) || ( is_string( $figure[ $key ] ) && 1 === preg_match( '/^[0-9]{1,5}$/', $figure[ $key ] ) ) ) ) {
					$page = (string) $figure[ $key ];
					break;
				}
			}
			break;
		}
		if ( '' === $caption ) {
			$caption = trim( (string) preg_replace( '/\[FIGURE\b[^\]]*\]/iu', '', $trailing ) );
		}
		if ( '' === $caption && '' === $page ) {
			return '';
		}
		$label = '' !== $page ? 'تصویر · صفحهٔ ' . self::fa_digits( $page ) . ' در نسخهٔ PDF' : 'تصویر در نسخهٔ PDF';
		return '<p class="ab-figure-note" dir="auto"><span class="ab-figure-label">' . $label . '</span>' . ( '' !== $caption ? ' ' . self::render_reader_inline( $caption ) : '' ) . '</p>';
	}

	/**
	 * Human-readable presentation title for a canonical book section.
	 *
	 * Canonical IDs and source titles stay untouched. The curated Governance
	 * labels replace a duplicated translated number with the section's subject.
	 *
	 * @param array<string,mixed> $section Validated repository section.
	 */
	public static function reader_section_title( array $section ): string {
		$fallback = isset( $section['title_fa'] ) && is_string( $section['title_fa'] ) ? $section['title_fa'] : '';
		if ( 'P02' !== ( $section['part_id'] ?? null ) || 'C09' !== ( $section['chapter_id'] ?? null ) || ! self::is_legacy_book() ) {
			return $fallback;
		}

		$subjects = array(
			'S01' => 'پیشگفتار و راهنمای استفاده از دفترچه',
			'S02' => 'الزامات حقوقی و مقرراتی هوش مصنوعی',
			'S03' => 'ادغام هوش مصنوعی اعتمادپذیر در سیاست‌های سازمان',
			'S04' => 'تعیین سطح مدیریت ریسک بر پایهٔ تحمل ریسک',
			'S05' => 'مستندسازی و شفافیت مدیریت ریسک',
			'S06' => 'پایش مستمر و بازبینی دوره‌ای ریسک',
			'S07' => 'فهرست‌برداری و مدیریت سامانه‌های هوش مصنوعی',
			'S08' => 'بازنشستگی ایمن سامانه‌های هوش مصنوعی',
			'S09' => 'نقش‌ها، مسئولیت‌ها و خطوط ارتباطی',
			'S10' => 'آموزش مدیریت ریسک برای کارکنان و شرکا',
			'S11' => 'پاسخگویی رهبری اجرایی در برابر ریسک',
			'S12' => 'تصمیم‌گیری با تیم‌های متنوع',
			'S13' => 'نقش انسان و نظارت بر سامانه‌های هوش مصنوعی',
			'S14' => 'فرهنگ ایمنی، تفکر انتقادی و تیم قرمز',
			'S15' => 'ارزیابی و مستندسازی تأثیرات هوش مصنوعی',
			'S16' => 'آزمون، مدیریت رخداد و اشتراک‌گذاری اطلاعات',
			'S17' => 'مشارکت ذی‌نفعان بیرونی',
			'S18' => 'ادغام بازخورد در طراحی و پیاده‌سازی',
			'S19' => 'مدیریت ریسک تأمین‌کنندگان و طرف‌های ثالث',
			'S20' => 'آمادگی برای خرابی سامانه‌های طرف ثالث',
		);
		$section_id = isset( $section['section_id'] ) && is_string( $section['section_id'] ) ? $section['section_id'] : '';
		if ( ! isset( $subjects[ $section_id ] ) ) {
			return $fallback;
		}
		if ( 'S01' === $section_id ) {
			return $subjects[ $section_id ];
		}
		// Canonical titles read "AI Governance n.m — …"; the reader overlay
		// shortens them to the NIST code "GOVERN n.m". Both keep the
		// hand-written subject so the heading says what the section is about.
		if ( 1 !== preg_match( '/(AI Governance|^GOVERN)\s+([0-9]+\.[0-9]+)/u', $fallback, $matches ) ) {
			return $fallback;
		}
		return $matches[1] . ' ' . $matches[2] . ' — ' . $subjects[ $section_id ];
	}

	/**
	 * Remove only duplicated Governance code headings from the reading view.
	 * The immutable canonical source remains available to search/citation layers.
	 *
	 * @param array<string,mixed> $section Validated repository section.
	 */
	public static function reader_section_body( array $section ): string {
		$text = isset( $section['fa_text'] ) && is_string( $section['fa_text'] ) ? $section['fa_text'] : '';
		if ( 'P02' !== ( $section['part_id'] ?? null ) || 'C09' !== ( $section['chapter_id'] ?? null ) || ! self::is_legacy_book() ) {
			return $text;
		}
		$text = (string) preg_replace( '/^#{1,5}\s+(?:GOVERN|حاکمیت)(?:\s+[0-9۰-۹]+(?:\.[0-9۰-۹]+)?(?:\s*\([^\n]+\))?)?\s*$/imu', '', $text );
		return trim( (string) preg_replace( "/\n{3,}/", "\n\n", $text ) );
	}

	/** True when the reader overlay marks a section as source-PDF debris. */
	public static function is_omitted_section( array $section ): bool {
		return ! empty( $section['reader_omit'] );
	}

	/**
	 * Reader defaults an administrator can change (Settings → کتاب‌خوان).
	 *
	 * @return array{page_chars:int,default_wide:bool,default_focus:bool}
	 */
	public static function reader_settings(): array {
		$settings = array(
			'page_chars'    => self::DEFAULT_PAGE_CHARS,
			'default_wide'  => false,
			'default_focus' => false,
			'views_show'      => true,
			'views_hide_zero' => true,
		);
		$stored = function_exists( 'get_option' ) ? get_option( 'kbk_reader_settings', array() ) : array();
		if ( is_array( $stored ) ) {
			if ( isset( $stored['page_chars'] ) && is_numeric( $stored['page_chars'] ) ) {
				$settings['page_chars'] = max( self::MIN_PAGE_CHARS, min( self::MAX_PAGE_CHARS, (int) $stored['page_chars'] ) );
			}
			$settings['default_wide']  = ! empty( $stored['default_wide'] );
			$settings['default_focus'] = ! empty( $stored['default_focus'] );
			if ( array_key_exists( 'views_show', $stored ) ) {
				$settings['views_show'] = ! empty( $stored['views_show'] );
			}
			if ( array_key_exists( 'views_hide_zero', $stored ) ) {
				$settings['views_hide_zero'] = ! empty( $stored['views_hide_zero'] );
			}
		}
		return $settings;
	}

	/**
	 * Split a chapter into reading pages. A page takes sections until the
	 * next one would push it past the character budget. A section longer
	 * than the budget is itself cut into parts at its own headings
	 * (section_parts()); each part is then packed like a section, so only an
	 * indivisible block (a part with no heading to cut at) ever exceeds the
	 * budget. Omitted (debris) sections weigh nothing.
	 *
	 * @param array<string,mixed> $chapter
	 * @return array<int,array<int,array{index:int,part:int,parts:int,range:?array}>> Zero-based page => slices.
	 */
	public static function chapter_page_slices( array $chapter, ?int $budget = null ): array {
		$budget = null === $budget ? self::reader_settings()['page_chars'] : max( 1, $budget );
		static $cache = array();
		$key = self::chapter_cache_key( $chapter, $budget );
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}
		$pages = array();
		$page  = array();
		$chars = 0;
		foreach ( $chapter['sections'] ?? array() as $index => $section ) {
			$units = array();
			if ( self::is_omitted_section( $section ) ) {
				$units[] = array( array( 'index' => $index, 'part' => 1, 'parts' => 1, 'range' => null ), 0 );
			} else {
				$length = mb_strlen( (string) ( $section['fa_text'] ?? '' ), 'UTF-8' );
				$ranges = $length > $budget ? self::section_parts( self::reader_document( self::reader_section_body( $section ), (string) $section['structural_id'] ), $budget ) : array();
				if ( count( $ranges ) < 2 ) {
					$units[] = array( array( 'index' => $index, 'part' => 1, 'parts' => 1, 'range' => null ), $length );
				} else {
					foreach ( $ranges as $offset => $range ) {
						$units[] = array( array( 'index' => $index, 'part' => $offset + 1, 'parts' => count( $ranges ), 'range' => array( $range[0], $range[1] ) ), $range[2] );
					}
				}
			}
			foreach ( $units as $unit ) {
				list( $slice, $weight ) = $unit;
				$weight += $weight > 0 ? self::SECTION_OVERHEAD_CHARS : 0;
				if ( $page && $chars + $weight > $budget && $weight > 0 ) {
					$pages[] = $page;
					$page    = array();
					$chars   = 0;
				}
				$page[] = $slice;
				$chars += $weight;
			}
		}
		if ( $page || ! $pages ) {
			$pages[] = $page;
		}
		if ( count( $cache ) > 32 ) {
			$cache = array();
		}
		return $cache[ $key ] = $pages;
	}

	/** Cache key for a chapter's pagination: IDs, budget and body sizes. */
	private static function chapter_cache_key( array $chapter, int $budget ): string {
		$key = ( $chapter['part_id'] ?? '' ) . ( $chapter['chapter_id'] ?? '' ) . ':' . $budget;
		foreach ( $chapter['sections'] ?? array() as $section ) {
			$key .= ':' . ( is_array( $section ) ? strlen( (string) ( $section['fa_text'] ?? '' ) ) . ( empty( $section['reader_omit'] ) ? '' : 'o' ) : '' );
		}
		return md5( $key );
	}

	/**
	 * Section indexes on each page (a split section appears on every page
	 * that holds one of its parts).
	 *
	 * @param array<string,mixed> $chapter
	 * @return array<int,int[]> Zero-based page => section indexes.
	 */
	public static function chapter_pages( array $chapter, ?int $budget = null ): array {
		return array_map(
			static function ( array $slices ): array {
				return array_values( array_unique( array_column( $slices, 'index' ) ) );
			},
			self::chapter_page_slices( $chapter, $budget )
		);
	}

	/**
	 * One-based page number for every anchor in a chapter: each structural ID
	 * (its first part), and for a split section each continuation part
	 * (`ID-p2`…) and each in-section heading (`ID-h3`…). Headings of an
	 * unsplit section share their section's page and are resolved from the
	 * ID prefix by the reader script.
	 *
	 * @param array<string,mixed> $chapter
	 * @return array<string,int>
	 */
	public static function chapter_section_pages( array $chapter, ?int $budget = null ): array {
		static $cache = array();
		$key = self::chapter_cache_key( $chapter, null === $budget ? self::reader_settings()['page_chars'] : max( 1, $budget ) );
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}
		$map = array();
		foreach ( self::chapter_page_slices( $chapter, $budget ) as $page_index => $slices ) {
			foreach ( $slices as $slice ) {
				$section = $chapter['sections'][ $slice['index'] ];
				$sid     = (string) $section['structural_id'];
				if ( 1 === $slice['part'] ) {
					$map[ $sid ] = $page_index + 1;
				}
				if ( $slice['parts'] < 2 ) {
					continue;
				}
				if ( $slice['part'] > 1 ) {
					$map[ $sid . '-p' . $slice['part'] ] = $page_index + 1;
				}
				$doc = self::reader_document( self::reader_section_body( $section ), $sid );
				foreach ( $doc['headings'] as $line => $heading ) {
					if ( $line >= $slice['range'][0] && $line < $slice['range'][1] && '' !== $heading['id'] ) {
						$map[ $heading['id'] ] = $page_index + 1;
					}
				}
			}
		}
		return $cache[ $key ] = $map;
	}

	/** Chapter reading URL; page 1 keeps the historical chapter address. */
	public static function reader_url( string $part_id, ?string $chapter_id = null, int $page = 1 ): string {
		$url = self::book_path( 'read/?' . self::PART_QUERY_VAR . '=' . rawurlencode( $part_id ) );
		if ( null !== $chapter_id ) {
			$url .= '&' . self::CHAPTER_QUERY_VAR . '=' . rawurlencode( $chapter_id );
			if ( $page > 1 ) {
				$url .= '&' . self::PAGE_QUERY_VAR . '=' . $page;
			}
		}
		return home_url( $url );
	}

	/**
	 * The one link builder for a section anywhere in the book: it resolves
	 * the chapter page that holds the section, so TOC, search, related,
	 * citation, verification, concept and graph links all land.
	 */
	public static function section_url( string $part_id, string $chapter_id, string $structural_id ): string {
		$page       = 1;
		$repository = self::repository();
		$chapter    = $repository ? $repository->find_chapter( $part_id, $chapter_id ) : null;
		if ( null !== $chapter ) {
			$page = self::chapter_section_pages( $chapter )[ $structural_id ] ?? 1;
		}
		$url = self::book_path( 'read/?' . self::PART_QUERY_VAR . '=' . rawurlencode( $part_id ) . '&' . self::CHAPTER_QUERY_VAR . '=' . rawurlencode( $chapter_id ) );
		if ( $page > 1 ) {
			$url .= '&' . self::PAGE_QUERY_VAR . '=' . $page;
		}
		$url .= '&' . self::SECTION_QUERY_VAR . '=' . rawurlencode( $structural_id ) . '#' . rawurlencode( $structural_id );
		return home_url( $url );
	}

	/**
	 * The page of the current chapter to render: a requested section wins
	 * (its page is where it lives), then a valid page number, then page 1.
	 *
	 * @param array<string,mixed> $chapter
	 * @param array<string,mixed>|null $section
	 * @return array{page:int,pages:int,indexes:int[],slices:array<int,array<string,mixed>>,all:array<int,array<int,array<string,mixed>>>}
	 */
	public static function current_reader_page( array $chapter, ?array $section = null ): array {
		$all    = self::chapter_pages( $chapter );
		$slices = self::chapter_page_slices( $chapter );
		$pages  = count( $all );
		$page  = 1;
		if ( null !== $section ) {
			$page = self::chapter_section_pages( $chapter )[ $section['structural_id'] ] ?? 1;
		} else {
			$page = min( $pages, self::requested_page() );
		}
		return array(
			'page'    => $page,
			'pages'   => $pages,
			'indexes' => $all[ $page - 1 ] ?? array(),
			'slices'  => $slices[ $page - 1 ] ?? array(),
			'all'     => $slices,
		);
	}

	/**
	 * A concept's definition block. A Persian definition from the data wins;
	 * otherwise the source's English definition is shown as what it is —
	 * labelled «تعریف در منبع (انگلیسی)» and isolated as LTR English. Nothing
	 * is machine-translated here.
	 */
	public static function definition_html( string $definition_fa, string $definition_en ): string {
		if ( '' !== trim( $definition_fa ) ) {
			return '<div class="ab-definition"><p class="ab-definition-label">تعریف</p><p dir="auto">' . self::bidi_html( trim( $definition_fa ) ) . '</p></div>';
		}
		if ( '' === trim( $definition_en ) ) {
			return '';
		}
		return '<div class="ab-definition"><p class="ab-definition-label">تعریف در منبع (انگلیسی)</p><p class="ab-definition-en" lang="en" dir="ltr">' . htmlspecialchars( trim( $definition_en ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '</p></div>';
	}

	/** A secondary English name: muted, `lang="en"`, isolated LTR. */
	public static function english_html( string $text, string $tag = 'p' ): string {
		$tag = in_array( $tag, array( 'p', 'span' ), true ) ? $tag : 'p';
		return '' === trim( $text ) ? '' : '<' . $tag . ' class="ab-title-en" lang="en" dir="ltr">' . htmlspecialchars( trim( $text ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '</' . $tag . '>';
	}

	/** Persian label for a graph relation code; the code stays the key. */
	public static function relation_label( string $code ): string {
		$labels = array(
			'DEFINED_IN'     => 'تعریف‌شده در',
			'PART_OF'        => 'بخشی از',
			'RELATED_TO'     => 'مرتبط با',
			'MITIGATES'      => 'کاهش می‌دهد',
			'CAUSES'         => 'موجب می‌شود',
			'AFFECTS'        => 'اثر می‌گذارد بر',
			'MEASURED_BY'    => 'سنجیده می‌شود با',
			'APPLIES_TO'     => 'به کار می‌رود در',
			'REFERENCES'     => 'ارجاع می‌دهد به',
			'EXTENDS'        => 'گسترش می‌دهد',
			'IMPLEMENTS'     => 'پیاده‌سازی می‌کند',
			'CONTRASTS_WITH' => 'در تقابل با',
			'IMPLEMENTED_BY' => 'پیاده‌سازی‌شده با',
			'OWNED_BY'       => 'در مسئولیت',
			'EVIDENCED_BY'   => 'مستند به',
			'SUPPORTED_BY'   => 'پشتیبانی‌شده با',
			'DERIVED_FROM'   => 'برگرفته از',
		);
		return $labels[ $code ] ?? 'رابطه';
	}

	/** Persian label for a concept/entity type code. */
	public static function entity_type_label( string $type ): string {
		$labels = array(
			'Concept'                => 'مفهوم',
			'Process'                => 'فرایند',
			'Risk'                   => 'ریسک',
			'EvaluationMethod'       => 'روش ارزیابی',
			'Property'               => 'ویژگی',
			'Control'                => 'کنترل',
			'Attack'                 => 'حمله',
			'Mitigation'             => 'راهکار کاهش',
			'Framework'              => 'چارچوب',
			'EvidenceArtifact'       => 'مستند شاهد',
			'OrganizationalRole'     => 'نقش سازمانی',
			'Organization'           => 'سازمان',
			'LegalConcept'           => 'مفهوم حقوقی',
			'Document'               => 'سند',
			'ModelType'              => 'نوع مدل',
			'Publication'            => 'نشریه',
			'KPI'                    => 'شاخص کلیدی عملکرد',
			'ImplementationTemplate' => 'قالب پیاده‌سازی',
			'KRI'                    => 'شاخص کلیدی ریسک',
			'RiskRegisterItem'       => 'قلم ثبت ریسک',
		);
		return $labels[ $type ] ?? 'موجودیت';
	}

	/**
	 * Deterministic neighbourhood layout: neighbours sit on two alternating
	 * rings around the centre and any two label boxes that still collide are
	 * pushed apart along their rays. The viewBox is the bounding box of every
	 * label plus a margin, so no label is ever clipped. A label longer than
	 * one line wraps onto at most two balanced lines (never truncated); each
	 * box is sized to its own lines, and the collision pass uses each box's
	 * own height.
	 *
	 * @param array<int,array{label:string,sub:string}> $nodes
	 * @return array{view:array{0:float,1:float,2:float,3:float},center:array{x:float,y:float,w:float,h:float,lines:string[],ys:float[]},nodes:array<int,array<string,mixed>>}
	 */
	public static function graph_layout( string $center_label, array $nodes ): array {
		$measure = static function ( string $text, float $size ): float {
			return mb_strlen( $text, 'UTF-8' ) * $size * 0.56;
		};
		$center_lines = self::graph_label_lines( $center_label );
		$center_width = 0.0;
		foreach ( $center_lines as $line ) {
			$center_width = max( $center_width, $measure( $line, 16 ) );
		}
		$center_h = 52.0 + 20 * ( count( $center_lines ) - 1 );
		$center   = array(
			'x'     => 0.0,
			'y'     => 0.0,
			'w'     => max( 120.0, $center_width + 36 ),
			'h'     => $center_h,
			'lines' => $center_lines,
			'ys'    => array_map(
				static function ( int $i ) use ( $center_lines ): float {
					return round( 6 - 10 * ( count( $center_lines ) - 1 ) + 20 * $i, 1 );
				},
				array_keys( $center_lines )
			),
		);
		$count  = count( $nodes );
		$boxes  = array();
		foreach ( $nodes as $node ) {
			$lines = self::graph_label_lines( $node['label'] );
			$width = $measure( $node['sub'], 13 );
			foreach ( $lines as $line ) {
				$width = max( $width, $measure( $line, 14 ) );
			}
			// Lines are 18px apart over a 13px relation line; the block is
			// centred in the box.
			$block   = 18 * count( $lines ) + 21;
			$top     = -$block / 2;
			$boxes[] = array(
				'w'      => max( 110.0, $width + 30 ),
				'h'      => $block + 19.0,
				'lines'  => $lines,
				'line_y' => array_map(
					static function ( int $i ) use ( $top ): float {
						return round( $top + 14 + 18 * $i, 1 );
					},
					array_keys( $lines )
				),
				'rel_y'  => round( $top + 18 * count( $lines ) + 17, 1 ),
			);
		}
		// Start tight; the collision pass below only ever pushes boxes outward.
		$radius = max( 150.0, ( $center['w'] / 2 ) + 70 );
		$placed = array();
		foreach ( $nodes as $index => $node ) {
			$angle = -M_PI / 2 + ( $count > 0 ? ( 2 * M_PI * $index / $count ) : 0 );
			$ring  = ( $count > 6 && 1 === $index % 2 ) ? $radius + 70 : $radius;
			$placed[] = array(
				'angle' => $angle,
				'r'     => $ring,
			) + $boxes[ $index ] + $node;
		}
		$position = static function ( array $item ): array {
			return array( cos( $item['angle'] ) * $item['r'] * 1.15, sin( $item['angle'] ) * $item['r'] * 0.72 );
		};
		$overlap = static function ( array $a, array $b, float $gap ): bool {
			return abs( $a[0] - $b[0] ) * 2 < $a[2] + $b[2] + $gap && abs( $a[1] - $b[1] ) * 2 < $a[3] + $b[3] + $gap;
		};
		for ( $pass = 0; $pass < 200; $pass++ ) {
			$moved = false;
			foreach ( $placed as $i => $a ) {
				list( $ax, $ay ) = $position( $a );
				if ( $overlap( array( $ax, $ay, $a['w'], $a['h'] ), array( 0, 0, $center['w'], $center['h'] ), 24 ) ) {
					$placed[ $i ]['r'] += 10;
					$moved = true;
					continue;
				}
				foreach ( $placed as $j => $b ) {
					if ( $j <= $i ) {
						continue;
					}
					list( $bx, $by ) = $position( $placed[ $j ] );
					list( $ax, $ay ) = $position( $placed[ $i ] );
					if ( $overlap( array( $ax, $ay, $placed[ $i ]['w'], $placed[ $i ]['h'] ), array( $bx, $by, $b['w'], $b['h'] ), 12 ) ) {
						$placed[ $placed[ $j ]['r'] >= $placed[ $i ]['r'] ? $j : $i ]['r'] += 10;
						$moved = true;
					}
				}
			}
			if ( ! $moved ) {
				break;
			}
		}
		$min_x = -$center['w'] / 2;
		$max_x = $center['w'] / 2;
		$min_y = -$center['h'] / 2;
		$max_y = $center['h'] / 2;
		$out   = array();
		foreach ( $placed as $item ) {
			list( $x, $y ) = $position( $item );
			$min_x = min( $min_x, $x - $item['w'] / 2 );
			$max_x = max( $max_x, $x + $item['w'] / 2 );
			$min_y = min( $min_y, $y - $item['h'] / 2 );
			$max_y = max( $max_y, $y + $item['h'] / 2 );
			$out[] = array( 'x' => round( $x, 1 ), 'y' => round( $y, 1 ) ) + $item;
		}
		$margin = 16;
		return array(
			'view'   => array( round( $min_x - $margin, 1 ), round( $min_y - $margin, 1 ), round( $max_x - $min_x + 2 * $margin, 1 ), round( $max_y - $min_y + 2 * $margin, 1 ) ),
			'center' => $center,
			'nodes'  => $out,
		);
	}

	/**
	 * A graph label as one line (≤ 24 characters) or two balanced lines,
	 * broken between words; the line width grows until two lines suffice,
	 * so a label is never cut.
	 *
	 * @return string[]
	 */
	public static function graph_label_lines( string $label ): array {
		$label  = trim( (string) preg_replace( '/\s+/u', ' ', $label ) );
		$length = mb_strlen( $label, 'UTF-8' );
		if ( $length <= 24 ) {
			return array( $label );
		}
		$words = explode( ' ', $label );
		for ( $width = max( 14, (int) ceil( $length / 2 ) ); $width < $length; $width++ ) {
			$lines = array( '' );
			foreach ( $words as $word ) {
				$last      = count( $lines ) - 1;
				$candidate = '' === $lines[ $last ] ? $word : $lines[ $last ] . ' ' . $word;
				if ( '' === $lines[ $last ] || mb_strlen( $candidate, 'UTF-8' ) <= $width ) {
					$lines[ $last ] = $candidate;
				} else {
					$lines[] = $word;
				}
			}
			if ( count( $lines ) <= 2 ) {
				return $lines;
			}
		}
		return array( $label );
	}

	private static function render_reader_inline( string $text ): string {
		// A figure marker inside running text is source debris; never print it.
		$text = trim( (string) preg_replace( '/\s*\[FIGURE\b[^\]]*\]\s*/iu', ' ', $text ) );
		// Footnote references become private-use placeholders that survive
		// escaping; an orphan (no definition in this section) is removed.
		$text = (string) preg_replace_callback(
			'/\s*\[\^([^\]]*)\]/u',
			static function ( array $m ): string {
				$out = '';
				foreach ( self::footnote_numbers( $m[1] ) as $number ) {
					if ( null !== self::$notes && isset( self::$notes['notes'][ $number ] ) ) {
						$out .= "\u{E000}" . $number . "\u{E001}";
					}
				}
				return $out;
			},
			$text
		);
		// Markdown links to http(s) addresses open in a new tab; anything else
		// (javascript:, relative, malformed) stays literal text.
		$links = array();
		$text  = (string) preg_replace_callback(
			'/\[([^\]\n]+)\]\((https?:\/\/[^\s()<>"]+)\)/iu',
			static function ( array $m ) use ( &$links ): string {
				$links[] = array( 'text' => $m[1], 'url' => $m[2] );
				return "\u{E002}" . ( count( $links ) - 1 ) . "\u{E003}";
			},
			$text
		);
		$safe = self::bidi_html( $text );
		$safe = (string) preg_replace( '/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $safe );
		$safe = (string) preg_replace( '/(?<!\*)\*([^*]+)\*(?!\*)/u', '<em>$1</em>', $safe );
		$safe = (string) preg_replace( '/`([^`]+)`/u', '<code>$1</code>', $safe );
		if ( array() !== $links ) {
			$safe = (string) preg_replace_callback(
				'/\x{E002}([0-9]+)\x{E003}/u',
				static function ( array $m ) use ( $links ): string {
					$link = $links[ (int) $m[1] ];
					$href = function_exists( 'esc_url' ) ? esc_url( $link['url'], array( 'http', 'https' ) ) : htmlspecialchars( $link['url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
					return '' === $href ? self::bidi_html( $link['text'] ) : '<a href="' . $href . '" rel="noopener noreferrer" target="_blank">' . self::bidi_html( $link['text'] ) . '</a>';
				},
				$safe
			);
		}
		if ( false !== strpos( $safe, "\u{E000}" ) ) {
			$safe = (string) preg_replace_callback(
				'/\x{E000}([0-9]+)\x{E001}/u',
				static function ( array $m ): string {
					return self::footnote_ref( $m[1] );
				},
				$safe
			);
		}
		return $safe;
	}

	/**
	 * Split one Markdown table row into cells.
	 *
	 * Exactly one leading and one trailing pipe are row delimiters; every
	 * other unescaped pipe separates cells, so an empty first or last cell
	 * survives as an empty string instead of shifting the row. `\|` is a
	 * literal pipe inside a cell.
	 *
	 * @return string[]
	 */
	public static function split_table_row( string $line ): array {
		$line = trim( $line );
		if ( '' !== $line && '|' === $line[0] ) {
			$line = (string) substr( $line, 1 );
		}
		if ( '' !== $line && '|' === substr( $line, -1 ) && '\\' !== substr( $line, -2, 1 ) ) {
			$line = (string) substr( $line, 0, -1 );
		}
		$cells = preg_split( '/(?<!\\\\)\|/u', $line );
		if ( false === $cells ) {
			return array( $line );
		}
		return array_map(
			static function ( string $cell ): string {
				return trim( str_replace( '\\|', '|', $cell ) );
			},
			$cells
		);
	}

	/**
	 * Tables keep every cell. Rows shorter than the widest row are padded with
	 * empty cells; a body row wider than its header extends the header with
	 * empty heading cells rather than dropping or merging data. Tables with a
	 * header and two to five columns carry data-label on each cell so narrow
	 * screens can stack them as cards. A headerless table of two or three
	 * columns stacks too, its first cell becoming the card title (no
	 * labels); wider headerless tables scroll.
	 *
	 * @param string[] $lines
	 */
	private static function render_reader_table( array $lines ): string {
		$rows = array_map( array( __CLASS__, 'split_table_row' ), $lines );
		$has_heading = isset( $rows[1] ) && array_reduce(
			$rows[1],
			static function ( bool $valid, string $cell ): bool {
				return $valid && 1 === preg_match( '/^:?-{3,}:?$/', $cell );
			},
			true
		);
		$head = $has_heading ? $rows[0] : array();
		$body = $has_heading ? array_slice( $rows, 2 ) : $rows;
		$width = count( $head );
		foreach ( $body as $row ) {
			$width = max( $width, count( $row ) );
		}
		$pad = static function ( array $row ) use ( $width ): array {
			return array_pad( $row, $width, '' );
		};
		$labels = array_map(
			static function ( string $cell ): string {
				return trim( (string) preg_replace( array( '/\s*\[\^[^\]]*\]/u', '/[*`]+/u' ), '', $cell ) );
			},
			$pad( $head )
		);
		$cards  = $has_heading && $width >= 2 && $width <= 5;
		$titled = ! $has_heading && $width >= 2 && $width <= 3;
		$class  = 'ab-table-wrap ' . ( $cards ? 'ab-table-cards' : ( $titled ? 'ab-table-cards ab-table-titled' : 'ab-table-scroll' ) ) . ( $width <= 2 ? ' ab-table-narrow' : '' );
		$render_body_row = static function ( array $cells ) use ( $pad, $labels, $has_heading, $titled ): string {
			$html = '<tr>';
			foreach ( $pad( $cells ) as $offset => $cell ) {
				$label = $has_heading && '' !== $labels[ $offset ] ? ' data-label="' . htmlspecialchars( $labels[ $offset ], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '"' : '';
				$title = $titled && 0 === $offset ? ' class="ab-cell-title"' : '';
				$html .= '<td' . $label . $title . '>' . self::render_reader_inline( $cell ) . '</td>';
			}
			return $html . '</tr>';
		};
		$html = '<div class="' . $class . '" data-cols="' . (int) $width . '"><table>';
		if ( $has_heading ) {
			$html .= '<thead><tr>';
			foreach ( $pad( $head ) as $cell ) {
				$html .= '<th scope="col">' . self::render_reader_inline( $cell ) . '</th>';
			}
			$html .= '</tr></thead>';
		}
		$html .= '<tbody>' . implode( '', array_map( $render_body_row, $body ) ) . '</tbody></table></div>';
		return $html;
	}

	/**
	 * Resolve the current /ai-book/read/ request against the repository.
	 *
	 * Selection rules (fail closed, never guess):
	 * - No `part` requested → default to the first canonical part/chapter.
	 * - `part` requested but unknown → part/chapter stay null, `part_not_found` is set.
	 * - `part` known, no `chapter` requested → default to that part's first chapter.
	 * - `part` known, `chapter` requested but unknown/ambiguous in that part → chapter stays
	 *   null, `chapter_not_found` is set; the part's chapter list can still render.
	 * - `section` is only honored when it resolves within the selected chapter; otherwise
	 *   it is silently ignored (it only ever affects which in-page anchor is pre-highlighted).
	 *
	 * @return array{part:?array<string,mixed>,chapter:?array<string,mixed>,section:?array<string,mixed>,part_not_found:bool,chapter_not_found:bool}
	 */
	public static function current_reader_selection(): array {
		$selection = array(
			'part'              => null,
			'chapter'           => null,
			'section'           => null,
			'part_not_found'    => false,
			'chapter_not_found' => false,
		);
		$repository = self::repository();
		if ( null === $repository ) {
			return $selection;
		}

		$requested_part = self::requested_identifier( self::PART_QUERY_VAR );
		if ( null === $requested_part ) {
			$parts                = $repository->parts();
			$selection['part']    = $parts[0] ?? null;
		} else {
			$part = $repository->find_part( $requested_part );
			if ( null === $part ) {
				$selection['part_not_found'] = true;
				return $selection;
			}
			$selection['part'] = $part;
		}
		if ( null === $selection['part'] ) {
			return $selection;
		}

		$requested_chapter = self::requested_identifier( self::CHAPTER_QUERY_VAR );
		if ( null === $requested_chapter ) {
			$chapters              = $selection['part']['chapters'];
			$selection['chapter']  = $chapters[0] ?? null;
		} else {
			$chapter = $repository->find_chapter( $selection['part']['part_id'], $requested_chapter );
			if ( null === $chapter ) {
				$selection['chapter_not_found'] = true;
				return $selection;
			}
			$selection['chapter'] = $chapter;
		}
		if ( null === $selection['chapter'] ) {
			return $selection;
		}

		$requested_section = self::requested_identifier( self::SECTION_QUERY_VAR );
		if ( null !== $requested_section ) {
			foreach ( $selection['chapter']['sections'] as $section ) {
				if ( $section['structural_id'] === $requested_section || $section['content_id'] === $requested_section ) {
					$selection['section'] = $section;
					break;
				}
			}
		}
		return $selection;
	}

	/**
	 * The raw search query for the current /ai-book/search/ request, with
	 * control characters and NUL bytes removed and the length capped before
	 * any further use. Unlike identifiers, a query is free Persian/English
	 * text, so it is sanitized (not allowlisted); the search engine treats
	 * anything that survives as plain text and the template escapes it again.
	 */
	public static function requested_search_query(): ?string {
		$value = get_query_var( self::SEARCH_QUERY_VAR );
		if ( is_array( $value ) ) {
			return null;
		}
		$value = trim( (string) wp_unslash( $value ) );
		if ( '' === $value ) {
			return null;
		}
		$clean = str_replace( "\0", '', $value );
		$clean = preg_replace( '/[\x00-\x1F\x7F]/u', ' ', $clean );
		if ( null === $clean ) {
			$clean = preg_replace( '/[^\x20-\x7E]/', '', $value );
		}
		$clean = trim( (string) $clean );
		if ( '' === $clean ) {
			return null;
		}
		if ( 1 === preg_match( '/^.{0,120}/us', $clean, $m ) ) {
			return rtrim( $m[0] );
		}
		return substr( $clean, 0, 120 );
	}

	/**
	 * The allowlisted concept-ID query for the current /ai-book/glossary/
	 * request, or null.
	 */
	public static function requested_concept(): ?string {
		$value = (string) get_query_var( self::CONCEPT_QUERY_VAR );
		if ( '' === $value || 1 !== preg_match( '/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $value ) ) {
			return null;
		}
		return $value;
	}

	/** @return KBK_AI_Book_Search|null */
	public static function search_engine() {
		if ( null !== self::$search || null !== self::$search_error ) {
			return self::$search;
		}
		$repository = self::repository();
		if ( null === $repository ) {
			self::$search_error = 'CONFIG_REQUIRED';
			return null;
		}
		try {
			self::$search = new KBK_AI_Book_Search( $repository );
			self::$search->find_term( 'RISK' ); // force artifact load/validation now, not on first render
		} catch ( InvalidArgumentException | UnexpectedValueException $error ) {
			self::$search        = null;
			self::$search_error  = 'ARTIFACT_INVALID';
		}
		return self::$search;
	}

	public static function search_status(): string {
		self::search_engine();
		return null === self::$search_error ? ( self::$search ? self::$search->status() : 'CONFIG_REQUIRED' ) : self::$search_error;
	}

	/**
	 * Resolved state of the current /ai-book/search/ request. Never throws;
	 * every failure mode degrades to an honest, bounded empty state.
	 *
	 * @return array{status:string,query:?string,tokens:int,results:array<int,array<string,mixed>>,total:int,limit:int,truncated:bool}
	 */
	public static function current_search(): array {
		$engine = self::search_engine();
		if ( null === $engine ) {
			return array(
				'status'    => self::$search_error ?? 'CONFIG_REQUIRED',
				'query'     => self::requested_search_query(),
				'tokens'    => 0,
				'results'   => array(),
				'total'     => 0,
				'limit'     => KBK_AI_Book_Search::DEFAULT_LIMIT,
				'truncated' => false,
				'page'      => 1,
				'pages'     => 1,
			);
		}
		return $engine->search( (string) self::requested_search_query(), KBK_AI_Book_Search::DEFAULT_LIMIT, self::requested_page() );
	}

	/**
	 * The identifier typed into /ai-book/verify/: a content ID, structural ID
	 * or SHA-256. Every one of those is ASCII letters, digits and hyphens, so
	 * anything else is not an identifier and is dropped, not cleaned up.
	 */
	public static function requested_verify_id(): ?string {
		$value = get_query_var( self::VERIFY_QUERY_VAR );
		if ( is_array( $value ) ) {
			return null;
		}
		$value = trim( (string) wp_unslash( $value ) );
		return 1 === preg_match( '/^[A-Za-z0-9-]{1,80}$/', $value ) ? $value : null;
	}

	/**
	 * The /ai-book/verify/ view model: the release facts, and the lookup result
	 * when an identifier was submitted (null match means it is not in the edition).
	 *
	 * @return array{status:string,query:?string,submitted:bool,release:?array,match:?array}
	 */
	public static function current_verification(): array {
		$raw       = get_query_var( self::VERIFY_QUERY_VAR );
		$submitted = ! is_array( $raw ) && '' !== trim( (string) $raw );
		$query     = self::requested_verify_id();
		$repository = self::repository();
		if ( null === $repository ) {
			return array( 'status' => self::repository_status(), 'query' => $query, 'submitted' => $submitted, 'release' => null, 'match' => null );
		}
		return array(
			'status'    => 'READY',
			'query'     => $query,
			'submitted' => $submitted,
			'release'   => $repository->release(),
			'match'     => null === $query ? null : $repository->verify( $query ),
		);
	}

	/**
	 * The resolved glossary term for the current /ai-book/glossary/ request,
	 * or null (fail closed) for absent/unknown identifiers.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function current_glossary_term(): ?array {
		$concept = self::requested_concept();
		if ( null === $concept ) {
			return null;
		}
		$engine = self::search_engine();
		if ( null === $engine ) {
			return null;
		}
		return $engine->find_term( strtoupper( $concept ) );
	}

	/** @return KBK_AI_Book_Catalog|null */
	public static function catalog() {
		if ( null !== self::$catalog || null !== self::$catalog_error ) {
			return self::$catalog;
		}
		$repository = self::repository();
		if ( null === $repository ) {
			self::$catalog_error = 'CONFIG_REQUIRED';
			return null;
		}
		try {
			self::$catalog = new KBK_AI_Book_Catalog( $repository );
			self::$catalog->glossary_terms(); // force artifact load/validation now
		} catch ( InvalidArgumentException | UnexpectedValueException $error ) {
			self::$catalog       = null;
			self::$catalog_error = 'ARTIFACT_INVALID';
		}
		return self::$catalog;
	}

	public static function catalog_status(): string {
		self::catalog();
		return null === self::$catalog_error ? ( self::$catalog ? self::$catalog->status() : 'CONFIG_REQUIRED' ) : self::$catalog_error;
	}

	/** @return KBK_AI_Book_Graph|null */
	public static function graph() {
		if ( null !== self::$graph || null !== self::$graph_error ) {
			return self::$graph;
		}
		$catalog = self::catalog();
		if ( null === $catalog || KBK_AI_Book_Catalog::STATUS_READY !== $catalog->status() ) {
			self::$graph_error = null === $catalog ? 'CONFIG_REQUIRED' : 'ARTIFACT_INVALID';
			return null;
		}
		$repository = self::repository();
		if ( null === $repository ) {
			self::$graph_error = 'CONFIG_REQUIRED';
			return null;
		}
		try {
			self::$graph = new KBK_AI_Book_Graph( $repository, $catalog );
		} catch ( InvalidArgumentException | UnexpectedValueException $error ) {
			self::$graph       = null;
			self::$graph_error = 'ARTIFACT_INVALID';
		}
		return self::$graph;
	}

	public static function graph_status(): string {
		self::graph();
		return null === self::$graph_error ? ( self::$graph ? self::$graph->status() : 'CONFIG_REQUIRED' ) : self::$graph_error;
	}

	/** @return KBK_AI_Book_Ask|null */
	public static function ask() {
		if ( null !== self::$ask || null !== self::$ask_error ) {
			return self::$ask;
		}
		$repository = self::repository();
		if ( null === $repository ) {
			self::$ask_error = 'CONFIG_REQUIRED';
			return null;
		}
		try {
			$provider = null;
			if ( defined( 'KBK_AI_BOOK_ASK_ENDPOINT' ) && defined( 'KBK_AI_BOOK_ASK_API_KEY' ) ) {
				$provider = array(
					'endpoint' => (string) constant( 'KBK_AI_BOOK_ASK_ENDPOINT' ),
					'api_key'  => (string) constant( 'KBK_AI_BOOK_ASK_API_KEY' ),
					'model'    => defined( 'KBK_AI_BOOK_ASK_MODEL' ) ? (string) constant( 'KBK_AI_BOOK_ASK_MODEL' ) : '',
				);
			}
			self::$ask = new KBK_AI_Book_Ask( $repository, null, $provider );
			self::$ask->status(); // force corpus load/validation now
		} catch ( InvalidArgumentException | UnexpectedValueException $error ) {
			self::$ask       = null;
			self::$ask_error = 'ARTIFACT_INVALID';
		}
		return self::$ask;
	}

	public static function ask_status(): string {
		self::ask();
		if ( null !== self::$ask_error ) {
			return self::$ask_error;
		}
		if ( null === self::$ask ) {
			return 'CONFIG_REQUIRED';
		}
		if ( KBK_AI_Book_Ask::STATUS_READY !== self::$ask->status() ) {
			return self::$ask->status();
		}
		return self::$ask->provider_configured() ? 'READY' : 'PROVIDER_REQUIRED';
	}

	/**
	 * Allowlisted template ID for the current /ai-book/templates/ request.
	 */
	public static function requested_template(): ?string {
		$value = (string) get_query_var( self::TEMPLATE_QUERY_VAR );
		if ( '' === $value || 1 !== preg_match( '/^TPL-P\d{2}-\d{1,2}$/', $value ) ) {
			return null;
		}
		return $value;
	}

	/** Allowlisted entity ID for the current /ai-book/concepts/ request. */
	public static function requested_entity(): ?string {
		$value = (string) get_query_var( self::ENTITY_QUERY_VAR );
		if ( '' === $value || 1 !== preg_match( '/^E:[A-Za-z][A-Za-z0-9]{0,40}:[a-z0-9_-]{1,100}$/', $value ) ) {
			return null;
		}
		return $value;
	}

	/** Allowlisted entity type filter for the current /ai-book/concepts/ request. */
	public static function requested_entity_type(): ?string {
		$value = (string) get_query_var( self::TYPE_QUERY_VAR );
		if ( '' === $value || 1 !== preg_match( '/^[A-Za-z][A-Za-z0-9]{0,40}$/', $value ) ) {
			return null;
		}
		return $value;
	}

	/** Clamped page number for paginated catalog views. */
	public static function requested_page(): int {
		$value = (string) get_query_var( self::PAGE_QUERY_VAR );
		if ( '' === $value || 1 !== preg_match( '/^\d{1,4}$/', $value ) ) {
			return 1;
		}
		return max( 1, (int) $value );
	}

	/**
	 * The current ask request: sanitized question plus a bounded engine
	 * answer (retrieval runs locally; generation is provider-gated).
	 *
	 * @return array<string,mixed>
	 */
	public static function current_ask(): array {
		$engine = self::ask();
		if ( null === $engine ) {
			return array(
				'status'    => 'CONFIG_REQUIRED',
				'query'     => null,
				'tokens'    => 0,
				'provider'  => null,
				'provider_error' => null,
				'retrieval' => array(),
				'answer'    => null,
			);
		}
		return $engine->ask( self::requested_search_query() ?? '' );
	}

	/** The validated PDF subsystem, or null in a degraded state. */
	public static function pdf() {
		if ( null !== self::$pdf || null !== self::$pdf_error ) {
			return self::$pdf;
		}
		$repository = self::repository();
		if ( null === $repository ) {
			self::$pdf_error = 'CONFIG_REQUIRED';
			return null;
		}
		try {
			self::$pdf = new KBK_AI_Book_Pdf( $repository );
			self::$pdf->status(); // force manifest + file validation now
		} catch ( InvalidArgumentException | UnexpectedValueException $error ) {
			self::$pdf       = null;
			self::$pdf_error = 'ARTIFACT_INVALID';
		}
		return self::$pdf;
	}

	public static function pdf_status(): string {
		self::pdf();
		return null !== self::$pdf_error ? self::$pdf_error : ( null === self::$pdf ? 'CONFIG_REQUIRED' : self::$pdf->status() );
	}

	/** The request intake, or null when unconfigured. */
	public static function request_engine() {
		if ( null !== self::$request ) {
			return self::$request;
		}
		$provider = null;
		if ( defined( 'KBK_AI_BOOK_REQUEST_ENDPOINT' ) && defined( 'KBK_AI_BOOK_REQUEST_API_KEY' ) ) {
			$provider = array(
				'endpoint' => (string) constant( 'KBK_AI_BOOK_REQUEST_ENDPOINT' ),
				'api_key'  => (string) constant( 'KBK_AI_BOOK_REQUEST_API_KEY' ),
			);
		}
		$edition = '';
		if ( null !== self::repository() ) {
			$edition = (string) self::repository()->summary()['edition_id'];
		}
		self::$request = new KBK_AI_Book_Request( $provider, $edition );
		return self::$request;
	}

	public static function request_status(): string {
		$engine = self::request_engine();
		return $engine->provider_configured() ? 'READY' : 'CONFIG_REQUIRED';
	}

	/**
	 * The current request view state: intake engine, submitted payload
	 * outcome (bounded, storage-free) and sanitized repost values.
	 *
	 * @return array<string,mixed>
	 */
	public static function current_request(): array {
		$engine   = self::request_engine();
		$reposted = array(
			'name'    => isset( $_POST['name'] ) && is_string( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'email'   => isset( $_POST['email'] ) && is_string( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '',
			'use'     => isset( $_POST['use'] ) && is_string( $_POST['use'] ) ? $_POST['use'] : '',
			'reason'  => isset( $_POST['reason'] ) && is_string( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '',
			'consent' => isset( $_POST['consent'] ) && is_string( $_POST['consent'] ) ? $_POST['consent'] : '',
		);
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return array(
				'status'    => $engine->provider_configured() ? 'READY' : 'CONFIG_REQUIRED',
				'errors'    => array(),
				'reference' => null,
				'provider'  => $engine->provider_configured(),
				'reposted'  => $reposted,
			);
		}
		$nonce = isset( $_POST[ KBK_AI_Book_Request::NONCE_FIELD ] ) && is_string( $_POST[ KBK_AI_Book_Request::NONCE_FIELD ] )
			? $_POST[ KBK_AI_Book_Request::NONCE_FIELD ]
			: '';
		if ( 1 !== wp_verify_nonce( $nonce, KBK_AI_Book_Request::NONCE_ACTION ) ) {
			return array(
				'status'    => 'INPUT_INVALID',
				'errors'    => array(
					array(
						'field' => 'session',
						'code'  => 'EXPIRED',
					),
				),
				'reference' => null,
				'provider'  => $engine->provider_configured(),
				'reposted'  => $reposted,
			);
		}
		$payload = array(
			'name'    => $reposted['name'],
			'email'   => $reposted['email'],
			'use'     => $reposted['use'],
			'reason'  => $reposted['reason'],
			'consent' => $reposted['consent'],
		);
		$result = $engine->submit( $payload );
		return array(
			'status'    => $result['status'],
			'errors'    => $result['errors'],
			'reference' => $result['reference'],
			'provider'  => $engine->provider_configured(),
			'reposted'  => $reposted,
		);
	}

	/**
	 * Execute the PDF stream plan on /ai-book/pdf/file/ before any theme
	 * output; every request for the file itself carries noindex headers.
	 */
	public static function maybe_stream_pdf(): void {
		if ( '1' !== (string) get_query_var( self::PDF_FILE_QUERY_VAR ) || '' === self::current_view() ) {
			return;
		}
		$pdf  = self::pdf();
		$plan = null === $pdf ? array() : $pdf->stream_plan();
		if ( empty( $plan['path'] ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}
		$if_none_match = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && is_string( $_SERVER['HTTP_IF_NONE_MATCH'] )
			? $_SERVER['HTTP_IF_NONE_MATCH']
			: null;
		if ( KBK_AI_Book_Pdf::etag_matches( $if_none_match, (string) $plan['etag'] ) ) {
			header( 'ETag: ' . $plan['etag'] );
			header( 'X-Robots-Tag: noindex, nofollow' );
			header( 'Cache-Control: private, max-age=0, must-revalidate' );
			status_header( 304 );
			exit;
		}
		foreach ( $plan['headers'] as $header ) {
			header( $header );
		}
		$handle = @fopen( $plan['path'], 'rb' );
		if ( false === $handle ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}
		while ( ! feof( $handle ) ) {
			echo (string) fread( $handle, 65536 );
			if ( connection_aborted() ) {
				break;
			}
		}
		fclose( $handle );
		exit;
	}

	public static function rewrite_rules(): void {
		if ( ! defined( 'KBK_FEATURE_AI_BOOK' ) || ! KBK_FEATURE_AI_BOOK ) {
			return;
		}
		// /books/ is the library: every title's shelf. Each book lives below it.
		add_rewrite_rule( '^books/?$', 'index.php?' . self::QUERY_VAR . '=library', 'top' );
		add_rewrite_rule( '^fa/books/?$', 'index.php?' . self::QUERY_VAR . '=library', 'top' );
		add_rewrite_rule( '^ai-book/?$', 'index.php?' . self::QUERY_VAR . '=home', 'top' );
		add_rewrite_rule( '^ai-book/read/?$', 'index.php?' . self::QUERY_VAR . '=read', 'top' );
		add_rewrite_rule( '^ai-book/search/?$', 'index.php?' . self::QUERY_VAR . '=search', 'top' );
		add_rewrite_rule( '^ai-book/glossary/?$', 'index.php?' . self::QUERY_VAR . '=glossary', 'top' );
		add_rewrite_rule( '^ai-book/sources/?$', 'index.php?' . self::QUERY_VAR . '=sources', 'top' );
		add_rewrite_rule( '^ai-book/templates/?$', 'index.php?' . self::QUERY_VAR . '=templates', 'top' );
		add_rewrite_rule( '^ai-book/concepts/?$', 'index.php?' . self::QUERY_VAR . '=concepts', 'top' );
		add_rewrite_rule( '^ai-book/graph/?$', 'index.php?' . self::QUERY_VAR . '=graph', 'top' );
		add_rewrite_rule( '^ai-book/ask/?$', 'index.php?' . self::QUERY_VAR . '=ask', 'top' );
		add_rewrite_rule( '^ai-book/pdf/?$', 'index.php?' . self::QUERY_VAR . '=pdf', 'top' );
		add_rewrite_rule( '^ai-book/request-pdf/?$', 'index.php?' . self::QUERY_VAR . '=request-pdf', 'top' );
		add_rewrite_rule( '^ai-book/pdf/file/?$', 'index.php?' . self::QUERY_VAR . '=pdf&' . self::PDF_FILE_QUERY_VAR . '=1', 'top' );
		// Every book lives at /fa/books/{slug}/ with the same views; the slug
		// is checked against the registry at request time (unknown → 404), so
		// adding a book never needs a rewrite flush. /books/{slug}/… are the
		// backward-compatible unlocalized aliases, answered with a 301.
		// Future translations follow the same /{locale}/books/{slug}/ shape.
		$book = '([a-z0-9]+(?:-[a-z0-9]+)*)';
		$with = '&' . self::BOOK_QUERY_VAR . '=$matches[1]';
		foreach ( array( '^books/', '^fa/books/' ) as $prefix ) {
			add_rewrite_rule( $prefix . $book . '/?$', 'index.php?' . self::QUERY_VAR . '=governance' . $with, 'top' );
			add_rewrite_rule( $prefix . $book . '/news/?$', 'index.php?' . self::QUERY_VAR . '=governance-news' . $with, 'top' );
			add_rewrite_rule( $prefix . $book . '/(' . implode( '|', self::BOOK_VIEWS ) . ')/?$', 'index.php?' . self::QUERY_VAR . '=$matches[2]' . $with, 'top' );
			add_rewrite_rule( $prefix . $book . '/pdf/file/?$', 'index.php?' . self::QUERY_VAR . '=pdf&' . self::PDF_FILE_QUERY_VAR . '=1' . $with, 'top' );
		}
		add_rewrite_rule( '^fa/books/' . $book . '/sitemap\\.xml$', 'index.php?' . self::QUERY_VAR . '=sitemap' . $with, 'top' );
		add_rewrite_rule( '^fa/books/' . $book . '/llms\\.txt$', 'index.php?' . self::QUERY_VAR . '=llms' . $with, 'top' );
		// The verification page keeps the address printed in the PDF and DOCX
		// editions, so it stays outside the book namespace and is never redirected.
		add_rewrite_rule( '^ai-book/verify/?$', 'index.php?' . self::QUERY_VAR . '=verify', 'top' );
	}

	public static function current_view(): string {
		$value = (string) get_query_var( self::QUERY_VAR );
		if ( ! in_array( $value, array_merge( array( 'library', 'home', 'governance', 'governance-news', 'verify' ), self::BOOK_VIEWS, self::FEED_VIEWS ), true ) ) {
			return '';
		}
		// A book address whose slug is not routed is not a Reader page.
		$requested = (string) get_query_var( self::BOOK_QUERY_VAR );
		return '' === $requested || KBK_AI_Book_Registry::is_routable( $requested ) ? $value : '';
	}

	public static function is_request(): bool {
		return '' !== self::current_view();
	}

	public static function template_include( string $template ): string {
		if ( ! self::is_request() ) {
			return $template;
		}
		$book_template = dirname( __DIR__ ) . '/templates/ai-book.php';
		return is_file( $book_template ) ? $book_template : $template;
	}

	public static function enqueue_assets(): void {
		if ( ! self::is_request() ) {
			return;
		}
		$theme_assets = trailingslashit( get_template_directory_uri() ) . 'assets/';
		wp_enqueue_style( 'kdcv-page-chrome', $theme_assets . 'css/page-chrome.min.css', array(), KBK_VERSION );
		wp_enqueue_style( 'kbk-ai-book', plugins_url( 'assets/ai-book.css', KBK_PLUGIN_FILE ), array( 'kdcv-page-chrome' ), KBK_VERSION );
		wp_enqueue_script( 'kdcv-page-chrome', $theme_assets . 'js/page-chrome.min.js', array(), KBK_VERSION, true );
		wp_enqueue_script( 'kbk-ai-book', plugins_url( 'assets/ai-book.js', KBK_PLUGIN_FILE ), array( 'kdcv-page-chrome' ), KBK_VERSION, true );
	}

	/** @param array<string,mixed> $robots @return array<string,mixed> */
	public static function robots( array $robots ): array {
		$never_index = in_array( self::current_view(), array( 'search', 'ask', 'request-pdf' ), true ) ||
			( 'pdf' === self::current_view() && '1' === (string) get_query_var( self::PDF_FILE_QUERY_VAR ) ) ||
			( 'verify' === self::current_view() && null !== self::requested_verify_id() );
		if ( self::is_request() && ( $never_index || ! defined( 'KBK_AI_BOOK_INDEXABLE' ) || ! KBK_AI_BOOK_INDEXABLE ) ) {
			$robots['noindex']  = true;
			if ( ! defined( 'KBK_AI_BOOK_INDEXABLE' ) || ! KBK_AI_BOOK_INDEXABLE ) {
				$robots['nofollow'] = true;
			}
		}
		return $robots;
	}

	/** The current book's validated repository (see current_book_slug()). */
	public static function repository(): ?KBK_AI_Book_Repository {
		$slug = self::current_book_slug();
		return null === $slug ? null : KBK_AI_Book_Registry::repository( $slug );
	}

	public static function repository_status(): string {
		$slug = self::current_book_slug();
		return null === $slug ? 'CONFIG_REQUIRED' : KBK_AI_Book_Registry::repository_status( $slug );
	}

	/** Longest <title> a page may carry before its book-name suffix is dropped. */
	const TITLE_MAX_CHARS = 70;

	/** Characters in a generated meta description before it is cut at a word. */
	const DESCRIPTION_CHARS = 155;

	/**
	 * Display facts of one book for titles, shelf cards, feeds and schema.
	 * The first book keeps its hand-written names; every other book takes
	 * them from its registry row, then from its own master/book.json.
	 *
	 * @return array<string,mixed>
	 */
	public static function book_display( ?string $slug = null ): array {
		$slug   = $slug ?? self::current_link_slug();
		$row    = KBK_AI_Book_Registry::find( $slug );
		$legacy = KBK_AI_Book_Registry::LEGACY_SLUG === $slug;
		$repo   = null !== $row && $row['routable'] ? KBK_AI_Book_Registry::repository( $slug ) : null;
		$book   = null !== $repo ? $repo->book() : array();
		$text   = static function ( $value ): string {
			return is_string( $value ) ? trim( $value ) : '';
		};
		$title_fa = $text( $book['title_fa'] ?? '' );
		$row_title = null !== $row ? (string) $row['title_fa'] : '';
		$short = '' !== $row_title ? $row_title : ( '' !== $text( $book['short_title_fa'] ?? '' ) ? $text( $book['short_title_fa'] ) : ( $legacy ? 'حاکمیت هوش مصنوعی' : $title_fa ) );
		$name  = $legacy && '' === $row_title ? 'AI Governance — حاکمیت هوش مصنوعی' : ( '' !== $row_title ? $row_title : ( '' !== $title_fa ? $title_fa : $slug ) );
		$short = '' !== $short ? $short : $name;
		$compiler = $text( $book['compiler'] ?? '' );
		$built    = $text( $book['built_at'] ?? '' );
		return array(
			'slug'        => $slug,
			'legacy'      => $legacy,
			'status'      => null !== $row ? (string) $row['status'] : KBK_AI_Book_Registry::STATUS_ANNOUNCED,
			'routable'    => null !== $row && $row['routable'],
			'ready'       => null !== $repo,
			'name'        => $name,
			'short'       => $short,
			'title_fa'    => $title_fa,
			'title_en'    => $text( $book['title_en'] ?? '' ),
			'subtitle_fa' => null !== $row && '' !== $row['subtitle_fa'] ? (string) $row['subtitle_fa'] : ( $title_fa !== $short ? $title_fa : '' ),
			'meta_fa'     => null !== $row && '' !== $row['meta_fa'] ? (string) $row['meta_fa'] : $compiler,
			'note_fa'     => null !== $row ? (string) $row['note_fa'] : '',
			'compiler'    => $compiler,
			'attribution' => $text( $book['attribution'] ?? '' ),
			'locale'      => $text( $book['locale'] ?? '' ),
			'edition_id'  => $text( $book['edition_id'] ?? '' ),
			'built_date'  => 1 === preg_match( '/^\d{4}-\d{2}-\d{2}/', $built, $date ) ? $date[0] : '',
			'cover'       => self::cover_urls( $slug, null !== $row ? (string) $row['cover'] : '' ),
			'url'         => home_url( self::book_path( '', $slug ) ),
		);
	}

	/**
	 * Cover image URLs of a book: the registry's cover URL, else the
	 * plugin's assets/books/{slug}-cover-w320/-w640.webp when shipped.
	 *
	 * @return array{w320:string,w640:string}|null
	 */
	public static function cover_urls( string $slug, string $cover = '' ): ?array {
		if ( '' !== $cover ) {
			$url = 0 === strpos( $cover, '/' ) ? home_url( $cover ) : $cover;
			return array( 'w320' => $url, 'w640' => $url );
		}
		$dir = dirname( __DIR__ ) . '/assets/books/';
		if ( ! KBK_AI_Book_Registry::valid_slug( $slug ) || ! is_file( $dir . $slug . '-cover-w320.webp' ) ) {
			return null;
		}
		$w320 = plugins_url( 'assets/books/' . $slug . '-cover-w320.webp', KBK_PLUGIN_FILE );
		$w640 = is_file( $dir . $slug . '-cover-w640.webp' ) ? plugins_url( 'assets/books/' . $slug . '-cover-w640.webp', KBK_PLUGIN_FILE ) : $w320;
		return array( 'w320' => $w320, 'w640' => $w640 );
	}

	/**
	 * A page title that fits TITLE_MAX_CHARS: "{label} — {book}", or just the
	 * label when the suffix would push it over (the label is never cut).
	 */
	public static function fitted_title( string $label, string $book ): string {
		$full = '' === $book ? $label : $label . ' — ' . $book;
		return self::char_length( $full ) <= self::TITLE_MAX_CHARS ? $full : $label;
	}

	private static function char_length( string $text ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : (int) preg_match_all( '/./us', $text );
	}

	/**
	 * Plain-text excerpt of reader Markdown for a meta description: footnotes,
	 * figure markers, tables, headings and inline markup are removed, then the
	 * text is cut at a word boundary and closed with «…».
	 */
	public static function plain_excerpt( string $text, int $limit = self::DESCRIPTION_CHARS ): string {
		$patterns = array(
			'/^\s*\[\^[^\]]+\]:.*$/mu'           => ' ',
			'/\[\^[^\]]*\]/u'                    => '',
			'/\[FIGURE[^\]]*\]/iu'               => ' ',
			'/^\s*\|.*$/mu'                      => ' ',
			'/^\s*#{1,6}\s+.*$/mu'               => ' ',
			'/!\[[^\]]*\]\([^)]*\)/u'            => ' ',
			'/\[([^\]]+)\]\([^)]*\)/u'           => '$1',
			'/^\s*(?:[-*+•]|[0-9۰-۹]+[.)])\s+/mu' => '',
			'/^\s*>\s?/mu'                       => '',
			'/\*\*|__|`|\*/u'                    => '',
			'/\s+/u'                             => ' ',
		);
		foreach ( $patterns as $pattern => $replacement ) {
			$text = (string) preg_replace( $pattern, $replacement, $text );
		}
		$text = trim( $text );
		if ( self::char_length( $text ) <= $limit ) {
			return $text;
		}
		$cut   = mb_substr( $text, 0, $limit, 'UTF-8' );
		$space = mb_strrpos( $cut, ' ', 0, 'UTF-8' );
		if ( false !== $space && $space > (int) ( $limit * 0.6 ) ) {
			$cut = mb_substr( $cut, 0, $space, 'UTF-8' );
		}
		return rtrim( $cut, " \t،,;:.-—–(" ) . '…';
	}

	/**
	 * Meta description of one chapter page: the excerpt of its first visible
	 * section (overlay applied), else the chapter title.
	 *
	 * @param array<string,mixed> $chapter
	 * @param int[]               $indexes Section indexes on the page.
	 */
	public static function chapter_description( array $chapter, array $indexes ): string {
		foreach ( $indexes as $index ) {
			$section = $chapter['sections'][ $index ] ?? null;
			if ( ! is_array( $section ) || self::is_omitted_section( $section ) ) {
				continue;
			}
			$excerpt = self::plain_excerpt( self::reader_section_body( $section ) );
			if ( '' !== $excerpt ) {
				return $excerpt;
			}
		}
		return (string) ( $chapter['title_fa'] ?? '' );
	}

	/**
	 * Every indexable address of a book with its build date: the landing, the
	 * tool pages and each page of each chapter (search, ask, the PDF file and
	 * the request form are never listed).
	 *
	 * @return array<int,array{loc:string,lastmod:string}>
	 */
	public static function sitemap_entries( string $slug ): array {
		$repository = KBK_AI_Book_Registry::repository( $slug );
		if ( null === $repository ) {
			return array();
		}
		$lastmod = (string) self::book_display( $slug )['built_date'];
		$entries = array();
		foreach ( array( '', 'news/', 'glossary/', 'sources/', 'templates/', 'concepts/', 'graph/', 'pdf/' ) as $view ) {
			$entries[] = array( 'loc' => home_url( self::book_path( $view, $slug ) ), 'lastmod' => $lastmod );
		}
		foreach ( $repository->parts() as $part ) {
			foreach ( $part['chapters'] as $chapter ) {
				$pages = max( 1, count( self::chapter_pages( $chapter ) ) );
				for ( $page = 1; $page <= $pages; $page++ ) {
					$query = 'read/?' . self::PART_QUERY_VAR . '=' . rawurlencode( $part['part_id'] ) . '&' . self::CHAPTER_QUERY_VAR . '=' . rawurlencode( $chapter['chapter_id'] ) . ( $page > 1 ? '&' . self::PAGE_QUERY_VAR . '=' . $page : '' );
					$entries[] = array( 'loc' => home_url( self::book_path( $query, $slug ) ), 'lastmod' => $lastmod );
				}
			}
		}
		return $entries;
	}

	/** @param array<int,array{loc:string,lastmod:string}> $entries */
	public static function sitemap_xml( array $entries ): string {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( $entries as $entry ) {
			$xml .= '<url><loc>' . htmlspecialchars( $entry['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . '</loc>';
			if ( '' !== $entry['lastmod'] ) {
				$xml .= '<lastmod>' . htmlspecialchars( $entry['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . '</lastmod>';
			}
			$xml .= "</url>\n";
		}
		return $xml . "</urlset>\n";
	}

	/**
	 * The book's llms.txt: what it is, its structure with chapter URLs, how
	 * to cite and verify, and its main sources. Built from the bundle only.
	 */
	public static function llms_txt( string $slug ): string {
		$repository = KBK_AI_Book_Registry::repository( $slug );
		if ( null === $repository ) {
			return '';
		}
		$book    = self::book_display( $slug );
		$summary = $repository->summary();
		$line    = static function ( string $text ): string {
			return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		};
		$out  = '# ' . $line( '' !== $book['title_fa'] ? $book['title_fa'] : $book['name'] ) . "\n\n";
		$lead = array_filter( array( $book['title_en'], $book['attribution'] ) );
		if ( array() !== $lead ) {
			$out .= '> ' . $line( implode( ' — ', $lead ) ) . "\n\n";
		}
		$out .= 'کتاب فارسی «' . $line( $book['short'] ) . '»'
			. ( '' !== $book['compiler'] ? '، تدوین ' . $line( $book['compiler'] ) : '' )
			. '؛ ویرایش ' . $summary['edition_id']
			. '، ' . $summary['parts'] . ' بخش، ' . $summary['chapters'] . ' فصل و ' . $summary['sections'] . ' بخش محتوایی'
			. ( '' !== $book['built_date'] ? '؛ ساخت ' . $book['built_date'] : '' ) . ".\n"
			. 'نشانی کتاب: ' . $book['url'] . "\n\n";
		$out .= "## ساختار\n\n";
		foreach ( $repository->parts() as $part ) {
			$out .= '### ' . self::location_label( $part['part_id'] ) . ' — ' . $line( $part['title_fa'] ) . "\n\n";
			foreach ( $part['chapters'] as $chapter ) {
				$url  = home_url( self::book_path( 'read/?' . self::PART_QUERY_VAR . '=' . rawurlencode( $part['part_id'] ) . '&' . self::CHAPTER_QUERY_VAR . '=' . rawurlencode( $chapter['chapter_id'] ), $slug ) );
				$out .= '- [' . $line( $chapter['title_fa'] ) . '](' . $url . ')' . "\n";
			}
			$out .= "\n";
		}
		$out .= "## ابزارهای کتاب\n\n";
		foreach ( array( 'glossary/' => 'واژه‌نامه', 'concepts/' => 'مفاهیم', 'graph/' => 'نگارهٔ مفاهیم', 'sources/' => 'منابع', 'templates/' => 'قالب‌ها', 'pdf/' => 'نسخهٔ PDF', 'sitemap.xml' => 'نقشهٔ سایت کتاب' ) as $view => $label ) {
			$out .= '- [' . $label . '](' . home_url( self::book_path( $view, $slug ) ) . ")\n";
		}
		$code = KBK_AI_Book_Registry::code( $slug );
		$out .= "\n## استناد و اعتبارسنجی\n\n"
			. '- شناسهٔ ساختاری هر بخش: `KDJ-' . $code . '-' . $summary['edition_id'] . '-Pnn-Cnn-Snn` (بخش، فصل، بخش محتوایی).' . "\n"
			. '- شناسهٔ محتوا: شناسهٔ ساختاری و هشت نویسهٔ هگز از SHA-256 همان متن، مانند `KDJ-' . $code . '-' . $summary['edition_id'] . '-P01-C01-S01-XXXXXXXX`.' . "\n"
			. '- پیوند پایدار هر بخش: ' . home_url( self::book_path( 'read/?' . self::PART_QUERY_VAR . '=Pnn&' . self::CHAPTER_QUERY_VAR . '=Cnn&' . self::SECTION_QUERY_VAR . '={structural-id}', $slug ) ) . "\n"
			. '- اعتبارسنجی شناسه یا اثر انگشت SHA-256: ' . home_url( '/ai-book/verify/?' . self::VERIFY_QUERY_VAR . '={id}' ) . "\n";
		$sources = array();
		try {
			$catalog = new KBK_AI_Book_Catalog( $repository );
			$sources = $catalog->sources();
		} catch ( InvalidArgumentException | UnexpectedValueException $error ) {
			$sources = array();
		}
		if ( array() !== $sources ) {
			usort( $sources, static function ( array $a, array $b ): int {
				return $b['sections_count'] <=> $a['sections_count'];
			} );
			$out .= "\n## منابع\n\n";
			foreach ( array_slice( $sources, 0, 12 ) as $source ) {
				$title = '' !== $source['title_fa'] ? $source['title_fa'] . ' (' . $source['title'] . ')' : $source['title'];
				$out  .= '- ' . $line( $title )
					. ( '' !== $source['series_identifier'] ? ' — ' . $line( $source['series_identifier'] ) : '' )
					. ( '' !== $source['organization'] ? '، ' . $line( $source['organization'] ) : '' )
					. ( '' !== $source['doi_url'] ? ' — ' . $source['doi_url'] : '' ) . "\n";
			}
			$out .= '- فهرست کامل: ' . home_url( self::book_path( 'sources/', $slug ) ) . "\n";
		}
		return $out;
	}

	/**
	 * Serve /fa/books/{slug}/sitemap.xml and llms.txt before any theme
	 * output. The sitemap is cached per edition and page budget.
	 */
	public static function maybe_serve_book_feed(): void {
		$view = self::current_view();
		if ( ! in_array( $view, self::FEED_VIEWS, true ) ) {
			return;
		}
		$slug = self::current_book_slug();
		$repository = null === $slug ? null : KBK_AI_Book_Registry::repository( $slug );
		if ( null === $repository ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}
		$summary = $repository->summary();
		$key     = 'kbk_book_' . $view . '_' . md5( $slug . '|' . $summary['edition_id'] . '|' . (string) ( $repository->book()['built_at'] ?? '' ) . '|' . self::reader_settings()['page_chars'] . '|' . home_url( '/' ) . '|' . ( defined( 'KBK_VERSION' ) ? KBK_VERSION : '' ) );
		$body    = function_exists( 'get_transient' ) ? get_transient( $key ) : false;
		if ( ! is_string( $body ) || '' === $body ) {
			$body = 'sitemap' === $view ? self::sitemap_xml( self::sitemap_entries( $slug ) ) : self::llms_txt( $slug );
			if ( function_exists( 'set_transient' ) ) {
				set_transient( $key, $body, DAY_IN_SECONDS );
			}
		}
		status_header( 200 );
		if ( 'sitemap' === $view ) {
			header( 'Content-Type: application/xml; charset=UTF-8' );
		} else {
			header( 'Content-Type: text/plain; charset=UTF-8' );
			header( 'X-Robots-Tag: noindex' );
		}
		header( 'Cache-Control: public, max-age=300, must-revalidate' );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML/plain text built and escaped above.
		exit;
	}

	/**
	 * Advertise every routed book's sitemap and llms.txt in WordPress's
	 * virtual robots.txt — only once the books are indexable, so crawlers
	 * are never sent to noindex pages.
	 *
	 * @param mixed $output
	 * @param mixed $public
	 */
	public static function robots_txt( $output, $public ): string {
		$output = (string) $output;
		if ( ! $public || ! defined( 'KBK_AI_BOOK_INDEXABLE' ) || ! KBK_AI_BOOK_INDEXABLE ) {
			return $output;
		}
		$lines = '';
		foreach ( KBK_AI_Book_Registry::routable_slugs() as $slug ) {
			if ( null === KBK_AI_Book_Registry::repository( $slug ) ) {
				continue;
			}
			$lines .= 'Sitemap: ' . home_url( self::book_path( 'sitemap.xml', $slug ) ) . "\n"
				. '# ' . home_url( self::book_path( 'llms.txt', $slug ) ) . "\n";
		}
		return '' === $lines ? $output : rtrim( $output, "\n" ) . "\n\n# Books: one sitemap and one llms.txt per title.\n" . $lines;
	}
}
