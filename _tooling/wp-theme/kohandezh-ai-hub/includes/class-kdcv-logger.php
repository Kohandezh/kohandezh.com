<?php
/**
 * Conversation log — DB-backed.
 *
 * Schema is provider-agnostic so the same table works for z.ai / OpenAI /
 * Anthropic / whatever comes next. The `provider` column distinguishes them.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDCV_AI_Logger {

	/**
	 * Create the chat log table. Called on plugin activation and on every
	 * admin screen until the schema option matches the plugin version.
	 */
	public static function install() {
		global $wpdb;
		$table   = $wpdb->prefix . KDCV_AI_TABLE;
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			ip_hash VARCHAR(64) NOT NULL DEFAULT '',
			locale VARCHAR(5) NOT NULL DEFAULT '',
			source VARCHAR(16) NOT NULL DEFAULT 'visitor',
			provider VARCHAR(24) NOT NULL DEFAULT '',
			model VARCHAR(64) NOT NULL DEFAULT '',
			status VARCHAR(32) NOT NULL DEFAULT 'ok',
			question TEXT NOT NULL,
			answer TEXT NOT NULL,
			PRIMARY KEY (id),
			KEY created_at (created_at),
			KEY source (source),
			KEY provider (provider),
			KEY status (status)
		) {$charset};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Insert a row. Trim/cap fields so a runaway client cannot blow up the
	 * table — values are public-facing.
	 */
	public static function add( array $row ) {
		global $wpdb;
		$table = $wpdb->prefix . KDCV_AI_TABLE;
		$wpdb->insert( $table, array(
			'created_at' => current_time( 'mysql', true ),
			'ip_hash'    => isset( $row['ip_hash'] ) ? substr( (string) $row['ip_hash'], 0, 64 ) : '',
			'locale'     => isset( $row['locale'] ) ? substr( (string) $row['locale'], 0, 5 ) : '',
			'source'     => isset( $row['source'] ) && $row['source'] === 'admin' ? 'admin' : 'visitor',
			'provider'   => isset( $row['provider'] ) ? substr( (string) $row['provider'], 0, 24 ) : '',
			'model'      => isset( $row['model'] ) ? substr( (string) $row['model'], 0, 64 ) : '',
			'status'     => isset( $row['status'] ) ? substr( (string) $row['status'], 0, 32 ) : 'ok',
			'question'   => isset( $row['question'] ) ? mb_substr( (string) $row['question'], 0, 1000 ) : '',
			'answer'     => isset( $row['answer'] ) ? mb_substr( (string) $row['answer'], 0, 4000 ) : '',
		), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
		return (int) $wpdb->insert_id;
	}

	public static function count_all() {
		global $wpdb;
		$table = $wpdb->prefix . KDCV_AI_TABLE;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	public static function get_page( $per_page, $offset ) {
		global $wpdb;
		$table = $wpdb->prefix . KDCV_AI_TABLE;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT created_at, ip_hash, locale, source, provider, model, status, question, answer
			 FROM {$table}
			 ORDER BY id DESC
			 LIMIT %d OFFSET %d",
			$per_page, $offset
		) );
	}

	public static function truncate() {
		global $wpdb;
		$table = $wpdb->prefix . KDCV_AI_TABLE;
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	public static function fetch_all_for_export() {
		global $wpdb;
		$table = $wpdb->prefix . KDCV_AI_TABLE;
		return $wpdb->get_results(
			"SELECT created_at, ip_hash, locale, source, provider, model, status, question, answer
			 FROM {$table}
			 ORDER BY id ASC"
		);
	}
}
