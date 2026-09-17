<?php
/**
 * Plugin Name:       KDCV Right-Click Guard
 * Plugin URI:        https://kohandezh.com/
 * Description:       Disables the right-click context menu on the public site. One on/off switch under Settings → Right-Click Guard; when it is off, right-click works normally everywhere.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Kohan System Farda
 * License:           GPL-2.0-or-later
 * Text Domain:       kdcv-rightclick-guard
 *
 * @package KDCVRightClickGuard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KDCV_RCG_VERSION', '1.1.0' );
define( 'KDCV_RCG_OPTION', 'kdcv_rcg_enabled' );

/**
 * Whether the guard is enabled. Defaults to ON until the owner turns it off.
 *
 * @return bool
 */
function kdcv_rcg_is_enabled() {
	$value = get_option( KDCV_RCG_OPTION, true );

	if ( is_string( $value ) ) {
		$value = trim( $value );
		if ( '' === $value ) {
			// Checkbox-style save with the field absent means "off".
			return false;
		}
	}

	return (bool) $value;
}

/**
 * Boolean sanitizer for the option.
 *
 * @param mixed $value Raw value from the settings form.
 * @return bool
 */
function kdcv_rcg_sanitize( $value ) {
	if ( is_string( $value ) ) {
		return ( '1' === trim( $value ) );
	}
	return (bool) $value;
}

/**
 * Register the option.
 */
add_action( 'admin_init', function () {
	register_setting(
		'kdcv_rcg_settings',
		KDCV_RCG_OPTION,
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'kdcv_rcg_sanitize',
			'default'           => true,
			'show_in_rest'      => false,
		)
	);
} );

/**
 * Settings page under Settings → Right-Click Guard.
 */
add_action( 'admin_menu', function () {
	add_options_page(
		'Right-Click Guard',
		'Right-Click Guard',
		'manage_options',
		'kdcv-rightclick-guard',
		'kdcv_rcg_render_settings_page'
	);
} );

/**
 * Render the settings page.
 */
function kdcv_rcg_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$enabled = kdcv_rcg_is_enabled();
	?>
	<div class="wrap">
		<h1>Right-Click Guard</h1>

		<form method="post" action="options.php">
			<?php settings_fields( 'kdcv_rcg_settings' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">راست‌کلیک بازدیدکنندگان</th>
					<td>
						<label style="display:block;margin-bottom:6px;">
							<input
								type="radio"
								name="<?php echo esc_attr( KDCV_RCG_OPTION ); ?>"
								value="1"
								<?php checked( $enabled ); ?>
							/>
							<strong>روشن (Guard ON)</strong> — راست‌کلیک در سایت غیرفعال می‌شود
						</label>
						<label style="display:block;">
							<input
								type="radio"
								name="<?php echo esc_attr( KDCV_RCG_OPTION ); ?>"
								value="0"
								<?php checked( ! $enabled ); ?>
							/>
							<strong>خاموش (Guard OFF)</strong> — راست‌کلیک به‌صورت عادی کار می‌کند
						</label>
						<p class="description">
							فقط بخش عمومی سایت (frontend) تحت تأثیر است؛ wp-admin همیشه آزاد است.
							اگر LiteSpeed Cache فعال است، پس از تغییر این کلید کش سایت را پاک کنید.
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Quick-jump link on the Plugins screen.
 *
 * @param string[] $links Existing action links.
 * @return string[]
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	$url = admin_url( 'options-general.php?page=kdcv-rightclick-guard' );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">Settings</a>' );
	return $links;
} );

/**
 * Front end: publish the switch to the page BEFORE anything reads it.
 *
 * The ten CV templates plus PSN, Certificates and 404 each carry their own
 * right-click handler inline -- it shows a localized toast, speaks it and cues
 * the avatar. That handler predates this plugin and blocked the context menu
 * unconditionally, which is why turning the guard off never restored
 * right-click: the plugin only ever removed its OWN listener. The inline
 * handler now reads window.KDCV_RIGHTCLICK_GUARD, so this flag is what
 * actually drives it.
 *
 * Printed at wp_head priority 1, and in BOTH states -- "off" has to be said
 * out loud to be obeyed, because the inline handler defaults to on.
 */
add_action( 'wp_head', function () {
	if ( is_admin() ) {
		return;
	}

	echo '<script>window.KDCV_RIGHTCLICK_GUARD=' . ( kdcv_rcg_is_enabled() ? 'true' : 'false' ) . ";</script>\n";
}, 1 );

/**
 * Fallback guard for the templates that have no inline handler (blog posts,
 * portfolio). It re-reads the flag per event so both guards always agree.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! kdcv_rcg_is_enabled() ) {
		return;
	}

	wp_register_script( 'kdcv-rc-guard', false, array(), KDCV_RCG_VERSION, true );
	wp_enqueue_script( 'kdcv-rc-guard' );

	wp_add_inline_script(
		'kdcv-rc-guard',
		'(function(){'
			. "document.addEventListener('contextmenu',function(e){"
			. 'if(window.KDCV_RIGHTCLICK_GUARD===false)return;'
			. 'e.preventDefault();});'
			. '}());'
	);
}, 99 );

/**
 * Clean up on uninstall.
 */
register_uninstall_hook( __FILE__, 'kdcv_rcg_uninstall' );

/**
 * Delete the stored option.
 */
function kdcv_rcg_uninstall() {
	delete_option( KDCV_RCG_OPTION );
}
