<?php
/**
 * Plugin Name: Kohan Root Deployer (Temporary)
 * Description: Atomically deploys hash-verified sitemap, robots and multilingual LLM summaries. Remove after deployment.
 * Version: 1.0.1
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function kohan_root_deployer_activate() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		wp_die( esc_html__( 'You are not allowed to deploy site files.', 'kohan-root-deployer' ) );
	}

	$expected = array(
		'sitemap.xml' => 'ba11a5e77bd5016704104109a1f9c3360c8ae21034dc1c6b7f7157b232d3be99',
		'robots.txt'   => '2be25f3c63b23ea1d6606fe0c616ef093d93dbf377aff938cc51aae0dab2c085',
		'llms.txt'     => '977b2bc9f854bacaa3f69479bf2d09b5d30ffa39aff14af90b7f07c95210ea8b',
		'fa-llms.txt'  => 'a13313d930de37376c87c5551657fde27fcedc8881f8f4ffc98b172e48871f31',
		'ar-llms.txt'  => 'c7be2d1f25849915da75d1fc2c224fdb6479b5c57dbc0210274c0871fbb159d2',
		'de-llms.txt'  => '9877373cacd52ed8791cfc0ddabc208cd6af7a6c520c0d3065f4f11d676c358c',
		'es-llms.txt'  => 'd338b5696322fe50e517591aec198eadcf8f0f5346d6acb90e193102ae6ad3f2',
		'fr-llms.txt'  => '3f3e7dccff2d2a958c3455c5eb52fd3c120e21a6090c43ff24697e93bc44dfb5',
		'tr-llms.txt'  => '88fbedf0b4311435e63b48e090f0cbb8a72dbeb327e2cd66a9711400844d8bcb',
		'zh-llms.txt'  => 'd9bedca2eac89a153144ea180af7f70dd73f15da11d28ad3ce99e83d0244e44b',
		'ja-llms.txt'  => 'ed8939232932720d5d647de2508ecb8ac361b05be76187481a82d85815c4b51a',
	);

	$root    = realpath( ABSPATH );
	$payload = realpath( __DIR__ . '/payload' );
	if ( ! $root || ! $payload || ! is_writable( $root ) ) {
		throw new RuntimeException( 'WordPress root or payload is unavailable.' );
	}

	$originals = array();
	foreach ( $expected as $file => $hash ) {
		$source = $payload . '/' . $file;
		$target = $root . '/' . $file;
		if ( basename( $file ) !== $file || ! is_file( $source ) || hash_file( 'sha256', $source ) !== $hash ) {
			throw new RuntimeException( 'Preflight failed for ' . $file );
		}
		$originals[ $file ] = is_file( $target ) ? file_get_contents( $target ) : null;
	}

	$written = array();
	try {
		foreach ( $expected as $file => $hash ) {
			$target = $root . '/' . $file;
			$temp   = $root . '/.' . $file . '.kohan-' . wp_generate_password( 8, false, false ) . '.tmp';
			$data   = file_get_contents( $payload . '/' . $file );
			if ( false === $data || false === file_put_contents( $temp, $data, LOCK_EX ) || ! rename( $temp, $target ) ) {
				@unlink( $temp );
				throw new RuntimeException( 'Atomic write failed for ' . $file );
			}
			$written[] = $file;
		}
	} catch ( Throwable $error ) {
		foreach ( array_reverse( $written ) as $file ) {
			if ( null === $originals[ $file ] ) {
				@unlink( $root . '/' . $file );
			} else {
				file_put_contents( $root . '/' . $file, $originals[ $file ], LOCK_EX );
			}
		}
		throw $error;
	}

	update_option( 'kohan_root_deployer_result', array( 'time' => gmdate( 'c' ), 'files' => $expected ), false );
}
register_activation_hook( __FILE__, 'kohan_root_deployer_activate' );

add_action(
	'admin_notices',
	function () {
		$result = get_option( 'kohan_root_deployer_result' );
		if ( is_array( $result ) ) {
			echo '<div class="notice notice-success"><p><strong>Kohan root deployment completed:</strong> '
				. esc_html( count( $result['files'] ) . ' verified files at ' . $result['time'] )
				. '. Deactivate and delete this temporary plugin after production verification.</p></div>';
		}
	}
);
