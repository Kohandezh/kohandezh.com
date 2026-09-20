<?php
/**
 * Storage-free, configuration-gated PDF request intake.
 *
 * Submissions are validated, bounded and forwarded to the configured
 * provider. Nothing is persisted locally; without provider configuration
 * the form degrades to an honest CONFIG_REQUIRED state.
 *
 * @package kohandezh-knowledge
 */

if ( ! defined( 'KBK_TESTING' ) && ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KBK_AI_Book_Request
 */
class KBK_AI_Book_Request {

	const STATUS_CONFIG_REQUIRED      = 'CONFIG_REQUIRED';
	const STATUS_INPUT_INVALID        = 'INPUT_INVALID';
	const STATUS_SUBMITTED            = 'SUBMITTED';
	const STATUS_REJECTED             = 'REJECTED';
	const STATUS_PROVIDER_UNAVAILABLE = 'PROVIDER_UNAVAILABLE';
	const STATUS_PROVIDER_INVALID     = 'PROVIDER_INVALID';

	const MAX_NAME_CHARS   = 80;
	const MAX_EMAIL_CHARS  = 120;
	const MAX_REASON_CHARS = 500;
	const MAX_REF_CHARS    = 32;
	const TIMEOUT_SECONDS  = 30;

	const USE_PURPOSES = array( 'personal', 'education', 'research' );

	const NONCE_ACTION = 'kbk_ai_book_request';
	const NONCE_FIELD  = 'kbk_nonce';

	/** @var array{endpoint:string,api_key:string}|null */
	private $provider;

	/** @var string */
	private $edition;

	/**
	 * @param array{endpoint:string,api_key:string}|null $provider Provider config; null keeps the form honestly disabled.
	 * @param string                                     $edition  Active edition reported to the provider.
	 */
	public function __construct( ?array $provider, string $edition = '' ) {
		$this->provider = null !== $provider && '' !== trim( (string) ( $provider['endpoint'] ?? '' ) ) && '' !== trim( (string) ( $provider['api_key'] ?? '' ) )
			? array(
				'endpoint' => (string) $provider['endpoint'],
				'api_key'  => (string) $provider['api_key'],
			)
			: null;
		$this->edition = $edition;
	}

	public function provider_configured(): bool {
		return null !== $this->provider;
	}

	/**
	 * Validate a POST-shaped payload and, when configured, forward it.
	 *
	 * @return array{status:string,reference:?string,errors:array<int,array{field:string,code:string}>}
	 */
	public function submit( array $post ): array {
		$errors = array();
		$name   = isset( $post['name'] ) && is_string( $post['name'] ) ? trim( $post['name'] ) : '';
		$email  = isset( $post['email'] ) && is_string( $post['email'] ) ? trim( $post['email'] ) : '';
		$use    = isset( $post['use'] ) && is_string( $post['use'] ) ? $post['use'] : '';
		$reason = isset( $post['reason'] ) && is_string( $post['reason'] ) ? trim( $post['reason'] ) : '';
		$consent = isset( $post['consent'] ) && is_string( $post['consent'] ) ? $post['consent'] : '';

		if ( '' !== $name && ( '' === $name || mb_strlen( $name ) > self::MAX_NAME_CHARS ) ) {
			$errors[] = array(
				'field' => 'name',
				'code'  => 'BOUNDED',
			);
		}
		if ( '' === $email ) {
			$errors[] = array(
				'field' => 'email',
				'code'  => 'REQUIRED',
			);
		} elseif ( mb_strlen( $email ) > self::MAX_EMAIL_CHARS || false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			$errors[] = array(
				'field' => 'email',
				'code'  => 'INVALID',
			);
		}
		if ( ! in_array( $use, self::USE_PURPOSES, true ) ) {
			$errors[] = array(
				'field' => 'use',
				'code'  => 'INVALID',
			);
		}
		if ( mb_strlen( $reason ) > self::MAX_REASON_CHARS ) {
			$errors[] = array(
				'field' => 'reason',
				'code'  => 'BOUNDED',
			);
		}
		if ( '1' !== $consent ) {
			$errors[] = array(
				'field' => 'consent',
				'code'  => 'REQUIRED',
			);
		}
		if ( array() !== $errors ) {
			return array(
				'status'    => self::STATUS_INPUT_INVALID,
				'reference' => null,
				'errors'    => $errors,
			);
		}
		if ( null === $this->provider ) {
			return array(
				'status'    => self::STATUS_CONFIG_REQUIRED,
				'reference' => null,
				'errors'    => array(),
			);
		}

		$body = array(
			'edition' => $this->edition,
			'name'    => $name,
			'email'   => $email,
			'use'     => $use,
			'reason'  => $reason,
		);
		$response = wp_remote_post(
			$this->provider['endpoint'],
			array(
				'timeout'  => self::TIMEOUT_SECONDS,
				'headers'  => array(
					'Content-Type'  => 'application/json; charset=utf-8',
					'Authorization' => 'Bearer ' . $this->provider['api_key'],
				),
				'body'     => (string) wp_json_encode( $body ),
				'data_format' => 'body',
			)
		);
		if ( is_wp_error( $response ) ) {
			return array(
				'status'    => self::STATUS_PROVIDER_UNAVAILABLE,
				'reference' => null,
				'errors'    => array(),
			);
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			return array(
				'status'    => self::STATUS_PROVIDER_UNAVAILABLE,
				'reference' => null,
				'errors'    => array(),
			);
		}
		try {
			$reference = $this->validate_provider_response( wp_remote_retrieve_body( $response ) );
		} catch ( UnexpectedValueException $error ) {
			return array(
				'status'    => self::STATUS_PROVIDER_INVALID,
				'reference' => null,
				'errors'    => array(),
			);
		}
		if ( null === $reference ) {
			return array(
				'status'    => self::STATUS_REJECTED,
				'reference' => null,
				'errors'    => array(),
			);
		}
		return array(
			'status'    => self::STATUS_SUBMITTED,
			'reference' => $reference,
			'errors'    => array(),
		);
	}

	/**
	 * Contract: `status` is "ok" (with bounded reference) or "rejected";
	 * anything else is invalid. Returns the reference or null when rejected.
	 *
	 * @throws UnexpectedValueException On contract violation.
	 */
	public function validate_provider_response( $body ): ?string {
		if ( ! is_string( $body ) || '' === trim( $body ) || mb_strlen( $body ) > 2048 ) {
			throw new UnexpectedValueException( 'request.response.body must be a bounded JSON string' );
		}
		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			throw new UnexpectedValueException( 'request.response.body must decode to an object' );
		}
		if ( ! isset( $decoded['status'] ) || ! is_string( $decoded['status'] ) ) {
			throw new UnexpectedValueException( 'request.response.status must be a string' );
		}
		if ( 'rejected' === $decoded['status'] ) {
			return null;
		}
		if ( 'ok' !== $decoded['status'] ) {
			throw new UnexpectedValueException( 'request.response.status has an unsupported value' );
		}
		if ( ! isset( $decoded['reference'] ) || ! is_string( $decoded['reference'] )
			|| '' === $decoded['reference'] || mb_strlen( $decoded['reference'] ) > self::MAX_REF_CHARS
			|| 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $decoded['reference'] ) ) {
			throw new UnexpectedValueException( 'request.response.reference must be a bounded opaque token' );
		}
		return $decoded['reference'];
	}
}
