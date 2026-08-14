<?php
/**
 * Anthropic (Claude) provider.
 *
 * Endpoint: https://api.anthropic.com/v1/messages
 * Auth:     x-api-key header + anthropic-version (NOT Bearer).
 * Body:     system is a top-level field, not a message role, and the answer
 *           comes back as content[0].text — both differ from the OpenAI shape,
 *           which is why this is its own class rather than a base_url swap.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDCV_AI_Provider_Anthropic extends KDCV_AI_Provider {

	const ENDPOINT = 'https://api.anthropic.com/v1/messages';
	const VERSION  = '2023-06-01';
	const TIMEOUT  = 20;

	public function label() {
		return 'Anthropic (Claude)';
	}

	public function default_model() {
		return 'claude-haiku-4-5-20251001';
	}

	public function ask( $system, $user, array $options = array() ) {
		$key = isset( $this->config['api_key'] ) ? trim( $this->config['api_key'] ) : '';
		if ( $key === '' ) {
			return array(
				'ok'     => false,
				'status' => 'not_configured',
				'reason' => 'Anthropic API key is empty — set it in AI Hub → Settings.',
			);
		}

		$body = wp_json_encode( array(
			'model'       => $this->pick_model( $options ),
			'system'      => $system,
			'temperature' => isset( $options['temperature'] ) ? (float) $options['temperature'] : 0.2,
			'max_tokens'  => isset( $options['max_tokens'] ) ? (int) $options['max_tokens'] : 400,
			'messages'    => array(
				array( 'role' => 'user', 'content' => $user ),
			),
		) );

		$response = wp_remote_post( self::ENDPOINT, array(
			'timeout' => self::TIMEOUT,
			'headers' => array(
				'x-api-key'         => $key,
				'anthropic-version' => self::VERSION,
				'Content-Type'      => 'application/json',
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
		$answer  = isset( $decoded['content'][0]['text'] ) ? trim( (string) $decoded['content'][0]['text'] ) : '';
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
