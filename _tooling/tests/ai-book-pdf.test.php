<?php
/**
 * Phase 14: PDF viewer facts/stream plan + storage-free configuration-gated
 * request intake, exercised against the canonical AiBook root.
 *
 * Usage: php ai-book-pdf.test.php /Users/emperor/Documents/AI/AiBook
 */

if ( 2 > $argc || '' === trim( $argv[1] ?? '' ) ) {
	fwrite( STDERR, "Usage: php ai-book-pdf.test.php <canonical-root>\n" );
	exit( 1 );
}
define( 'KBK_TESTING', true );
define( 'ABSPATH', __DIR__ );
define( 'KBK_PLUGIN_FILE', __FILE__ );
define( 'KBK_VERSION', 'test' );
$root = rtrim( $argv[1], '/' );

$GLOBALS['kbk_test_query'] = array();
function get_query_var( $name ) { return $GLOBALS['kbk_test_query'][ $name ] ?? ''; }
function wp_unslash( $value ) { return $value; }
function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
function sanitize_text_field( $value ) { return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : ''; }
function sanitize_textarea_field( $value ) { return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : ''; }
function status_header( $code ) { $GLOBALS['kbk_test_status'] = $code; }
function nocache_headers() {}
function add_filter( $name, $callback, $priority = 10 ) {}
function add_action( $name, $callback, $priority = 10 ) {}
function wp_remote_post( $url, $args ) {
	$queue = $GLOBALS['kbk_request_stub_responses'] ?? array();
	$GLOBALS['kbk_request_calls'][] = array( 'url' => $url, 'args' => $args );
	$next = array_shift( $GLOBALS['kbk_request_stub_responses'] );
	return $next ?? null;
}
function wp_remote_retrieve_response_code( $response ) { return is_array( $response ) ? ( $response['code'] ?? 0 ) : 0; }
function wp_remote_retrieve_body( $response ) { return is_array( $response ) ? ( $response['body'] ?? '' ) : ''; }
function is_wp_error( $thing ) { return null === $thing; }

require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-artifacts.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-repository.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-search.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-catalog.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-graph.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-ask.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-pdf.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-request.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book.php';

function fail( string $message ): void {
	fwrite( STDERR, "FAIL {$message}\n" );
	exit( 1 );
}

/* Block 1 — viewer facts and stream plan from the real canonical root. */
$repository = new KBK_AI_Book_Repository( $root );
$repository->bundle();
$pdf = new KBK_AI_Book_Pdf( $repository );
if ( 'READY' !== $pdf->status() ) {
	fail( 'pdf status must be READY for the canonical release' );
}
$meta = $pdf->meta();
if ( null === $meta || '2026E1' !== $meta['edition'] || 1292 !== $meta['pages'] || 64 !== strlen( $meta['sha256'] ) || 64 !== strlen( $meta['merkle_root'] ) ) {
	fail( 'pdf meta must carry the bounded edition facts' );
}
$manifest = json_decode( (string) file_get_contents( $root . '/provenance/release-manifest.json' ), true );
if ( $meta['sha256'] !== $manifest['artifacts']['06_book_fa.pdf']['sha256'] || $meta['bytes'] !== $manifest['artifacts']['06_book_fa.pdf']['bytes'] ) {
	fail( 'pdf meta must mirror the release manifest' );
}
$actual_sha = hash_file( 'sha256', $meta['path'] );
if ( $meta['sha256'] !== $actual_sha ) {
	fail( 'canonical pdf file must match its release manifest sha256' );
}
$plan = $pdf->stream_plan();
if ( 'READY' !== $plan['status'] || $meta['path'] !== $plan['path'] || $meta['bytes'] !== $plan['bytes'] ) {
	fail( 'stream plan must reference the validated file' );
}
$headers = implode( "\n", $plan['headers'] );
if ( false === strpos( $headers, 'Content-Type: application/pdf' ) || false === strpos( $headers, 'noindex' ) || false === strpos( $headers, 'Content-Disposition: inline; filename="kohandezh-ai-book-2026E1-fa.pdf"' ) || false !== strpos( $headers, $root ) ) {
	fail( 'stream headers must be typed, noindexed, named and free of local paths' );
}
if ( '/ai-book/pdf/file/' !== $meta['stream_url'] ) {
	fail( 'stream url must live in the canonical ai-book namespace' );
}

