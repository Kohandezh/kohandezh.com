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

	const QUERY_VAR = 'kbk_ai_book';

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
		return $vars;
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
