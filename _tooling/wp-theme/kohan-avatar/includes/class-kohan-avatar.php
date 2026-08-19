<?php
/**
 * Kohan Avatar — main plugin class.
 *
 * Enqueues the self-contained avatar runtime on the front end, injects
 * server-derived configuration (asset URLs via WordPress helpers only),
 * registers the Settings > Kohan Avatar admin page, and disables any prior
 * theme-level floating avatar so exactly one avatar instance exists.
 *
 * @package KohanAvatar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Kohan_Avatar {

	const OPTION = 'kohan_avatar_options';
	const TTS_OPTION = 'kohan_avatar_tts';

	/**
	 * Canonical mood allowlist. Every mood the controller/events bridge may
	 * be asked to play is validated against this set server-side and again
	 * in the browser. Keeps arbitrary mood strings out of the runtime.
	 */
	const MOODS = array(
		'idle', 'running-right', 'running-left', 'waving', 'jumping', 'angry',
		'confused', 'macbook-work', 'ipad-review', 'pointer-look',
		'wink', 'angry-still', 'confused-vision', 'guarded', 'russian-roulette',
		'drag-annoyed', 'fall-scared', 'goodbye-smoke', 'walking-patrol',
	);

	protected static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_footer', array( $this, 'print_voice_config' ), 20 );

		// Replace, don't stack: neutralise the old theme avatar assets.
		// The theme hardcodes the old avatar as deferred <script> tags in its
		// templates (not via wp_enqueue), so filtering enqueued sources is not
		// enough on its own — we also pre-empt the legacy bootstrap guards from
		// the head, where a non-deferred inline script runs before any deferred
		// body script, making the old avatar self-disable cleanly.
		add_action( 'wp_head', array( $this, 'preempt_legacy_avatar' ), 0 );
		add_action( 'wp_print_scripts', array( $this, 'dequeue_legacy_avatar' ), 100 );
		add_filter( 'style_loader_src', array( $this, 'block_legacy_style' ), 10, 2 );
		add_filter( 'script_loader_src', array( $this, 'block_legacy_script' ), 10, 2 );
	}

	/* ------------------------------------------------------------------ */
	/* Options                                                             */
	/* ------------------------------------------------------------------ */

	public static function defaults() {
		return array(
			'enabled'          => 1,
			'position'         => 'bottom-left',
			'scale'            => 1.0,
			'idle_min'         => 30,
			'idle_max'         => 75,
			'drag'             => 1,
			'pointer_look'     => 1,
			'supplemental'     => 1,
			'weapon_moods'     => 1,
			'response_events'  => 0,
			'chat'             => 1,
			'fire_probability' => 0.5,
		);
	}

	public function get_options() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function activate() {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults() );
		}
		if ( false === get_option( self::TTS_OPTION, false ) ) {
			add_option( self::TTS_OPTION, self::tts_defaults() );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Voice / TTS options (server-side keys, never exposed to browser)    */
	/* ------------------------------------------------------------------ */

	public static function tts_defaults() {
		return array(
			'enabled'  => 0,
			'provider' => 'webspeech', // webspeech | openai | elevenlabs | whisper | custom
			'endpoint' => '',
			'api_key'  => '',
			'voice'    => '',
		);
	}

	public function get_tts_options() {
		$saved = get_option( self::TTS_OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::tts_defaults() );
	}

	/**
	 * Speech-to-text settings, stored separately from TTS.
	 *
	 * Kept as its own option rather than extra keys on the TTS array because the
	 * two are independently useful: a site may want the server voice without
	 * accepting microphone uploads, or the reverse. `reuse_tts_key` covers the
	 * common case where both run on the same OpenAI account, so the operator
	 * pastes the key once.
	 */
	const STT_OPTION = 'kohan_avatar_stt';

	public static function stt_defaults() {
		return array(
			'enabled'       => 0,
			'provider'      => 'openai', // openai | custom
			'endpoint'      => 'https://api.openai.com/v1/audio/transcriptions',
			'api_key'       => '',
			'model'         => 'whisper-1',
			'reuse_tts_key' => 1,
		);
	}

	public function get_stt_options() {
		$saved = get_option( self::STT_OPTION, array() );
		$o     = wp_parse_args( is_array( $saved ) ? $saved : array(), self::stt_defaults() );
		if ( ! empty( $o['reuse_tts_key'] ) && '' === trim( (string) $o['api_key'] ) ) {
			$t             = $this->get_tts_options();
			$o['api_key']  = $t['api_key'];
		}
		return $o;
	}

	/** Whether transcription can actually be served (enabled + key + endpoint). */
	public function stt_configured() {
		$s = $this->get_stt_options();
		return ! empty( $s['enabled'] ) && ! empty( $s['api_key'] ) && ! empty( $s['endpoint'] );
	}

	/**
	 * Whether a real server-side voice is configured (provider set + key present +
	 * endpoint). Drives the boolean flag the browser receives.
	 */
	public function voice_configured() {
		$t = $this->get_tts_options();
		if ( empty( $t['enabled'] ) ) {
			return false;
		}
		if ( in_array( $t['provider'], array( 'webspeech', '' ), true ) ) {
			return false;
		}
		return ! empty( $t['api_key'] ) && ! empty( $t['endpoint'] );
	}

	/**
	 * Public, non-secret voice config for the front-end. The API key is NEVER
	 * included. Output via wp_footer into window.KDCV_VOICE.
	 */
	public function voice_config_for_js() {
		$t = $this->get_tts_options();
		return array(
			'provider'   => $t['provider'],
			'configured' => $this->voice_configured(),
			'endpoint'   => $this->voice_configured() ? esc_url_raw( rest_url( 'kohan-avatar/v1/tts' ) ) : '',
			'voice'      => $t['voice'],
			// Transcription is advertised the same way: a boolean and a route,
			// never the key. The mic UI stays on the browser's own SpeechRecognition
			// unless this says the server can do better.
			'stt'        => array(
				'configured' => $this->stt_configured(),
				'endpoint'   => $this->stt_configured() ? esc_url_raw( rest_url( 'kohan-avatar/v1/stt' ) ) : '',
			),
		);
	}

	/**
	 * Inject window.KDCV_VOICE on the front-end so wisdom-quotes.js (and any other
	 * avatar speech) knows whether to use the server voice or the Web Speech API.
	 */
	public function print_voice_config() {
		if ( is_admin() ) {
			return;
		}
		$cfg = $this->voice_config_for_js();
		echo '<script>window.KDCV_VOICE = ' . wp_json_encode( $cfg ) . ';</script>' . "\n";
	}

	/* ------------------------------------------------------------------ */
	/* Front-end enqueue                                                   */
	/* ------------------------------------------------------------------ */

	/**
	 * Per-file cache-busting version: the file's own mtime (falls back to the
	 * plugin version). Keeps an edited script/style from being served stale.
	 */
	private function file_ver( $rel ) {
		$path = KOHAN_AVATAR_DIR . ltrim( $rel, '/' );
		$mt   = @filemtime( $path );
		return $mt ? (string) $mt : KOHAN_AVATAR_VERSION;
	}

	public function enqueue_front() {
		$o = $this->get_options();
		if ( empty( $o['enabled'] ) ) {
			return;
		}

		$ver      = $this->asset_version();
		$css_url  = KOHAN_AVATAR_URL . 'assets/css/kohan-avatar.css';
		$js_url   = KOHAN_AVATAR_URL . 'assets/js/kohan-avatar.js';
		$evt_url  = KOHAN_AVATAR_URL . 'assets/js/kohan-avatar-events.js';

		wp_enqueue_style( 'kohan-avatar', $css_url, array(), $this->file_ver( 'assets/css/kohan-avatar.css' ) );

		wp_register_script( 'kohan-avatar', $js_url, array(), $this->file_ver( 'assets/js/kohan-avatar.js' ), true );

		$config = array(
			'assetBase' => esc_url_raw( KOHAN_AVATAR_URL . 'assets/kohan' ),
			'ariaLabel' => 'Kohan avatar',
			// kdcv/v1/chat has never existed. The AI Hub registers kdcv/v1/ask and
			// nothing else, so every message the avatar sent 404'd: the bubble sat
			// on "pending" forever and the assistant looked dead.
			'chatRoute' => esc_url_raw( rest_url( 'kdcv/v1/ask' ) ),
			'options'   => array(
				'enabled'         => true,
				'position'        => sanitize_key( $o['position'] ),
				'scale'           => (float) $o['scale'],
				'idleRangeMs'     => array( (int) $o['idle_min'] * 1000, (int) $o['idle_max'] * 1000 ),
				'drag'            => (bool) $o['drag'],
				'pointerLook'     => (bool) $o['pointer_look'],
				'supplemental'    => (bool) $o['supplemental'],
				'weaponMoods'     => (bool) $o['weapon_moods'],
				'responseEvents'  => (bool) $o['response_events'],
				'chat'            => (bool) $o['chat'],
				'rouletteEvery'   => 3,
				'fireProbability' => (float) $o['fire_probability'],
			),
			'strings'   => array(
				'title'       => 'Kohan',
				'status'      => __( 'AI assistant', 'kohan-avatar' ),
				'placeholder' => __( 'Ask me anything…', 'kohan-avatar' ),
				'send'        => __( 'Send', 'kohan-avatar' ),
				'open'        => __( 'Open chat', 'kohan-avatar' ),
				'close'       => __( 'Close chat', 'kohan-avatar' ),
				'greeting'    => __( "Hi! I'm Kohan. Ask me about the work, projects or how to get in touch.", 'kohan-avatar' ),
				'error'       => __( "I couldn't reach the assistant right now. Please use the contact form.", 'kohan-avatar' ),
			),
		);

		wp_add_inline_script(
			'kohan-avatar',
			'window.KohanAvatarConfig = ' . wp_json_encode( $config ) . ';',
			'before'
		);
		wp_enqueue_script( 'kohan-avatar' );

		// Preload only the idle atlas — supplemental strips load on demand.
		add_action( 'wp_head', array( $this, 'preload_atlas' ), 2 );

		if ( ! empty( $o['response_events'] ) ) {
			wp_enqueue_script( 'kohan-avatar-events', $evt_url, array( 'kohan-avatar' ), $this->file_ver( 'assets/js/kohan-avatar-events.js' ), true );
		}

		if ( ! empty( $o['chat'] ) ) {
			wp_enqueue_style( 'kohan-avatar-chat', KOHAN_AVATAR_URL . 'assets/css/kohan-avatar-chat.css', array( 'kohan-avatar' ), $this->file_ver( 'assets/css/kohan-avatar-chat.css' ) );
			wp_enqueue_script( 'kohan-avatar-chat', KOHAN_AVATAR_URL . 'assets/js/kohan-avatar-chat.js', array( 'kohan-avatar' ), $this->file_ver( 'assets/js/kohan-avatar-chat.js' ), true );
		}
	}

	public function preload_atlas() {
		$href = esc_url( KOHAN_AVATAR_URL . 'assets/kohan/spritesheet.webp?v=' . $this->asset_version() );
		echo '<link rel="preload" as="image" href="' . $href . '" type="image/webp">' . "\n";
	}

	/**
	 * Version string for cache-busting: prefers the SHA in version.json
	 * (written by the sync script), falls back to the plugin version.
	 */
	public function asset_version() {
		$vfile = KOHAN_AVATAR_DIR . 'assets/kohan/version.json';
		if ( is_readable( $vfile ) ) {
			$data = json_decode( file_get_contents( $vfile ), true );
			if ( is_array( $data ) && ! empty( $data['hash'] ) ) {
				return substr( preg_replace( '/[^a-f0-9]/i', '', (string) $data['hash'] ), 0, 12 );
			}
		}
		return KOHAN_AVATAR_VERSION;
	}

	/* ------------------------------------------------------------------ */
	/* Legacy avatar removal (replace, not stack)                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Print an inline head script that trips the legacy avatar bootstrap
	 * guards so the old theme avatar (ai-pet + legacy kohan-avatar) and its
	 * fetch patch never initialise. Runs before the deferred legacy scripts.
	 * Only emitted when the plugin avatar is enabled.
	 */
	public function preempt_legacy_avatar() {
		$o = $this->get_options();
		if ( empty( $o['enabled'] ) ) {
			return;
		}
		echo "<script>window.__KDCV_PET_BOOTSTRAPPED__=true;window.__KOHAN_AVATAR_BOOTSTRAPPED__=true;window.__KOHAN_FETCH_PATCHED__=true;</script>\n";
	}

	public function dequeue_legacy_avatar() {
		foreach ( array( 'ai-pet', 'kohan-avatar-legacy', 'kdcv-pet', 'kohan-avatar-theme' ) as $h ) {
			wp_dequeue_script( $h );
			wp_deregister_script( $h );
			wp_dequeue_style( $h );
			wp_deregister_style( $h );
		}
	}

	private function is_legacy_avatar_src( $src ) {
		if ( ! $src ) {
			return false;
		}
		// The old theme shipped ai-pet / kohan-avatar under the theme assets;
		// block those so the plugin's single instance is the only avatar.
		return (bool) preg_match( '#/themes/[^/]+/assets/(js|css)/(ai-pet|kohan-avatar)(\.min)?\.(js|css)#', $src );
	}

	public function block_legacy_style( $src, $handle ) {
		return $this->is_legacy_avatar_src( $src ) ? '' : $src;
	}

	public function block_legacy_script( $src, $handle ) {
		return $this->is_legacy_avatar_src( $src ) ? '' : $src;
	}

	/* ------------------------------------------------------------------ */
	/* Admin: Settings > Kohan Avatar                                      */
	/* ------------------------------------------------------------------ */

	public function admin_menu() {
		add_options_page(
			__( 'Kohan Avatar', 'kohan-avatar' ),
			__( 'Kohan Avatar', 'kohan-avatar' ),
			'manage_options',
			'kohan-avatar',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'kohan_avatar_group',
			self::OPTION,
			array( 'sanitize_callback' => array( $this, 'sanitize_options' ) )
		);
		register_setting(
			'kohan_avatar_tts_group',
			self::TTS_OPTION,
			array( 'sanitize_callback' => array( $this, 'sanitize_tts_options' ) )
		);

		register_setting(
			'kohan_avatar_stt_group',
			self::STT_OPTION,
			array( 'sanitize_callback' => array( $this, 'sanitize_stt_options' ) )
		);
	}

	public function sanitize_stt_options( $input ) {
		$d    = self::stt_defaults();
		$prev = get_option( self::STT_OPTION, array() );
		$prev = wp_parse_args( is_array( $prev ) ? $prev : array(), $d );
		$out  = array();

		$out['enabled']       = empty( $input['enabled'] ) ? 0 : 1;
		$out['reuse_tts_key'] = empty( $input['reuse_tts_key'] ) ? 0 : 1;

		$prov             = isset( $input['provider'] ) ? sanitize_key( $input['provider'] ) : $d['provider'];
		$out['provider']  = in_array( $prov, array( 'openai', 'custom' ), true ) ? $prov : $d['provider'];
		$out['endpoint']  = isset( $input['endpoint'] ) ? esc_url_raw( trim( $input['endpoint'] ) ) : '';
		$out['model']     = isset( $input['model'] ) ? sanitize_text_field( trim( $input['model'] ) ) : $d['model'];
		if ( '' === $out['endpoint'] ) {
			$out['endpoint'] = $d['endpoint'];
		}

		// Same contract as the TTS key: never echoed back to the form, so a blank
		// field means "keep what is stored", not "erase it".
		if ( ! empty( $input['clear_key'] ) ) {
			$out['api_key'] = '';
		} elseif ( ! empty( $input['api_key'] ) ) {
			$out['api_key'] = sanitize_text_field( trim( $input['api_key'] ) );
		} else {
			$out['api_key'] = $prev['api_key'];
		}
		return $out;
	}

	public function sanitize_tts_options( $input ) {
		$d     = self::tts_defaults();
		$prev  = $this->get_tts_options();
		$out   = array();
		$out['enabled']  = empty( $input['enabled'] ) ? 0 : 1;
		$prov            = isset( $input['provider'] ) ? sanitize_key( $input['provider'] ) : $d['provider'];
		$allowed         = array( 'webspeech', 'openai', 'elevenlabs', 'whisper', 'custom' );
		$out['provider'] = in_array( $prov, $allowed, true ) ? $prov : $d['provider'];
		$out['endpoint'] = isset( $input['endpoint'] ) ? esc_url_raw( trim( $input['endpoint'] ) ) : '';
		$out['voice']    = isset( $input['voice'] ) ? sanitize_text_field( trim( $input['voice'] ) ) : '';
		// API key: never echo back. Clear, replace, or keep.
		if ( ! empty( $input['clear_key'] ) ) {
			$out['api_key'] = '';
		} elseif ( ! empty( $input['api_key'] ) ) {
			$out['api_key'] = sanitize_text_field( trim( $input['api_key'] ) );
		} else {
			$out['api_key'] = $prev['api_key']; // keep existing when field left blank
		}
		return $out;
	}

	public function sanitize_options( $input ) {
		$d   = self::defaults();
		$out = array();
		$out['enabled']         = empty( $input['enabled'] ) ? 0 : 1;
		$pos                    = isset( $input['position'] ) ? sanitize_key( $input['position'] ) : $d['position'];
		$out['position']        = in_array( $pos, array( 'bottom-left', 'bottom-right', 'top-left', 'top-right' ), true ) ? $pos : $d['position'];
		$out['scale']           = min( 2.0, max( 0.5, (float) ( $input['scale'] ?? $d['scale'] ) ) );
		$out['idle_min']        = min( 600, max( 5, (int) ( $input['idle_min'] ?? $d['idle_min'] ) ) );
		$out['idle_max']        = min( 1200, max( $out['idle_min'] + 1, (int) ( $input['idle_max'] ?? $d['idle_max'] ) ) );
		$out['drag']            = empty( $input['drag'] ) ? 0 : 1;
		$out['pointer_look']    = empty( $input['pointer_look'] ) ? 0 : 1;
		$out['supplemental']    = empty( $input['supplemental'] ) ? 0 : 1;
		$out['weapon_moods']    = empty( $input['weapon_moods'] ) ? 0 : 1;
		$out['response_events'] = empty( $input['response_events'] ) ? 0 : 1;
		$out['chat']            = empty( $input['chat'] ) ? 0 : 1;
		$out['fire_probability']= min( 1.0, max( 0.0, (float) ( $input['fire_probability'] ?? $d['fire_probability'] ) ) );
		return $out;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		require KOHAN_AVATAR_DIR . 'admin/settings.php';
	}

	/**
	 * Read the deployed asset hash/version for display and REST.
	 */
	public function version_info() {
		$vfile = KOHAN_AVATAR_DIR . 'assets/kohan/version.json';
		if ( is_readable( $vfile ) ) {
			$data = json_decode( file_get_contents( $vfile ), true );
			if ( is_array( $data ) ) {
				return $data;
			}
		}
		return array( 'hash' => '', 'generatedAt' => '', 'files' => array() );
	}
}
