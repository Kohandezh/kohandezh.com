<?php
/**
 * KBK_AI_Book — feature-flagged WordPress routes for the Persian book.
 *
 * @package Kohandezh_Knowledge
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'KBK_TESTING' ) ) {
	exit;
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

	/**
	 * Bounds any hand-typed or linked identifier before it ever reaches the
	 * repository. The repository only ever compares this value against known
	 * IDs/slugs (never uses it to build a filesystem path), but request input
	 * is validated at the boundary regardless of how it is later used.
	 */
	const IDENTIFIER_PATTERN = '/^[A-Za-z0-9-]{1,80}$/';

	/** @var KBK_AI_Book_Repository|null */
	private static $repository;

	/** @var string|null */
	private static $repository_error;

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

	public static function hooks(): void {
		if ( ! defined( 'KBK_FEATURE_AI_BOOK' ) || ! KBK_FEATURE_AI_BOOK ) {
			return;
		}
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'init', array( __CLASS__, 'rewrite_rules' ), 20 );
		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 30 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
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
			);
		}
		return $engine->search( (string) self::requested_search_query() );
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

	public static function rewrite_rules(): void {
		if ( ! defined( 'KBK_FEATURE_AI_BOOK' ) || ! KBK_FEATURE_AI_BOOK ) {
			return;
		}
		add_rewrite_rule( '^ai-book/?$', 'index.php?' . self::QUERY_VAR . '=home', 'top' );
		add_rewrite_rule( '^ai-book/read/?$', 'index.php?' . self::QUERY_VAR . '=read', 'top' );
		add_rewrite_rule( '^ai-book/search/?$', 'index.php?' . self::QUERY_VAR . '=search', 'top' );
		add_rewrite_rule( '^ai-book/glossary/?$', 'index.php?' . self::QUERY_VAR . '=glossary', 'top' );
		add_rewrite_rule( '^ai-book/sources/?$', 'index.php?' . self::QUERY_VAR . '=sources', 'top' );
		add_rewrite_rule( '^ai-book/templates/?$', 'index.php?' . self::QUERY_VAR . '=templates', 'top' );
		add_rewrite_rule( '^ai-book/concepts/?$', 'index.php?' . self::QUERY_VAR . '=concepts', 'top' );
		add_rewrite_rule( '^ai-book/graph/?$', 'index.php?' . self::QUERY_VAR . '=graph', 'top' );
	}

	public static function current_view(): string {
		$value = (string) get_query_var( self::QUERY_VAR );
		return in_array( $value, array( 'home', 'read', 'search', 'glossary', 'sources', 'templates', 'concepts', 'graph' ), true ) ? $value : '';
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
		wp_enqueue_style( 'kbk-ai-book', plugins_url( 'assets/ai-book.css', KBK_PLUGIN_FILE ), array(), KBK_VERSION );
		wp_enqueue_script( 'kbk-ai-book', plugins_url( 'assets/ai-book.js', KBK_PLUGIN_FILE ), array(), KBK_VERSION, true );
	}

	/** @param array<string,mixed> $robots @return array<string,mixed> */
	public static function robots( array $robots ): array {
		if ( self::is_request() && ( ! defined( 'KBK_AI_BOOK_INDEXABLE' ) || ! KBK_AI_BOOK_INDEXABLE ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	public static function repository(): ?KBK_AI_Book_Repository {
		if ( null !== self::$repository || null !== self::$repository_error ) {
			return self::$repository;
		}
		if ( ! defined( 'KBK_AI_BOOK_ROOT' ) || ! is_string( KBK_AI_BOOK_ROOT ) || '' === trim( KBK_AI_BOOK_ROOT ) ) {
			self::$repository_error = 'CONFIG_REQUIRED';
			return null;
		}
		try {
			self::$repository = new KBK_AI_Book_Repository( KBK_AI_BOOK_ROOT );
			self::$repository->bundle();
		} catch ( InvalidArgumentException $error ) {
			self::$repository_error = 'CONFIG_INVALID';
		} catch ( UnexpectedValueException $error ) {
			self::$repository_error = 'ARTIFACT_INVALID';
		}
		return self::$repository;
	}

	public static function repository_status(): string {
		self::repository();
		return null === self::$repository_error ? 'READY' : self::$repository_error;
	}
}
