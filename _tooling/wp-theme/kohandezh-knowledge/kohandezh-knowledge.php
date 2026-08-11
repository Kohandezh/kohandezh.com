<?php
/**
 * Plugin Name:       Kohandezh Knowledge
 * Plugin URI:        https://kohandezh.com
 * Description:       Layer B — Enterprise AI & Quantum Knowledge Platform. Additive, isolated from the personal-brand Layer A. Registers knowledge content types, taxonomies, the claim/evidence model, and a read-only REST API (kohandezh/v1). No homepage or Layer A changes; conditionally loaded and feature-flagged.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Mohammad Ali Kohandezh
 * License:           GPL-2.0-or-later
 * Text Domain:       kohandezh-knowledge
 *
 * Architecture (see docs/ARCHITECTURE.md, docs/ONTOLOGY.md):
 *   includes/class-kbk-post-types.php  — CPTs, taxonomies, registered meta
 *   includes/class-kbk-rest.php        — REST GET kohandezh/v1/{entities,topics}
 *
 * Design contract:
 *   - Lives OUTSIDE the theme → does not flow through sync-from-static.py (ADR-0001).
 *   - Registers nothing that runs on Layer A front-end; no global enqueue.
 *   - Feature-flagged; deactivate = full rollback (docs/ROLLBACK.md).
 *   - No CPT/taxonomy collision with existing plugins (ADR-0004 verified).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KBK_VERSION', '0.1.0' );
define( 'KBK_REST_NAMESPACE', 'kohandezh/v1' );
define( 'KBK_ENTITY_BASE', 'https://kohandezh.com/entity/' );

/**
 * Feature flags — override in wp-config.php to disable a subsystem instantly.
 * Example: define( 'KBK_FEATURE_NEWS', false );
 */
$kbk_default_flags = array(
	'KBK_FEATURE_KNOWLEDGE' => true,
	'KBK_FEATURE_NEWS'      => true,
	'KBK_FEATURE_GLOSSARY'  => true,
	'KBK_FEATURE_CASE'      => true,
	'KBK_FEATURE_RESEARCH'  => true,
	'KBK_FEATURE_REST'      => true,
	'KBK_FEATURE_NEWS_FETCH'=> false, // default OFF — never auto-fetch in MVP
);
foreach ( $kbk_default_flags as $flag => $default ) {
	if ( ! defined( $flag ) ) {
		define( $flag, $default );
	}
}
unset( $kbk_default_flags );

require_once __DIR__ . '/includes/class-kbk-post-types.php';
require_once __DIR__ . '/includes/class-kbk-rest.php';
require_once __DIR__ . '/includes/class-kbk-routes.php';
require_once __DIR__ . '/includes/class-kbk-schema.php';
require_once __DIR__ . '/includes/class-kbk-seed.php';
require_once __DIR__ . '/includes/class-kbk-news.php';

/**
 * Activation: flush rewrite rules so new CPT archives + virtual hubs resolve.
 * No destructive migration; no data writes beyond the version marker.
 * Idempotent — safe to re-run.
 */
register_activation_hook( __FILE__, 'kbk_activate' );
function kbk_activate() {
	KBK_Post_Types::register_all();
	KBK_Routes::rewrite_rules();
	KBK_News::install_sources();
	flush_rewrite_rules();
	update_option( 'kbk_schema_version', KBK_VERSION );
}

/**
 * Deactivation: flush rewrite rules so Layer A routes return to clean state.
 * No data deleted (content preserved for reactivation).
 */
register_deactivation_hook( __FILE__, 'kbk_deactivate' );
function kbk_deactivate() {
	flush_rewrite_rules();
}

// Bootstrap.
add_action( 'init', array( 'KBK_Post_Types', 'register_all' ) );
KBK_Routes::hooks();
KBK_Schema::hooks();
KBK_Seed::hooks();
KBK_News::hooks();
add_action( 'rest_api_init', array( 'KBK_REST', 'register_routes' ) );

/**
 * Hardening: never expose Layer B private meta over REST regardless of registration.
 */
add_filter( 'is_protected_meta', static function ( $protected, $meta_key ) {
	if ( 0 === strpos( $meta_key, '_kbk_' ) ) {
		return true;
	}
	return $protected;
}, 10, 2 );
