<?php
/**
 * Test helper: lay the fixture bundle out as a book root on disk.
 *
 *   $root = kbk_test_bundle( 'ICS', array( 'title_fa' => '…' ) );
 *
 * Copies book.json / content_ids.json / citations.json into
 * {tmp}/master/book.json, provenance/content_ids.json and
 * provenance/citation-registry.json, rewriting the ID code ("KDJ-AI-" →
 * "KDJ-{code}-") so one fixture proves the book-agnostic ID grammar. The
 * directory is removed when the test process ends.
 *
 * @param array<string,mixed> $book_fields Top-level book.json fields to set.
 */
function kbk_test_bundle( string $code, array $book_fields = array() ): string {
	$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kbk-bundle-' . strtolower( $code ) . '-' . getmypid() . '-' . substr( md5( json_encode( $book_fields ) ), 0, 6 );
	foreach ( array( '', '/master', '/provenance' ) as $dir ) {
		if ( ! is_dir( $root . $dir ) ) {
			mkdir( $root . $dir, 0700, true );
		}
	}
	$read = static function ( string $name ) use ( $code ): array {
		$raw = (string) file_get_contents( __DIR__ . '/' . $name );
		return json_decode( str_replace( 'KDJ-AI-', 'KDJ-' . $code . '-', $raw ), true );
	};
	$book = array_merge( $read( 'book.json' ), $book_fields );
	$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
	file_put_contents( $root . '/master/book.json', json_encode( $book, $flags ) );
	file_put_contents( $root . '/provenance/content_ids.json', json_encode( $read( 'content_ids.json' ), $flags ) );
	file_put_contents( $root . '/provenance/citation-registry.json', json_encode( $read( 'citations.json' ), $flags ) );
	register_shutdown_function( static function () use ( $root ): void {
		foreach ( array( '/master/book.json', '/provenance/content_ids.json', '/provenance/citation-registry.json' ) as $file ) {
			@unlink( $root . $file );
		}
		@rmdir( $root . '/master' );
		@rmdir( $root . '/provenance' );
		@rmdir( $root );
	} );
	return $root;
}
