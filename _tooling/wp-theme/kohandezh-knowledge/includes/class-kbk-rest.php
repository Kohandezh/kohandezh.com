<?php
/**
 * KBK_REST — read-only public REST API for the Knowledge Platform.
 *
 * Namespace: kohandezh/v1 (new; does NOT touch the existing kdcv/v1 — ADR-0003).
 *
 *   GET /wp-json/kohandezh/v1/entities            list public knowledge entities
 *   GET /wp-json/kohandezh/v1/entities/{id}       single entity
 *   GET /wp-json/kohandezh/v1/topics              topic taxonomy index
 *
 * Rules (docs/SECURITY.md):
 *   - reads are public; writes are NOT exposed here.
 *   - pagination bounded (per_page ≤ 50).
 *   - output escaped.
 *   - evidence_status = unverified / disputed / deprecated NEVER returned.
 *   - no private meta exposed.
 *
 * @package Kohandezh_Knowledge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KBK_REST {

	const MAX_PER_PAGE = 50;
	const DEFAULT_PER_PAGE = 20;
	const HIDDEN_EVIDENCE = array( 'unverified', 'disputed', 'deprecated' );

	public static function register_routes() {
		if ( ! KBK_FEATURE_REST ) {
			return;
		}

		register_rest_route(
			KBK_REST_NAMESPACE,
			'/entities',
			array(
				'methods'             => 'GET',
				'callback'            => array( 'KBK_REST', 'list_entities' ),
				'permission_callback' => '__return_true', // public read
				'args'                => self::list_args(),
			)
		);

		register_rest_route(
			KBK_REST_NAMESPACE,
			'/entities/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( 'KBK_REST', 'get_entity' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array( 'validate_callback' => static function ( $v ) { return (int) $v > 0; } ),
				),
			)
		);

		register_rest_route(
			KBK_REST_NAMESPACE,
			'/topics',
			array(
				'methods'             => 'GET',
				'callback'            => array( 'KBK_REST', 'list_topics' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	private static function list_args() {
		return array(
			'per_page' => array(
				'default'           => self::DEFAULT_PER_PAGE,
				'validate_callback' => static function ( $v ) { return (int) $v > 0 && (int) $v <= self::MAX_PER_PAGE; },
			),
			'page'     => array(
				'default'           => 1,
				'validate_callback' => static function ( $v ) { return (int) $v > 0; },
			),
			'type'     => array(
				'default'           => 'kbk_knowledge',
				'validate_callback' => static function ( $v ) {
					return in_array( $v, array( 'kbk_knowledge', 'kbk_news', 'kbk_case', 'kbk_research', 'kbk_glossary' ), true );
				},
			),
		);
	}

	/**
	 * Build a meta_query excluding hidden evidence statuses.
	 */
	private static function visible_meta_clause() {
		return array(
			'relation' => 'OR',
			array(
				'key'     => 'kbk_evidence_status',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => 'kbk_evidence_status',
				'value'   => self::HIDDEN_EVIDENCE,
				'compare' => 'NOT IN',
			),
		);
	}

	public static function list_entities( WP_REST_Request $request ) {
		$per_page = (int) $request['per_page'];
		$page     = (int) $request['page'];
		$type     = $request['type'];

		$query = new WP_Query( array(
			'post_type'      => $type,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'meta_query'     => array( self::visible_meta_clause() ),
			'no_found_rows'  => false,
		) );

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = self::serialize( $post );
		}

		return rest_ensure_response( array(
			'items'       => $items,
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
		) );
	}

	public static function get_entity( WP_REST_Request $request ) {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return new WP_Error( 'kbk_not_found', 'Entity not found.', array( 'status' => 404 ) );
		}
		// Enforce visibility rule on single reads too.
		$status = get_post_meta( $post_id, 'kbk_evidence_status', true );
		if ( in_array( $status, self::HIDDEN_EVIDENCE, true ) ) {
			return new WP_Error( 'kbk_not_found', 'Entity not found.', array( 'status' => 404 ) );
		}
		return rest_ensure_response( self::serialize( $post ) );
	}

	public static function list_topics() {
		$terms = get_terms( array(
			'taxonomy'   => 'kbk_topic',
			'hide_empty' => false,
			'number'     => 200,
		) );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		$out = array();
		foreach ( $terms as $t ) {
			$out[] = array(
				'id'    => (int) $t->term_id,
				'slug'  => sanitize_title( $t->slug ),
				'name'  => esc_html( $t->name ),
				'count' => (int) $t->count,
				'url'   => esc_url_raw( get_term_link( $t ) ),
			);
		}
		return rest_ensure_response( array( 'items' => $out ) );
	}

	/**
	 * Serialize a post to a public entity payload (escaped, no private meta).
	 */
	private static function serialize( WP_Post $post ) {
		$id     = KBK_Post_Types::canonical_entity_id( $post->ID );
		$status = get_post_meta( $post->ID, 'kbk_evidence_status', true );
		return array(
			'id'                 => (int) $post->ID,
			'entity_id'          => esc_url_raw( $id ),
			'title'              => esc_html( get_the_title( $post ) ),
			'slug'               => sanitize_title( $post->post_name ),
			'type'               => sanitize_key( $post->post_type ),
			'url'                => esc_url_raw( get_permalink( $post ) ),
			'summary'            => esc_html( get_post_meta( $post->ID, 'kbk_summary', true ) ),
			'language'           => esc_html( get_post_meta( $post->ID, 'kbk_language', true ) ),
			'schema_type'        => esc_html( get_post_meta( $post->ID, 'kbk_schema_type', true ) ),
			'evidence_status'    => $status ? esc_html( $status ) : 'primary',
			'last_reviewed'      => esc_html( get_post_meta( $post->ID, 'kbk_last_reviewed', true ) ),
			'date_published'     => esc_html( mysql2date( 'c', $post->post_date ) ),
			'date_updated'       => esc_html( mysql2date( 'c', $post->post_modified ) ),
			'topics'             => self::term_names( $post->ID, 'kbk_topic' ),
			'industries'         => self::term_names( $post->ID, 'kbk_industry' ),
			'related_entity_ids' => array_map( 'esc_url_raw', (array) get_post_meta( $post->ID, 'kbk_related_entities' ) ),
			'source_refs'        => array_map( 'esc_url_raw', (array) get_post_meta( $post->ID, 'kbk_source_refs' ) ),
		);
	}

	private static function term_names( $post_id, $tax ) {
		$terms = get_the_terms( $post_id, $tax );
		if ( ! is_array( $terms ) ) {
			return array();
		}
		$out = array();
		foreach ( $terms as $t ) {
			$out[] = array( 'slug' => sanitize_title( $t->slug ), 'name' => esc_html( $t->name ) );
		}
		return $out;
	}
}
