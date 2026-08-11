<?php
/**
 * KBK_Post_Types — Layer B content model.
 *
 * Registers CPTs, taxonomies, and registered post meta for the Knowledge Platform.
 * Guarded by feature flags (see kohandezh-knowledge.php). No collision with existing
 * plugins (ADR-0004: kohandezh-ai-hub registers no CPTs/taxonomies).
 *
 * @package Kohandezh_Knowledge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KBK_Post_Types {

	/**
	 * Register all CPTs + taxonomies + meta (idempotent).
	 */
	public static function register_all() {
		self::register_cpts();
		self::register_taxonomies();
		self::register_meta();
	}

	/**
	 * Evidence-status vocabulary (single source of truth for taxonomy + meta).
	 */
	public static function evidence_statuses() {
		return array(
			'primary'      => __( 'Verified primary source', 'kohandezh-knowledge' ),
			'secondary'    => __( 'Verified secondary source', 'kohandezh-knowledge' ),
			'internal'     => __( 'Internally documented', 'kohandezh-knowledge' ),
			'user'         => __( 'User-provided', 'kohandezh-knowledge' ),
			'unverified'   => __( 'Unverified', 'kohandezh-knowledge' ),
			'disputed'     => __( 'Disputed', 'kohandezh-knowledge' ),
			'deprecated'   => __( 'Deprecated', 'kohandezh-knowledge' ),
		);
	}

	public static function register_cpts() {
		$archive_slug = 'knowledge';
		$common = array(
			'public'          => true,
			'has_archive'     => false, // per-type archive enabled selectively below
			'hierarchical'    => false,
			'menu_position'   => 21,
			'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'custom-fields', 'revisions' ),
			'show_in_rest'    => true, // enable block editor + REST wp/v2 exposure
			'menu_icon'       => 'dashicons-book',
		);

		$cpts = array();

		if ( KBK_FEATURE_KNOWLEDGE ) {
			$cpts['kbk_knowledge'] = array(
				'labels'       => array( 'name' => 'Knowledge', 'singular_name' => 'Knowledge Article' ),
				'has_archive'  => $archive_slug,
				'rewrite'      => array( 'slug' => $archive_slug, 'with_front' => false ),
				'menu_icon'    => 'dashicons-welcome-learn-more',
			);
		}
		if ( KBK_FEATURE_NEWS ) {
			$cpts['kbk_news'] = array(
				'labels'       => array( 'name' => 'News', 'singular_name' => 'News Article' ),
				'has_archive'  => 'news',
				'rewrite'      => array( 'slug' => 'news', 'with_front' => false ),
				'menu_icon'    => 'dashicons-format-aside',
			);
		}
		if ( KBK_FEATURE_GLOSSARY ) {
			$cpts['kbk_glossary'] = array(
				'labels'       => array( 'name' => 'Glossary', 'singular_name' => 'Glossary Term' ),
				'has_archive'  => 'glossary',
				'rewrite'      => array( 'slug' => 'glossary', 'with_front' => false ),
				'menu_icon'    => 'dashicons-book-alt',
			);
		}
		if ( KBK_FEATURE_CASE ) {
			$cpts['kbk_case'] = array(
				'labels'       => array( 'name' => 'Case Studies', 'singular_name' => 'Case Study' ),
				'has_archive'  => 'case-studies',
				'rewrite'      => array( 'slug' => 'case-studies', 'with_front' => false ),
				'menu_icon'    => 'dashicons-portfolio',
			);
		}
		if ( KBK_FEATURE_RESEARCH ) {
			$cpts['kbk_research'] = array(
				'labels'       => array( 'name' => 'Research', 'singular_name' => 'Research Article' ),
				'has_archive'  => 'research',
				'rewrite'      => array( 'slug' => 'research', 'with_front' => false ),
				'menu_icon'    => 'dashicons-clipboard',
			);
		}

		foreach ( $cpts as $slug => $args ) {
			if ( ! post_type_exists( $slug ) ) {
				register_post_type( $slug, wp_parse_args( $args, $common ) );
			}
		}
	}

	public static function register_taxonomies() {
		// Topic — applies to all content CPTs.
		self::register_tax( 'kbk_topic', 'Topic', 'topic', array( 'kbk_knowledge', 'kbk_news', 'kbk_case', 'kbk_research', 'kbk_glossary' ), true );
		// Industry / Technology — cross-cutting.
		self::register_tax( 'kbk_industry', 'Industry', 'industry', array( 'kbk_knowledge', 'kbk_news', 'kbk_case', 'kbk_research' ), true );
		self::register_tax( 'kbk_tech', 'Technology', 'technology', array( 'kbk_knowledge', 'kbk_case', 'kbk_research' ), true );
		// Domain flags.
		self::register_tax( 'kbk_ai_domain', 'AI Domain', 'ai-domain', array( 'kbk_knowledge', 'kbk_news' ), false );
		self::register_tax( 'kbk_quantum_domain', 'Quantum Domain', 'quantum-domain', array( 'kbk_knowledge', 'kbk_news' ), false );
		// Evidence status (vocabulary).
		$evidence = self::evidence_statuses();
		self::register_tax( 'kbk_evidence', 'Evidence Status', 'evidence', array( 'kbk_knowledge', 'kbk_news', 'kbk_case', 'kbk_research' ), false, array_keys( $evidence ) );
		// Content type.
		self::register_tax( 'kbk_content_type', 'Content Type', 'content-type', array( 'kbk_knowledge' ), false );
		// News source.
		self::register_tax( 'kbk_source', 'News Source', 'source', array( 'kbk_news' ), false );
	}

	private static function register_tax( $slug, $singular, $rewrite_slug, $types, $hierarchical, $allowed_terms = null ) {
		if ( taxonomy_exists( $slug ) ) {
			return;
		}
		register_taxonomy(
			$slug,
			$types,
			array(
				'labels'            => array( 'name' => $singular . 's', 'singular_name' => $singular ),
				'public'            => true,
				'hierarchical'      => $hierarchical,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => $rewrite_slug, 'with_front' => false ),
			)
		);
		if ( null !== $allowed_terms ) {
			// Reserved-vocabulary taxonomy: seed default terms once.
			$existing = get_terms( array( 'taxonomy' => $slug, 'hide_empty' => false, 'fields' => 'slugs' ) );
			if ( ! is_wp_error( $existing ) ) {
				foreach ( array_diff( $allowed_terms, $existing ) as $term_slug ) {
					wp_insert_term( ucfirst( $term_slug ), $slug, array( 'slug' => $term_slug ) );
				}
			}
		}
	}

	/**
	 * Registered post meta (with sanitize callbacks) — exposed to REST as single,
	 * non-private fields (private meta starts with _kbk_ and is protected in the bootstrap).
	 */
	public static function register_meta() {
		$types = array( 'kbk_knowledge', 'kbk_news', 'kbk_case', 'kbk_research', 'kbk_glossary' );
		$string_meta = array(
			'kbk_entity_id'          => 'KBK_Post_Types::sanitize_entity_id',
			'kbk_summary'            => 'sanitize_text_field',
			'kbk_language'           => 'sanitize_text_field',
			'kbk_translation_group'  => 'sanitize_text_field',
			'kbk_schema_type'        => 'sanitize_text_field',
			'kbk_editorial_status'   => 'sanitize_text_field',
		);
		foreach ( $types as $pt ) {
			foreach ( $string_meta as $key => $sanitizer ) {
				register_post_meta(
					$pt,
					$key,
					array(
						'type'              => 'string',
						'single'            => true,
						'show_in_rest'      => true,
						'sanitize_callback' => $sanitizer,
						'auth_callback'     => static function () {
							return current_user_can( 'edit_posts' );
						},
					)
				);
			}
			// Array meta.
			register_post_meta( $pt, 'kbk_related_entities', array(
				'type'              => 'string',
				'single'            => false,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static function () { return current_user_can( 'edit_posts' ); },
			) );
			register_post_meta( $pt, 'kbk_source_refs', array(
				'type'              => 'string',
				'single'            => false,
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => static function () { return current_user_can( 'edit_posts' ); },
			) );
		}
	}

	/**
	 * Derive the stable canonical entity URI for a post (used by REST + JSON-LD).
	 */
	public static function canonical_entity_id( $post_id ) {
		$stored = get_post_meta( $post_id, 'kbk_entity_id', true );
		if ( $stored ) {
			return $stored;
		}
		$slug = get_post_field( 'post_name', $post_id );
		return $slug ? KBK_ENTITY_BASE . $slug : '';
	}

	public static function sanitize_entity_id( $value ) {
		$value = esc_url_raw( $value );
		return ( 0 === strpos( $value, KBK_ENTITY_BASE ) ) ? $value : '';
	}
}
