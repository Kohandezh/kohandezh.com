<?php
/**
 * Local dev router for kohandezh.com.
 *
 * Usage:
 *   ZAI_API_KEY=xxxxx.xxxx php -S 127.0.0.1:8735 -t kohandezh.com dev-server.php
 *
 * Three jobs:
 *   1. POST /wp-json/kdcv/v1/ask  →  forwards to z.ai (glm-4.5-flash, free)
 *   2. GET  /*                     →  serves static files from kohandezh.com/
 *      HTML responses get a tiny <script> injected that sets window.KDCV_CONFIG.askUrl,
 *      so the widget's API-first path kicks in (no static file edits needed).
 *   3. Rate-limited per-IP, same as the production plugin (10 req/min).
 *
 * When you deploy: nothing in kohandezh.com/ changed; the live plugin provides
 * the same endpoint and the same KDCV_CONFIG injection via wp_localize_script.
 */

// ─── Config ────────────────────────────────────────────────────────────────
// Production hygiene: never let warnings leak into the JSON response.
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/kdcv-dev-php.log');

$ZAI_KEY     = getenv('ZAI_API_KEY') ?: '';
$ZAI_MODEL   = getenv('ZAI_MODEL')   ?: 'glm-4.5-flash';   // free-tier default
$ZAI_ENDPOINT = 'https://api.z.ai/api/paas/v4/chat/completions';
$STATIC_ROOT = __DIR__;
$RATE_PER_MIN = 10;
$MAX_QUESTION = 500;
$MAX_FACTS    = 80;
$MAX_FACT_LEN = 300;
$ALLOWED_LOCALES = ['en','fa','ar','de','es','fr','tr','zh','ja'];

/**
 * Mirror production's Content-Security-Policy locally.
 *
 * The policy is ENFORCED in .htaccess and in the theme's functions.php. The PHP
 * built-in server does not read .htaccess, so without this the one environment
 * where the site is actually exercised is the one environment where the policy
 * is absent — a broken policy would only surface after deploy. Set
 * KDCV_DEV_CSP=0 to switch it off while debugging something unrelated.
 */
if (getenv('KDCV_DEV_CSP') !== '0') {
	header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https://fontiran.com; font-src 'self' data:; media-src 'self'; connect-src 'self' https://api.web3forms.com; form-action 'self' https://api.web3forms.com; frame-src https://calendar.google.com https://www.aparat.com https://aparat.com; worker-src 'self'; manifest-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'");
}

// ─── Helpers ───────────────────────────────────────────────────────────────
function dbg($m) { error_log('[kdcv-dev] ' . $m); }

