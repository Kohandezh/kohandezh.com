<?php
/**
 * Kohan Avatar — Settings > Kohan Avatar admin screen.
 * Rendered by Kohan_Avatar::render_settings_page(); $this is the plugin.
 *
 * @package KohanAvatar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$o    = $this->get_options();
$info = $this->version_info();
$hash = isset( $info['hash'] ) ? (string) $info['hash'] : '';
$tts          = $this->get_tts_options();
$tts_has_key  = ! empty( $tts['api_key'] );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Kohan Avatar', 'kohan-avatar' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Single interactive Kohan avatar rendered from the authoritative sprite atlas. This plugin replaces any prior floating avatar.', 'kohan-avatar' ); ?>
	</p>

	<form method="post" action="options.php">
		<?php settings_fields( 'kohan_avatar_group' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enabled', 'kohan-avatar' ); ?></th>
				<td>
					<label><input type="checkbox" name="kohan_avatar_options[enabled]" value="1" <?php checked( $o['enabled'], 1 ); ?>>
					<?php esc_html_e( 'Show the Kohan avatar on the front end', 'kohan-avatar' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Position', 'kohan-avatar' ); ?></th>
				<td>
					<select name="kohan_avatar_options[position]">
						<?php
						$positions = array(
							'bottom-left'  => __( 'Bottom left', 'kohan-avatar' ),
							'bottom-right' => __( 'Bottom right', 'kohan-avatar' ),
							'top-left'     => __( 'Top left', 'kohan-avatar' ),
							'top-right'    => __( 'Top right', 'kohan-avatar' ),
						);
						foreach ( $positions as $val => $label ) {
							printf(
								'<option value="%s" %s>%s</option>',
								esc_attr( $val ),
								selected( $o['position'], $val, false ),
								esc_html( $label )
							);
						}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Scale', 'kohan-avatar' ); ?></th>
				<td>
					<input type="number" step="0.05" min="0.5" max="2" name="kohan_avatar_options[scale]" value="<?php echo esc_attr( $o['scale'] ); ?>">
					<p class="description"><?php esc_html_e( '0.5–2.0 (1.0 = default size)', 'kohan-avatar' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Idle delay (seconds)', 'kohan-avatar' ); ?></th>
				<td>
					<?php esc_html_e( 'min', 'kohan-avatar' ); ?>
					<input type="number" min="5" max="600" name="kohan_avatar_options[idle_min]" value="<?php echo esc_attr( $o['idle_min'] ); ?>" style="width:5em">
					&nbsp;<?php esc_html_e( 'max', 'kohan-avatar' ); ?>
					<input type="number" min="6" max="1200" name="kohan_avatar_options[idle_max]" value="<?php echo esc_attr( $o['idle_max'] ); ?>" style="width:5em">
					<p class="description"><?php esc_html_e( 'Random wait before each idle action. Every third completed interval plays Russian Roulette.', 'kohan-avatar' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Interactions', 'kohan-avatar' ); ?></th>
				<td>
					<label><input type="checkbox" name="kohan_avatar_options[drag]" value="1" <?php checked( $o['drag'], 1 ); ?>>
					<?php esc_html_e( 'Allow drag (pointer capture)', 'kohan-avatar' ); ?></label><br>
					<label><input type="checkbox" name="kohan_avatar_options[pointer_look]" value="1" <?php checked( $o['pointer_look'], 1 ); ?>>
					<?php esc_html_e( '16-direction pointer-look on hover', 'kohan-avatar' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Moods', 'kohan-avatar' ); ?></th>
				<td>
					<label><input type="checkbox" name="kohan_avatar_options[supplemental]" value="1" <?php checked( $o['supplemental'], 1 ); ?>>
					<?php esc_html_e( 'Supplemental moods (wink, confused-vision, drag-annoyed, fall-scared, goodbye-smoke…)', 'kohan-avatar' ); ?></label><br>
					<label><input type="checkbox" name="kohan_avatar_options[weapon_moods]" value="1" <?php checked( $o['weapon_moods'], 1 ); ?>>
					<?php esc_html_e( 'Weapon-related moods (guarded, russian-roulette)', 'kohan-avatar' ); ?></label>
					<p class="description"><?php esc_html_e( 'Russian Roulette is a fictional, non-graphic camera-facing sequence. Disable to remove it and the guarded pose from idle/click pools.', 'kohan-avatar' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Chat panel', 'kohan-avatar' ); ?></th>
				<td>
					<label><input type="checkbox" name="kohan_avatar_options[chat]" value="1" <?php checked( $o['chat'], 1 ); ?>>
					<?php esc_html_e( 'Show a chat panel that opens beside the avatar (glowing assistant)', 'kohan-avatar' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Response-event mapping', 'kohan-avatar' ); ?></th>
				<td>
					<label><input type="checkbox" name="kohan_avatar_options[response_events]" value="1" <?php checked( $o['response_events'], 1 ); ?>>
					<?php esc_html_e( 'React to AI chat lifecycle (generating → MacBook, review → iPad, error → angry)', 'kohan-avatar' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Fire probability', 'kohan-avatar' ); ?></th>
				<td>
					<input type="number" step="0.05" min="0" max="1" name="kohan_avatar_options[fire_probability]" value="<?php echo esc_attr( $o['fire_probability'] ); ?>" style="width:5em">
					<p class="description"><?php esc_html_e( 'Chance the Russian Roulette branch ends on muzzle-flash (then scared → confused → idle).', 'kohan-avatar' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>

	<hr>
	<h2><?php esc_html_e( 'Assets &amp; Sync', 'kohan-avatar' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Deployed asset hash', 'kohan-avatar' ); ?></th>
			<td><code><?php echo $hash ? esc_html( $hash ) : esc_html__( '(run sync to generate)', 'kohan-avatar' ); ?></code></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Generated at', 'kohan-avatar' ); ?></th>
			<td><code><?php echo esc_html( isset( $info['generatedAt'] ) ? $info['generatedAt'] : '—' ); ?></code></td>
		</tr>
	</table>
	<p>
		<button type="button" class="button button-secondary" id="kohan-sync-btn"><?php esc_html_e( 'Sync / Refresh assets', 'kohan-avatar' ); ?></button>
		<span id="kohan-sync-status" style="margin-inline-start:8px"></span>
	</p>
	<pre id="kohan-sync-log" style="display:none;max-height:280px;overflow:auto;background:#111;color:#8fefb8;padding:12px;border-radius:8px"></pre>

	<script>
	(function () {
		var btn = document.getElementById('kohan-sync-btn');
		if (!btn) return;
		btn.addEventListener('click', function () {
			var status = document.getElementById('kohan-sync-status');
			var log = document.getElementById('kohan-sync-log');
			btn.disabled = true;
			status.textContent = <?php echo wp_json_encode( __( 'Syncing…', 'kohan-avatar' ) ); ?>;
			fetch(<?php echo wp_json_encode( esc_url_raw( rest_url( 'kohan-avatar/v1/sync' ) ) ); ?>, {
				method: 'POST',
				headers: { 'X-WP-Nonce': <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?> },
				credentials: 'same-origin'
			}).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
			.then(function (res) {
				btn.disabled = false;
				if (res.ok && res.j && res.j.ok) {
					status.textContent = <?php echo wp_json_encode( __( 'Done. Reload to see changes.', 'kohan-avatar' ) ); ?>;
				} else {
					status.textContent = (res.j && (res.j.message || (res.j.code))) || 'Error';
				}
				if (res.j && res.j.log) { log.style.display = 'block'; log.textContent = res.j.log; }
			}).catch(function (e) {
				btn.disabled = false;
				status.textContent = 'Error: ' + e;
			});
		});
	})();
	</script>

	<hr>
	<h2><?php esc_html_e( 'Voice &amp; TTS Services', 'kohan-avatar' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'API keys for voice/TTS services (e.g. a Whisper voice clone, OpenAI, ElevenLabs) are stored SERVER-SIDE only and are never sent to the browser. The front-end avatar receives only a boolean "configured" flag and calls a WordPress REST proxy route that uses the key here. If "Provider" is "webspeech" or empty, the browser uses the built-in Web Speech API (no key needed).', 'kohan-avatar' ); ?>
	</p>
	<form method="post" action="options.php">
		<?php settings_fields( 'kohan_avatar_tts_group' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable server voice', 'kohan-avatar' ); ?></th>
				<td>
					<label><input type="checkbox" name="kohan_avatar_tts[enabled]" value="1" <?php checked( $tts['enabled'], 1 ); ?>>
					<?php esc_html_e( 'Use this provider instead of the browser Web Speech API', 'kohan-avatar' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Provider', 'kohan-avatar' ); ?></th>
				<td>
					<select name="kohan_avatar_tts[provider]">
						<?php
						$providers = array(
							'webspeech'   => __( 'Browser Web Speech API (no key)', 'kohan-avatar' ),
							'openai'      => __( 'OpenAI TTS (tts-1 / tts-1-hd)', 'kohan-avatar' ),
							'elevenlabs'  => __( 'ElevenLabs (voice clone)', 'kohan-avatar' ),
							'whisper'     => __( 'Custom Whisper deployment', 'kohan-avatar' ),
							'custom'      => __( 'Custom HTTP endpoint (POST text → audio)', 'kohan-avatar' ),
						);
						foreach ( $providers as $val => $label ) {
							printf( '<option value="%s" %s>%s</option>', esc_attr( $val ), selected( $tts['provider'], $val, false ), esc_html( $label ) );
						}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="kohan_tts_endpoint"><?php esc_html_e( 'Endpoint URL', 'kohan-avatar' ); ?></label></th>
				<td>
					<input type="url" id="kohan_tts_endpoint" name="kohan_avatar_tts[endpoint]" class="regular-text" value="<?php echo esc_attr( $tts['endpoint'] ); ?>" placeholder="https://api.example.com/v1/audio/speech">
					<p class="description"><?php esc_html_e( 'The TTS endpoint the server will POST to (with the key) when the browser asks for speech.', 'kohan-avatar' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="kohan_tts_key"><?php esc_html_e( 'API key', 'kohan-avatar' ); ?></label></th>
				<td>
					<input type="password" id="kohan_tts_key" name="kohan_avatar_tts[api_key]" class="regular-text" value="" autocomplete="off" placeholder="<?php echo $tts_has_key ? esc_attr__( '•••••••• (key set, leave blank to keep)', 'kohan-avatar' ) : esc_attr__( 'Paste API key…', 'kohan-avatar' ); ?>">
					<?php if ( $tts_has_key ) : ?>
						<p class="description" style="color:#3f8a55"><?php esc_html_e( 'A key is stored (masked for security). It never reaches the browser.', 'kohan-avatar' ); ?></p>
						<label><input type="checkbox" name="kohan_avatar_tts[clear_key]" value="1"> <?php esc_html_e( 'Clear the stored key', 'kohan-avatar' ); ?></label>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Stored encrypted in the WP options table; rendered as dots here, never echoed to the front end.', 'kohan-avatar' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="kohan_tts_voice"><?php esc_html_e( 'Voice / model', 'kohan-avatar' ); ?></label></th>
				<td>
					<input type="text" id="kohan_tts_voice" name="kohan_avatar_tts[voice]" class="regular-text" value="<?php echo esc_attr( $tts['voice'] ); ?>" placeholder="tts-1 / eleven_multilingual_v2 / your-clone-id">
					<p class="description"><?php esc_html_e( 'Provider-specific voice or model identifier (sent to the provider, not the browser).', 'kohan-avatar' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Save TTS settings', 'kohan-avatar' ) ); ?>
	</form>
</div>
