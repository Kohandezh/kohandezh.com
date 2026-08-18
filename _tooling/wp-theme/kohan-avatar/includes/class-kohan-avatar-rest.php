<?php
/**
 * Kohan Avatar — REST endpoints.
 *
 * Exposes a read-only version endpoint plus an authenticated sync/refresh
 * action. The sync action is guarded by `manage_options` and the WP REST
 * nonce; it never accepts a caller-supplied filesystem path — the source
 * directory lives server-side in the sync script only. There is no public,
 * unauthenticated refresh endpoint.
 *
 * @package KohanAvatar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Kohan_Avatar_REST {

	const NS = 'kohan-avatar/v1';

	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes() {
		register_rest_route(
			self::NS,
			'/version',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_version' ),
				'permission_callback' => '__return_true', // version is non-sensitive
			)
		);

		register_rest_route(
			self::NS,
			'/sync',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'run_sync' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NS,
			'/tts',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'proxy_tts' ),
				'permission_callback' => '__return_true', // anonymous speech; no secrets returned
				'args'                => array(
					'text'   => array( 'required' => true, 'type' => 'string' ),
					'locale' => array( 'type' => 'string' ),
					'voice'  => array( 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/stt',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'proxy_stt' ),
				'permission_callback' => '__return_true', // anonymous speech; no secrets returned
				'args'                => array(
					'audio'  => array( 'required' => true, 'type' => 'string' ), // data: URI or bare base64
					'locale' => array( 'type' => 'string' ),
				),
			)
		);
	}

	public function can_manage() {
		return current_user_can( 'manage_options' );
	}

	public function get_version( $request ) {
		$info = Kohan_Avatar::instance()->version_info();
		return rest_ensure_response(
			array(
				'plugin' => KOHAN_AVATAR_VERSION,
				'assets' => $info,
			)
		);
	}

	/**
	 * Run the server-side sync script. The source path is fixed inside the
	 * script; the request carries no path. Requires manage_options; the REST
	 * nonce is enforced by WordPress for logged-in cookie auth.
	 */
	public function run_sync( $request ) {
		if ( ! $this->can_manage() ) {
			return new WP_Error( 'forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
		}

		$script = KOHAN_AVATAR_DIR . 'scripts/sync-kohan-assets.sh';
		if ( ! is_readable( $script ) ) {
			return new WP_Error( 'no_script', 'Sync script not found.', array( 'status' => 500 ) );
		}

		// Only run if shell execution is available and not disabled.
		if ( ! function_exists( 'shell_exec' ) || $this->exec_disabled() ) {
			return new WP_Error(
				'exec_unavailable',
				'Shell execution is disabled on this host. Run scripts/sync-kohan-assets.sh from your deploy pipeline instead.',
				array( 'status' => 501 )
			);
		}

		$cmd    = 'bash ' . escapeshellarg( $script ) . ' 2>&1';
		$output = shell_exec( $cmd );

		$info = Kohan_Avatar::instance()->version_info();
		return rest_ensure_response(
			array(
				'ok'      => true,
				'log'     => is_string( $output ) ? $output : '',
				'version' => $info,
			)
		);
	}

	/**
	 * Real client IP for rate limiting. Dual-mode:
	 *   • Behind Cloudflare → HTTP_CF_CONNECTING_IP is authoritative (set by CF's
	 *     edge, which OVERWRITES any client-supplied value, so it is not spoofable).
	 *   • Direct on LiteSpeed (current production) → REMOTE_ADDR is the real peer.
	 * We deliberately do NOT trust the raw X-Forwarded-For chain: it is a list the
	 * client can prepend to, so trusting it would let one attacker rotate keys to
	 * reset their limit. When a generic reverse proxy is added without CF, set the
	 * trusted header in wp-config.php (KDCV_TRUSTED_PROXY_IP_HEADER) and it is used
	 * verbatim from REMOTE_ADDR-side (i.e. only when the request actually came from
	 * the proxy's address) — kept out of scope here to avoid unsafe defaults.
	 */
	private function client_ip() {
		/* REMOTE_ADDR is the only unforgeable value — it is the TCP peer. The
		   CF header is trusted ONLY when the request actually came from a
		   configured proxy address; otherwise an attacker sends a fresh
		   CF-Connecting-IP per request and every call lands in a new bucket,
		   which made the TTS/STT limits protecting a PAID key decorative.
		   Production is not behind Cloudflare yet, so this list is empty
		   unless KDCV_TRUSTED_PROXIES is defined. */
		$trusted = defined( 'KDCV_TRUSTED_PROXIES' ) ? (array) KDCV_TRUSTED_PROXIES : array();
		$peer    = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
		if ( in_array( $peer, $trusted, true ) && ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			return (string) $_SERVER['HTTP_CF_CONNECTING_IP'];
		}
		return $peer;
	}

	private function exec_disabled() {
		$disabled = explode( ',', (string) ini_get( 'disable_functions' ) );
		$disabled = array_map( 'trim', $disabled );
		return in_array( 'shell_exec', $disabled, true );
	}

	/**
	 * TTS proxy. The browser POSTs {text, locale, voice}; this route uses the
	 * server-side API key (from Settings→Kohan Avatar→Voice & TTS Services) to
	 * call the configured provider and returns base64 audio. The key NEVER
	 * reaches the browser. If no server voice is configured, it returns
	 * {audio: null, fallback: 'webspeech'} so the caller falls back to the
	 * Web Speech API silently.
	 */
	public function proxy_tts( $request ) {
		$avatar = Kohan_Avatar::instance();

		// --- rate limit: 20/min + 200/hour per real client IP (protects the paid key) ---
		// See client_ip(): Cloudflare-authoritative when CF is in front, else REMOTE_ADDR
		// (correct on direct LiteSpeed now and behind CF later). NEVER trusts the
		// client-spoofable X-Forwarded-For chain, so one attacker cannot rotate the
		// header to reset their bucket, and behind a proxy all users are NOT merged.
		$ip   = $this->client_ip();
		$min  = (int) get_transient( $mk = 'kdcv_tts_rl_min_' . md5( $ip ) );
		$hour = (int) get_transient( $hk = 'kdcv_tts_rl_hour_' . md5( $ip ) );
		if ( $min >= 20 || $hour >= 200 ) {
			return new WP_Error( 'rate_limited', 'Too many speech requests. Please slow down.', array( 'status' => 429 ) );
		}
		set_transient( $mk, $min + 1, MINUTE_IN_SECONDS );
		set_transient( $hk, $hour + 1, HOUR_IN_SECONDS );

		if ( ! $avatar->voice_configured() ) {
			return rest_ensure_response( array( 'audio' => null, 'fallback' => 'webspeech' ) );
		}

		$t        = $avatar->get_tts_options();
		$text     = trim( (string) $request->get_param( 'text' ) );
		$locale   = (string) $request->get_param( 'locale' );
		/* The `voice` parameter used to pass straight through to the provider as
		   the billed `model`, on an UNAUTHENTICATED route holding a paid key —
		   so any visitor could select a more expensive model on the owner's
		   account. The request may now only pick from what the operator has
		   actually configured; anything else falls back to the configured
		   voice. */
		$requested = (string) $request->get_param( 'voice' );
		$allowed   = array_filter( array_map( 'trim', array( $t['voice'], 'tts-1', 'tts-1-hd' ) ) );
		$voice     = in_array( $requested, $allowed, true ) ? $requested : (string) $t['voice'];
		if ( $text === '' || strlen( $text ) > 1200 ) {
			return new WP_Error( 'bad_text', 'Text required (max 1200 chars).', array( 'status' => 400 ) );
		}

		$endpoint = $t['endpoint'];
		if ( ! $this->endpoint_allowed( $endpoint ) ) {
			return new WP_Error( 'bad_endpoint', 'TTS endpoint not allowed.', array( 'status' => 400 ) );
		}

		// Provider-specific request shaping. Defaults: POST JSON, Bearer key, expect audio back.
		$body    = '';
		$headers = array(
			'Authorization' => 'Bearer ' . $t['api_key'],
			'Content-Type'  => 'application/json',
			'Timeout'        => '15',
		);

		switch ( $t['provider'] ) {
			case 'openai':
				$body = wp_json_encode(
					array(
						'model' => $voice ?: 'tts-1',
						'input' => $text,
						'voice' => 'alloy',
					)
				);
				break;
			case 'elevenlabs':
				// Endpoint should include {voice} or be the full path; we POST {text}.
				$body    = wp_json_encode( array( 'text' => $text ) );
				break;
			case 'whisper':
			case 'custom':
			default:
				// Generic passthrough: send {text, locale, voice}.
				$body = wp_json_encode(
					array(
						'text'   => $text,
						'locale' => $locale,
						'voice'  => $voice,
					)
				);
				break;
		}

		$resp = wp_remote_post(
			$endpoint,
			array(
				'timeout'     => 15,
				'redirection' => 2,
				'headers'     => $headers,
				'body'        => $body,
			)
		);

		if ( is_wp_error( $resp ) ) {
			return rest_ensure_response( array( 'audio' => null, 'fallback' => 'webspeech' ) );
		}

		$code   = wp_remote_retrieve_response_code( $resp );
		$audio  = wp_remote_retrieve_body( $resp ); // raw audio bytes
		$ctype  = wp_remote_retrieve_header( $resp, 'content-type' );

		if ( 200 !== (int) $code || empty( $audio ) || 0 === strpos( $ctype, 'application/json' ) ) {
			// Provider error → fail silent to webspeech rather than leak server detail.
			return rest_ensure_response( array( 'audio' => null, 'fallback' => 'webspeech' ) );
		}

		$mime = preg_match( '~^[\w.-]+/[\w.-]+~', $ctype ) ? explode( ';', $ctype )[0] : 'audio/mpeg';
		$b64  = base64_encode( $audio ); // phpcs:ignore — audio bytes, not secrets
		return rest_ensure_response(
			array(
				'audio' => 'data:' . $mime . ';base64,' . $b64,
			)
		);
	}

	/**
	 * SSRF guard: only http(s), no private/loopback/link-local ranges.
	 */
	/**
	 * Speech-to-text proxy — the mirror of proxy_tts().
	 *
	 * The browser POSTs {audio, locale} where `audio` is a recorded clip as a
	 * data: URI (or bare base64). This route sends it to the configured provider
	 * using the SERVER-side key and returns only {text}. The key never reaches
	 * the browser, exactly as with TTS.
	 *
	 * When nothing is configured it answers {text: null, fallback: 'webspeech'},
	 * so the existing browser SpeechRecognition path keeps working untouched —
	 * the same silent-degradation contract the TTS route already uses.
	 *
	 * Uploads are the expensive, abusable direction (a paid key plus inbound
	 * bytes), so the limits are tighter than TTS: 10/min, 60/hour, and a hard
	 * cap on decoded audio size checked BEFORE the provider is called.
	 */
	public function proxy_stt( $request ) {
		$avatar = Kohan_Avatar::instance();

		$ip   = $this->client_ip();
		$min  = (int) get_transient( $mk = 'kdcv_stt_rl_min_' . md5( $ip ) );
		$hour = (int) get_transient( $hk = 'kdcv_stt_rl_hour_' . md5( $ip ) );
		if ( $min >= 10 || $hour >= 60 ) {
			return new WP_Error( 'rate_limited', 'Too many transcription requests. Please slow down.', array( 'status' => 429 ) );
		}
		set_transient( $mk, $min + 1, MINUTE_IN_SECONDS );
		set_transient( $hk, $hour + 1, HOUR_IN_SECONDS );

		if ( ! $avatar->stt_configured() ) {
			return rest_ensure_response( array( 'text' => null, 'fallback' => 'webspeech' ) );
		}

		$s   = $avatar->get_stt_options();
		$raw = (string) $request->get_param( 'audio' );

		// Accept "data:audio/webm;codecs=opus;base64,AAAA..." or bare base64.
		$mime = 'audio/webm';
		if ( 0 === strpos( $raw, 'data:' ) ) {
			if ( ! preg_match( '~^data:([\w.+-]+/[\w.+-]+)[^,]*;base64,(.*)$~s', $raw, $m ) ) {
				return new WP_Error( 'bad_audio', 'Malformed audio payload.', array( 'status' => 400 ) );
			}
			$mime = strtolower( $m[1] );
			$raw  = $m[2];
		}

		$bytes = base64_decode( strtr( $raw, ' ', '+' ), true );
		if ( false === $bytes || '' === $bytes ) {
			return new WP_Error( 'bad_audio', 'Audio could not be decoded.', array( 'status' => 400 ) );
		}
		// ~8 MB of decoded audio is far more than a chat utterance needs.
		if ( strlen( $bytes ) > 8 * 1024 * 1024 ) {
			return new WP_Error( 'audio_too_large', 'Audio clip is too large (8 MB max).', array( 'status' => 413 ) );
		}
		// Only formats the common providers actually accept.
		$ext_map = array(
			'audio/webm' => 'webm', 'audio/ogg' => 'ogg', 'audio/mpeg' => 'mp3',
			'audio/mp4'  => 'mp4',  'audio/m4a' => 'm4a', 'audio/wav'  => 'wav',
			'audio/x-wav' => 'wav', 'audio/mpga' => 'mp3',
		);
		$base = explode( ';', $mime )[0];
		if ( ! isset( $ext_map[ $base ] ) ) {
			return new WP_Error( 'bad_audio_type', 'Unsupported audio format.', array( 'status' => 415 ) );
		}

		$endpoint = $s['endpoint'];
		if ( ! $this->endpoint_allowed( $endpoint ) ) {
			return new WP_Error( 'bad_endpoint', 'STT endpoint not allowed.', array( 'status' => 400 ) );
		}

		// multipart/form-data, built by hand: wp_remote_post has no file helper.
		$boundary = wp_generate_password( 24, false );
		$eol      = "\r\n";
		$locale   = strtolower( substr( (string) $request->get_param( 'locale' ), 0, 5 ) );
		$fields   = array( 'model' => $s['model'] ?: 'whisper-1' );
		if ( preg_match( '/^[a-z]{2}$/', substr( $locale, 0, 2 ) ) ) {
			$fields['language'] = substr( $locale, 0, 2 );
		}

		$payload = '';
		foreach ( $fields as $k => $v ) {
			$payload .= '--' . $boundary . $eol;
			$payload .= 'Content-Disposition: form-data; name="' . $k . '"' . $eol . $eol;
			$payload .= $v . $eol;
		}
		$payload .= '--' . $boundary . $eol;
		$payload .= 'Content-Disposition: form-data; name="file"; filename="clip.' . $ext_map[ $base ] . '"' . $eol;
		$payload .= 'Content-Type: ' . $base . $eol . $eol;
		$payload .= $bytes . $eol;
		$payload .= '--' . $boundary . '--' . $eol;

		$resp = wp_remote_post(
			$endpoint,
			array(
				'timeout'     => 30, // transcription is slower than synthesis
				'redirection' => 2,
				'headers'     => array(
					'Authorization' => 'Bearer ' . $s['api_key'],
					'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
				),
				'body'        => $payload,
			)
		);

		if ( is_wp_error( $resp ) ) {
			return rest_ensure_response( array( 'text' => null, 'fallback' => 'webspeech' ) );
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			// Fail silent to the browser recogniser rather than leak provider detail.
			return rest_ensure_response( array( 'text' => null, 'fallback' => 'webspeech' ) );
		}

		$decoded = json_decode( wp_remote_retrieve_body( $resp ), true );
		$text    = is_array( $decoded ) && isset( $decoded['text'] ) ? trim( (string) $decoded['text'] ) : '';
		if ( '' === $text ) {
			return rest_ensure_response( array( 'text' => null, 'fallback' => 'webspeech' ) );
		}

		return rest_ensure_response( array( 'text' => wp_strip_all_tags( $text ) ) );
	}

	private function endpoint_allowed( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return false;
		}
		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['scheme'] ) || ! in_array( strtolower( $parsed['scheme'] ), array( 'http', 'https' ), true ) ) {
			return false;
		}
		$host = isset( $parsed['host'] ) ? strtolower( $parsed['host'] ) : '';
		if ( '' === $host ) {
			return false;
		}
		// Block obvious local/private hosts; allow real provider domains.
		if ( in_array( $host, array( 'localhost', '0.0.0.0' ), true ) ) {
			return false;
		}
		if ( preg_match( '/^(10\.|127\.|192\.168\.|169\.254\.|::1$|fc00:|fe80:)/i', $host ) ) {
			return false;
		}
		if ( preg_match( '/^172\.(1[6-9]|2\d|3[01])\./', $host ) ) {
			return false;
		}
		return true;
	}
}
