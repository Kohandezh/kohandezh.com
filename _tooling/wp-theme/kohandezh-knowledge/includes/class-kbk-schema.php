<?php
/**
 * KBK_Schema — conditional JSON-LD + BreadcrumbList for Layer B only.
 *
 * Critical isolation rule: NOTHING is emitted on Layer A. Every public method
 * first checks KBK_Routes::is_layer_b() and bails otherwise. JSON-LD prints
 * via wp_head; the static @id is the canonical entity URI (docs/ONTOLOGY.md).
 *
 * @package Kohandezh_Knowledge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KBK_Schema {

	public static function hooks() {
		add_action( 'wp_head', array( __CLASS__, 'emit_jsonld' ), 20 );
	}

	/**
	 * Emit JSON-LD graphs only on Layer B requests.
	 */
	public static function emit_jsonld() {
		if ( ! KBK_Routes::is_layer_b() ) {
			return;
		}
		$graphs = self::build_graphs();
		if ( empty( $graphs ) ) {
			return;
		}
		// Validate each graph parses as JSON before emitting (defensive; never emit broken LD).
		$out = array();
		foreach ( $graphs as $g ) {
			$json = wp_json_encode( $g, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( false !== $json ) {
				$out[] = $json;
			}
		}
		if ( $out ) {
			echo "\n<!-- Kohandezh Knowledge (Layer B) structured data -->\n";
			foreach ( $out as $json ) {
				echo '<script type="application/ld+json">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — JSON-LD, validated above
			}
		}
	}

	private static function build_graphs() {
		$graphs   = array();
		$graphs[] = self::breadcrumb();

		if ( KBK_Routes::is_hub( 'enterprise-ai' ) || KBK_Routes::is_hub( 'quantum' ) ) {
			$graphs[] = self::collection_page( KBK_Routes::current_hub_label() );
		} elseif ( KBK_Routes::is_entity_view() ) {
			$post    = KBK_Routes::current_entity_post();
			if ( $post ) {
				$graphs[] = self::entity_graph( $post );
			}
		} elseif ( is_post_type_archive( array( 'kbk_knowledge', 'kbk_news', 'kbk_glossary', 'kbk_case', 'kbk_research' ) ) ) {
			$graphs[] = self::collection_page( get_queried_object()->labels->name ?? 'Knowledge' );
		}

		return array_filter( $graphs );
	}

	private static function breadcrumb() {
		$crumbs = KBK_Routes::breadcrumbs();
		$list   = array();
		$pos    = 1;
		foreach ( $crumbs as $crumb ) {
			$list[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => $crumb['name'],
				'item'     => $crumb['url'],
			);
		}
		return array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'@id'             => esc_url_raw( self::current_url() . '#breadcrumb' ),
			'itemListElement' => $list,
		);
	}

	private static function collection_page( $name ) {
		return array(
			'@context'  => 'https://schema.org',
			'@type'     => 'CollectionPage',
			'@id'       => esc_url_raw( self::current_url() ),
			'name'      => $name,
			'url'       => esc_url_raw( self::current_url() ),
			'isPartOf'  => array( '@id' => esc_url_raw( home_url( '/#website' ) ) ),
			'inLanguage' => self::current_language(),
		);
	}

	private static function entity_graph( WP_Post $post ) {
		$entity_id = KBK_Post_Types::canonical_entity_id( $post->ID );
		$type      = get_post_meta( $post->ID, 'kbk_schema_type', true ) ?: self::default_schema_type_for( $post->post_type );
		return array(
			'@context'       => 'https://schema.org',
			'@type'          => $type,
			'@id'            => esc_url_raw( $entity_id ),
			'name'           => esc_html( get_the_title( $post ) ),
			'url'            => esc_url_raw( get_permalink( $post ) ),
			'description'    => esc_html( get_post_meta( $post->ID, 'kbk_summary', true ) ),
			'inLanguage'     => self::current_language(),
			'datePublished'  => esc_html( mysql2date( 'c', $post->post_date ) ),
			'dateModified'   => esc_html( mysql2date( 'c', $post->post_modified ) ),
			'isPartOf'       => array( '@id' => esc_url_raw( home_url( '/#website' ) ) ),
		);
	}

	private static function default_schema_type_for( $post_type ) {
		$map = array(
			'kbk_knowledge' => 'TechArticle',
			'kbk_news'      => 'NewsArticle',
			'kbk_glossary'  => 'DefinedTerm',
			'kbk_case'      => 'Article',
			'kbk_research'  => 'ScholarlyArticle',
		);
		return $map[ $post_type ] ?? 'Article';
	}

	private static function current_url() {
		return home_url( add_query_arg( null, null ) );
	}

	/** Public accessor for templates (canonical URL of the current Layer B page). */
	public static function current_url_public() {
		return self::current_url();
	}

	private static function current_language() {
		$lang = get_query_var( 'kbk_lang' );
		return $lang ? sanitize_text_field( $lang ) : 'en';
	}
}
