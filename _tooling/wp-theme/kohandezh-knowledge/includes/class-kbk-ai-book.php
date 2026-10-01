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
	const PDF_FILE_QUERY_VAR = 'kbk_pdf_file';

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
			$repository = self::repository();
			$target = $repository ? self::legacy_reader_path( $request_uri, $repository ) : null;
			if ( null !== $target ) {
				wp_safe_redirect( home_url( $target ), 301 );
				exit;
			}
		}
		if ( 1 !== preg_match( '#^/books/ai-governance(?:/|\?|$)#', $request_uri ) ) {
			return;
		}
		$localized_uri = preg_replace( '#^/books/ai-governance#', '/fa/books/ai-governance', $request_uri, 1 );
		if ( ! is_string( $localized_uri ) ) {
			return;
		}
		wp_safe_redirect( home_url( $localized_uri ), 301 );
		exit;
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
		return substr( $url, 0, $offset ) . '/fa/books/ai-governance/' . substr( $url, $offset + strlen( $marker ) );
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
	 */
	public static function render_reader_markdown( string $text ): string {
		$lines = preg_split( '/\r\n|\r|\n/', $text );
		if ( false === $lines ) {
			return '';
		}

		$html       = array();
		$paragraph  = array();
		$list_items = array();
		$list_type  = null;
		$flush_paragraph = static function () use ( &$html, &$paragraph ): void {
			if ( $paragraph ) {
				$html[]    = '<p dir="auto">' . self::render_reader_inline( implode( ' ', $paragraph ) ) . '</p>';
				$paragraph = array();
			}
		};
		$flush_list = static function () use ( &$html, &$list_items, &$list_type ): void {
			if ( null !== $list_type && $list_items ) {
				$items = array_map(
					static function ( string $item ): string {
						return '<li dir="auto">' . self::render_reader_inline( $item ) . '</li>';
					},
					$list_items
				);
				$html[] = '<' . $list_type . '>' . implode( '', $items ) . '</' . $list_type . '>';
			}
			$list_items = array();
			$list_type  = null;
		};

		$count = count( $lines );
		for ( $index = 0; $index < $count; $index++ ) {
			$line = trim( $lines[ $index ] );
			if ( '' === $line ) {
				$flush_paragraph();
				$flush_list();
				continue;
			}

			if ( 1 === preg_match( '/^\|.*\|$/u', $line ) ) {
				$table_lines = array();
				while ( $index < $count && 1 === preg_match( '/^\s*\|.*\|\s*$/u', $lines[ $index ] ) ) {
					$table_lines[] = trim( $lines[ $index ] );
					$index++;
				}
				$index--;
				$flush_paragraph();
				$flush_list();
				$html[] = self::render_reader_table( $table_lines );
				continue;
			}

			if ( 1 === preg_match( '/^(#{1,5})\s+(.+)$/u', $line, $matches ) ) {
				$flush_paragraph();
				$flush_list();
				$level  = min( 5, max( 3, strlen( $matches[1] ) + 1 ) );
				$html[] = '<h' . $level . '>' . self::render_reader_inline( $matches[2] ) . '</h' . $level . '>';
				continue;
			}

			if ( 1 === preg_match( '/^[-*]\s+(.+)$/u', $line, $matches ) || 1 === preg_match( '/^\d+[.)]\s+(.+)$/u', $line, $matches ) ) {
				$requested_type = 1 === preg_match( '/^\d/u', $line ) ? 'ol' : 'ul';
				$flush_paragraph();
				if ( null !== $list_type && $requested_type !== $list_type ) {
					$flush_list();
				}
				$list_type    = $requested_type;
				$list_items[] = $matches[1];
				continue;
			}

			$flush_list();
			if ( 1 === preg_match( '/^>\s*(.+)$/u', $line, $matches ) ) {
				$flush_paragraph();
				$html[] = '<blockquote><p>' . self::render_reader_inline( $matches[1] ) . '</p></blockquote>';
				continue;
			}
			if ( 1 === preg_match( '/^\[FIGURE[^]]*\]/iu', $line ) ) {
				$flush_paragraph();
				$html[] = '<p class="ab-source-marker"><bdi>' . self::render_reader_inline( $line ) . '</bdi></p>';
				continue;
			}
			$paragraph[] = $line;
		}

		$flush_paragraph();
		$flush_list();
		return implode( "\n", array_filter( $html ) );
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
		if ( 'P02' !== ( $section['part_id'] ?? null ) || 'C09' !== ( $section['chapter_id'] ?? null ) ) {
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
		if ( 1 !== preg_match( '/AI Governance\s+([0-9]+\.[0-9]+)/u', $fallback, $matches ) ) {
			return $fallback;
		}
		return 'AI Governance ' . $matches[1] . ' — ' . $subjects[ $section_id ];
	}

	/**
	 * Remove only duplicated Governance code headings from the reading view.
	 * The immutable canonical source remains available to search/citation layers.
	 *
	 * @param array<string,mixed> $section Validated repository section.
	 */
	public static function reader_section_body( array $section ): string {
		$text = isset( $section['fa_text'] ) && is_string( $section['fa_text'] ) ? $section['fa_text'] : '';
		if ( 'P02' !== ( $section['part_id'] ?? null ) || 'C09' !== ( $section['chapter_id'] ?? null ) ) {
			return $text;
		}
		$text = (string) preg_replace( '/^#{1,5}\s+(?:GOVERN|حاکمیت)(?:\s+[0-9۰-۹]+(?:\.[0-9۰-۹]+)?(?:\s*\([^\n]+\))?)?\s*$/imu', '', $text );
		return trim( (string) preg_replace( "/\n{3,}/", "\n\n", $text ) );
	}

	private static function render_reader_inline( string $text ): string {
		$safe = htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		$safe = (string) preg_replace( '/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $safe );
		$safe = (string) preg_replace( '/(?<!\*)\*([^*]+)\*(?!\*)/u', '<em>$1</em>', $safe );
		$safe = (string) preg_replace( '/`([^`]+)`/u', '<code>$1</code>', $safe );
		return $safe;
	}

	/** @param string[] $lines */
	private static function render_reader_table( array $lines ): string {
		$rows = array_map(
			static function ( string $line ): array {
				return array_map( 'trim', explode( '|', trim( $line, " \t\n\r\0\x0B|" ) ) );
			},
			$lines
		);
		$has_heading = isset( $rows[1] ) && array_reduce(
			$rows[1],
			static function ( bool $valid, string $cell ): bool {
				return $valid && 1 === preg_match( '/^:?-{3,}:?$/', $cell );
			},
			true
		);
		$render_row = static function ( array $cells, string $tag ): string {
			return '<tr>' . implode(
				'',
				array_map(
					static function ( string $cell ) use ( $tag ): string {
						return '<' . $tag . '>' . self::render_reader_inline( $cell ) . '</' . $tag . '>';
					},
					$cells
				)
			) . '</tr>';
		};
		if ( ! $has_heading ) {
			return '<div class="ab-table-wrap"><table><tbody>' . implode( '', array_map( static function ( array $row ) use ( $render_row ): string { return $render_row( $row, 'td' ); }, $rows ) ) . '</tbody></table></div>';
		}
		$head = $render_row( $rows[0], 'th' );
		$body = array_slice( $rows, 2 );
		return '<div class="ab-table-wrap"><table><thead>' . $head . '</thead><tbody>' . implode( '', array_map( static function ( array $row ) use ( $render_row ): string { return $render_row( $row, 'td' ); }, $body ) ) . '</tbody></table></div>';
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
		// Backward-compatible unlocalized aliases. Indexable pages declare the
		// locale-prefixed Persian namespace below as canonical.
		add_rewrite_rule( '^books/ai-governance/?$', 'index.php?' . self::QUERY_VAR . '=governance', 'top' );
		add_rewrite_rule( '^books/ai-governance/news/?$', 'index.php?' . self::QUERY_VAR . '=governance-news', 'top' );
		foreach ( array( 'read', 'search', 'glossary', 'sources', 'templates', 'concepts', 'graph', 'ask', 'pdf', 'request-pdf' ) as $view ) {
			add_rewrite_rule( '^books/ai-governance/' . $view . '/?$', 'index.php?' . self::QUERY_VAR . '=' . $view, 'top' );
		}
		add_rewrite_rule( '^books/ai-governance/pdf/file/?$', 'index.php?' . self::QUERY_VAR . '=pdf&' . self::PDF_FILE_QUERY_VAR . '=1', 'top' );
		// Only Persian exists today. Future translations must follow the same
		// /{locale}/books/ai-governance/ shape, but are not routed until real
		// localized content is available.
		add_rewrite_rule( '^fa/books/ai-governance/?$', 'index.php?' . self::QUERY_VAR . '=governance', 'top' );
		add_rewrite_rule( '^fa/books/ai-governance/news/?$', 'index.php?' . self::QUERY_VAR . '=governance-news', 'top' );
		foreach ( array( 'read', 'search', 'glossary', 'sources', 'templates', 'concepts', 'graph', 'ask', 'pdf', 'request-pdf' ) as $view ) {
			add_rewrite_rule( '^fa/books/ai-governance/' . $view . '/?$', 'index.php?' . self::QUERY_VAR . '=' . $view, 'top' );
		}
		add_rewrite_rule( '^fa/books/ai-governance/pdf/file/?$', 'index.php?' . self::QUERY_VAR . '=pdf&' . self::PDF_FILE_QUERY_VAR . '=1', 'top' );
	}

	public static function current_view(): string {
		$value = (string) get_query_var( self::QUERY_VAR );
		return in_array( $value, array( 'library', 'home', 'governance', 'governance-news', 'read', 'search', 'glossary', 'sources', 'templates', 'concepts', 'graph', 'ask', 'pdf', 'request-pdf' ), true ) ? $value : '';
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
			( 'pdf' === self::current_view() && '1' === (string) get_query_var( self::PDF_FILE_QUERY_VAR ) );
		if ( self::is_request() && ( $never_index || ! defined( 'KBK_AI_BOOK_INDEXABLE' ) || ! KBK_AI_BOOK_INDEXABLE ) ) {
			$robots['noindex']  = true;
			if ( ! defined( 'KBK_AI_BOOK_INDEXABLE' ) || ! KBK_AI_BOOK_INDEXABLE ) {
				$robots['nofollow'] = true;
			}
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
