<?php
/**
 * Custom OpenAI-compatible provider.
 *
 * For any service that speaks the OpenAI chat shape at its own address:
 * DeepSeek, Groq, Together, OpenRouter, a local Ollama, an in-house gateway.
 * The endpoint comes from the per-provider "Base URL" field in AI Hub →
 * Settings; everything else reuses the OpenAI request/response handling.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDCV_AI_Provider_Custom extends KDCV_AI_Provider_OpenAI {

	public function label() {
		return 'Custom (OpenAI-compatible)';
	}

	public function default_model() {
		// No sensible universal default exists across gateways; the model
		// field in settings is effectively required for this provider.
		return '';
	}

	public function ask( $system, $user, array $options = array() ) {
		$key      = isset( $this->config['api_key'] ) ? trim( $this->config['api_key'] ) : '';
		$endpoint = isset( $this->config['base_url'] ) ? trim( $this->config['base_url'] ) : '';
		$model    = $this->pick_model( $options );

		if ( $endpoint === '' || ! preg_match( '#^https://#', $endpoint ) ) {
			return array(
				'ok'     => false,
				'status' => 'not_configured',
				'reason' => 'Custom provider needs an https:// Base URL (the full /chat/completions address) in AI Hub → Settings.',
			);
		}
		if ( $model === '' ) {
			return array(
				'ok'     => false,
				'status' => 'not_configured',
				'reason' => 'Custom provider needs a model name in AI Hub → Settings.',
			);
		}

		$headers = array( 'Content-Type' => 'application/json' );
		if ( $key !== '' ) {
			// A local gateway may be keyless; only send auth when a key is set.
			$headers['Authorization'] = 'Bearer ' . $key;
		}

		$body = wp_json_encode( array(
			'model'       => $model,
			'temperature' => isset( $options['temperature'] ) ? (float) $options['temperature'] : 0.2,
			'max_tokens'  => isset( $options['max_tokens'] ) ? (int) $options['max_tokens'] : 400,
			'messages'    => array(
				array( 'role' => 'system', 'content' => $system ),
				array( 'role' => 'user',   'content' => $user ),
			),
		) );

		$response = wp_remote_post( $endpoint, array(
			'timeout' => self::TIMEOUT,
			'headers' => $headers,
			'body'    => $body,
		) );

		return $this->finish_openai_shape( $response );
	}
}
