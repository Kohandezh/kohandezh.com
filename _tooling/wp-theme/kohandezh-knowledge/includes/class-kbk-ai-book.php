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

	public static function rewrite_rules(): void {
		if ( ! defined( 'KBK_FEATURE_AI_BOOK' ) || ! KBK_FEATURE_AI_BOOK ) {
			return;
		}
		add_rewrite_rule( '^ai-book/?$', 'index.php?' . self::QUERY_VAR . '=home', 'top' );
		add_rewrite_rule( '^ai-book/read/?$', 'index.php?' . self::QUERY_VAR . '=read', 'top' );
	}

	public static function current_view(): string {
		$value = (string) get_query_var( self::QUERY_VAR );
		return in_array( $value, array( 'home', 'read' ), true ) ? $value : '';
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
