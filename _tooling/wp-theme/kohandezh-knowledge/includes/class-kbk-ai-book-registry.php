<?php
/**
 * KBK_AI_Book_Registry — every book the Reader serves, one bundle each.
 *
 * A book is a row {slug, root, code?, title_fa?, subtitle_fa?, meta_fa?,
 * note_fa?, cover?, status?}. The bundle under `root` has the same format as
 * the AI Governance book (master/book.json, provenance/…, release/…), so the
 * Reader renders every title the same way. Rows come from, in this order:
 *
 *   1. KBK_AI_BOOK_ROOT — the implicit {slug: ai-governance} row (locked).
 *   2. KBK_BOOKS — a JSON string or PHP array of rows in wp-config (locked).
 *   3. The `kbk_books` option — rows edited in Settings → «کتاب‌خوان کهن‌دژ».
 *
 * The first row with a given slug wins. A "published" row is routed at
 * /fa/books/{slug}/; an "announced" row only shows a shelf card. An option
 * row whose root is not a directory holding master/book.json is kept (the
 * admin page shows it in red) but never routed. Constant rows are routed even
 * when their root is broken, so the Reader shows its honest degraded state
 * instead of a 404 — the behaviour the single-book plugin always had.
 *
 * No WordPress dependency beyond optional get_option(), so it is testable in
 * isolation.
 *
 * @package Kohandezh_Knowledge
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'KBK_TESTING' ) ) {
	exit;
}

final class KBK_AI_Book_Registry {

	const OPTION           = 'kbk_books';
	const SLUG_PATTERN     = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
	const SLUG_MAX         = 40;
	const CODE_PATTERN     = '/^[A-Z0-9]{1,8}$/';
	const LEGACY_SLUG      = 'ai-governance';
	const STATUS_PUBLISHED = 'published';
	const STATUS_ANNOUNCED = 'announced';
	const TEXT_MAX         = 200;

	/** @var array<int,array<string,mixed>>|null */
	private static $rows;

	/** @var array<string,KBK_AI_Book_Repository|null> */
	private static $repositories = array();

	/** @var array<string,string> */
	private static $errors = array();

	/** @var array<string,string> */
	private static $codes = array();

	/** Forget every cached row and repository (tests, settings save). */
	public static function reset(): void {
		self::$rows         = null;
		self::$repositories = array();
		self::$errors       = array();
		self::$codes        = array();
	}

	public static function valid_slug( $slug ): bool {
		return is_string( $slug ) && strlen( $slug ) <= self::SLUG_MAX && 1 === preg_match( self::SLUG_PATTERN, $slug );
	}

	/**
	 * Rows the option starts with before an administrator saves the table:
	 * the announced ICS book keeps its shelf card exactly as it was.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function default_option_rows(): array {
		return array(
			array(
				'slug'        => 'ics-cybersecurity',
				'status'      => self::STATUS_ANNOUNCED,
				'title_fa'    => 'امنیت سایبری در سیستم‌های اتوماسیون صنعتی',
				'subtitle_fa' => 'راهنمای جامع بر اساس استانداردهای IEC 62443',
				'meta_fa'     => 'محمدعلی کهن‌دژ، روزبه بابازاده، نیلوفر کریمی آذر · تابستان ۱۴۰۴',
				'note_fa'     => 'نسخهٔ دیجیتال این کتاب به‌زودی در همین بخش منتشر می‌شود.',
			),
		);
	}

	/** True when $root is a directory that holds a master/book.json. */
	public static function root_ok( string $root ): bool {
		$root = trim( $root );
		return '' !== $root && is_dir( $root ) && is_file( rtrim( $root, '/\\' ) . DIRECTORY_SEPARATOR . 'master' . DIRECTORY_SEPARATOR . 'book.json' );
	}

	/**
	 * Normalize one raw row; null when the slug is invalid.
	 *
	 * @param mixed $raw
	 * @return array<string,mixed>|null
	 */
	public static function normalize_row( $raw, bool $locked, string $source ): ?array {
		if ( ! is_array( $raw ) ) {
			return null;
		}
		$slug = isset( $raw['slug'] ) && is_string( $raw['slug'] ) ? strtolower( trim( $raw['slug'] ) ) : '';
		if ( ! self::valid_slug( $slug ) ) {
			return null;
		}
		$text = static function ( $value ): string {
			if ( ! is_string( $value ) ) {
				return '';
			}
			$value = trim( preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $value ) ?? '' );
			return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, self::TEXT_MAX ) : substr( $value, 0, self::TEXT_MAX );
		};
		$root   = $text( $raw['root'] ?? '' );
		$code   = isset( $raw['code'] ) && is_string( $raw['code'] ) ? strtoupper( trim( $raw['code'] ) ) : '';
		$status = isset( $raw['status'] ) && in_array( $raw['status'], array( self::STATUS_PUBLISHED, self::STATUS_ANNOUNCED ), true )
			? $raw['status']
			: ( '' === $root ? self::STATUS_ANNOUNCED : self::STATUS_PUBLISHED );
		$cover = $text( $raw['cover'] ?? '' );
		if ( '' !== $cover && 1 !== preg_match( '#^(?:https?://[^\s"<>]+|/[^\s"<>]*)$#i', $cover ) ) {
			$cover = '';
		}
		$root_ok = '' !== $root && self::root_ok( $root );
		return array(
			'slug'        => $slug,
			'root'        => $root,
			'code'        => 1 === preg_match( self::CODE_PATTERN, $code ) ? $code : '',
			'title_fa'    => $text( $raw['title_fa'] ?? '' ),
			'subtitle_fa' => $text( $raw['subtitle_fa'] ?? '' ),
			'meta_fa'     => $text( $raw['meta_fa'] ?? '' ),
			'note_fa'     => $text( $raw['note_fa'] ?? '' ),
			'cover'       => $cover,
			'status'      => $status,
			'locked'      => $locked,
			'source'      => $source,
			'root_ok'     => $root_ok,
			// Constant rows are trusted configuration: routed even when broken
			// so the Reader can say so. Option rows must prove their bundle.
			'routable'    => self::STATUS_PUBLISHED === $status && '' !== $root && ( $locked || $root_ok ),
		);
	}

	/**
	 * Raw rows of the KBK_BOOKS constant (JSON string or PHP array).
	 *
	 * @return array<int,mixed>
	 */
	private static function constant_rows(): array {
		if ( ! defined( 'KBK_BOOKS' ) ) {
			return array();
		}
		$value = constant( 'KBK_BOOKS' );
		if ( is_string( $value ) ) {
			$value = json_decode( $value, true );
		}
		return is_array( $value ) ? array_values( $value ) : array();
	}

	/**
	 * Raw rows of the `kbk_books` option; the defaults until it is first saved.
	 *
	 * @return array<int,mixed>
	 */
	public static function option_rows(): array {
		if ( ! function_exists( 'get_option' ) ) {
			return self::default_option_rows();
		}
		$stored = get_option( self::OPTION, false );
		if ( false === $stored ) {
			return self::default_option_rows();
		}
		return is_array( $stored ) ? array_values( $stored ) : array();
	}

	/**
	 * Every book, merged and normalized; the first row per slug wins.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function rows(): array {
		if ( null !== self::$rows ) {
			return self::$rows;
		}
		$raw = array();
		if ( defined( 'KBK_AI_BOOK_ROOT' ) && is_string( KBK_AI_BOOK_ROOT ) && '' !== trim( KBK_AI_BOOK_ROOT ) ) {
			$raw[] = array( array( 'slug' => self::LEGACY_SLUG, 'root' => KBK_AI_BOOK_ROOT, 'status' => self::STATUS_PUBLISHED ), true, 'KBK_AI_BOOK_ROOT' );
		}
		foreach ( self::constant_rows() as $row ) {
			$raw[] = array( $row, true, 'KBK_BOOKS' );
		}
		foreach ( self::option_rows() as $row ) {
			$raw[] = array( $row, false, self::OPTION );
		}
		$rows = array();
		$seen = array();
		foreach ( $raw as list( $row, $locked, $source ) ) {
			$normalized = self::normalize_row( $row, $locked, $source );
			if ( null === $normalized || isset( $seen[ $normalized['slug'] ] ) ) {
				continue;
			}
			$seen[ $normalized['slug'] ] = true;
			$rows[]                      = $normalized;
		}
		return self::$rows = $rows;
	}

	/** @return array<string,mixed>|null */
	public static function find( string $slug ): ?array {
		foreach ( self::rows() as $row ) {
			if ( $row['slug'] === $slug ) {
				return $row;
			}
		}
		return null;
	}

	/** @return string[] Slugs routed at /fa/books/{slug}/, in registry order. */
	public static function routable_slugs(): array {
		$slugs = array();
		foreach ( self::rows() as $row ) {
			if ( $row['routable'] ) {
				$slugs[] = $row['slug'];
			}
		}
		return $slugs;
	}

	public static function is_routable( string $slug ): bool {
		$row = self::valid_slug( $slug ) ? self::find( $slug ) : null;
		return null !== $row && $row['routable'];
	}

	/** The book a request without its own slug belongs to: the first routed one. */
	public static function default_slug(): ?string {
		$slugs = self::routable_slugs();
		return $slugs[0] ?? null;
	}

	/** The validated repository of a routed book, built once per request. */
	public static function repository( string $slug ): ?KBK_AI_Book_Repository {
		if ( array_key_exists( $slug, self::$repositories ) ) {
			return self::$repositories[ $slug ];
		}
		$row = self::is_routable( $slug ) ? self::find( $slug ) : null;
		self::$repositories[ $slug ] = null;
		if ( null === $row ) {
			self::$errors[ $slug ] = 'CONFIG_REQUIRED';
			return null;
		}
		try {
			$repository = new KBK_AI_Book_Repository( $row['root'] );
			$repository->bundle();
			self::$repositories[ $slug ] = $repository;
		} catch ( InvalidArgumentException $error ) {
			self::$errors[ $slug ] = 'CONFIG_INVALID';
		} catch ( UnexpectedValueException $error ) {
			self::$errors[ $slug ] = 'ARTIFACT_INVALID';
		}
		return self::$repositories[ $slug ];
	}

	public static function repository_status( string $slug ): string {
		self::repository( $slug );
		return self::$errors[ $slug ] ?? 'READY';
	}

	/**
	 * The ID code of a book ("AI" in KDJ-AI-2026E1-…): the row's own code,
	 * else the first one found in the bundle's content IDs.
	 */
	public static function code( string $slug ): string {
		if ( isset( self::$codes[ $slug ] ) ) {
			return self::$codes[ $slug ];
		}
		$row  = self::find( $slug );
		$code = null !== $row ? (string) $row['code'] : '';
		if ( '' === $code ) {
			$repository = self::repository( $slug );
			if ( null !== $repository ) {
				foreach ( array_keys( $repository->bundle()['content_ids']['ids'] ?? array() ) as $content_id ) {
					if ( 1 === preg_match( '/^KDJ-([A-Z0-9]{1,8})-/', (string) $content_id, $matches ) ) {
						$code = $matches[1];
						break;
					}
				}
			}
		}
		return self::$codes[ $slug ] = '' !== $code ? $code : 'AI';
	}

	/**
	 * Settings sanitizer for the `kbk_books` option. Blank and removed rows
	 * disappear; an invalid or duplicate slug is dropped with a notice; a
	 * broken root is kept (shown red, never routed).
	 *
	 * @param mixed $input
	 * @return array<int,array<string,string>>
	 */
	public static function sanitize_option( $input ): array {
		$notice = static function ( string $code, string $message ): void {
			if ( function_exists( 'add_settings_error' ) ) {
				add_settings_error( self::OPTION, $code, $message );
			}
		};
		$clean = static function ( $value ): string {
			$value = is_scalar( $value ) ? (string) $value : '';
			$value = function_exists( 'wp_unslash' ) ? wp_unslash( $value ) : $value;
			$value = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( $value ) );
			return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, self::TEXT_MAX ) : substr( $value, 0, self::TEXT_MAX );
		};
		$locked = array();
		foreach ( self::rows() as $row ) {
			if ( $row['locked'] ) {
				$locked[ $row['slug'] ] = true;
			}
		}
		$rows = array();
		$seen = array();
		foreach ( is_array( $input ) ? $input : array() as $raw ) {
			if ( ! is_array( $raw ) || ! empty( $raw['remove'] ) ) {
				continue;
			}
			$row = array();
			foreach ( array( 'slug', 'root', 'title_fa', 'subtitle_fa', 'meta_fa', 'note_fa', 'cover', 'status' ) as $field ) {
				$row[ $field ] = $clean( $raw[ $field ] ?? '' );
			}
			$row['slug'] = strtolower( $row['slug'] );
			if ( '' === $row['slug'] && '' === $row['root'] && '' === $row['title_fa'] && '' === $row['cover'] ) {
				continue;
			}
			if ( ! self::valid_slug( $row['slug'] ) ) {
				$notice( 'kbk_books_slug', 'نامک «' . $row['slug'] . '» پذیرفته نشد: فقط حروف کوچک لاتین، رقم و خط تیره (حداکثر ۴۰ نویسه).' );
				continue;
			}
			if ( isset( $seen[ $row['slug'] ] ) || isset( $locked[ $row['slug'] ] ) ) {
				$notice( 'kbk_books_duplicate', 'نامک «' . $row['slug'] . '» تکراری است یا در wp-config تعریف شده؛ این ردیف ذخیره نشد.' );
				continue;
			}
			$seen[ $row['slug'] ] = true;
			if ( '' !== $row['cover'] ) {
				$row['cover'] = function_exists( 'esc_url_raw' ) ? esc_url_raw( $row['cover'], array( 'http', 'https' ) ) : $row['cover'];
				if ( '' === $row['cover'] && 0 === strpos( $clean( $raw['cover'] ?? '' ), '/' ) ) {
					$row['cover'] = $clean( $raw['cover'] );
				}
			}
			if ( ! in_array( $row['status'], array( self::STATUS_PUBLISHED, self::STATUS_ANNOUNCED ), true ) ) {
				$row['status'] = '' === $row['root'] ? self::STATUS_ANNOUNCED : self::STATUS_PUBLISHED;
			}
			if ( self::STATUS_PUBLISHED === $row['status'] && ! self::root_ok( $row['root'] ) ) {
				$notice( 'kbk_books_root', 'کتاب «' . $row['slug'] . '» ذخیره شد ولی منتشر نمی‌شود: مسیر ریشه پوشه‌ای با master/book.json نیست.' );
			}
			$rows[] = array_filter( $row, static function ( $value ): bool { return '' !== $value; } ) + array( 'slug' => $row['slug'], 'status' => $row['status'] );
		}
		self::reset();
		return $rows;
	}
}
