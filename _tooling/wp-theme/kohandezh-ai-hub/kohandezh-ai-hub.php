<?php
/**
 * Plugin Name:       Kohandezh AI Hub
 * Plugin URI:        https://kohandezh.com
 * Description:       Multi-provider AI proxy (z.ai, OpenAI, Anthropic, …) for the Kohandezh CV assistant widget. Exposes a public REST endpoint, a wp-admin chat playground, conversation logs, and a settings screen for API keys — independent of the active theme.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Mohammad Ali Kohandezh
 * License:           GPL-2.0-or-later
 * Text Domain:       kdcv-ai-hub
 *
 * Architecture:
 *   includes/class-kdcv-provider.php        — abstract provider + registry
 *   includes/class-kdcv-provider-zai.php    — z.ai (Zhipu GLM) implementation
 *   includes/class-kdcv-logger.php          — DB-backed conversation log
 *   includes/class-kdcv-rest.php            — POST /wp-json/kdcv/v1/ask
 *   admin/settings.php                      — API keys + default provider
 *   admin/chat.php                          — chatbox + history + export/clear
 *
 * Adding a new provider later:
 *   1. Drop a class file in includes/ that extends KDCV_AI_Provider.
 *   2. Register it via kdcv_ai_register_provider() in the bootstrap below.
 *   3. It auto-appears in the Settings dropdown and the chat playground.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KDCV_AI_VERSION', '1.0.0' );
define( 'KDCV_AI_FILE', __FILE__ );
define( 'KDCV_AI_DIR', plugin_dir_path( __FILE__ ) );
define( 'KDCV_AI_URL', plugin_dir_url( __FILE__ ) );
define( 'KDCV_AI_TABLE', 'kdcv_ai_chats' );

require_once KDCV_AI_DIR . 'includes/class-kdcv-provider.php';
require_once KDCV_AI_DIR . 'includes/class-kdcv-provider-zai.php';
require_once KDCV_AI_DIR . 'includes/class-kdcv-logger.php';
require_once KDCV_AI_DIR . 'includes/class-kdcv-rest.php';

/**
 * Register bundled providers. Future providers go here (or in an mu-plugin
 * that calls kdcv_ai_register_provider() on the init hook).
 */
add_action( 'plugins_loaded', function () {
	kdcv_ai_register_provider( 'zai', array(
		'label'      => 'z.ai (Zhipu GLM)',
		'class'      => 'KDCV_AI_Provider_ZAI',
		'default_mm' => 'glm-4.6',
	) );
	// Example slot for the next provider (uncomment when its class exists):
	// kdcv_ai_register_provider( 'openai', array(
	//     'label' => 'OpenAI', 'class' => 'KDCV_AI_Provider_OpenAI', 'default_mm' => 'gpt-4o-mini',
	// ) );
} );

KDCV_AI_REST::register();

if ( is_admin() ) {
	// chat.php registers the parent menu page; settings.php hangs a submenu
	// off it. Load order matters so the parent exists when the child registers.
	require_once KDCV_AI_DIR . 'admin/chat.php';
	require_once KDCV_AI_DIR . 'admin/settings.php';
}

register_activation_hook( __FILE__, array( 'KDCV_AI_Logger', 'install' ) );
// Ensure the schema also exists on plugin upgrades (activation hook doesn't fire).
add_action( 'admin_init', function () {
	if ( get_option( 'kdcv_ai_schema' ) !== KDCV_AI_VERSION ) {
		KDCV_AI_Logger::install();
		update_option( 'kdcv_ai_schema', KDCV_AI_VERSION );
	}
} );
