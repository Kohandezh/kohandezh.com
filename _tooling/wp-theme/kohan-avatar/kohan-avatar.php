<?php
/**
 * Plugin Name:       Kohan Avatar
 * Plugin URI:        https://kohandezh.com/
 * Description:       Single interactive character avatar (Kohan) rendered from an authoritative sprite atlas. Self-contained; replaces any prior floating avatar. Assets are enqueued with WordPress URL helpers — no hard-coded local paths reach the browser. Includes Voice & TTS Services (server-side API keys for OpenAI/ElevenLabs/Whisper voice cloning) and the wisdom-quotes bubble integration hook.
 * Version:           2.4.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Kohan System Farda
 * License:           GPL-2.0-or-later
 * Text Domain:       kohan-avatar
 *
 * @package KohanAvatar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KOHAN_AVATAR_VERSION', '2.4.0' );
define( 'KOHAN_AVATAR_FILE', __FILE__ );
define( 'KOHAN_AVATAR_DIR', plugin_dir_path( __FILE__ ) );
define( 'KOHAN_AVATAR_URL', plugin_dir_url( __FILE__ ) );

require_once KOHAN_AVATAR_DIR . 'includes/class-kohan-avatar.php';
require_once KOHAN_AVATAR_DIR . 'includes/class-kohan-avatar-rest.php';

/**
 * Boot the plugin once WordPress is ready.
 */
function kohan_avatar_boot() {
	Kohan_Avatar::instance();
	Kohan_Avatar_REST::instance();
}
add_action( 'plugins_loaded', 'kohan_avatar_boot' );

/**
 * On activation seed default options if none exist.
 */
register_activation_hook( __FILE__, array( 'Kohan_Avatar', 'activate' ) );
