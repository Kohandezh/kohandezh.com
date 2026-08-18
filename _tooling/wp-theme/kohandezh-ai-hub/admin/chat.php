<?php
/**
 * Neutralise spreadsheet formula injection. A visitor question beginning with
 * = + - @ (or tab/CR) is executed as a formula when the export is opened in
 * Excel or Sheets, so the cell is prefixed with an apostrophe. The visible
 * text is unchanged for a reader.
 */
function kdcv_csv_safe( $v ) {
	$v = (string) $v;
	return ( $v !== '' && strpbrk( $v[0], "=+-@\t\r" ) !== false ) ? "'" . $v : $v;
}

/**
 * AI Chat admin page.
 *
 * Top-level menu item under the AI Hub group. Two halves:
 *   1. Live chatbox that reuses the same public REST endpoint (so the admin
 *      playground exercises the exact code path visitors hit).
 *   2. History table with Export CSV / Export JSON / Clear All.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	$hook = add_menu_page(
		'Kohandezh AI Hub',
		'AI Hub',
		'manage_options',
		'kdcv-ai-chat',
		'kdcv_ai_chat_render',
		'dashicons-format-chat',
		26
	);
	add_action( 'admin_print_styles-' . $hook, 'kdcv_ai_chat_assets' );
} );

function kdcv_ai_chat_assets() {
	?>
	<style>
		.kdcv-ask-wrap { max-width: 1280px; }
		.kdcv-ask-cols { display: grid; grid-template-columns: 400px 1fr; gap: 24px; align-items: start; }
		@media (max-width: 1100px) { .kdcv-ask-cols { grid-template-columns: 1fr; } }
		.kdcv-chat-card,
		.kdcv-history-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; padding: 16px; }
		.kdcv-chat-log {
			min-height: 260px; max-height: 460px; overflow-y: auto;
			background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px;
			padding: 12px; margin: 10px 0; font-size: 13px; line-height: 1.55;
		}
		.kdcv-chat-line { margin: 0 0 10px; white-space: pre-wrap; word-wrap: break-word; }
		.kdcv-chat-line.q { color: #1d2327; }
		.kdcv-chat-line.a { color: #046331; }
		.kdcv-chat-line .role { font-weight: 600; margin-inline-end: 6px; }
		.kdcv-chat-line.err { color: #b32d2e; }
		.kdcv-chat-form label { font-weight: 500; font-size: 12.5px; display: block; margin: 8px 0 2px; }
		.kdcv-chat-form textarea,
		.kdcv-chat-form input[type="text"],
		.kdcv-chat-form select { width: 100%; margin-bottom: 4px; }
		.kdcv-history-actions { display: flex; gap: 8px; flex-wrap: wrap; margin: 0 0 12px; }
		.kdcv-history-actions form { margin: 0; }
		.kdcv-pet-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
		.kdcv-pet-table th, .kdcv-pet-table td { padding: 8px 10px; border-bottom: 1px solid #dcdcde; text-align: start; vertical-align: top; }
		.kdcv-pet-table thead th { background: #f0f0f1; position: sticky; top: 0; }
		.kdcv-pet-table .col-q, .kdcv-pet-table .col-a { max-width: 360px; }
		.kdcv-pet-table td.col-q, .kdcv-pet-table td.col-a { white-space: pre-wrap; word-wrap: break-word; }
		.kdcv-badge { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
		.kdcv-badge.visitor { background: #e7f0fa; color: #1d4fc1; }
		.kdcv-badge.admin   { background: #fff8e5; color: #8a5a00; }
		.kdcv-badge.ok      { background: #e6f4ea; color: #137333; }
		.kdcv-badge.bad     { background: #fce8e6; color: #b32d2e; }
		.kdcv-pager { margin-top: 12px; display: flex; gap: 8px; align-items: center; }
		.kdcv-empty { color: #666; font-style: italic; }
	</style>
	<?php
}

function kdcv_ai_chat_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Insufficient permissions.' );
	}

	global $wpdb;
	$table = $wpdb->prefix . KDCV_AI_TABLE;

	// Bulk actions.
	if ( isset( $_POST['kdcv_action'] ) && check_admin_referer( 'kdcv_ai_bulk' ) ) {
		$action = sanitize_text_field( wp_unslash( $_POST['kdcv_action'] ) );
		if ( $action === 'clear' ) {
			KDCV_AI_Logger::truncate();
			echo '<div class="notice notice-success is-dismissible"><p>Chat history cleared.</p></div>';
		} elseif ( $action === 'export_csv' ) {
			kdcv_ai_chat_export_csv();
			exit;
		} elseif ( $action === 'export_json' ) {
			kdcv_ai_chat_export_json();
			exit;
		}
	}

	$per_page = 50;
	$paged    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
	$offset   = ( $paged - 1 ) * $per_page;
	$total    = KDCV_AI_Logger::count_all();
	$rows     = KDCV_AI_Logger::get_page( $per_page, $offset );

	$rest_url = esc_url_raw( rest_url( 'kdcv/v1/ask' ) );
	$locale   = function_exists( 'get_user_locale' ) ? get_user_locale() : 'en';
	$providers = KDCV_AI_Provider::all_for_ui();
	$settings  = get_option( 'kdcv_ai_settings', array() );
	$any_ready = ! empty( KDCV_AI_Provider::ready_ids() );
	?>
	<div class="wrap kdcv-ask-wrap">
		<h1>Kohandezh AI Hub <small style="font-weight:400;color:#666;">— multi-provider chat proxy</small></h1>

		<?php if ( ! $any_ready ) : ?>
			<div class="notice notice-error">
				<p><strong>No provider has an API key configured.</strong>
				Go to <a href="<?php echo esc_url( admin_url( 'admin.php?page=kdcv-ai-settings' ) ); ?>">AI Hub → Settings</a>
				and add at least one key.</p>
			</div>
		<?php endif; ?>

		<div class="kdcv-ask-cols">
			<div class="kdcv-chat-card">
				<h2 style="margin-top:0;">Try the assistant</h2>
				<form class="kdcv-chat-form" id="kdcv-ask-test" autocomplete="off">
					<label for="kdcv-ask-provider">Provider</label>
					<select id="kdcv-ask-provider" name="provider">
						<option value="">(site default)</option>
						<?php foreach ( $providers as $id => $meta ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $meta['label'] ); ?><?php echo $meta['ready'] ? '' : ' — no key'; ?></option>
						<?php endforeach; ?>
					</select>

					<label for="kdcv-ask-locale">Answer language</label>
					<select id="kdcv-ask-locale" name="locale">
						<?php
						$langs = array(
							'en' => 'English', 'fa' => 'Persian', 'ar' => 'Arabic', 'de' => 'German',
							'es' => 'Spanish', 'fr' => 'French', 'tr' => 'Turkish',
							'zh' => 'Chinese', 'ja' => 'Japanese',
						);
						foreach ( $langs as $code => $name ) {
							$sel = ( $code === $locale ) ? ' selected' : '';
							echo '<option value="' . esc_attr( $code ) . '"' . $sel . '>' . esc_html( $name ) . "</option>\n";
						}
						?>
					</select>

					<label for="kdcv-ask-facts">CV facts (optional, one per line)</label>
					<textarea id="kdcv-ask-facts" name="facts" rows="5" placeholder="• CEO of KSF&#10;• 12 years building AI products&#10;• Microsoft certified…"></textarea>

					<label for="kdcv-ask-question">Question</label>
					<input type="text" id="kdcv-ask-question" name="question" placeholder="Ask something…" maxlength="500" />

					<?php submit_button( 'Ask', 'primary', 'kdcv-ask-submit', false ); ?>
				</form>

				<div class="kdcv-chat-log" id="kdcv-ask-log" aria-live="polite">
					<div class="kdcv-chat-line kdcv-empty">Answers from the live proxy will appear here.</div>
				</div>

				<p class="description">
					Want to change keys or the default provider?
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=kdcv-ai-settings' ) ); ?>">Open Settings →</a>
				</p>
			</div>

			<div class="kdcv-history-card">
				<h2 style="margin-top:0;">History <span class="kdcv-badge ok"><?php echo esc_html( number_format_i18n( $total ) ); ?> total</span></h2>

				<div class="kdcv-history-actions">
					<form method="post" style="display:inline;">
						<?php wp_nonce_field( 'kdcv_ai_bulk' ); ?>
						<input type="hidden" name="kdcv_action" value="export_csv" />
						<?php submit_button( 'Export CSV', 'secondary', 'kdcv-export-csv', false ); ?>
					</form>
					<form method="post" style="display:inline;">
						<?php wp_nonce_field( 'kdcv_ai_bulk' ); ?>
						<input type="hidden" name="kdcv_action" value="export_json" />
						<?php submit_button( 'Export JSON', 'secondary', 'kdcv-export-json', false ); ?>
					</form>
					<form method="post" style="display:inline;" onsubmit="return confirm('Delete ALL chat history? This cannot be undone.');">
						<?php wp_nonce_field( 'kdcv_ai_bulk' ); ?>
						<input type="hidden" name="kdcv_action" value="clear" />
						<?php submit_button( 'Clear All', 'delete', 'kdcv-clear', false ); ?>
					</form>
				</div>

				<?php if ( empty( $rows ) ) : ?>
					<p class="kdcv-empty">No conversations logged yet.</p>
				<?php else : ?>
					<table class="kdcv-pet-table">
						<thead>
							<tr>
								<th>Time (UTC)</th>
								<th>Source</th>
								<th>Provider</th>
								<th>Model</th>
								<th>Locale</th>
								<th>Status</th>
								<th class="col-q">Question</th>
								<th class="col-a">Answer</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $r ) : ?>
								<?php $is_ok = ( $r->status === 'ok' ); ?>
								<tr>
									<td><?php echo esc_html( $r->created_at ); ?></td>
									<td><span class="kdcv-badge <?php echo $r->source === 'admin' ? 'admin' : 'visitor'; ?>"><?php echo esc_html( $r->source ); ?></span></td>
									<td><?php echo esc_html( $r->provider ?: '—' ); ?></td>
									<td><code><?php echo esc_html( $r->model ?: '—' ); ?></code></td>
									<td><?php echo esc_html( $r->locale ); ?></td>
									<td><span class="kdcv-badge <?php echo $is_ok ? 'ok' : 'bad'; ?>"><?php echo esc_html( $r->status ); ?></span></td>
									<td class="col-q"><?php echo esc_html( $r->question ); ?></td>
									<td class="col-a"><?php echo esc_html( $r->answer ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php
					$pages = max( 1, (int) ceil( $total / $per_page ) );
					if ( $pages > 1 ) :
						?>
						<div class="kdcv-pager">
							<?php
							echo esc_html( "Page {$paged} of {$pages}" );
							if ( $paged > 1 ) {
								echo ' <a class="button" href="' . esc_url( add_query_arg( 'paged', $paged - 1 ) ) . '">← Prev</a>';
							}
							if ( $paged < $pages ) {
								echo ' <a class="button" href="' . esc_url( add_query_arg( 'paged', $paged + 1 ) ) . '">Next →</a>';
							}
							?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<script>
	(function () {
		var form     = document.getElementById('kdcv-ask-test');
		var logEl    = document.getElementById('kdcv-ask-log');
		var qInput   = document.getElementById('kdcv-ask-question');
		var factsEl  = document.getElementById('kdcv-ask-facts');
		var locEl    = document.getElementById('kdcv-ask-locale');
		var provEl   = document.getElementById('kdcv-ask-provider');
		var endpoint = <?php echo wp_json_encode( $rest_url ); ?>;
		var emptyLine = logEl ? logEl.querySelector('.kdcv-empty') : null;

		function clearEmpty() { if (emptyLine) { emptyLine.parentNode.removeChild(emptyLine); emptyLine = null; } }
		function addLine(role, text, cls) {
			clearEmpty();
			var row = document.createElement('div');
			row.className = 'kdcv-chat-line ' + (cls || role);
			var label = document.createElement('span');
			label.className = 'role';
			label.textContent = role === 'q' ? 'You:' : 'AI:';
			row.appendChild(label);
			row.appendChild(document.createTextNode(text));
			logEl.appendChild(row);
			logEl.scrollTop = logEl.scrollHeight;
		}
		function addTyping() {
			clearEmpty();
			var row = document.createElement('div');
			row.className = 'kdcv-chat-line a';
			row.id = 'kdcv-ask-typing';
			row.textContent = '…';
			logEl.appendChild(row);
			logEl.scrollTop = logEl.scrollHeight;
		}
		function removeTyping() {
			var t = document.getElementById('kdcv-ask-typing');
			if (t) t.parentNode.removeChild(t);
		}

		if (form) {
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				var question = (qInput.value || '').trim();
				if (!question) { qInput.focus(); return; }
				addLine('q', question);
				qInput.value = '';
				addTyping();

				var facts = (factsEl.value || '')
					.split(/\r?\n/)
					.map(function (l) { return l.replace(/^\s*•\s*/, '').trim(); })
					.filter(Boolean)
					.map(function (line) { return { title: line, body: '' }; });

				var body = {
					question: question,
					locale:   locEl.value || 'en',
					source:   'admin',
					facts:    facts
				};
				if (provEl.value) body.provider = provEl.value;

				fetch(endpoint, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					credentials: 'same-origin',
					body: JSON.stringify(body)
				}).then(function (r) {
					return r.json().then(function (j) { return { ok: r.ok, json: j }; });
				}).then(function (out) {
					removeTyping();
					if (out.ok && out.json && out.json.available && out.json.answer) {
						addLine('a', out.json.answer);
					} else {
						var reason = (out.json && out.json.reason) || ('HTTP ' + (out.ok ? 'ok' : 'error'));
						addLine('a', 'No answer (' + reason + ').', 'err');
					}
				}).catch(function (err) {
					removeTyping();
					addLine('a', 'Request failed: ' + (err && err.message ? err.message : err), 'err');
				});
			});
		}
	})();
	</script>
	<?php
}