function send_json($obj, $status = 200) {
	http_response_code($status);
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-cache');
	echo json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

function read_body_json() {
	$raw = file_get_contents('php://input');
	$json = json_decode($raw, true);
	return is_array($json) ? $json : [];
}

function rate_check($ip) {
	global $RATE_PER_MIN;
	$bucket = '/tmp/kdcv_dev_rate_' . md5($ip);
	$now   = time();
	$window = 60;
	$data = [];
	if (is_file($bucket)) {
		$data = json_decode((string)file_get_contents($bucket), true) ?: [];
		// drop entries older than the window
		$data = array_values(array_filter($data, function ($t) use ($now, $window) {
			return ($now - $t) < $window;
		}));
	}
	if (count($data) >= $RATE_PER_MIN) return false;
	$data[] = $now;
	file_put_contents($bucket, json_encode($data));
	return true;
}

function build_facts_block($facts_in) {
	global $MAX_FACTS, $MAX_FACT_LEN;
	if (!is_array($facts_in)) return '';
	$out = [];
	foreach ($facts_in as $f) {
		if (!is_array($f)) continue;
		$title = isset($f['title']) ? trim(strip_tags((string)$f['title'])) : '';
		$body  = isset($f['body'])  ? trim(strip_tags((string)$f['body']))  : '';
		$title = mb_substr($title, 0, $MAX_FACT_LEN);
		$body  = mb_substr($body, 0, $MAX_FACT_LEN);
		if ($title === '' && $body === '') continue;
		$line = $title . ($body !== '' ? ' — ' . $body : '');
		$out[] = '• ' . $line;
		if (count($out) >= $MAX_FACTS) break;
	}
	return implode("\n", $out);
}

function build_system_prompt($locale) {
	$names = [
		'en' => 'English', 'fa' => 'Persian (Farsi)', 'ar' => 'Arabic',
		'de' => 'German',  'es' => 'Spanish',         'fr' => 'French',
		'tr' => 'Turkish', 'zh' => 'Simplified Chinese', 'ja' => 'Japanese',
	];
	$lang = $names[$locale] ?? 'English';
	return "You are the CV assistant embedded on kohandezh.com. " .
		"Answer the visitor question using ONLY the facts extracted from this page below. " .
		"Do not invent information. If the facts do not contain the answer, say briefly that you could not find it on this page. " .
		"Keep the answer concise (1 to 3 short sentences). " .
		"Reply in {$lang}. Do not use markdown, bullet lists, or code blocks.";
}

function handle_ask() {
	global $ZAI_KEY, $ZAI_MODEL, $ZAI_ENDPOINT, $MAX_QUESTION, $ALLOWED_LOCALES;

	if ($ZAI_KEY === '') {
		send_json(['available' => false, 'reason' => 'no ZAI_API_KEY env var'], 503);
	}
	$body     = read_body_json();
	$question = trim((string)($body['question'] ?? ''));
	$locale   = (string)($body['locale'] ?? 'en');
	$facts_in = $body['facts'] ?? [];

	if ($question === '' || mb_strlen($question) > $MAX_QUESTION) {
		send_json(['available' => false, 'reason' => 'bad-question'], 400);
	}
	if (!in_array($locale, $ALLOWED_LOCALES, true)) $locale = 'en';

	$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
	if (!rate_check($ip)) {
		send_json(['available' => false, 'reason' => 'rate-limited'], 429);
	}

	$facts_block = build_facts_block($facts_in);
	$system      = build_system_prompt($locale);
	$user        = "FACTS FROM THIS PAGE:\n" . ($facts_block !== '' ? $facts_block : '(none extracted)')
		. "\n\nQUESTION: " . $question;

	$payload = json_encode([
		'model'       => $ZAI_MODEL,
		'temperature' => 0.2,
		'max_tokens'  => 400,
		'messages'    => [
			['role' => 'system', 'content' => $system],
			['role' => 'user',   'content' => $user],
		],
	], JSON_UNESCAPED_UNICODE);

	$ch = curl_init($ZAI_ENDPOINT);
	curl_setopt_array($ch, [
		CURLOPT_POST           => true,
		CURLOPT_POSTFIELDS     => $payload,
		CURLOPT_HTTPHEADER     => [
			'Authorization: Bearer ' . $ZAI_KEY,
			'Content-Type: application/json',
		],
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 20,
	]);
	$resp   = curl_exec($ch);
	$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$err    = curl_error($ch);
	// curl_close() is a no-op since PHP 8.0 and deprecated in 8.5 — skip it.

	if ($resp === false) {
		dbg("transport error: $err");
		send_json(['available' => false, 'reason' => 'transport_error'], 502);
	}
	if ($status !== 200) {
		dbg("upstream $status: " . substr((string)$resp, 0, 300));
		send_json(['available' => false, 'reason' => 'upstream_' . $status, 'upstream' => substr((string)$resp, 0, 300)], 502);
	}

	$decoded = json_decode((string)$resp, true);
	$answer  = trim((string)($decoded['choices'][0]['message']['content'] ?? ''));
	if ($answer === '') {
		send_json(['available' => false, 'reason' => 'empty-answer'], 502);
	}

	send_json(['available' => true, 'answer' => $answer, 'model' => $ZAI_MODEL], 200);
}

// ─── Routing ───────────────────────────────────────────────────────────────
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 1. REST endpoint
if ($method === 'POST' && $uri === '/wp-json/kdcv/v1/ask') {
	handle_ask();   // exits
}
if ($method === 'OPTIONS' && $uri === '/wp-json/kdcv/v1/ask') {
	// CORS preflight for cross-origin widget testing
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Methods: POST, OPTIONS');
	header('Access-Control-Allow-Headers: Content-Type');
	http_response_code(204);
	exit;
}

// 2. Let PHP's built-in server handle static assets (returns false → serve file).
$path = $STATIC_ROOT . $uri;
if (preg_match('/\.(?:png|jpe?g|webp|gif|svg|css|js|woff2?|ttf|otf|eot|mp[34]|pdf|ico|txt|xml|map)$/i', $uri)) {
	if (is_file($path)) return false;  // serve as-is
	http_response_code(404);
	exit;
}

// 3. HTML pages: serve but inject KDCV_CONFIG right after <head>.
if (is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'html') {
	$html = file_get_contents($path);
	$inject = '<script>window.KDCV_CONFIG = window.KDCV_CONFIG || {}; '
		. 'window.KDCV_CONFIG.askUrl = "/wp-json/kdcv/v1/ask";</script>';
	// KDCV_DEV_TRACE=1 records load-time errors with their real source.
	// window.onerror only reports 'Script error.' for cross-origin files,
	// but everything here is same-origin, so filename+line survive.
	if (getenv('KDCV_DEV_TRACE') === '1') {
		$inject .= '<script>window.__KDCV_ERRS=[];window.addEventListener("error",function(e){'
			. 'window.__KDCV_ERRS.push({m:e.message,f:e.filename,l:e.lineno,c:e.colno,'
			. 't:(e.target&&e.target.tagName)||null,s:(e.target&&(e.target.src||e.target.href))||null});'
			. '},true);</script>';
	}
	// Inject right after the first <head> tag.
	$html = preg_replace('/<head[^>]*>/i', '$0' . $inject, $html, 1);
	header('Content-Type: text/html; charset=utf-8');
	echo $html;
	exit;
}

// Directory → index.html
if (is_dir($path)) {
	$candidate = rtrim($path, '/') . '/index.html';
	if (is_file($candidate)) {
		$html = file_get_contents($candidate);
		$inject = '<script>window.KDCV_CONFIG = window.KDCV_CONFIG || {}; '
			. 'window.KDCV_CONFIG.askUrl = "/wp-json/kdcv/v1/ask";</script>';
		$html = preg_replace('/<head[^>]*>/i', '$0' . $inject, $html, 1);
		header('Content-Type: text/html; charset=utf-8');
		echo $html;
		exit;
	}
}

// 4. Fallback: serve the static file if it exists, otherwise 404.
//    Never let PHP's built-in server guess — it can fall through to
//    index.html for non-existent files, which is bad for SEO and confusing.
if (is_file($path)) {
	return false;  // let PHP serve it natively
}

// 5. 404: serve the arcade page so a missing route still gets the random-game
//    experience, but keep the real HTTP 404 status so bots/tools see "not found".
//    404.html's JS (404-games.min.js) picks a random game each load via
//    ?game= / data-game="random" / sessionStorage anti-repeat logic.
http_response_code(404);
$arcade = __DIR__ . '/404.html';
if (is_file($arcade)) {
	header('Content-Type: text/html; charset=utf-8');
	readfile($arcade);
} else {
	header('Content-Type: text/plain; charset=utf-8');
	echo "404 Not Found\n";
}
exit;