/* Block 2 — validator boundary on tampered manifests and files. */
$tmp_dir = sys_get_temp_dir() . '/kbk-pdf-test-' . getmypid();
@mkdir( $tmp_dir . '/provenance', 0777, true );
@mkdir( $tmp_dir . '/release', 0777, true );
$manifest['artifacts']['06_book_fa.pdf']['sha256'] = str_repeat( 'a', 63 );
file_put_contents( $tmp_dir . '/provenance/release-manifest.json', (string) wp_json_encode( $manifest ) );
try {
	KBK_AI_Book_Artifacts::validate_release_manifest( json_decode( (string) file_get_contents( $tmp_dir . '/provenance/release-manifest.json' ), true ), '2026E1' );
	fail( 'short sha256 must be rejected' );
} catch ( UnexpectedValueException $e ) {
}
$manifest['artifacts']['06_book_fa.pdf']['sha256'] = str_repeat( 'a', 64 );
$manifest['artifacts']['06_book_fa.pdf']['bytes']  = -1;
file_put_contents( $tmp_dir . '/provenance/release-manifest.json', (string) wp_json_encode( $manifest ) );
try {
	KBK_AI_Book_Artifacts::validate_release_manifest( json_decode( (string) file_get_contents( $tmp_dir . '/provenance/release-manifest.json' ), true ), '2026E1' );
	fail( 'negative bytes must be rejected' );
} catch ( UnexpectedValueException $e ) {
}
unset( $manifest['artifacts']['06_book_fa.pdf'] );
try {
	KBK_AI_Book_Artifacts::validate_release_manifest( $manifest, '2026E1' );
	fail( 'manifest without the pdf artifact must be rejected' );
} catch ( UnexpectedValueException $e ) {
}
$pdf_tampered = new KBK_AI_Book_Pdf( $repository, $tmp_dir );
if ( 'ARTIFACT_INVALID' !== $pdf_tampered->status() ) {
	fail( 'invalid manifest (negative bytes) must fail closed as ARTIFACT_INVALID' );
}
unlink( $tmp_dir . '/provenance/release-manifest.json' );
$pdf_tampered = new KBK_AI_Book_Pdf( $repository, $tmp_dir );
if ( 'CONFIG_REQUIRED' !== $pdf_tampered->status() ) {
	fail( 'missing manifest must fail closed as CONFIG_REQUIRED' );
}
file_put_contents( $tmp_dir . '/provenance/release-manifest.json', (string) wp_json_encode( $manifest_original = json_decode( (string) file_get_contents( $root . '/provenance/release-manifest.json' ), true ) ) );
file_put_contents( $tmp_dir . '/release/06_book_fa.pdf', 'not a pdf' );
$pdf_tampered = new KBK_AI_Book_Pdf( $repository, $tmp_dir );
if ( 'ARTIFACT_INVALID' !== $pdf_tampered->status() ) {
	fail( 'truncated pdf must fail closed as ARTIFACT_INVALID' );
}
copy( $root . '/provenance/release-manifest.json', $tmp_dir . '/provenance/release-manifest.json' );
$truncated = (string) file_get_contents( $root . '/release/06_book_fa.pdf', false, null, 0, 1024 );
file_put_contents( $tmp_dir . '/release/06_book_fa.pdf', $truncated );
$pdf_tampered = new KBK_AI_Book_Pdf( $repository, $tmp_dir );
$degraded_plan = $pdf_tampered->stream_plan();
if ( 'ARTIFACT_INVALID' !== $pdf_tampered->status() || null !== $pdf_tampered->meta() || 'ARTIFACT_INVALID' !== $degraded_plan['status'] || '' !== $degraded_plan['path'] || array() !== $degraded_plan['headers'] ) {
	fail( 'size-mismatched pdf must fail closed without facts' );
}

/* Block 3 — request intake: field validation and honest unconfigured state. */
$unconfigured = new KBK_AI_Book_Request( null, '2026E1' );
if ( $unconfigured->provider_configured() ) {
	fail( 'null provider must stay unconfigured' );
}
$bad = $unconfigured->submit( array(
	'name'    => str_repeat( 'ن', 90 ),
	'email'   => 'not-an-email',
	'use'     => 'weaponized',
	'reason'  => str_repeat( 'د', 501 ),
	'consent' => '',
) );
if ( array( 'name', 'email', 'use', 'reason', 'consent' ) !== array_column( $bad['errors'], 'field' ) ) {
	fail( 'request intake must flag every invalid field' );
}
$missing_email = $unconfigured->submit( array( 'name' => 'سارا', 'email' => '', 'use' => 'personal', 'reason' => '', 'consent' => '1' ) );
if ( array( 'email' ) !== array_column( $missing_email['errors'], 'field' ) ) {
	fail( 'missing email must be the only error' );
}
if ( 'CONFIG_REQUIRED' !== $unconfigured->submit( array( 'name' => 'سارا', 'email' => 'sara@example.com', 'use' => 'personal', 'reason' => '', 'consent' => '1' ) )['status'] ) {
	fail( 'valid input without provider must degrade to CONFIG_REQUIRED, never pretend success' );
}

