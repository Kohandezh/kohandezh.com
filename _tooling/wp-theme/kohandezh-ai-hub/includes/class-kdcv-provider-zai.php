<?php
/**
 * z.ai (Zhipu GLM) provider.
 *
 * Endpoint: https://api.z.ai/api/paas/v4/chat/completions
 * Auth:     Bearer <api_key>
 * Body:     OpenAI-compatible (model, messages, temperature, max_tokens).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDCV_AI_Provider_ZAI extends KDCV_AI_Provider {

	const ENDPOINT = 'https://api.z.ai/api/paas/v4/chat/completions';
	const TIMEOUT  = 20;

	public function label() {
		return 'z.ai (Zhipu GLM)';
	}

	public function default_model() {
		return 'glm-4.6';
	}

	public function ask( $system, $user, array $options = array() ) {
		$key = isset( $this->config['api_key'] ) ? trim( $this->config['api_key'] ) : '';
		if ( $key === '' ) {
			return array(
				'ok'     => false,
				'status' => 'not_configured',
				'reason' => 'z.ai API key is empty — set it in Settings → AI Hub.',
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
		if ( ! isset( $decoded['choices'][0]['message']['content'] ) ) {
			return array(
				'ok'     => false,
				'status' => 'empty_answer',
				'reason' => 'Upstream returned no answer content.',
			);
		}

		$answer = trim( (string) $decoded['choices'][0]['message']['content'] );
		if ( $answer === '' ) {
			return array(
				'ok'     => false,
				'status' => 'empty_answer',
				'reason' => 'Upstream returned an empty string.',
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
