<?php
/**
 * OpenAI provider.
 *
 * Endpoint: https://api.openai.com/v1/chat/completions
 * Auth:     Bearer <api_key>
 * Body:     the reference OpenAI chat shape (model, messages, temperature,
 *           max_tokens) — the same shape z.ai mimics, so this class differs
 *           from the z.ai one only in endpoint and defaults.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDCV_AI_Provider_OpenAI extends KDCV_AI_Provider {

	const ENDPOINT = 'https://api.openai.com/v1/chat/completions';
	const TIMEOUT  = 20;

	public function label() {
		return 'OpenAI (GPT)';
	}

	public function default_model() {
		return 'gpt-4o-mini';
	}

	public function ask( $system, $user, array $options = array() ) {
		$key = isset( $this->config['api_key'] ) ? trim( $this->config['api_key'] ) : '';
		if ( $key === '' ) {
			return array(
				'ok'     => false,
				'status' => 'not_configured',
				'reason' => 'OpenAI API key is empty — set it in AI Hub → Settings.',
			);
		}

		$body = wp_json_encode( array(
			'model'       => $this->pick_model( $options ),
			'temperature' => isset( $options['temperature'] ) ? (float) $options['temperature'] : 0.2,
			'max_tokens'  => isset( $options['max_tokens'] ) ? (int) $options['max_tokens'] : 400,
			'messages'    => array(
				array( 'role' => 'system', 'content' => $system ),
				array( 'role' => 'user',   'content' => $user ),
			),
		) );

		$response = wp_remote_post( self::ENDPOINT, array(
			'timeout' => self::TIMEOUT,
			'headers' => array(
				'Authorization' => 'Bearer ' . $key,
				'Content-Type'  => 'application/json',
			),
			'body'    => $body,
		) );

		return $this->finish_openai_shape( $response );
	}

	/**
	 * Shared response handling for every OpenAI-shaped API. The custom
	 * provider subclasses this class, so keep the parsing in one place.
	 *
	 * @param array|WP_Error $response wp_remote_post() result.
	 * @return array Contract shape from KDCV_AI_Provider::ask().
	 */
	protected function finish_openai_shape( $response ) {
		if ( is_wp_error( $response ) ) {
			return array(
				'ok'     => false,
				'status' => 'transport_error',
				'reason' => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return array(
				'ok'     => false,
				'status' => 'upstream_' . $code,
				'reason' => 'HTTP ' . $code . ': ' . substr( (string) wp_remote_retrieve_body( $response ), 0, 200 ),
			);
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		$answer  = isset( $decoded['choices'][0]['message']['content'] )
			? trim( (string) $decoded['choices'][0]['message']['content'] )
			: '';
		if ( $answer === '' ) {
			return array(
				'ok'     => false,
				'status' => 'empty_answer',
				'reason' => 'Upstream returned no answer content.',
			);
		}

		return array(
			'ok'     => true,
			'answer' => $answer,
			'status' => 'ok',
			'reason' => '',
		);
	}
}