function kdcv_ai_chat_export_csv() {
	$rows = KDCV_AI_Logger::fetch_all_for_export();
	$filename = 'kohandezh-ai-chat-' . gmdate( 'Ymd-His' ) . '.csv';
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Cache-Control: no-cache' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM for Excel
	fputcsv( $out, array( 'created_at_utc', 'source', 'provider', 'model', 'locale', 'status', 'ip_hash', 'question', 'answer' ) );
	foreach ( $rows as $r ) {
		fputcsv( $out, array_map( 'kdcv_csv_safe', array(
			$r->created_at, $r->source, $r->provider, $r->model, $r->locale,
			$r->status, $r->ip_hash, $r->question, $r->answer,
		) ) );
	}
	fclose( $out );
}

function kdcv_ai_chat_export_json() {
	$rows = KDCV_AI_Logger::fetch_all_for_export();
	$filename = 'kohandezh-ai-chat-' . gmdate( 'Ymd-His' ) . '.json';
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Cache-Control: no-cache' );
	$clean = array_map( function ( $r ) {
		return array(
			'created_at_utc' => $r->created_at,
			'source'         => $r->source,
			'provider'       => $r->provider,
			'model'          => $r->model,
			'locale'         => $r->locale,
			'status'         => $r->status,
			'ip_hash'        => $r->ip_hash,
			'question'       => $r->question,
			'answer'         => $r->answer,
		);
	}, $rows );
	echo wp_json_encode( array(
		'exported_at_utc' => gmdate( 'Y-m-d H:i:s' ),
		'count'           => count( $clean ),
		'rows'            => $clean,
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}
