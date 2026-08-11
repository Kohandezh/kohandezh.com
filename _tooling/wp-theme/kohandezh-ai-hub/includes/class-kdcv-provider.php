<?php
/**
 * Provider abstraction + registry.
 *
 * Every backend (z.ai, OpenAI, Anthropic, …) implements this contract. The
 * REST controller stays provider-agnostic — it just calls
 * KDCV_AI_Provider::ask() on whichever provider the request selects.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class KDCV_AI_Provider {

	/**
	 * Stored config (api_key, model, …) merged on top of the registration
	 * defaults. Set by the registry in build().
	 *
	 * @var array
	 */
	protected $config = array();

	public function __construct( array $config = array() ) {
		$this->config = $config;
	}

	/**
	 * Human-readable label shown in the admin dropdown.
	 *
	 * @return string
	 */
	abstract public function label();

	/**
	 * Default model name for this provider (e.g. 'glm-4.6').
	 *
	 * @return string
	 */
	abstract public function default_model();

	/**
	 * Send the chat completion request to the upstream API.
	 *
	 * Return shape:
	 *   array(
	 *     'ok'     => bool,
	 *     'answer' => string,                       // present when ok=true
	 *     'status' => 'ok' | 'upstream_429' | …,    // machine-readable bucket
	 *     'reason' => string,                       // human-readable detail
	 *   )
	 *
	 * @param string $system   System prompt.
	 * @param string $user     User prompt (already includes facts context).
	 * @param array  $options  {model, temperature, max_tokens} overrides.
	 * @return array
	 */
	abstract public function ask( $system, $user, array $options = array() );

	/**
	 * Helper: which model should this call use?
	 *
	 * @param array $options
	 * @return string
	 */
	protected function pick_model( array $options ) {
		if ( ! empty( $options['model'] ) ) {
			return (string) $options['model'];
		}
		if ( ! empty( $this->config['model'] ) ) {
			return (string) $this->config['model'];
		}
		return $this->default_model();
	}

	/**
	 * Is this provider ready to serve requests? (e.g., has an API key.)
	 *
	 * @return bool
	 */
	public function is_ready() {
		return ! empty( $this->config['api_key'] );
	}

	/**
	 * Public accessor for the model this provider will use, accounting for
	 * Settings overrides. $config is protected; without this getter the REST
	 * controller can't read it and would always fall back to default_model().
	 *
	 * @return string
	 */
	public function get_model() {
		if ( ! empty( $this->config['model'] ) ) {
			return (string) $this->config['model'];
		}
		return $this->default_model();
	}

	// ──────────────────────────────────────────────────────────────────────
	//  Registry
	// ──────────────────────────────────────────────────────────────────────

	/**
	 * Internal registry of provider_id => meta.
	 *
	 * @var array
	 */
	private static $registry = array();

	/**
	 * Register a provider so it becomes available across the plugin.
	 *
	 * @param string $id    Short id ('zai', 'openai', …).
	 * @param array  $meta  {label, class, default_mm}.
	 */
	public static function register( $id, array $meta ) {
		self::$registry[ $id ] = $meta;
	}

	/**
	 * List all registered provider ids.
	 *
	 * @return string[]
	 */
	public static function ids() {
		return array_keys( self::$registry );
	}

	/**
	 * List provider ids that have an API key configured in Settings.
	 *
	 * @return string[]
	 */
	public static function ready_ids() {
		$settings = get_option( 'kdcv_ai_settings', array() );
		$out      = array();
		foreach ( self::$registry as $id => $meta ) {
			$key = isset( $settings[ $id ]['api_key'] ) ? $settings[ $id ]['api_key'] : '';
			if ( $key !== '' && class_exists( $meta['class'] ) ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * Build an instance of the given provider, merging stored Settings
	 * (api_key, model override) on top of registration defaults.
	 *
	 * @param string $id  Provider id; null = default from Settings.
	 * @return KDCV_AI_Provider|null
	 */
	public static function build( $id = null ) {
		$settings = get_option( 'kdcv_ai_settings', array() );
		if ( $id === null ) {
			$id = isset( $settings['default_provider'] ) ? $settings['default_provider'] : '';
		}
		if ( ! isset( self::$registry[ $id ] ) ) {
			return null;
		}
		$meta    = self::$registry[ $id ];
		$class   = $meta['class'];
		if ( ! class_exists( $class ) ) {
			return null;
		}
		$stored  = isset( $settings[ $id ] ) ? (array) $settings[ $id ] : array();
		$config  = array_merge(
			array(
				'label'  => $meta['label'],
				'model'  => isset( $meta['default_mm'] ) ? $meta['default_mm'] : '',
			),
			$stored
		);
		/** @var KDCV_AI_Provider $instance */
		$instance = new $class( $config );
		return $instance;
	}

	/**
	 * Convenience accessor used by the admin UI.
	 *
	 * @return array  id => ['label' => …, 'ready' => bool]
	 */
	public static function all_for_ui() {
		$out = array();
		$ready = self::ready_ids();
		foreach ( self::$registry as $id => $meta ) {
			$out[ $id ] = array(
				'label' => isset( $meta['label'] ) ? $meta['label'] : $id,
				'ready' => in_array( $id, $ready, true ),
			);
		}
		return $out;
	}
}

/**
 * Free function shortcut — makes registration in plugins_loaded cleaner.
 *
 * @param string $id
 * @param array  $meta
 */
function kdcv_ai_register_provider( $id, array $meta ) {
	KDCV_AI_Provider::register( $id, $meta );
}