/* Block 4 — request intake: provider contract with stubbed HTTP. */
$provider = new KBK_AI_Book_Request( array( 'endpoint' => 'https://requests.example.test/v1', 'api_key' => 'test-key' ), '2026E1' );
if ( ! $provider->provider_configured() ) {
	fail( 'endpoint+key must count as configured' );
}
$GLOBALS['kbk_request_calls']         = array();
$GLOBALS['kbk_request_stub_responses'] = array( null ); // transport error
$unreachable = $provider->submit( array( 'name' => 'سارا', 'email' => 'sara@example.com', 'use' => 'education', 'reason' => 'درس', 'consent' => '1' ) );
if ( 'PROVIDER_UNAVAILABLE' !== $unreachable['status'] || null !== $unreachable['reference'] ) {
	fail( 'unreachable provider must degrade honestly' );
}
$sent = $GLOBALS['kbk_request_calls'][0] ?? array();
if ( 'https://requests.example.test/v1' !== ( $sent['url'] ?? '' ) || false === strpos( (string) wp_json_encode( $sent['args']['headers'] ?? array() ), 'Bearer test-key' ) ) {
	fail( 'provider call must target the endpoint with the bearer key' );
}
$payload = json_decode( (string) ( $sent['args']['body'] ?? '' ), true );
if ( ! is_array( $payload ) || '2026E1' !== ( $payload['edition'] ?? '' ) || 'sara@example.com' !== ( $payload['email'] ?? '' ) || 'education' !== ( $payload['use'] ?? '' ) ) {
	fail( 'provider payload must be bounded to the validated fields plus edition' );
}
$GLOBALS['kbk_request_stub_responses'] = array( array( 'code' => 503, 'body' => '' ) );
if ( 'PROVIDER_UNAVAILABLE' !== $provider->submit( array( 'name' => '', 'email' => 'sara@example.com', 'use' => 'personal', 'reason' => '', 'consent' => '1' ) )['status'] ) {
	fail( 'non-2xx provider must degrade honestly' );
}
foreach ( array(
	'{"status":"ok"}',
	'{"status":"ok","reference":"' . str_repeat( 'R', 33 ) . '"}',
	'{"status":"ok","reference":"bad ref!"}',
	'{"status":"pending","reference":"R1"}',
	'not json',
	'{"status":"ok","reference":"R1"}' . str_repeat( 'x', 2100 ),
) as $invalid_body ) {
	try {
		@$provider->validate_provider_response( $invalid_body );
		fail( 'provider response contract must reject: ' . substr( $invalid_body, 0, 30 ) );
	} catch ( UnexpectedValueException $e ) {
	}
}
$GLOBALS['kbk_request_stub_responses'] = array( array( 'code' => 200, 'body' => '{"status":"ok","reference":"REQ-8f2a"}' ) );
$ok = $provider->submit( array( 'name' => 'سارا', 'email' => 'sara@example.com', 'use' => 'research', 'reason' => '', 'consent' => '1' ) );
if ( 'SUBMITTED' !== $ok['status'] || 'REQ-8f2a' !== $ok['reference'] || array() !== $ok['errors'] ) {
	fail( 'valid provider response must submit with its bounded reference' );
}
$GLOBALS['kbk_request_stub_responses'] = array( array( 'code' => 200, 'body' => '{"status":"rejected"}' ) );
if ( 'REJECTED' !== $provider->submit( array( 'name' => '', 'email' => 'sara@example.com', 'use' => 'personal', 'reason' => '', 'consent' => '1' ) )['status'] ) {
	fail( 'explicit provider rejection must be an honest REJECTED state' );
}

/* Block 5 — route layer without configuration (fresh process statics). */
$GLOBALS['kbk_test_query']['kbk_ai_book'] = 'pdf';
if ( 'CONFIG_REQUIRED' !== KBK_AI_Book::pdf_status() || null !== KBK_AI_Book::pdf() ) {
	fail( 'route layer pdf must fail closed without a root' );
}
$GLOBALS['kbk_test_query']['kbk_ai_book'] = 'request-pdf';
$request_state = KBK_AI_Book::current_request();
if ( 'CONFIG_REQUIRED' !== $request_state['status'] || array() !== $request_state['errors'] || null !== $request_state['reference'] || '' !== $request_state['reposted']['email'] || false !== $request_state['provider'] ) {
	fail( 'route layer request must degrade to a safe empty state' );
}

echo "PASS ai-book-pdf: manifest-backed viewer facts with offline sha256 verification, typed noindexed stream plan, tampered manifest/file fail-closed paths, bounded request intake with per-field errors, honest unconfigured state and provider contract (unavailable/invalid/rejected/ok)\n";
