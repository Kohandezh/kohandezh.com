<?php
/**
 * REST controller.
 *
 * Endpoint:  POST /wp-json/kdcv/v1/ask
 * Body JSON: {question, locale, facts: [{title, body}], source: 'admin'|'visitor', provider?: 'zai'}
 *
 * The widget doesn't know (or care) which provider answers — it just hits
 * this URL and reads `available` + `answer`. The provider is chosen here
 * based on Settings (default) or the request's `provider` field.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDCV_AI_REST {

	const RATE_PER_MIN = 10;
	const MAX_QUESTION = 500;
	const MAX_FACTS    = 80;
	const MAX_FACT_LEN = 300;
	const ALLOWED_LOCALES = array( 'en', 'fa', 'ar', 'de', 'es', 'fr', 'tr', 'zh', 'ja' );

	public static function register() {
		add_action( 'rest_api_init', function () {
			register_rest_route( 'kdcv/v1', '/ask', array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'question' => array( 'required' => true,  'type' => 'string' ),
					'locale'   => array( 'required' => false, 'type' => 'string' ),
					'facts'    => array( 'required' => false, 'type' => 'array' ),
					'source'   => array( 'required' => false, 'type' => 'string' ),
					'provider' => array( 'required' => false, 'type' => 'string' ),
				),
			) );
		} );
	}

	public static function handle( WP_REST_Request $req ) {
		// 1. Pick a provider — request override > Settings default > null.
		$requested_provider = sanitize_text_field( (string) $req->get_param( 'provider' ) );
		$provider = KDCV_AI_Provider::build( $requested_provider !== '' ? $requested_provider : null );

		// Build the standard "not available" envelope the JS expects.
		$unavailable = function ( $reason, $status = 503 ) use ( &$provider ) {
			$model_name = $provider ? ( method_exists( $provider, 'default_model' ) ? $provider->default_model() : '' ) : '';
			return new WP_REST_Response( array(
				'available' => false,
				'reason'    => $reason,
				'provider'  => $provider ? '' : '',
				'model'     => $model_name,
			), $status );
		};

		if ( ! $provider ) {
			return $unavailable( 'no-provider', 503 );
		}
		if ( ! $provider->is_ready() ) {
			return $unavailable( 'provider-not-configured', 503 );
		}

		// 2. Sanitize input.
		$question = trim( (string) $req->get_param( 'question' ) );
		if ( $question === '' || mb_strlen( $question ) > self::MAX_QUESTION ) {
			return $unavailable( 'bad-question', 400 );
		}

		$locale = (string) $req->get_param( 'locale' );
		if ( ! in_array( $locale, self::ALLOWED_LOCALES, true ) ) {
			$locale = 'en';
		}

		$source = (string) $req->get_param( 'source' ) === 'admin' ? 'admin' : 'visitor';

		// 3. Per-IP rate limit.
		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';
		$forwarded = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) )
			: '';
		if ( $forwarded !== '' ) {
			$candidates = array_filter( array_map( 'trim', explode( ',', $forwarded ) ) );
			if ( ! empty( $candidates ) ) {
				$ip = $candidates[0];
			}
		}
		$ip_hash = md5( (string) $ip . wp_salt() );
		$bucket  = 'kdcv_ai_' . md5( (string) $ip );
		$count   = (int) get_transient( $bucket );
		if ( $count <= 0 ) {
			set_transient( $bucket, 1, MINUTE_IN_SECONDS );
		} elseif ( $count >= self::RATE_PER_MIN ) {
			self::log( $provider, $ip_hash, $locale, $source, $question, '', 'rate_limited' );
			return $unavailable( 'rate-limited', 429 );
		} else {
			set_transient( $bucket, $count + 1, MINUTE_IN_SECONDS );
		}

		// 4. Build prompts.
		$facts_block = self::build_facts_block( $req->get_param( 'facts' ) );
		$system      = self::build_system_prompt( $locale );
		$user        = "FACTS FROM THIS PAGE:\n" . ( $facts_block !== '' ? $facts_block : '(none extracted)' )
			. "\n\nQUESTION: " . $question;

		// 5. Ask the provider.
		$model_used = $provider->get_model();
		$result     = $provider->ask( $system, $user, array() );

		if ( ! $result['ok'] ) {
			self::log( $provider, $ip_hash, $locale, $source, $question,
				mb_substr( (string) $result['reason'], 0, 500 ),
				(string) $result['status'] );
			return $unavailable( $result['status'], 502 );
		}

		self::log( $provider, $ip_hash, $locale, $source, $question, $result['answer'], 'ok', $model_used );

		return new WP_REST_Response( array(
			'available' => true,
			'answer'    => $result['answer'],
			'provider'  => '',  // opaque to client
			'model'     => $model_used,
		), 200 );
	}

	private static function build_facts_block( $facts_in ) {
		if ( ! is_array( $facts_in ) ) {
			return '';
		}
		$out = array();
		foreach ( $facts_in as $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			$title = isset( $f['title'] )
				? mb_substr( trim( wp_strip_all_tags( (string) $f['title'] ) ), 0, self::MAX_FACT_LEN )
				: '';
			$body  = isset( $f['body'] )
				? mb_substr( trim( wp_strip_all_tags( (string) $f['body'] ) ), 0, self::MAX_FACT_LEN )
				: '';
			if ( $title === '' && $body === '' ) {
				continue;
			}
			$line = $title;
			if ( $body !== '' ) {
				$line .= ' — ' . $body;
			}
			$out[] = '• ' . $line;
			if ( count( $out ) >= self::MAX_FACTS ) {
				break;
			}
		}
		return implode( "\n", $out );
	}

	private static function build_system_prompt( $locale ) {
		$lang_names = array(
			'en' => 'English', 'fa' => 'Persian (Farsi)', 'ar' => 'Arabic',
			'de' => 'German', 'es' => 'Spanish', 'fr' => 'French',
			'tr' => 'Turkish', 'zh' => 'Simplified Chinese', 'ja' => 'Japanese',
		);
		$lang_name = isset( $lang_names[ $locale ] ) ? $lang_names[ $locale ] : 'English';
		return sprintf(
			'You are the CV assistant embedded on kohandezh.com. ' .
			'Answer the visitor question using ONLY the facts extracted from this page below. ' .
			'Do not invent information. If the facts do not contain the answer, say briefly that you could not find it on this page. ' .
			'Keep the answer concise (1 to 3 short sentences). ' .
			'Reply in %s. Do not use markdown, bullet lists, or code blocks.',
			$lang_name
		);
	}

	private static function log( $provider, $ip_hash, $locale, $source, $question, $answer, $status, $model = '' ) {
		$provider_id = '';
		if ( $provider instanceof KDCV_AI_Provider ) {
			// Reverse-lookup provider id from registry by class name.
			foreach ( KDCV_AI_Provider::ids() as $id ) {
				$built = KDCV_AI_Provider::build( $id );
				if ( $built && get_class( $built ) === get_class( $provider ) ) {
					$provider_id = $id;
					break;
				}
			}
		}
		if ( $model === '' && $provider instanceof KDCV_AI_Provider ) {
			$model = $provider->get_model();
		}
		KDCV_AI_Logger::add( array(
			'ip_hash'  => $ip_hash,
			'locale'   => $locale,
			'source'   => $source,
			'provider' => $provider_id,
			'model'    => $model,
			'status'   => $status,
			'question' => $question,
			'answer'   => $answer,
		) );
	}
}
