<?php
/**
 * Plugin Name: Kohan PHP Deployer (Temporary)
 * Description: Atomically deploys a fixed, hash-verified KohandezhCV PHP payload. Remove after deployment.
 * Version: 1.0.7
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function kohan_php_deployer_activate() {
	if ( ! current_user_can( 'update_themes' ) ) {
		wp_die( esc_html__( 'You are not allowed to update themes.', 'kohan-php-deployer' ) );
	}

	$expected = array(
		'front-page.php' => '66fa700b2620177253f552e3f4c5f83ad36bb2679132ea146facef38477bbd52',
		'page-fa.php'    => 'b345f8178e5c4e52c72dcc5035d3a86ad123b6ed7802290e59286a98af31bcc0',
		'page-ar.php'    => 'cc04ef0eccd742bf4b4992506a6480e845c6685c04959d677f700be7b230f00d',
		'page-de.php'    => 'e65beb724916190926d954740a102f74e304cc90f173955aa8d826530674be0f',
		'page-es.php'    => '447719b5be43a1bbded5eea5c76efee8307c0a7f925aae7438d5673ea563652a',
		'page-fr.php'    => 'da1052db942cb7a767c04c13cf08ea6fe14166c448f79f66d80b9eed45ccf264',
		'page-tr.php'    => 'f697bbb1ad3efc90cbdcfca69c57cc91e4e450fa262d97f1e3ac43637ef193ee',
		'page-zh.php'    => '3a588bf6556aed054d3fc0f3a13195ce9c5f4ba706087a6454a5d34e9ff33bf8',
		'page-ja.php'    => 'ece58553320c4bf436e42c43c5d4cbc557bad287d27d328068b30bb3b85b03ab',
		'functions.php'  => 'bb9b19682771a0baa5652e80029fd3ad9e53b4933a90c111b1fd226af164e245',
	);

	$theme_root = realpath( get_theme_root() . '/kohandezhcv' );
	$payload    = realpath( __DIR__ . '/payload' );
	if ( ! $theme_root || ! $payload || basename( $theme_root ) !== 'kohandezhcv' || ! is_writable( $theme_root ) ) {
		throw new RuntimeException( 'Theme target or payload is unavailable.' );
	}

	$originals = array();
	foreach ( $expected as $file => $hash ) {
		$source = $payload . '/' . $file;
		$target = $theme_root . '/' . $file;
		if ( ! is_file( $source ) || hash_file( 'sha256', $source ) !== $hash || ! is_file( $target ) ) {
			throw new RuntimeException( 'Preflight failed for ' . $file );
		}
		$originals[ $file ] = file_get_contents( $target );
		if ( false === $originals[ $file ] ) {
			throw new RuntimeException( 'Could not read current ' . $file );
		}
	}

	$written = array();
	try {
		foreach ( $expected as $file => $hash ) {
			$source = $payload . '/' . $file;
			$target = $theme_root . '/' . $file;
			$temp   = $theme_root . '/.' . $file . '.kohan-' . wp_generate_password( 8, false, false ) . '.tmp';
			$data   = file_get_contents( $source );
			if ( false === $data || false === file_put_contents( $temp, $data, LOCK_EX ) || ! rename( $temp, $target ) ) {
				@unlink( $temp );
				throw new RuntimeException( 'Atomic write failed for ' . $file );
			}
			$written[] = $file;
		}
	} catch ( Throwable $error ) {
		foreach ( array_reverse( $written ) as $file ) {
			file_put_contents( $theme_root . '/' . $file, $originals[ $file ], LOCK_EX );
		}
		throw $error;
	}

	update_option(
		'kohan_php_deployer_result',
		array(
			'time'  => gmdate( 'c' ),
			'files' => $expected,
		),
		false
	);
}
register_activation_hook( __FILE__, 'kohan_php_deployer_activate' );

add_action(
	'admin_notices',
	function () {
		$result = get_option( 'kohan_php_deployer_result' );
		if ( is_array( $result ) ) {
			echo '<div class="notice notice-success"><p><strong>Kohan PHP deployment completed:</strong> '
				. esc_html( count( $result['files'] ) . ' verified files at ' . $result['time'] )
				. '. Deactivate and delete this temporary plugin after production verification.</p></div>';
		}
	}
);
