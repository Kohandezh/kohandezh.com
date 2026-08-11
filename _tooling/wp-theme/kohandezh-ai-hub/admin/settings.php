<?php
/**
 * Settings admin page — API keys + default provider + per-provider model.
 *
 * Keys live in wp_options (encrypted-at-rest by the host is the operator's
 * call; WP itself doesn't encrypt options). Edits to keys trigger a notice
 * and never appear in the conversation log.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_submenu_page(
		'kdcv-ai-chat',                          // parent slug (registered by chat.php)
		'AI Hub Settings',
		'Settings',
		'manage_options',
		'kdcv-ai-settings',
		'kdcv_ai_settings_render'
	);
}, 11 ); // priority 11 so the parent (registered at default 10 in chat.php) exists.

add_action( 'admin_init', function () {
	register_setting(
		'kdcv_ai_settings_group',
		'kdcv_ai_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'kdcv_ai_settings_sanitize',
			'default'           => array(),
		)
	);
} );

/**
 * Sanitize on save. Trim keys, whitelist provider ids, drop unknown fields.
 */
function kdcv_ai_settings_sanitize( $input ) {
	$out    = array();
	$ids    = KDCV_AI_Provider::ids();
	$clean  = is_array( $input ) ? $input : array();

	$default = isset( $clean['default_provider'] ) ? sanitize_text_field( $clean['default_provider'] ) : '';
	if ( in_array( $default, $ids, true ) ) {
		$out['default_provider'] = $default;
	} elseif ( ! empty( $ids ) ) {
		$out['default_provider'] = $ids[0];
	}

	foreach ( $ids as $id ) {
		$submitted = isset( $clean[ $id ] ) && is_array( $clean[ $id ] ) ? $clean[ $id ] : array();
		$key       = isset( $submitted['api_key'] ) ? trim( (string) $submitted['api_key'] ) : '';
		// If the masked placeholder is submitted unchanged, keep the stored value.
		if ( strpos( $key, '•••' ) === 0 && strlen( $key ) >= 8 ) {
			$existing = get_option( 'kdcv_ai_settings', array() );
			$key      = isset( $existing[ $id ]['api_key'] ) ? $existing[ $id ]['api_key'] : '';
		}
		$model = isset( $submitted['model'] ) ? sanitize_text_field( $submitted['model'] ) : '';
		$out[ $id ] = array(
			'api_key' => $key,
			'model'   => $model,
		);
	}
	return $out;
}

function kdcv_ai_settings_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Insufficient permissions.' );
	}
	$settings = get_option( 'kdcv_ai_settings', array() );
	$providers = KDCV_AI_Provider::all_for_ui();
	$default   = isset( $settings['default_provider'] ) ? $settings['default_provider'] : '';
	?>
	<div class="wrap">
		<h1>AI Hub Settings</h1>
		<p>Configure API keys for each provider. The widget and chat playground will use the <strong>default provider</strong> unless they explicitly ask for another one.</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'kdcv_ai_settings_group' ); ?>

			<h2 class="title">Default provider</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="kdcv-default-provider">Used for visitor questions</label></th>
					<td>
						<select id="kdcv-default-provider" name="kdcv_ai_settings[default_provider]">
							<?php foreach ( $providers as $id => $meta ) : ?>
								<?php $sel = ( $id === $default ) ? ' selected' : ''; ?>
								<option value="<?php echo esc_attr( $id ); ?>"<?php echo $sel; ?>>
									<?php echo esc_html( $meta['label'] ); ?><?php echo $meta['ready'] ? '' : ' (no key)'; ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">Switch any time — takes effect immediately on the next request.</p>
					</td>
				</tr>
			</table>

			<h2 class="title">Provider credentials</h2>
			<?php foreach ( $providers as $id => $meta ) :
				$cfg    = isset( $settings[ $id ] ) ? $settings[ $id ] : array();
				$has_key = ! empty( $cfg['api_key'] );
				$model   = isset( $cfg['model'] ) ? $cfg['model'] : '';
				?>
				<h3><?php echo esc_html( $meta['label'] ); ?> <code><?php echo esc_html( $id ); ?></code></h3>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="kdcv-key-<?php echo esc_attr( $id ); ?>">API key</label></th>
						<td>
							<input type="password" id="kdcv-key-<?php echo esc_attr( $id ); ?>"
								name="kdcv_ai_settings[<?php echo esc_attr( $id ); ?>][api_key]"
								value="<?php echo $has_key ? esc_attr( str_pad( '', min( 12, strlen( $cfg['api_key'] ) ), '•' ) ) : ''; ?>"
								class="regular-text" autocomplete="off" spellcheck="false" />
							<?php if ( $has_key ) : ?>
								<span class="description">Key is set. Submit a new value to replace it; leave as-is to keep.</span>
							<?php else : ?>
								<span class="description" style="color:#b32d2e;">Not configured — paste your key here.</span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="kdcv-model-<?php echo esc_attr( $id ); ?>">Model override</label></th>
						<td>
							<input type="text" id="kdcv-model-<?php echo esc_attr( $id ); ?>"
								name="kdcv_ai_settings[<?php echo esc_attr( $id ); ?>][model]"
								value="<?php echo esc_attr( $model ); ?>"
								class="regular-text" placeholder="(provider default)" autocomplete="off" />
							<p class="description">Leave blank to use the provider's recommended model. For z.ai: <code>glm-4.6</code>, <code>glm-4.5</code>, <code>glm-4.5-air</code>.</p>
						</td>
					</tr>
				</table>
			<?php endforeach; ?>

			<?php submit_button( 'Save changes' ); ?>
		</form>

		<hr>
		<h2>Adding more providers</h2>
		<p>Each new API is one PHP class that extends <code>KDCV_AI_Provider</code> and one
		<code>kdcv_ai_register_provider()</code> call in the plugin bootstrap. See the comment
		at the top of <code>kohandezh-ai-hub.php</code>. No DB migration needed — the log table
		is provider-agnostic.</p>
	</div>
	<?php
}
