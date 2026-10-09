<?php
/**
 * KBK_AI_Book_Views — per-section and per-chapter view counters.
 *
 *   POST /wp-json/kohandezh/v1/book-views  {book, part, chapter, section}
 *   GET  /wp-json/kohandezh/v1/book-views?book=&part=&chapter=
 *
 * Privacy: a counter is a bare integer per key. No cookie, no IP, no user
 * agent, no timestamp per visitor is stored; repeat views are deduplicated
 * in the browser only (sessionStorage). Keys:
 *   {slug}|{structural_id}        one section
 *   {slug}|{part}|{chapter}       one chapter total
 * Every ID is checked against the book's repository before it is counted,
 * so the table can never fill with garbage keys.
 *
 * @package Kohandezh_Knowledge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KBK_AI_Book_Views {

	const SCHEMA_OPTION  = 'kbk_views_schema';
	const SCHEMA_VERSION = 1;
	const ROUTE          = '/book-views';
	const MAX_BODY       = 1024;
	const SECTION_RE     = '/^KDJ-[A-Z0-9]{1,8}-\d{4}E\d+-P\d{2}-C\d{2}-S\d{2}$/';
	const PART_RE        = '/^P\d{2}$/';
	const CHAPTER_RE     = '/^C\d{2}$/';

	/** @var bool|null */
	private static $table_ok = null;

	public static function hooks(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
		add_action( 'admin_post_kbk_views_reset', array( __CLASS__, 'handle_reset' ) );
	}

	public static function reset_cache(): void {
		self::$table_ok = null;
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'kbk_views';
	}

	/** Counters are on unless the admin switched them off. */
	public static function enabled(): bool {
		$settings = KBK_AI_Book::reader_settings();
		return ! empty( $settings['views_show'] );
	}

	public static function hide_zero(): bool {
		$settings = KBK_AI_Book::reader_settings();
		return ! empty( $settings['views_hide_zero'] );
	}

	// ---------------------------------------------------------------- schema

	public static function maybe_install(): void {
		if ( (int) get_option( self::SCHEMA_OPTION, 0 ) >= self::SCHEMA_VERSION ) {
			return;
		}
		self::install();
	}

	public static function install(): void {
		global $wpdb;
		$charset = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';
		$sql     = 'CREATE TABLE ' . self::table() . " (\n"
			. "view_key varchar(120) NOT NULL,\n"
			. "views bigint(20) unsigned NOT NULL DEFAULT 0,\n"
			. "updated datetime NOT NULL DEFAULT '1970-01-01 00:00:00',\n"
			. "PRIMARY KEY  (view_key)\n"
			. ") {$charset};";
		if ( ! function_exists( 'dbDelta' ) && defined( 'ABSPATH' ) && is_readable( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		if ( function_exists( 'dbDelta' ) ) {
			dbDelta( $sql );
		} else {
			$wpdb->query( str_replace( 'CREATE TABLE ', 'CREATE TABLE IF NOT EXISTS ', $sql ) );
		}
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION );
		self::$table_ok = null;
	}

	/** True when the table exists; installs it once if it does not (zip upload without activation). */
	public static function ensure_table(): bool {
		if ( null !== self::$table_ok ) {
			return self::$table_ok;
		}
		global $wpdb;
		$table = self::table();
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( $found !== $table ) {
			self::install();
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		}
		self::$table_ok = ( $found === $table );
		return self::$table_ok;
	}

	// ------------------------------------------------------------ validation

	public static function section_key( string $slug, string $section ): string {
		return $slug . '|' . $section;
	}

	public static function chapter_key( string $slug, string $part, string $chapter ): string {
		return $slug . '|' . $part . '|' . $chapter;
	}

	/**
	 * Resolve a chapter of a routed book by its stable IDs only (no slugs).
	 *
	 * @return array<string,mixed>|null
	 */
	public static function resolve_chapter( string $slug, string $part, string $chapter ): ?array {
		if ( 1 !== preg_match( self::PART_RE, $part ) || 1 !== preg_match( self::CHAPTER_RE, $chapter ) ) {
			return null;
		}
		if ( ! KBK_AI_Book_Registry::is_routable( $slug ) ) {
			return null;
		}
		$repository = KBK_AI_Book_Registry::repository( $slug );
		if ( null === $repository ) {
			return null;
		}
		$found = $repository->find_chapter( $part, $chapter );
		return ( null !== $found && $found['chapter_id'] === $chapter ) ? $found : null;
	}

	/** @param array<string,mixed> $chapter */
	public static function chapter_has_section( array $chapter, string $section ): bool {
		if ( 1 !== preg_match( self::SECTION_RE, $section ) ) {
			return false;
		}
		foreach ( $chapter['sections'] as $row ) {
			if ( isset( $row['structural_id'] ) && $row['structural_id'] === $section ) {
				return true;
			}
		}
		return false;
	}

	/** @param array<string,mixed> $chapter @return string[] */
	public static function chapter_section_ids( array $chapter ): array {
		$ids = array();
		foreach ( $chapter['sections'] as $row ) {
			if ( isset( $row['structural_id'] ) && 1 === preg_match( self::SECTION_RE, (string) $row['structural_id'] ) ) {
				$ids[] = (string) $row['structural_id'];
			}
		}
		return $ids;
	}

	// --------------------------------------------------------------- storage

	public static function increment( string $key ): bool {
		if ( ! self::ensure_table() ) {
			return false;
		}
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		$sql = $wpdb->prepare(
			'INSERT INTO ' . self::table() . ' (view_key, views, updated) VALUES (%s, 1, %s) ON DUPLICATE KEY UPDATE views = views + 1, updated = %s',
			$key,
			$now,
			$now
		);
		return false !== $wpdb->query( $sql );
	}

	/**
	 * @param string[] $keys
	 * @return array<string,int> every requested key, 0 when never counted
	 */
	public static function counts( array $keys ): array {
		$out = array_fill_keys( $keys, 0 );
		if ( array() === $keys || ! self::ensure_table() ) {
			return $out;
		}
		global $wpdb;
		$placeholders = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
		$rows         = $wpdb->get_results( $wpdb->prepare( 'SELECT view_key, views FROM ' . self::table() . " WHERE view_key IN ({$placeholders})", $keys ), ARRAY_A );
		foreach ( (array) $rows as $row ) {
			if ( isset( $out[ $row['view_key'] ] ) ) {
				$out[ $row['view_key'] ] = (int) $row['views'];
			}
		}
		return $out;
	}

	/**
	 * All counts for one chapter in a single query.
	 *
	 * @param array<string,mixed> $chapter
	 * @return array{sections:array<string,int>,chapter:int}
	 */
	public static function chapter_counts( string $slug, string $part, array $chapter ): array {
		$ids  = self::chapter_section_ids( $chapter );
		$keys = array();
		foreach ( $ids as $id ) {
			$keys[] = self::section_key( $slug, $id );
		}
		$chapter_key = self::chapter_key( $slug, $part, (string) $chapter['chapter_id'] );
		$keys[]      = $chapter_key;
		$counts      = self::counts( $keys );
		$sections    = array();
		foreach ( $ids as $id ) {
			$sections[ $id ] = $counts[ self::section_key( $slug, $id ) ];
		}
		return array( 'sections' => $sections, 'chapter' => $counts[ $chapter_key ] );
	}

	/** Sum of the chapter totals of one book (one query). */
	public static function book_total( string $slug ): int {
		if ( ! self::ensure_table() ) {
			return 0;
		}
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(views) FROM ' . self::table() . ' WHERE view_key LIKE %s', $wpdb->esc_like( $slug . '|P' ) . '%' ) );
	}

	/** @return array<int,array{key:string,views:int}> most-viewed section keys */
	public static function top_sections( int $limit = 30 ): array {
		if ( ! self::ensure_table() ) {
			return array();
		}
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT view_key, views FROM ' . self::table() . ' WHERE view_key LIKE %s ORDER BY views DESC, view_key ASC LIMIT %d', '%|KDJ-%', max( 1, $limit ) ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array( 'key' => (string) $row['view_key'], 'views' => (int) $row['views'] );
		}
		return $out;
	}

	public static function reset_all(): void {
		if ( ! self::ensure_table() ) {
			return;
		}
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . self::table() );
	}

	// ------------------------------------------------------------------ REST

	public static function register_routes(): void {
		register_rest_route(
			KBK_REST_NAMESPACE,
			self::ROUTE,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'rest_get' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'rest_post' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/** @param array<string,mixed> $data */
	private static function respond( array $data, int $status = 200 ) {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'no-store, max-age=0' );
		$response->header( 'X-Robots-Tag', 'noindex' );
		return $response;
	}

	private static function str( $value ): string {
		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Core of GET, testable without WordPress REST classes.
	 *
	 * @return array{0:int,1:array<string,mixed>}
	 */
	public static function handle_get( string $slug, string $part, string $chapter_id ): array {
		if ( ! self::enabled() ) {
			return array( 404, array( 'code' => 'views_disabled' ) );
		}
		$chapter = self::resolve_chapter( $slug, $part, $chapter_id );
		if ( null === $chapter ) {
			return array( 400, array( 'code' => 'invalid_target' ) );
		}
		return array( 200, self::chapter_counts( $slug, $part, $chapter ) );
	}

	/**
	 * Core of POST, testable without WordPress REST classes.
	 *
	 * @return array{0:int,1:array<string,mixed>}
	 */
	public static function handle_post( string $slug, string $part, string $chapter_id, string $section ): array {
		if ( ! self::enabled() ) {
			return array( 404, array( 'code' => 'views_disabled' ) );
		}
		$chapter = self::resolve_chapter( $slug, $part, $chapter_id );
		if ( null === $chapter || ! self::chapter_has_section( $chapter, $section ) ) {
			return array( 400, array( 'code' => 'invalid_target' ) );
		}
		$section_key = self::section_key( $slug, $section );
		$chapter_key = self::chapter_key( $slug, $part, $chapter_id );
		if ( ! self::increment( $section_key ) || ! self::increment( $chapter_key ) ) {
			return array( 503, array( 'code' => 'storage_unavailable' ) );
		}
		$counts = self::counts( array( $section_key, $chapter_key ) );
		return array( 200, array( 'views' => $counts[ $section_key ], 'chapter_views' => $counts[ $chapter_key ] ) );
	}

	public static function rest_get( $request ) {
		list( $status, $data ) = self::handle_get( self::str( $request->get_param( 'book' ) ), self::str( $request->get_param( 'part' ) ), self::str( $request->get_param( 'chapter' ) ) );
		return self::respond( $data, $status );
	}

	public static function rest_post( $request ) {
		$body = (string) $request->get_body();
		if ( strlen( $body ) > self::MAX_BODY ) {
			return self::respond( array( 'code' => 'body_too_large' ), 413 );
		}
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			// sendBeacon may arrive as text/plain or form data; fall back to params.
			$data = array(
				'book'    => $request->get_param( 'book' ),
				'part'    => $request->get_param( 'part' ),
				'chapter' => $request->get_param( 'chapter' ),
				'section' => $request->get_param( 'section' ),
			);
		}
		list( $status, $out ) = self::handle_post( self::str( $data['book'] ?? '' ), self::str( $data['part'] ?? '' ), self::str( $data['chapter'] ?? '' ), self::str( $data['section'] ?? '' ) );
		return self::respond( $out, $status );
	}

	// ----------------------------------------------------------------- admin

	public static function handle_reset(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'forbidden', 403 );
		}
		check_admin_referer( 'kbk_views_reset' );
		self::reset_all();
		wp_safe_redirect( add_query_arg( array( 'page' => KBK_AI_Book_Settings::PAGE, 'kbk_views_reset' => '1' ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Top sections with resolved titles, for the settings table.
	 *
	 * @return array<int,array{book:string,chapter:string,section:string,views:int}>
	 */
	public static function top_rows( int $limit = 30 ): array {
		$out = array();
		foreach ( self::top_sections( $limit ) as $row ) {
			$pos = strpos( $row['key'], '|' );
			if ( false === $pos ) {
				continue;
			}
			$slug    = substr( $row['key'], 0, $pos );
			$id      = substr( $row['key'], $pos + 1 );
			$book    = KBK_AI_Book_Registry::find( $slug );
			$repo    = KBK_AI_Book_Registry::is_routable( $slug ) ? KBK_AI_Book_Registry::repository( $slug ) : null;
			$chapter = '';
			$title   = $id;
			if ( null !== $repo && preg_match( '/-(P\d{2})-(C\d{2})-S\d{2}$/', $id, $m ) ) {
				$found = $repo->find_chapter( $m[1], $m[2] );
				if ( null !== $found ) {
					$chapter = (string) $found['title_fa'];
				}
				$section = $repo->find_section( $id );
				if ( null !== $section ) {
					$title = KBK_AI_Book::reader_section_title( $section );
				}
			}
			$out[] = array(
				'book'    => null !== $book && '' !== (string) $book['title_fa'] ? (string) $book['title_fa'] : $slug,
				'chapter' => $chapter,
				'section' => $title,
				'views'   => $row['views'],
			);
		}
		return $out;
	}
}
