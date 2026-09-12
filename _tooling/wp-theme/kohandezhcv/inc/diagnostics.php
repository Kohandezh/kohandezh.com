<?php
/** Read-only Tools → Site Health → Info. No provider calls or secret values. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
add_filter( 'debug_information', function ( $info ) {
	if ( ! current_user_can( 'manage_options' ) ) { return $info; }
	$fields = array(
		'release' => array( 'label' => 'Theme release', 'value' => wp_get_theme()->get( 'Version' ) ),
		'live_avatar' => array( 'label' => 'Live Avatar', 'value' => 'Pre-launch (owner-confirmed); separate from the working Kohan Pet.' ),
		'operational' => array( 'label' => 'Blogyar / AI Honeypot operations', 'value' => 'Last success, last error and queue are not exposed by an integration in this theme. Installation/version alone does not prove a working job.' ),
	);
	if ( function_exists( 'get_plugins' ) && function_exists( 'is_plugin_active' ) ) {
		foreach ( array( 'blogyar' => 'Blogyar', 'honeypot' => 'AI Honeypot', 'kohan-avatar' => 'Kohan Pet' ) as $needle => $label ) {
			$matches = array();
			foreach ( get_plugins() as $file => $plugin ) {
				if ( false !== stripos( $file . ' ' . $plugin['Name'], $needle ) ) {
					$active = is_plugin_active( $file ) || is_plugin_active_for_network( $file );
					$matches[] = $plugin['Name'] . ' ' . $plugin['Version'] . ( $active ? ' (active)' : ' (inactive)' );
				}
			}
			$fields[ $needle ] = array( 'label' => $label . ' plugin inventory', 'value' => $matches ? implode( '; ', $matches ) : 'No matching standard plugin found; an external integration is not ruled out.' );
		}
	}
	foreach ( array( '', 'fa-', 'ar-', 'de-', 'es-', 'fr-', 'tr-', 'zh-', 'ja-', 'ru-' ) as $prefix ) {
		$name = $prefix . 'llms.txt';
		$root = ABSPATH . $name;
		$bundle = get_template_directory() . '/' . $name;
		$value = ! is_readable( $bundle ) ? 'ERROR: theme summary missing' : 'Theme fallback available; no physical root file';
		if ( is_readable( $root ) && is_readable( $bundle ) ) {
			$value = hash_file( 'sha256', $root ) === hash_file( 'sha256', $bundle ) ? 'Root and theme match' : 'DRIFT: physical root differs from theme; root may shadow the new summary';
		}
		$fields[ $name ] = array( 'label' => $name, 'value' => $value );
	}
	$fields['seo_owner'] = array( 'label' => 'Post metadata owner', 'value' => kdcv_external_post_seo() ? 'External SEO plugin; verify its Article/description output after release' : 'KohandezhCV publication module' );
	$info['kohandezh'] = array( 'label' => 'Kohandezh release diagnostics', 'description' => 'Read-only inventory. Public crawler access/cache still requires HTTP verification.', 'fields' => $fields );
	return $info;
} );
