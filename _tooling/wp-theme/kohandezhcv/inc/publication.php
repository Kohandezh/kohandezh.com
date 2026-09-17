<?php
/** Public-post metadata and a DB-backed sitemap, independent of the static CV. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function kdcv_external_post_seo() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' )
		|| defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' )
		|| defined( 'THE_SEO_FRAMEWORK_VERSION' );
}

function kdcv_public_post( $post ) {
	return $post && 'post' === $post->post_type && 'publish' === $post->post_status && '' === $post->post_password;
}

function kdcv_post_description( $post ) {
	$text = $post->post_excerpt ?: $post->post_content;
	$text = html_entity_decode( wp_strip_all_tags( strip_shortcodes( $text ), true ), ENT_QUOTES, 'UTF-8' );
	$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
	return wp_html_excerpt( $text ?: get_the_title( $post ), 160, '…' );
}

function kdcv_post_schema( $post ) {
	if ( ! kdcv_public_post( $post ) ) { return array(); }
	$url = get_permalink( $post );
	$data = array(
		'@context' => 'https://schema.org', '@type' => 'BlogPosting',
		'@id' => $url . '#article', 'url' => $url,
		'mainEntityOfPage' => array( '@type' => 'WebPage', '@id' => $url ),
		'headline' => wp_strip_all_tags( get_the_title( $post ) ),
		'description' => kdcv_post_description( $post ),
		// This template's initial/server-rendered article is Persian. Client-side
		// translations are not claimed as separately published canonical articles.
		'inLanguage' => 'fa',
		'datePublished' => get_the_date( DATE_W3C, $post ),
		'dateModified' => get_the_modified_date( DATE_W3C, $post ),
	);
	$name = get_the_author_meta( 'display_name', $post->post_author );
	if ( $name ) {
		$data['author'] = array( '@type' => 'Person', 'name' => $name );
		$author_url = esc_url_raw( get_the_author_meta( 'user_url', $post->post_author ), array( 'http', 'https' ) );
		if ( $author_url ) { $data['author']['url'] = $author_url; }
	}
	$image = get_the_post_thumbnail_url( $post, 'full' );
	if ( $image ) { $data['image'] = array( $image ); }
	return $data;
}

add_action( 'wp_head', function () {
	if ( ! is_singular( 'post' ) || is_preview() || kdcv_external_post_seo() ) { return; }
	$post = get_queried_object();
	$data = kdcv_post_schema( $post );
	if ( ! $data ) { return; }
	echo '<meta name="description" content="' . esc_attr( $data['description'] ) . '">' . "\n";
	echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>' . "\n";
}, 5 );

// The physical root sitemap owns curated CV/pages. This second sitemap owns
// published WP posts; no hand-maintained list can advertise an unpublished post.
// Query routes work immediately, including when a physical sitemap.xml shadows
// WordPress. They do not change permalinks or require flushing rewrite rules.
add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'kdcv_sitemap';
	$vars[] = 'kdcv_sitemap_page';
	return $vars;
} );

function kdcv_sitemap_query_args( $page = 1 ) {
	return array(
		'post_type' => 'post', 'post_status' => 'publish', 'has_password' => false,
		'posts_per_page' => 1000, 'paged' => $page, 'orderby' => 'ID', 'order' => 'ASC',
		'ignore_sticky_posts' => true, 'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	);
}

function kdcv_sitemap_xml( $entries, $index = false ) {
	$root = $index ? 'sitemapindex' : 'urlset';
	$tag = $index ? 'sitemap' : 'url';
	$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<$root xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
	foreach ( $entries as $entry ) {
		$xml .= '<' . $tag . '><loc>' . esc_xml( $entry['loc'] ) . '</loc>';
		if ( ! empty( $entry['lastmod'] ) ) { $xml .= '<lastmod>' . esc_xml( $entry['lastmod'] ) . '</lastmod>'; }
		$xml .= '</' . $tag . ">\n";
	}
	return $xml . '</' . $root . ">\n";
}

add_action( 'template_redirect', function () {
	$mode = get_query_var( 'kdcv_sitemap' );
	if ( ! $mode ) { return; }
	if ( ! in_array( $mode, array( 'index', 'posts' ), true ) || ! get_option( 'blog_public' ) ) {
		status_header( 404 ); nocache_headers(); exit;
	}
	$page = max( 1, absint( get_query_var( 'kdcv_sitemap_page', 1 ) ) );
	$args = kdcv_sitemap_query_args( 'index' === $mode ? 1 : $page );
	if ( 'index' === $mode ) { $args['fields'] = 'ids'; }
	$query = new WP_Query( $args );
	$entries = array();
	if ( 'index' === $mode ) {
		for ( $i = 1; $i <= (int) $query->max_num_pages; $i++ ) {
			$entries[] = array( 'loc' => add_query_arg( array( 'kdcv_sitemap' => 'posts', 'kdcv_sitemap_page' => $i ), home_url( '/' ) ) );
		}
	} else {
		if ( $page > max( 1, (int) $query->max_num_pages ) ) { status_header( 404 ); nocache_headers(); exit; }
		foreach ( $query->posts as $post ) {
			if ( kdcv_public_post( $post ) ) {
				$entries[] = array( 'loc' => get_permalink( $post ), 'lastmod' => get_the_modified_date( DATE_W3C, $post ) );
			}
		}
	}
	status_header( 200 );
	header( 'Content-Type: application/xml; charset=UTF-8' );
	header( 'Cache-Control: public, max-age=300, must-revalidate' );
	echo kdcv_sitemap_xml( $entries, 'index' === $mode );
	exit;
}, -20 );
