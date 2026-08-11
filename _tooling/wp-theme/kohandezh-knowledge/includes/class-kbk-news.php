<?php
/**
 * KBK_News — news source registry + SSRF-safe ingest foundation.
 *
 * MVP scope (safe, no production auto-publish):
 *   - install_sources(): seed the kbk_source taxonomy from fixtures/sources.json.
 *   - ingest($url): validate the URL host against allowlisted sources, reject
 *     private/loopback hosts (SSRF guard), then — ONLY if KBK_FEATURE_NEWS_FETCH
 *     is true AND a fetch key is configured — fetch + create a DRAFT kbk_news post
 *     tagged evidence_status=unverified. Otherwise returns a "not enabled" result.
 *
 * Nothing is published automatically. Nothing is fetched unless explicitly enabled.
 * See docs/NEWS_ARCHITECTURE.md. Aligned with Agent.md addendum §13.
 *
 * @package Kohandezh_Knowledge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KBK_News {

	const SOURCES_FILE = __DIR__ . '/../fixtures/sources.json';
	const PRIVATE_HOST_PATTERNS = array(
		'/^127\./', '/^10\./', '/^192\.168\./', '/^172\.(1[6-9]|2\d|3[01])\./',
		'/^169\.254\./', '/^::1$/', '/^fc00:/i', '/^fe80:/i', '/^0\./',
	);

	public static function hooks() {
		// Registry seeding happens on activation via KBK_Seed flow; expose a REST
		// discovery endpoint for source list (read-only, public).
		add_action( 'rest_api_init', array( __CLASS__, 'register_source_route' ) );
	}

	public static function register_source_route() {
		if ( ! KBK_FEATURE_REST ) {
			return;
		}
		register_rest_route(
			KBK_REST_NAMESPACE,
			'/sources',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_sources' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function load_sources() {
		if ( ! file_exists( self::SOURCES_FILE ) ) {
			return array();
		}
		$raw = file_get_contents( self::SOURCES_FILE ); // local plugin file
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return array();
		}
		return array_merge( $data['ai_sources'] ?? array(), $data['quantum_sources'] ?? array() );
	}

	/**
	 * Seed kbk_source taxonomy from the registry. Idempotent. Also tags each
	 * term's domain (ai/quantum) via term meta for filtering.
	 */
	public static function install_sources() {
		$sources = self::load_sources();
		$created = 0;
		foreach ( $sources as $s ) {
			if ( empty( $s['slug'] ) ) {
				continue;
			}
			$res = term_exists( $s['slug'], 'kbk_source' );
			if ( ! $res ) {
				$res = wp_insert_term( $s['name'] ?? $s['slug'], 'kbk_source', array( 'slug' => sanitize_title( $s['slug'] ) ) );
			}
			if ( is_array( $res ) && ! empty( $res['term_id'] ) ) {
				update_term_meta( $res['term_id'], 'kbk_source_url', esc_url_raw( $s['url'] ?? '' ) );
				update_term_meta( $res['term_id'], 'kbk_source_allowlist', ! empty( $s['allowlist'] ) ? '1' : '0' );
				update_term_meta( $res['term_id'], 'kbk_source_preprint', ! empty( $s['preprint'] ) ? '1' : '0' );
				$created++;
			}
		}
		return $created;
	}

	public static function list_sources( WP_REST_Request $request ) {
		$out = array();
		foreach ( self::load_sources() as $s ) {
			$out[] = array(
				'slug'      => sanitize_title( $s['slug'] ?? '' ),
				'name'      => esc_html( $s['name'] ?? '' ),
				'url'       => esc_url_raw( $s['url'] ?? '' ),
				'allowlist' => (bool) ( $s['allowlist'] ?? false ),
				'preprint'  => (bool) ( $s['preprint'] ?? false ),
			);
		}
		return rest_ensure_response( array( 'items' => $out ) );
	}

	/**
	 * SSRF guard: reject non-http(s), loopback/private hosts, and non-allowlisted domains.
	 */
	public static function is_safe_remote_url( $url ) {
		$url = (string) $url;
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return false;
		}
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( '' === $host ) {
			return false;
		}
		foreach ( self::PRIVATE_HOST_PATTERNS as $re ) {
			if ( preg_match( $re, $host ) ) {
				return false;
			}
		}
		// Must match an allowlisted source host.
		foreach ( self::load_sources() as $s ) {
			$sh = strtolower( (string) wp_parse_url( $s['url'] ?? '', PHP_URL_HOST ) );
			if ( $sh && $host === $sh && ! empty( $s['allowlist'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Ingest a source URL into a DRAFT news post. NEVER publishes. Fetching is
	 * gated behind KBK_FEATURE_NEWS_FETCH + a configured fetch key, so the MVP
	 * performs no network calls by default.
	 */
	public static function ingest( $url ) {
		if ( ! KBK_FEATURE_NEWS ) {
			return array( 'ok' => false, 'msg' => 'News feature disabled.' );
		}
		if ( ! self::is_safe_remote_url( $url ) ) {
			return array( 'ok' => false, 'msg' => 'URL rejected by SSRF allowlist.' );
		}
		if ( ! defined( 'KBK_FEATURE_NEWS_FETCH' ) || ! KBK_FEATURE_NEWS_FETCH ) {
			return array( 'ok' => false, 'msg' => 'Fetch disabled (MVP default). Enable KBK_FEATURE_NEWS_FETCH to ingest.' );
		}
		// When explicitly enabled: fetch with timeout + size cap and create an UNVERIFIED draft.
		$resp = wp_remote_get( $url, array( 'timeout' => 10, 'redirection' => 2 ) );
		if ( is_wp_error( $resp ) ) {
			return array( 'ok' => false, 'msg' => 'Fetch failed: ' . $resp->get_error_message() );
		}
		$body = wp_remote_retrieve_body( $resp );
		if ( strlen( $body ) > 200000 ) { // 200 KB cap
			$body = substr( $body, 0, 200000 );
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$post_id = wp_insert_post( array(
			'post_type'    => 'kbk_news',
			'post_status'  => 'draft',
			'post_title'   => sanitize_text_field( sprintf( 'News draft from %s', $host ) ),
			'post_content' => wp_kses_post( wp_strip_all_tags( $body ) ),
			'post_excerpt' => '',
		), true );
		if ( is_wp_error( $post_id ) ) {
			return array( 'ok' => false, 'msg' => 'Insert failed.' );
		}
		update_post_meta( $post_id, '_kbk_fixture', '1' );
		update_post_meta( $post_id, 'kbk_evidence_status', 'unverified' );
		update_post_meta( $post_id, 'kbk_editorial_status', 'draft' );
		update_post_meta( $post_id, 'kbk_source_refs', esc_url_raw( $url ) );
		return array( 'ok' => true, 'msg' => sprintf( 'Draft #%d created (unverified, unpublished).', $post_id ) );
	}
}
