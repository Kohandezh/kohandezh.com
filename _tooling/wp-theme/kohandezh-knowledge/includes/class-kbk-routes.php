<?php
/**
 * KBK_Routes — Layer B virtual routing + template loader + isolation guard.
 *
 * Adds the hub/entity virtual routes, exposes Layer-B detection helpers used by
 * KBK_Schema and the template, and swaps the template ONLY for Layer B requests.
 * On Layer A (homepage, CV pages, native blog) it never intervenes.
 *
 * Routes (per docs/INFORMATION_ARCHITECTURE.md):
 *   /enterprise-ai/  → query_var kbk_hub = enterprise-ai
 *   /quantum/        → query_var kbk_hub = quantum
 *   /entity/{slug}/  → query_var kbk_entity = {slug}
 *   CPT archives (/knowledge/, /news/, /glossary/, /case-studies/, /research/) handled by WP has_archive.
 *
 * @package Kohandezh_Knowledge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KBK_Routes {

	const HUBS = array(
		'enterprise-ai' => 'Enterprise AI Hub',
		'quantum'       => 'Quantum Computing Hub',
	);
	const TEMPLATE = 'templates/layer-b.php';

	public static function hooks() {
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'init', array( __CLASS__, 'rewrite_rules' ), 20 );
		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 20 );
	}

	public static function query_vars( $vars ) {
		$vars[] = 'kbk_hub';
		$vars[] = 'kbk_entity';
		$vars[] = 'kbk_lang';
		return $vars;
	}

	public static function rewrite_rules() {
		foreach ( self::HUBS as $slug => $label ) {
			add_rewrite_rule( '^' . $slug . '/?$', 'index.php?kbk_hub=' . $slug, 'top' );
		}
		add_rewrite_rule( '^entity/([^/]+)/?$', 'index.php?kbk_entity=$matches[1]', 'top' );
	}

	/**
	 * The isolation gate. Returns true ONLY on Layer B requests.
	 * Layer A (front page, CV pages, native blog, native pages) → false.
	 */
	public static function is_layer_b() {
		if ( self::is_hub_request() ) {
			return true;
		}
		if ( self::is_entity_request() ) {
			return true;
		}
		if ( is_post_type_archive( array( 'kbk_knowledge', 'kbk_news', 'kbk_glossary', 'kbk_case', 'kbk_research' ) ) ) {
			return true;
		}
		if ( is_tax( array( 'kbk_topic', 'kbk_industry', 'kbk_tech', 'kbk_ai_domain', 'kbk_quantum_domain', 'kbk_evidence', 'kbk_content_type', 'kbk_source' ) ) ) {
			return true;
		}
		if ( is_single() && in_array( get_post_type(), array( 'kbk_knowledge', 'kbk_news', 'kbk_glossary', 'kbk_case', 'kbk_research' ), true ) ) {
			return true;
		}
		return false;
	}

	public static function is_hub_request() {
		return '' !== get_query_var( 'kbk_hub' );
	}
	public static function is_hub( $which ) {
		return get_query_var( 'kbk_hub' ) === $which;
	}
	public static function is_entity_request() {
		return '' !== get_query_var( 'kbk_entity' );
	}
	public static function is_entity_view() {
		return self::is_entity_request();
	}

	public static function current_hub_label() {
		$slug = get_query_var( 'kbk_hub' );
		return self::HUBS[ $slug ] ?? '';
	}

	/**
	 * Resolve /entity/{slug} to a post across Layer B CPTs (or null → 404).
	 */
	public static function current_entity_post() {
		$slug = get_query_var( 'kbk_entity' );
		if ( '' === $slug ) {
			return null;
		}
		$q = new WP_Query( array(
			'name'             => $slug,
			'post_type'        => array( 'kbk_knowledge', 'kbk_news', 'kbk_glossary', 'kbk_case', 'kbk_research' ),
			'post_status'      => 'publish',
			'posts_per_page'   => 1,
			'no_found_rows'    => true,
		) );
		return $q->have_posts() ? $q->posts[0] : null;
	}

	/**
	 * Breadcrumb trail for Layer B pages: Home → Hub/Archive → (Entity).
	 */
	public static function breadcrumbs() {
		$crumbs = array(
			array(
				'name' => get_bloginfo( 'name' ) ?: 'Home',
				'url'  => home_url( '/' ),
			),
		);
		if ( self::is_hub_request() ) {
			$slug = get_query_var( 'kbk_hub' );
			$crumbs[] = array(
				'name' => self::HUBS[ $slug ] ?? ucfirst( $slug ),
				'url'  => home_url( '/' . $slug . '/' ),
			);
			return $crumbs;
		}
		if ( is_post_type_archive() ) {
			$pt = get_queried_object();
			if ( $pt && isset( $pt->labels->name ) ) {
				$crumbs[] = array(
					'name' => $pt->labels->name,
					'url'  => get_post_type_archive_link( $pt->name ),
				);
			}
			return $crumbs;
		}
		if ( is_tax() ) {
			$term = get_queried_object();
			if ( $term ) {
				$crumbs[] = array( 'name' => $term->name, 'url' => get_term_link( $term ) );
			}
			return $crumbs;
		}
		if ( self::is_entity_request() || is_single() ) {
			$post = self::is_entity_request() ? self::current_entity_post() : get_queried_object();
			if ( $post ) {
				$archive = get_post_type_archive_link( $post->post_type );
				if ( $archive ) {
					$pto = get_post_type_object( $post->post_type );
					$crumbs[] = array( 'name' => $pto->labels->name ?? 'Knowledge', 'url' => $archive );
				}
				$crumbs[] = array( 'name' => get_the_title( $post ), 'url' => get_permalink( $post ) );
			}
			return $crumbs;
		}
		return $crumbs;
	}

	/**
	 * Swap template only for Layer B; otherwise return the default untouched.
	 */
	public static function template_include( $template ) {
		if ( ! self::is_layer_b() ) {
			return $template;
		}
		// Entity route with no match → genuine 404 (do not render Layer B).
		if ( self::is_entity_request() && ! self::current_entity_post() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return get_404_template();
		}
		$plugin_template = trailingslashit( dirname( __DIR__ ) ) . self::TEMPLATE;
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}
		return $template;
	}

	/**
	 * Listing query for a hub: top Layer B content tagged with the domain.
	 */
	public static function hub_listing( $hub_slug, $limit = 12 ) {
		$tax = ( 'enterprise-ai' === $hub_slug ) ? 'kbk_ai_domain' : 'kbk_quantum_domain';
		return new WP_Query( array(
			'post_type'      => array( 'kbk_knowledge', 'kbk_news' ),
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'tax_query'      => array( array( 'taxonomy' => $tax, 'field' => 'slug', 'operator' => 'EXISTS' ) ),
			'no_found_rows'  => true,
		) );
	}
}
