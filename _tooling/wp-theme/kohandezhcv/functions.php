<?php
/**
 * KohandezhCV theme setup.
 *
 * The CV templates are self-contained HTML pages (they carry their own <head>),
 * so this file stays intentionally small: asset base URL, thumbnails, and
 * versioned page creation/migrations for both fresh installs and updates.
 */

define( 'KDCV', get_template_directory_uri() );
define( 'KDCV_CONTENT_SCHEMA_VERSION', '1.8.0' ); // bump: llms.txt canonical-slash + clean 404

add_action( 'after_setup_theme', function () {
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
} );

/**
 * Hardening: this theme has no remote-publishing, pingback, or third-party
 * REST consumer that needs any of the below. Trims WP/theme fingerprinting
 * and closes off unused surface without touching any real functionality.
 */
// Disables the wp-admin theme/plugin file editor. A common pivot point after
// any admin-account compromise (edit a theme file in the browser -> webshell);
// nothing in this workflow needs it, editing happens on disk.
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

add_filter( 'xmlrpc_enabled', '__return_false' );
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'template_redirect', 'rest_output_link_header', 11 );

// REST API user enumeration (/wp-json/wp/v2/users) leaks usernames — block
// it for unauthenticated requests; logged-in admin use is unaffected.
add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		unset( $endpoints['/wp/v2/users'] );
		unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}
	return $endpoints;
} );

// Old-style ?author=N URLs also enumerate usernames via redirect target.
add_action( 'template_redirect', function () {
	if ( is_author() && ! is_user_logged_in() ) {
		wp_die( 'Not found.', 404 );
	}
} );

/**
 * Legacy URL cleanup (added 2026-08).
 *
 * Before this site became a personal CV portfolio, the domain ran a generic
 * marketing/agency template (2016-2018): shop, cart, affiliate-marketing,
 * SEO services, BuddyPress community pages, music/video gallery, etc. Those
 * ~60 pages were still in the WP database and being indexed by Google,
 * diluting the personal brand for anyone searching "Mohammad Ali Kohandezh".
 *
 * This block 301-redirects every legacy slug to the homepage (or its modern
 * equivalent). Link juice is preserved, the URLs drop out of the index within
 * weeks, and a recruiter landing on an old cached link ends up on the real
 * portfolio. Safe to remove this whole block once Search Console shows zero
 * impressions for these paths (typically 3-6 months).
 */
add_action( 'template_redirect', function () {
	if ( is_admin() ) {
		return;
	}

	$request = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
	if ( $request === '' ) {
		return;
	}

	// Strip query string, trailing slash, decode %xx.
	$path = rawurldecode( strtok( $request, '?' ) );
	$path = untrailingslashit( $path );

	// 1) Static-only filenames -> their WP page equivalents.
	$static_to_wp = array(
		'/Certificates.html' => '/certificates/',
		'/PSN.html'          => '/psn/',
	);
	if ( isset( $static_to_wp[ $path ] ) ) {
		wp_redirect( home_url( $static_to_wp[ $path ] ), 301 );
		exit;
	}

	// 2a) Legacy slugs that have a SPECIFIC modern destination.
	$legacy_specific = array(
		'/contact'                       => '/#contact',
		'/resume'                        => '/',
		'/mohammad-kohandezh-resume'     => '/',
		'/services'                      => '/#service',
		'/logout'                        => '/',
	);
	if ( isset( $legacy_specific[ $path ] ) ) {
		wp_redirect( home_url( $legacy_specific[ $path ] ), 301 );
		exit;
	}

	// 2b) Legacy slugs that map to the homepage / #service anchor.
	// Exact-match only (no subpaths) — see $legacy_prefix below for trees.
	$legacy = array(
		// BuddyPress community pages (never used by current site).
		'/connections', '/members', '/following', '/followers',
		// WooCommerce shop (no shop exists).
		'/shop', '/cart', '/checkout', '/my-account',
		// Marketing/agency service pages (2016).
		'/search-engine-optimization', '/local-business-marketing',
		'/social-media-marketing', '/email-marketing', '/pay-per-click',
		'/content-marketing', '/mobile-marketing', '/digital-consultancy',
		'/reputation-management', '/affiliate-marketing',
		'/online-presence-analysis', '/conversion-rate-optimization',
		'/ecommerce-marketing', '/digital-marketing',
		'/instagram-advertising', '/telegram-advertising',
		// Old tech service pages (replaced by #service sections on homepage).
		'/veritas', '/symantec', '/microsoft', '/website-design',
		'/web-design', '/hosting-service', '/easy-app', '/home-1',
		'/backup-solution', '/backup-exec', '/veeam-backup',
		'/virtualization', '/vmware-solutions', '/microsoft-solutions',
		'/network', '/citrix-solutions', '/learning', '/security',
		'/endpoint-protection', '/anti-virus', '/penetration-testing',
		'/network-penetration', '/web-application-penetration',
		'/wireless-penetration', '/social-engineering', '/graphic',
		'/web-application', '/mobile-application', '/ios', '/android',
		'/programming', '/it-support',
		// Old personal/music pages (replaced by current portfolio).
		'/music', '/video', '/music-video', '/photos',
	);

	if ( in_array( $path, $legacy, true ) ) {
		wp_redirect( home_url( '/#service' ), 301 );
		exit;
	}

	// 2c) Prefix matches: redirect whole subtrees (e.g. /profile, /profile/edit,
	//     /profile/login, /profile/register, /members/anything). These are
	//     BuddyPress/legacy trees where every subpath is also stale.
	$legacy_prefix = array(
		'/profile',
	);
	foreach ( $legacy_prefix as $prefix ) {
		if ( $path === $prefix || strpos( $path, $prefix . '/' ) === 0 ) {
			wp_redirect( home_url( '/' ), 301 );
			exit;
		}
	}
}, 1 );

/**
 * Remove legacy pages from wp-sitemap.xml so they aren't advertised to
 * crawlers. Pairs with the 301 redirects above — redirects handle any
 * visitor who actually lands on the URL; this stops Google from even
 * trying to crawl them.
 */
add_filter( 'wp_sitemaps_posts_query_args', function ( $args ) {
	if ( ! isset( $args['post_type'] ) || ! in_array( 'page', (array) $args['post_type'], true ) ) {
		return $args;
	}

	$legacy_slugs = array(
		'connections', 'members', 'following', 'followers',
		'shop', 'cart', 'checkout', 'my-account',
		'search-engine-optimization', 'local-business-marketing',
		'social-media-marketing', 'email-marketing', 'pay-per-click',
		'content-marketing', 'mobile-marketing', 'digital-consultancy',
		'reputation-management', 'affiliate-marketing',
		'online-presence-analysis', 'conversion-rate-optimization',
		'ecommerce-marketing', 'digital-marketing',
		'instagram-advertising', 'telegram-advertising',
		'veritas', 'symantec', 'microsoft', 'website-design',
		'web-design', 'hosting-service', 'easy-app', 'home-1',
		'backup-solution', 'backup-exec', 'veeam-backup',
		'virtualization', 'vmware-solutions', 'microsoft-solutions',
		'network', 'citrix-solutions', 'learning', 'security',
		'endpoint-protection', 'anti-virus', 'penetration-testing',
		'network-penetration', 'web-application-penetration',
		'wireless-penetration', 'social-engineering', 'graphic',
		'web-application', 'mobile-application', 'ios', 'android',
		'programming', 'it-support',
		'contact', 'resume', 'mohammad-kohandezh-resume',
		'music', 'video', 'music-video', 'photos', 'logout',
		// profile/* are pages too — exclude them all. Slug is 'profile' for /profile.
		'profile',
	);

	$args['post_name__not_in'] = isset( $args['post_name__not_in'] )
		? array_merge( (array) $args['post_name__not_in'], $legacy_slugs )
		: $legacy_slugs;

	return $args;
} );

/**
 * Serve llms.txt and fa-llms.txt directly from the theme, so WordPress
 * picks them up without requiring a manual FTP upload.
 *
 * llms.txt is the de-facto standard for LLM crawlers (GPTBot, ClaudeBot,
 * PerplexityBot) to get a structured summary of the site.
 *
 * There is now one per language, not just English and Persian. An answer
 * engine responding in German or Japanese was previously offered only an
 * English summary, so the entity facts it quoted came back in the wrong
 * language — the nine CV pages were translated but the machine-readable
 * layer above them was not. All nine are generated from one table by
 * _tooling/gen-llms-txt.py, so they cannot drift apart as the CV changes.
 *
 * The static source files live at:
 *   kohandezh.com/llms.txt        (English — primary)
 *   kohandezh.com/{fa,ar,de,es,fr,tr,zh,ja}-llms.txt
 *
 * On a static deploy these are served as files. On WordPress, we add a
 * rewrite rule + template_redirect handler that streams them with the
 * correct text/plain content-type, so they work without uploading.
 */
add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'kdcv_llms';
	return $vars;
} );

add_action( 'init', function () {
	add_rewrite_rule( '^llms\.txt$', 'index.php?kdcv_llms=llms', 'top' );
	// One rule for the eight localized files; the locale is captured, and the
	// handler below only accepts codes from the allow-list, so the query var
	// can never be used to read an arbitrary path.
	add_rewrite_rule( '^(fa|ar|de|es|fr|tr|zh|ja)-llms\.txt$', 'index.php?kdcv_llms=$matches[1]-llms', 'top' );
} );

/**
 * Stop WordPress appending a trailing slash to the llms.txt URLs.
 *
 * redirect_canonical adds one because the permalink structure ends in "/", so
 * /de-llms.txt answered 301 -> /de-llms.txt/ -> 200. It worked, but robots.txt
 * and the <link rel="alternate"> tags on all nine CV pages advertise the
 * slash-less form, so every crawler paid a redirect hop to reach a file — and
 * a .txt URL ending in a slash is malformed-looking to anything parsing it.
 */
add_filter( 'redirect_canonical', function ( $redirect ) {
	return get_query_var( 'kdcv_llms' ) ? false : $redirect;
} );

add_action( 'template_redirect', function () {
	$which = get_query_var( 'kdcv_llms' );
	if ( ! $which ) {
		return;
	}

	// Allow-list, not a pattern: $which comes from a query var, and the value
	// is about to become part of a filesystem path. Anything not on this list
	// is a 404 before it can touch the disk.
	$allowed = array(
		'llms', 'fa-llms', 'ar-llms', 'de-llms', 'es-llms',
		'fr-llms', 'tr-llms', 'zh-llms', 'ja-llms',
	);
	if ( ! in_array( $which, $allowed, true ) ) {
		// Plain 404, not wp_die(): calling wp_die() here after status_header()
		// surfaced as a 500 rather than a 404 for an unknown locale code.
		status_header( 404 );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo "Not found.\n";
		exit;
	}

	// ABSPATH, not KDCV. KDCV is get_template_directory_uri() — a URL — and
	// file_exists() on "https://…" is always false, so this branch never fired
	// and the documented "a file uploaded to the site root wins" behaviour did
	// not exist. ABSPATH is the filesystem path to the WordPress root.
	$root    = ABSPATH . $which . '.txt';
	$bundled = get_template_directory() . '/' . $which . '.txt';

	$path = file_exists( $root ) ? $root : ( file_exists( $bundled ) ? $bundled : '' );

	if ( $path === '' || ! is_readable( $path ) ) {
		status_header( 404 );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo "Not found.\n";
		exit;
	}

	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Cache-Control: public, max-age=3600' );
	readfile( $path );
	exit;
} );

/**
 * Flush rewrites on theme activation so /llms.txt and /fa-llms.txt resolve.
 * Existing installs pick up the rules on the next admin_init migration
 * (see KDCV_CONTENT_SCHEMA_VERSION bump below).
 */
add_filter( 'wpseo_canonical', function ( $canonical ) {
	$cv_pages = array( 'fa', 'ar', 'de', 'es', 'fr', 'tr', 'zh', 'ja', 'psn', 'certificates', 'portfolio' );
	if ( is_front_page() || is_page( $cv_pages ) ) {
		return false; // Yoast treats false as "skip this meta tag".
	}
	return $canonical;
} );

/**
 * ============================================================
 *  Geo-IP language routing (P1, added 2026-08)
 * ============================================================
 *
 * Goal: a visitor from France lands on /fr/, a visitor from Iran on /fa/,
 * Germany -> /de/, Japan -> /ja/, etc. — automatically, by IP, not by
 * browser language (an Iranian developer using Chrome in English still
 * wants the Persian page).
 *
 * Fallback chain (highest priority first):
 *   1. ?lang=xx URL parameter         — always wins, sets cookie
 *   2. kdcv_lang cookie               — user previously chose
 *   3. Server-side GeoIP lookup       — IP -> country -> language
 *   4. (Client-side) browser language — handled by locale-router.js
 *   5. Default English                — canonical /
 *
 * SEO note: bots (Googlebot, GPTBot, ClaudeBot, PerplexityBot, …) are
 * detected by UA and NEVER geo-redirected. They see the English canonical
 * page at /, which is what we want indexed.
 *
 * The redirect uses 302 (temporary) so the user can override it by
 * clicking a language link, which sets the cookie. We also set a cookie
 * after the first geo-redirect so the same IP doesn't get re-routed on
 * every visit.
 *
 * GeoIP source priority:
 *   - Cloudflare HTTP_CF_IPCOUNTRY (free, instant — if CF proxy added later)
 *   - LiteSpeed/Apache GEOIP_COUNTRY_CODE (if host has MaxMind extension)
 *   - ip-api.com free HTTP API (fallback; cached 24h per IP via transient)
 */

/**
 * Map ISO-3166 country code -> site language code.
 * Returns '' for countries that should see English (the default).
 */
function kdcv_country_to_lang( $country ) {
	$country = strtoupper( (string) $country );
	if ( $country === '' ) {
		return '';
	}

	// Persian (fa): Iran, Afghanistan (Dari), Tajikistan (Tajik is written
	// in Cyrillic but the spoken language is mutually intelligible — leave
	// Tajikistan on English to avoid showing Cyrillic-fa confusion).
	$fa = array( 'IR', 'AF' );

	// Arabic (ar): MENA region. Some North African countries speak French
	// as a second language; we route them to Arabic since it's the mother
	// tongue and the portfolio is for an Iranian professional who likely
	// works with Arabic-speaking markets.
	$ar = array( 'SA', 'AE', 'EG', 'QA', 'KW', 'BH', 'OM', 'JO', 'LB', 'SY',
		'IQ', 'YE', 'PS', 'MA', 'DZ', 'TN', 'LY', 'SD', 'MR', 'DJ', 'SO', 'KM' );

	// German (de): DACH region plus Liechtenstein and small German-speaking
	// enclaves in Italy (South Tyrol) and Belgium.
	$de = array( 'DE', 'AT', 'CH', 'LI', 'LU' );

	// French (fr): Francophone Europe + parts of Africa + Quebec.
	// Note: rest of Canada stays on English (Quebec-specific would need
	// region-level GeoIP we don't have at country granularity).
	$fr = array( 'FR', 'BE', 'MC', 'AD', 'PF', 'NC', 'RE', 'MQ', 'GP', 'GF',
		'YT', 'BL', 'MF', 'PM', 'WF', 'BI', 'BJ', 'BF', 'CF', 'TD', 'KM',
		'CG', 'CI', 'GA', 'GN', 'ML', 'NE', 'CD', 'SN', 'TG', 'HT', 'MG' );

	// Spanish (es): Spain + Latin America + Equatorial Guinea.
	$es = array( 'ES', 'MX', 'AR', 'CO', 'CL', 'PE', 'VE', 'EC', 'GT', 'CU',
		'BO', 'DO', 'HN', 'PY', 'SV', 'NI', 'CR', 'PA', 'UY', 'PR', 'GQ' );

	// Turkish (tr): Turkey + Northern Cyprus.
	$tr = array( 'TR', 'CY' );

	// Chinese (zh): PRC, Hong Kong, Macao, Taiwan, Singapore.
	// (Taiwan uses Traditional Chinese; site zh-Hans is Simplified. Still
	// a closer match than English for a zh-Hant reader.)
	$zh = array( 'CN', 'HK', 'MO', 'TW', 'SG' );

	// Japanese (ja): Japan only.
	$ja = array( 'JP' );

	$maps = array(
		'fa' => $fa, 'ar' => $ar, 'de' => $de, 'fr' => $fr,
		'es' => $es, 'tr' => $tr, 'zh' => $zh, 'ja' => $ja,
	);
	foreach ( $maps as $lang => $countries ) {
		if ( in_array( $country, $countries, true ) ) {
			return $lang;
		}
	}
	return '';
}

/**
 * Resolve the visitor's country code via the cheapest available source.
 * Returns ISO-3166 alpha-2 (e.g. "IR", "DE", "US") or '' on failure.
 */
function kdcv_resolve_country() {
	// 1) Cloudflare (free with orange-cloud proxy): instant, no API call.
	if ( ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
		$cc = strtoupper( trim( $_SERVER['HTTP_CF_IPCOUNTRY'] ) );
		if ( $cc !== '' && $cc !== 'XX' && strlen( $cc ) === 2 ) {
			return $cc;
		}
	}

	// 2) LiteSpeed Cache GeoIP / Apache mod_geoip (host-installed MaxMind).
	foreach ( array( 'GEOIP_COUNTRY_CODE', 'XL_COUNTRY_CODE', 'COUNTRY_CODE' ) as $key ) {
		if ( ! empty( $_SERVER[ $key ] ) ) {
			$cc = strtoupper( trim( $_SERVER[ $key ] ) );
			if ( strlen( $cc ) === 2 ) {
				return $cc;
			}
		}
	}

	// 3) ip-api.com free HTTP API, cached per client IP via transient.
	// Two cache states: country code on success, sentinel '_FAIL_' on
	// failure — both cached, so a broken ip-api.com doesn't slow every
	// page load by trying again on every request.
	$ip = kdcv_client_ip();
	if ( $ip === '' ) {
		return '';
	}

	// Local/private IPs can't be geolocated; bail before hitting the API.
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
		return '';
	}

	$cache_key = 'kdcv_geo_' . md5( $ip );
	$cached = get_transient( $cache_key );
	if ( $cached !== false ) {
		if ( $cached === '_FAIL_' ) {
			return ''; // recent failure — don't retry for 5 min.
		}
		return strtoupper( $cached );
	}

	// ip-api.com free tier: HTTP only, 45 req/min, JSON. fields=countryCode
	// keeps payload tiny. Timeout 2s — if the API is slow/down we cache a
	// short-lived failure marker and fall through to English.
	$url  = 'http://ip-api.com/json/' . rawurlencode( $ip ) . '?fields=countryCode';
	$resp = wp_remote_get( $url, array( 'timeout' => 2 ) );
	if ( is_wp_error( $resp ) ) {
		set_transient( $cache_key, '_FAIL_', 5 * MINUTE_IN_SECONDS );
		return '';
	}
	$body = wp_remote_retrieve_body( $resp );
	$data = json_decode( $body, true );
	if ( ! is_array( $data ) || empty( $data['countryCode'] ) ) {
		set_transient( $cache_key, '_FAIL_', 5 * MINUTE_IN_SECONDS );
		return '';
	}

	$cc = strtoupper( $data['countryCode'] );
	set_transient( $cache_key, $cc, DAY_IN_SECONDS ); // 24h cache per IP
	return $cc;
}

/**
 * Get the visitor's real client IP, respecting common proxy headers.
 * Cloudflare and most CDNs set CF-Connecting-IP / X-Forwarded-For.
 */
function kdcv_client_ip() {
	$candidates = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
	foreach ( $candidates as $key ) {
		if ( empty( $_SERVER[ $key ] ) ) {
			continue;
		}
		// X-Forwarded-For can be a comma-separated list; first entry is the client.
		$ip = trim( explode( ',', $_SERVER[ $key ] )[0] );
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $ip;
		}
	}
	return '';
}

/**
 * Bot/crawler UA pattern — same regex as assets/js/locale-router.js v2.
 * Keep them in sync. Crawlers must NEVER be geo-redirected.
 */
function kdcv_is_bot() {
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
	if ( $ua === '' ) {
		return true; // No UA at all = probably a bot/scanner.
	}
	return (bool) preg_match(
		'/(bot|spider|crawler|slurp|bingpreview|facebookexternalhit|twitterbot|linkedinbot|whatsapp|telegrambot|skypeuripreview|google-?structured|feedfetcher|ia_archiver|archive\.org_bot|perplexity|gptbot|claudebot|oai-searchbot|anthropic-ai|bytespider|applebot|applebot-extended|yandex|baidu|duckduckbot|seznambot|facebot|meta-externalagent|semrush|ahrefsbot|dotbot|petalbot|dataforseo|mj12bot|sitesucker|cohere-ai|ccbot)/i',
		$ua
	);
}

/**
 * The actual geo-router. Fires on template_redirect, only on the front page.
 * Order: ?lang > cookie > geo-IP > (let JS router handle browser language).
 */
add_action( 'template_redirect', function () {
	// Only relevant on the English front page — every other URL keeps its
	// language by definition.
	if ( is_admin() || ! is_front_page() ) {
		return;
	}

	// 1) ?lang=xx — explicit override wins always; remember it for 1 year
	//    so the user isn't re-routed on the next visit.
	if ( isset( $_GET['lang'] ) ) {
		$lang = strtolower( substr( preg_replace( '/[^a-z-]/i', '', $_GET['lang'] ), 0, 5 ) );
		$allowed = array( 'en', 'fa', 'ar', 'de', 'es', 'fr', 'tr', 'zh', 'ja' );
		if ( in_array( $lang, $allowed, true ) ) {
			setcookie( 'kdcv_lang', $lang, time() + YEAR_IN_SECONDS, '/', '', is_ssl(), true );
			if ( $lang !== 'en' ) {
				wp_redirect( home_url( '/' . $lang . '/' ), 302 );
				exit;
			}
			// lang=en: explicitly staying on English, do nothing.
			return;
		}
	}

	// 2) Already-set cookie: respect user's previous choice.
	if ( isset( $_COOKIE['kdcv_lang'] ) ) {
		return; // user explicitly chose a language; leave them alone.
	}

	// 2b) Geo-routing cookie: we routed them on a previous visit. Use the
	//     cached value instead of calling the geo API again.
	if ( ! empty( $_COOKIE['kdcv_geo_lang'] ) ) {
		$cached = strtolower( preg_replace( '/[^a-z-]/', '', $_COOKIE['kdcv_geo_lang'] ) );
		$allowed = array( 'fa', 'ar', 'de', 'es', 'fr', 'tr', 'zh', 'ja' );
		if ( in_array( $cached, $allowed, true ) ) {
			wp_redirect( home_url( '/' . $cached . '/' ), 302 );
			exit;
		}
	}

	// 3) Bots never get redirected (so they index the canonical English).
	if ( kdcv_is_bot() ) {
		return;
	}

	// 4) Geo-IP routing: country -> language.
	$country = kdcv_resolve_country();
	$lang    = kdcv_country_to_lang( $country );

	if ( $lang === 'en' ) {
		// A real English signal (US/UK/CA/AU/…): stay on the English canonical
		// and let the JS router refine by browser language if it disagrees.
		return;
	}

	if ( $lang === '' ) {
		// No country mapping at all — private/local IP, geo lookup failed, or a
		// country we do not map. Fall back to PERSIAN rather than English: the
		// site owner is Iranian and the primary audience is Persian-speaking,
		// so "no signal" should resolve to fa.
		//
		// Safe for SEO because bots returned at step 3 above and never reach
		// here, so crawlers still index / as the English canonical.
		$lang = 'fa';
	}

	// Set cookie so the SAME visitor isn't re-routed every visit. 30 days
	// is short enough that a real relocation is picked up, long enough to
	// avoid hammering the geo API.
	setcookie( 'kdcv_geo_lang', $lang, time() + 30 * DAY_IN_SECONDS, '/', '', is_ssl(), true );

	wp_redirect( home_url( '/' . $lang . '/' ), 302 );
	exit;
}, 0 ); // priority 0 — run before any other template_redirect handler.

/**
 * robots.txt: explicitly allow AI training crawlers. This is a public
 * personal portfolio — being cited/trained on by GPT/Claude/Perplexity is
 * the entire point of the structured-data investment. WordPress's default
 * robots.txt doesn't address these bots either way; this makes the
 * "allow" explicit so a future policy change upstream can't silently
 * remove the site from LLM training data.
 */
add_filter( 'robots_txt', function ( $output, $public ) {
	if ( ! $public ) {
		return $output;
	}

	$ai_allow =
		"\n# ---------- AI training / retrieval crawlers (public portfolio: allow) ----------\n"
		. "User-agent: GPTBot\nAllow: /\n\n"
		. "User-agent: ChatGPT-User\nAllow: /\n\n"
		. "User-agent: OAI-SearchBot\nAllow: /\n\n"
		. "User-agent: ClaudeBot\nAllow: /\n\n"
		. "User-agent: anthropic-ai\nAllow: /\n\n"
		. "User-agent: PerplexityBot\nAllow: /\n\n"
		. "User-agent: PerplexityBot-User\nAllow: /\n\n"
		. "User-agent: Google-Extended\nAllow: /\n\n"
		. "User-agent: Bytespider\nAllow: /\n\n"
		. "User-agent: CCBot\nAllow: /\n\n"
		. "User-agent: FacebookBot\nAllow: /\n\n"
		. "User-agent: Meta-ExternalAgent\nAllow: /\n\n"
		. "User-agent: Applebot-Extended\nAllow: /\n\n"
		. "User-agent: Amazonbot\nAllow: /\n\n"
		. "User-agent: cohere-ai\nAllow: /\n\n";

	// Prefer the static multilingual sitemap (richer: has hreflang alternates
	// for all 9 languages). Falls back to wp-sitemap.xml if the static file
	// is ever removed.
	$static_sitemap = "\nSitemap: " . home_url( '/sitemap.xml' ) . "\n";

	// The llms.txt convention has no discovery mechanism of its own, so the
	// nine localized summaries are advertised here — robots.txt is the file
	// every crawler already fetches first. Kept identical to the static
	// robots.txt at the repo root; edit BOTH together.
	$llms = "\n# Machine-readable site summaries, one per language.\n";
	foreach ( array( '', 'fa-', 'ar-', 'de-', 'es-', 'fr-', 'tr-', 'zh-', 'ja-' ) as $prefix ) {
		$llms .= '# ' . home_url( '/' . $prefix . 'llms.txt' ) . "\n";
	}

	return $output . $ai_allow . $static_sitemap . $llms;
}, 10, 2 );

/**
 * Browser security headers for public theme pages.
 *
 * CSP is now ENFORCED on the front end. It was Report-Only while the policy was
 * unproven; the audit that promoted it found that every script, stylesheet,
 * font, image and media file the theme loads is same-origin, and that no file
 * in assets/js uses eval / new Function / new Worker / new Blob /
 * createObjectURL. The only cross-origin subresources are two iframe embeds
 * (Aparat, Google Calendar) and the contact form endpoint.
 *
 * 'unsafe-inline' remains: the generated CV templates carry inline
 * <style>/<script> blocks (theme boot, KDCV config) and style attributes, and
 * WordPress itself prints inline scripts.
 *
 * wp-admin is deliberately EXCLUDED. The admin loads inline handlers, blob:
 * previews in the media library and third-party plugin assets; a policy tuned
 * for the public pages would lock the owner out of their own dashboard.
 */
function kdcv_csp_value() {
	return implode( '; ', array(
		"default-src 'self'",
		"script-src 'self' 'unsafe-inline'",
		"style-src 'self' 'unsafe-inline'",
		// fontiran.com serves the IRANSansX licence badge that fa.html is required
		// to display. Hosting a copy ourselves would mean re-publishing the
		// licensor's mark, so the origin is allow-listed instead.
		"img-src 'self' data: https://fontiran.com",
		"font-src 'self' data:",
		"media-src 'self'",
		"connect-src 'self' https://api.web3forms.com",
		"form-action 'self' https://api.web3forms.com",
		'frame-src https://calendar.google.com https://www.aparat.com https://aparat.com',
		"worker-src 'self'",
		"manifest-src 'self'",
		"object-src 'none'",
		"base-uri 'self'",
		"frame-ancestors 'self'",
		'upgrade-insecure-requests',
	) );
}

add_action( 'send_headers', function () {
	if ( is_ssl() ) {
		header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
	}
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: camera=(), microphone=(self), geolocation=(), payment=(), interest-cohort=()' );
	header( 'Cross-Origin-Opener-Policy: same-origin' );
	header( 'Cross-Origin-Resource-Policy: same-origin' );
	header( 'X-Permitted-Cross-Domain-Policies: none' );
	header( 'X-DNS-Prefetch-Control: off' );

	if ( ! is_admin() && ! is_user_logged_in() ) {
		header( 'Content-Security-Policy: ' . kdcv_csp_value() );
	}

	// Cache-Control: long-lived for static assets, short for HTML.
	// Pairs with the LiteSpeed Cache plugin (which handles full-page cache
	// separately via its own storage). When the LSCache plugin is active,
	// these still help browser caching and any non-LSCache edge (Cloudflare).
	$request = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
	if ( preg_match( '#/wp-content/themes/kohandezhcv/assets/#', $request ) ) {
		// Versioned assets (?v=N) — never change, cache for a year.
		header( 'Cache-Control: public, max-age=31536000, immutable' );
	} elseif ( is_front_page() || is_page() ) {
		// HTML pages — revalidate every hour so updates are picked up.
		header( 'Cache-Control: public, max-age=3600, stale-while-revalidate=86400' );
	}
} );

/**
 * Canonical set of pages backed by generated theme templates.
 */
/**
 * Expose the theme's real URLs to the front-end JavaScript.
 *
 * page-chrome.js builds the shared footer and site menu at runtime. On the
 * static site it can use RELATIVE paths ("assets/images/logo/footer-logo.png",
 * "Certificates.html") because the pages sit next to the assets. Under
 * WordPress neither assumption holds: pages live at pretty slugs (/psn/,
 * /certificates/) and assets live under wp-content/themes/, so a relative path
 * resolves to /psn/assets/... and 404s.
 *
 * Printing the real bases here lets that script use absolute URLs when it is
 * running on WordPress and keep its relative fallback on the static build.
 * Kept as a tiny inline object rather than wp_localize_script so it is
 * available before any deferred script runs.
 */
function kdcv_expose_urls() {
	$pages = array();
	foreach ( array_keys( kdcv_required_pages() ) as $slug ) {
		$pages[ $slug ] = ( 'home' === $slug )
			? home_url( '/' )
			: home_url( '/' . $slug . '/' );
	}
	// videos.html is static-only (uploaded separately by FTP), so it has no
	// registered page and its URL is stated directly.
	$pages['videos'] = home_url( '/videos/' );

	$config = array(
		'assets' => trailingslashit( KDCV ) . 'assets/',
		'home'   => home_url( '/' ),
		'pages'  => $pages,
		// contact-forms.js posts here instead of straight to Web3Forms, so the
		// access key never reaches the browser. Its presence is also how the
		// front-end scripts detect that they are running on WordPress.
		'rest'   => esc_url_raw( rest_url() ),
	);

	printf(
		"<script>window.KDCV_WP=%s;</script>\n",
		wp_json_encode( $config )
	);
}
add_action( 'wp_head', 'kdcv_expose_urls', 1 );

function kdcv_required_pages() {
	return array(
		'home' => 'Home',
		'fa'   => 'CV — فارسی',
		'ar'   => 'CV — العربية',
		'de'   => 'CV — Deutsch',
		'es'   => 'CV — Español',
		'fr'   => 'CV — Français',
		'tr'   => 'CV — Türkçe',
		'zh'   => 'CV — 中文',
		'ja'   => 'CV — 日本語',
		'psn'  => 'PSN Trophy Room',
		'certificates' => 'Certificates',
		'blog' => 'Blog',
		'portfolio' => 'Portfolio',
		'privacy' => 'Privacy Policy',
		'terms' => 'Terms of Use',
	);
}

/**
 * Add missing pages without touching existing content or database IDs.
 *
 * @return bool Whether at least one page was created.
 */
function kdcv_ensure_required_pages() {
	$created = false;
	foreach ( kdcv_required_pages() as $slug => $title ) {
		if ( ! get_page_by_path( $slug ) ) {
			$result = wp_insert_post( array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_name'   => $slug,
				'post_title'  => $title,
			), true );
			if ( ! is_wp_error( $result ) ) {
				$created = true;
			}
		}
	}
	return $created;
}

/**
 * Keep the Home and Blog routes required by this theme.
 */
function kdcv_configure_front_pages() {
	$home = get_page_by_path( 'home' );
	$blog = get_page_by_path( 'blog' );
	if ( $home && $blog ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home->ID );
		update_option( 'page_for_posts', $blog->ID );
	}
}

/**
 * Fresh activation path.
 */
add_action( 'after_switch_theme', function () {
	kdcv_ensure_required_pages();
	kdcv_configure_front_pages();
	update_option( 'kdcv_content_schema_version', KDCV_CONTENT_SCHEMA_VERSION );
	flush_rewrite_rules();
	kdcv_harden_htaccess();
} );

/**
 * Idempotent update migration. Theme updates do not fire after_switch_theme,
 * so pages introduced by a newer release are also created on existing sites.
 */
add_action( 'admin_init', function () {
	$installed = (string) get_option( 'kdcv_content_schema_version', '0' );
	if ( version_compare( $installed, KDCV_CONTENT_SCHEMA_VERSION, '>=' ) ) {
		return;
	}

	$created = kdcv_ensure_required_pages();
	kdcv_configure_front_pages();
	update_option( 'kdcv_content_schema_version', KDCV_CONTENT_SCHEMA_VERSION );
	// Always flush on schema version change — needed so new rewrite rules
	// (e.g. /llms.txt) become active even when no new pages were created.
	flush_rewrite_rules( false );

	// One-time cleanup (P3, 2026-08): delete duplicate blog posts imported
	// twice by mistake during the WP blog-import. These specific IDs were
	// flagged by the user. The cleanup runs exactly once and remembers via
	// the kdcv_dup_cleanup_done option, so it is safe across reinstalls.
	if ( ! get_option( 'kdcv_dup_cleanup_done' ) ) {
		$duplicate_ids = array( 12871, 12866, 11850, 11538 );
		$deleted = array();
		foreach ( $duplicate_ids as $pid ) {
			$post = get_post( $pid );
			if ( $post && in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
				wp_delete_post( $pid, true ); // force_delete = bypass trash
				$deleted[] = $pid;
			}
		}
		update_option( 'kdcv_dup_cleanup_done', current_time( 'mysql' ) );
		update_option( 'kdcv_dup_cleanup_log', $deleted ); // record what was removed
	}
} );

/**
 * Block direct HTTP access to files WordPress can't gate through PHP hooks:
 * the debug log (WP_DEBUG_LOG writes here and Apache serves it to anyone who
 * requests the URL), readme.html (fingerprints the exact core version for
 * vulnerability scanners), and other files WP ships but never needs served.
 * Uses insert_with_markers so it survives on any host (Apache/LiteSpeed) and
 * re-running theme activation won't duplicate the block.
 */
function kdcv_harden_htaccess() {
	if ( ! function_exists( 'insert_with_markers' ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}
	$htaccess = ABSPATH . '.htaccess';
	if ( ! is_writable( dirname( $htaccess ) ) ) {
		return;
	}
	insert_with_markers( $htaccess, 'KDCV Hardening', array(
		'<FilesMatch "(^readme\.html$|^license\.txt$|^wp-config-sample\.php$)">',
		'  Require all denied',
		'</FilesMatch>',
		'<IfModule mod_rewrite.c>',
		'  RewriteRule ^wp-content/debug\.log$ - [F,L]',
		'</IfModule>',
		'<IfModule mod_headers.c>',
		'  Header always set X-Content-Type-Options "nosniff"',
		'  Header always set X-Frame-Options "SAMEORIGIN"',
		'  Header always set Referrer-Policy "strict-origin-when-cross-origin"',
		'  Header always set Permissions-Policy "camera=(), microphone=(self), geolocation=(), payment=(), interest-cohort=()"',
		'  Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"',
		'  Header always set Cross-Origin-Opener-Policy "same-origin"',
		'  Header always set Cross-Origin-Resource-Policy "same-origin"',
		'  Header always set X-Permitted-Cross-Domain-Policies "none"',
		'  Header always set X-DNS-Prefetch-Control "off"',
		'</IfModule>',
	) );
}

/**
 * Post view counter (kdcv_views post meta).
 * Logged-in users (the admin editing/previewing) don't inflate the numbers.
 * Surfaced in three places: the post's meta line on the front end, a sortable
 * "بازدید" column on the admin posts list, and a dashboard widget with totals.
 */
function kdcv_get_views( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	return (int) get_post_meta( $post_id, 'kdcv_views', true );
}

add_action( 'wp', function () {
	if ( ! is_single() || is_preview() || is_user_logged_in() ) {
		return;
	}
	$post_id = get_queried_object_id();
	if ( $post_id ) {
		update_post_meta( $post_id, 'kdcv_views', kdcv_get_views( $post_id ) + 1 );
	}
} );

add_filter( 'manage_posts_columns', function ( $cols ) {
	$cols['kdcv_views'] = 'بازدید';
	return $cols;
} );
add_action( 'manage_posts_custom_column', function ( $col, $post_id ) {
	if ( 'kdcv_views' === $col ) {
		echo esc_html( number_format_i18n( kdcv_get_views( $post_id ) ) );
	}
}, 10, 2 );
add_filter( 'manage_edit-post_sortable_columns', function ( $cols ) {
	$cols['kdcv_views'] = 'kdcv_views';
	return $cols;
} );
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() && $q->is_main_query() && 'kdcv_views' === $q->get( 'orderby' ) ) {
		$q->set( 'meta_key', 'kdcv_views' );
		$q->set( 'orderby', 'meta_value_num' );
	}
} );

add_action( 'wp_dashboard_setup', function () {
	wp_add_dashboard_widget( 'kdcv_views_widget', 'آمار بازدید نوشته‌ها', function () {
		global $wpdb;
		$total = (int) $wpdb->get_var(
			"SELECT SUM(meta_value+0) FROM {$wpdb->postmeta} WHERE meta_key = 'kdcv_views'"
		);
		echo '<p><strong>مجموع بازدید همه نوشته‌ها: ' . esc_html( number_format_i18n( $total ) ) . '</strong></p>';
		$top = get_posts( array(
			'numberposts' => 10,
			'meta_key'    => 'kdcv_views',
			'orderby'     => 'meta_value_num',
			'order'       => 'DESC',
		) );
		echo '<ol style="margin:0;padding-inline-start:20px;">';
		foreach ( $top as $p ) {
			echo '<li><a href="' . esc_url( get_edit_post_link( $p->ID ) ) . '">'
				. esc_html( get_the_title( $p ) ) . '</a> — '
				. esc_html( number_format_i18n( kdcv_get_views( $p->ID ) ) ) . ' بازدید</li>';
		}
		echo '</ol>';
	} );
} );

/**
 * Rough reading-time estimate for blog cards (Persian-friendly: counts words).
 */
function kdcv_reading_minutes( $post = null ) {
	$content = get_post_field( 'post_content', $post );
	$words   = count( preg_split( '/\s+/u', wp_strip_all_tags( $content ), -1, PREG_SPLIT_NO_EMPTY ) );
	return max( 1, (int) ceil( $words / 180 ) );
}

/**
 * Card image: featured image if set, otherwise rotate through theme artwork.
 */
function kdcv_card_image( $post, $index = 0 ) {
	$thumb = get_the_post_thumbnail_url( $post, 'large' );
	if ( $thumb ) {
		return $thumb;
	}
	$fallbacks = array( 'work-3.webp', 'work-2.webp', 'work-1.webp', 'service-1.webp', 'service-4.webp' );
	return KDCV . '/assets/images/section/' . $fallbacks[ $index % count( $fallbacks ) ];
}

/**
 * Branded wp-login: dark palette matching the site, plus an animated avatar
 * that watches while you type the username and covers (closes) its eyes when
 * the password field gains focus. Clicking WP's "show password" makes it peek.
 */
add_filter( 'login_headerurl', function () {
	return home_url( '/' );
} );
add_filter( 'login_headertext', function () {
	return 'Mohammad Ali Kohandezh';
} );

add_filter( 'login_message', function ( $message ) {
	$avatar = '
	<div class="kdcv-avatar-wrap">
	<svg id="kdcv-login-avatar" class="kdcv-avatar" viewBox="0 0 200 170" width="180" height="153" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
		<!-- antenna -->
		<line x1="100" y1="28" x2="100" y2="10" stroke="#2c3a35" stroke-width="4" stroke-linecap="round"/>
		<circle class="kdcv-antenna-dot" cx="100" cy="8" r="6" fill="#00DE51"/>
		<!-- ears -->
		<rect x="18" y="78" width="14" height="34" rx="7" fill="#16211f" stroke="#233029" stroke-width="2"/>
		<rect x="168" y="78" width="14" height="34" rx="7" fill="#16211f" stroke="#233029" stroke-width="2"/>
		<!-- head -->
		<rect x="30" y="28" width="140" height="120" rx="38" fill="#131d1a" stroke="#2c3a35" stroke-width="2.5"/>
		<!-- face panel -->
		<rect x="44" y="46" width="112" height="86" rx="26" fill="#0c1412"/>
		<!-- left eye -->
		<g class="kdcv-eye">
			<ellipse cx="76" cy="84" rx="13" ry="15" fill="#eef8ef"/>
			<circle class="kdcv-pupil" cx="76" cy="86" r="5.5" fill="#0b120c"/>
			<ellipse class="kdcv-lid" cx="76" cy="84" rx="14.5" ry="16.5" fill="#0c1412"/>
			<path class="kdcv-lash" d="M64 86 Q76 94 88 86" fill="none" stroke="#00DE51" stroke-width="3" stroke-linecap="round"/>
		</g>
		<!-- right eye -->
		<g class="kdcv-eye kdcv-eye-right">
			<ellipse cx="124" cy="84" rx="13" ry="15" fill="#eef8ef"/>
			<circle class="kdcv-pupil" cx="124" cy="86" r="5.5" fill="#0b120c"/>
			<ellipse class="kdcv-lid" cx="124" cy="84" rx="14.5" ry="16.5" fill="#0c1412"/>
			<path class="kdcv-lash" d="M112 86 Q124 94 136 86" fill="none" stroke="#00DE51" stroke-width="3" stroke-linecap="round"/>
		</g>
		<!-- mouth -->
		<path class="kdcv-mouth" d="M86 112 Q100 122 114 112" fill="none" stroke="#00DE51" stroke-width="4" stroke-linecap="round"/>
	</svg>
	</div>';
	return $avatar . $message;
} );

add_action( 'login_enqueue_scripts', function () { ?>
	<style>
		html, body.login { background: #080b0d !important; }
		body.login {
			font-family: "Inter", -apple-system, BlinkMacSystemFont, sans-serif;
		}
		.login h1 a { display: none; }
		.kdcv-avatar-wrap { display: flex; justify-content: center; margin-bottom: 4px; }
		.kdcv-avatar .kdcv-pupil { transition: transform .18s ease; }
		.kdcv-avatar .kdcv-lid {
			transform: scaleY(0);
			transform-box: fill-box;
			transform-origin: center top;
			transition: transform .28s ease;
		}
		.kdcv-avatar .kdcv-lash { opacity: 0; transition: opacity .2s ease .12s; }
		.kdcv-avatar.is-blind .kdcv-lid { transform: scaleY(1.05); }
		.kdcv-avatar.is-blind .kdcv-lash { opacity: 1; }
		.kdcv-avatar.is-blind.is-peek .kdcv-eye-right .kdcv-lid { transform: scaleY(0.45); }
		.kdcv-avatar.is-blind.is-peek .kdcv-eye-right .kdcv-lash { opacity: 0; }
		.kdcv-avatar .kdcv-mouth { transition: d .25s ease; }
		.kdcv-avatar.is-blind .kdcv-mouth { d: path("M90 114 Q100 110 110 114"); }
		.kdcv-antenna-dot { animation: kdcv-pulse 2.4s ease-in-out infinite; }
		@keyframes kdcv-pulse {
			0%, 100% { opacity: .5; }
			50% { opacity: 1; }
		}
		.login form {
			background: #10181a;
			border: 1px solid rgba(255, 255, 255, 0.08);
			border-radius: 18px;
			box-shadow: 0 24px 60px rgba(0, 0, 0, 0.45);
		}
		.login label { color: rgba(255, 255, 255, 0.72); }
		.login input[type="text"],
		.login input[type="password"] {
			background: #0b1113;
			border: 1px solid rgba(255, 255, 255, 0.15);
			border-radius: 10px;
			color: #f1f8ef;
		}
		.login input[type="text"]:focus,
		.login input[type="password"]:focus {
			border-color: #00DE51;
			box-shadow: 0 0 0 2px rgba(0, 222, 81, 0.25);
			outline: none;
		}
		.login .button.wp-hide-pw { color: rgba(255, 255, 255, 0.56); }
		.login .button.wp-hide-pw:hover,
		.login .button.wp-hide-pw:focus { color: #00DE51; box-shadow: none; }
		.login .forgetmenot input[type="checkbox"] { accent-color: #00DE51; }
		.wp-core-ui .button-primary {
			background: #00DE51;
			border-color: #00DE51;
			border-radius: 999px;
			color: #06130a;
			font-weight: 600;
			text-shadow: none;
		}
		.wp-core-ui .button-primary:hover,
		.wp-core-ui .button-primary:focus {
			background: #a8ff46;
			border-color: #a8ff46;
			color: #06130a;
		}
		.login #nav a, .login #backtoblog a { color: rgba(255, 255, 255, 0.56); }
		.login #nav a:hover, .login #backtoblog a:hover { color: #00DE51; }
		.login .message, .login .success, .login .notice {
			background: #10181a;
			border-left-color: #00DE51;
			color: rgba(255, 255, 255, 0.72);
		}
		.login .notice-error, .login #login_error {
			background: #10181a;
			color: rgba(255, 255, 255, 0.72);
		}
		.login .privacy-policy-page-link a { color: rgba(255, 255, 255, 0.4); }
	</style>
<?php } );

add_action( 'login_footer', function () { ?>
	<script>
	(function () {
		var avatar = document.getElementById('kdcv-login-avatar');
		if (!avatar) return;
		var user = document.getElementById('user_login');
		var pass = document.getElementById('user_pass');
		var pupils = avatar.querySelectorAll('.kdcv-pupil');

		function look() {
			if (!user) return;
			var len = Math.min(user.value.length, 24);
			var x = -5 + (len / 24) * 10;
			pupils.forEach(function (p) { p.style.transform = 'translate(' + x + 'px, 2.5px)'; });
		}
		function rest() {
			pupils.forEach(function (p) { p.style.transform = ''; });
		}
		if (user) {
			user.addEventListener('focus', look);
			user.addEventListener('input', look);
			user.addEventListener('blur', rest);
		}
		if (pass) {
			pass.addEventListener('focus', function () { avatar.classList.add('is-blind'); });
			pass.addEventListener('blur', function () { avatar.classList.remove('is-blind', 'is-peek'); });
		}
		var toggle = document.querySelector('.wp-hide-pw');
		if (toggle && pass) {
			toggle.addEventListener('click', function () {
				setTimeout(function () {
					avatar.classList.toggle('is-peek', pass.getAttribute('type') === 'text');
				}, 0);
			});
		}
	}());
	</script>
<?php } );

/**
 * Homepage "Blog and News" preview: renders the latest posts using the same
 * markup as the static blog-local-item cards, so new posts appear on the
 * homepage automatically. The remaining pages are loaded on scroll by
 * assets/js/home-blog-scroll.js via the REST API.
 */
function kdcv_render_home_blog_feed( $read_label = 'Read original', $limit = 6 ) {
	$query = new WP_Query( array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'no_found_rows'  => true,
	) );

	if ( ! $query->have_posts() ) {
		return '';
	}

	ob_start();
	while ( $query->have_posts() ) : $query->the_post();
		$cats = get_the_category();
		?>
		<article class="blog-local-item">
			<div class="blog-local-top">
				<h5 class="blog-local-title"><?php the_title(); ?></h5>
				<span class="blog-local-date"><?php echo esc_html( get_the_modified_date() ); ?></span>
			</div>
			<p class="blog-local-summary"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 28 ) ); ?></p>
			<div class="blog-local-meta">
				<span class="blog-local-tag"><?php echo $cats ? esc_html( $cats[0]->name ) : ''; ?></span>
				<a class="blog-local-link" href="<?php the_permalink(); ?>">
					<?php echo esc_html( $read_label ); ?> <i class="icon icon-arrow-right-top"></i>
				</a>
			</div>
		</article>
		<?php
	endwhile;
	wp_reset_postdata();
	return ob_get_clean();
}

/**
 * Neon-flow background for wp-login.php.
 *
 * Ported from the <TubesBackground> React component, with one deliberate
 * change: the reference does
 *
 *     await import('https://cdn.jsdelivr.net/npm/threejs-components@.../tubes1.min.js')
 *
 * Loading third-party JavaScript on the LOGIN PAGE is not acceptable. Any
 * compromise of that CDN — or of the package on it — would be running with
 * full access to the username and password fields at the moment they are
 * typed. So the effect is reimplemented here on a 2D canvas: same flowing
 * neon ribbons, same cursor tracking, same click-to-randomise, no three.js
 * and no network request at all.
 *
 * The canvas sits behind the form and is purely decorative: aria-hidden, and
 * it never receives pointer events, so it cannot interfere with the fields.
 */
add_action( 'login_enqueue_scripts', function () { ?>
	<style>
		#kdcv-neon {
			position: fixed;
			inset: 0;
			z-index: 0;
			display: block;
			width: 100%;
			height: 100%;
			pointer-events: none;
			background: #050708;
		}
		/* Everything WordPress renders must sit above the canvas and stay
		   fully legible — the effect is a backdrop, never a competitor. */
		body.login > *:not(#kdcv-neon) { position: relative; z-index: 1; }

		/* The form gains a slight veil so the ribbons never reduce the
		   contrast of the labels or the inputs behind them. */
		.login form {
			background: rgba(10, 18, 20, .93) !important;
			backdrop-filter: blur(10px);
			-webkit-backdrop-filter: blur(10px);
		}
		.login label,
		.login #nav a,
		.login #backtoblog a { text-shadow: 0 1px 3px rgba(0, 0, 0, .85); }
		.login #nav a, .login #backtoblog a { color: rgba(255, 255, 255, .72); }
		.login #nav, .login #backtoblog {
			background: rgba(6, 10, 11, .6);
			border-radius: 10px;
			padding: 6px 12px;
			display: inline-block;
		}
		.login h1 a, .kdcv-avatar-wrap { position: relative; z-index: 1; }

		@media (prefers-reduced-motion: reduce) {
			#kdcv-neon { display: none; }
		}
	</style>
<?php } );

add_action( 'login_footer', function () { ?>
	<canvas id="kdcv-neon" aria-hidden="true"></canvas>
	<script>
	(function () {
		var cv = document.getElementById('kdcv-neon');
		if (!cv || !cv.getContext) return;
		if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			cv.style.display = 'none';
			return;
		}

		var ctx = cv.getContext('2d');
		var dpr = Math.min(window.devicePixelRatio || 1, 2);
		var W = 0, H = 0;

		function resize() {
			W = cv.clientWidth; H = cv.clientHeight;
			cv.width = W * dpr; cv.height = H * dpr;
			ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
		}
		resize();
		window.addEventListener('resize', resize);

		var PALETTE = ['#f967fb', '#53bc28', '#6958d5'];
		var LIGHTS  = ['#83f36e', '#fe8a2e', '#ff008a', '#60aed5'];
		var colors  = PALETTE.concat(LIGHTS);

		function randomColor() {
			return '#' + Math.floor(Math.random() * 16777215).toString(16).padStart(6, '0');
		}

		// The cursor is the attractor the ribbons trail toward, exactly as the
		// original TubesCursor does.
		var mx = window.innerWidth / 2, my = window.innerHeight / 2;
		window.addEventListener('pointermove', function (e) { mx = e.clientX; my = e.clientY; });

		var TUBES = 7;
		var tubes = [];
		for (var i = 0; i < TUBES; i++) {
			var pts = [];
			for (var j = 0; j < 6; j++) pts.push({ x: Math.random() * W, y: Math.random() * H, vx: 0, vy: 0 });
			tubes.push({ pts: pts, color: colors[i % colors.length], w: 2 + Math.random() * 3, lag: 0.012 + i * 0.006 });
		}

		function step() {
			// Trail rather than clear, so the ribbons leave a light bloom.
			ctx.globalCompositeOperation = 'source-over';
			ctx.fillStyle = 'rgba(5, 7, 8, 0.18)';
			ctx.fillRect(0, 0, W, H);

			ctx.globalCompositeOperation = 'lighter';
			ctx.lineCap = 'round';
			ctx.lineJoin = 'round';

			for (var t = 0; t < tubes.length; t++) {
				var tube = tubes[t], pts = tube.pts;

				// Head chases the cursor; each following point chases the one
				// ahead of it, which is what gives the tube its whip.
				var tx = mx, ty = my;
				for (var p = 0; p < pts.length; p++) {
					var pt = pts[p];
					pt.vx += (tx - pt.x) * tube.lag;
					pt.vy += (ty - pt.y) * tube.lag;
					pt.vx *= 0.86; pt.vy *= 0.86;
					pt.x += pt.vx; pt.y += pt.vy;
					tx = pt.x; ty = pt.y;
				}

				ctx.beginPath();
				ctx.moveTo(pts[0].x, pts[0].y);
				for (var k = 1; k < pts.length - 1; k++) {
					var xc = (pts[k].x + pts[k + 1].x) / 2;
					var yc = (pts[k].y + pts[k + 1].y) / 2;
					ctx.quadraticCurveTo(pts[k].x, pts[k].y, xc, yc);
				}
				ctx.strokeStyle = tube.color;
				ctx.shadowColor = tube.color;
				ctx.shadowBlur = 18;
				ctx.lineWidth = tube.w;
				ctx.stroke();
			}
			ctx.shadowBlur = 0;
			requestAnimationFrame(step);
		}
		requestAnimationFrame(step);

		// Click to randomise, same as the reference. Bound to the canvas's
		// parent rather than the canvas (which is pointer-events:none), and
		// ignored when the click lands on a form control.
		document.addEventListener('click', function (e) {
			if (e.target.closest && e.target.closest('form, #nav, #backtoblog')) return;
			for (var i = 0; i < tubes.length; i++) tubes[i].color = randomColor();
		});
	})();
	</script>
<?php } );

/* =============================================================================
 * Reserve Online — server-side booking
 * =============================================================================
 * WHY SERVER-SIDE
 * A booking has to reach KohanSystemFarda@gmail.com's calendar. Doing that from
 * the browser would mean shipping a Google OAuth credential to every visitor —
 * anyone could then read or write the calendar. So the request is posted to
 * WordPress and everything sensitive stays here, the same rule the TTS key
 * already follows.
 *
 * WHY .ics RATHER THAN THE CALENDAR API
 * A full Google Calendar API integration needs an OAuth app, a consent flow and
 * a refresh token stored server-side. That is real work and real key custody.
 * Mailing a standards-compliant VEVENT invite achieves the same outcome today:
 * Gmail shows it as an invite with RSVP buttons and one tap files it on the
 * calendar. No credential exists to leak, and nothing here blocks moving to the
 * Calendar API — or to Odoo — later: only this one function changes.
 *
 * Endpoint: POST /wp-json/kohandezh/v1/reserve
 * ---------------------------------------------------------------------------- */

define( 'KDCV_BOOKING_INBOX', 'KohanSystemFarda@gmail.com' );

add_action( 'rest_api_init', function () {
	register_rest_route( 'kohandezh/v1', '/reserve', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true', // public booking form
		'callback'            => 'kdcv_handle_reservation',
	) );
} );

/**
 * Build an RFC 5545 VEVENT for the requested slot.
 */
function kdcv_booking_ics( $data ) {
	$start = strtotime( $data['datetime'] );
	$end   = $start + ( absint( $data['duration'] ) * 60 );
	$stamp = gmdate( 'Ymd\THis\Z' );
	$uid   = wp_generate_uuid4() . '@kohandezh.com';

	$esc = function ( $t ) {
		return preg_replace( '/([,;\\\\])/', '\\\\$1', str_replace( array( "\r\n", "\n" ), '\\n', $t ) );
	};

	$summary = sprintf( 'Consultation — %s', $data['name'] );
	$desc    = sprintf(
		"Booking requested via kohandezh.com\n\nName: %s\nEmail: %s\nPhone: %s\nTopic: %s\n\nNotes:\n%s",
		$data['name'], $data['email'], $data['phone'], $data['topic'], $data['notes']
	);

	return implode( "\r\n", array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//kohandezh.com//Reserve Online//EN',
		'CALSCALE:GREGORIAN',
		'METHOD:REQUEST',
		'BEGIN:VEVENT',
		'UID:' . $uid,
		'DTSTAMP:' . $stamp,
		'DTSTART:' . gmdate( 'Ymd\THis\Z', $start ),
		'DTEND:' . gmdate( 'Ymd\THis\Z', $end ),
		'SUMMARY:' . $esc( $summary ),
		'DESCRIPTION:' . $esc( $desc ),
		'ORGANIZER;CN=Kohandezh:mailto:' . KDCV_BOOKING_INBOX,
		'ATTENDEE;CN=' . $esc( $data['name'] ) . ';RSVP=TRUE:mailto:' . $data['email'],
		'STATUS:TENTATIVE',
		'SEQUENCE:0',
		'END:VEVENT',
		'END:VCALENDAR',
	) ) . "\r\n";
}

function kdcv_handle_reservation( WP_REST_Request $request ) {
	// Honeypot: bots fill every field, humans never see this one.
	if ( $request->get_param( 'website' ) ) {
		return new WP_REST_Response( array( 'success' => true ), 200 ); // silent drop
	}

	// Simple per-IP throttle so the endpoint cannot be used to spam the inbox.
	$bucket = 'kdcv_resv_' . md5( kdcv_client_ip() );
	if ( (int) get_transient( $bucket ) >= 5 ) {
		return new WP_REST_Response(
			array( 'success' => false, 'message' => 'Too many requests. Please try again later.' ),
			429
		);
	}

	$data = array(
		'name'     => sanitize_text_field( (string) $request->get_param( 'name' ) ),
		'email'    => sanitize_email( (string) $request->get_param( 'email' ) ),
		'phone'    => sanitize_text_field( (string) $request->get_param( 'phone' ) ),
		'topic'    => sanitize_text_field( (string) $request->get_param( 'topic' ) ),
		'notes'    => sanitize_textarea_field( (string) $request->get_param( 'notes' ) ),
		'datetime' => sanitize_text_field( (string) $request->get_param( 'datetime' ) ),
		'duration' => $request->get_param( 'duration' ) ? absint( $request->get_param( 'duration' ) ) : 30,
	);

	$when = strtotime( $data['datetime'] );
	if ( ! $data['name'] || ! is_email( $data['email'] ) || ! $data['phone'] || ! $when ) {
		return new WP_REST_Response(
			array( 'success' => false, 'message' => 'Please complete the required fields.' ),
			400
		);
	}
	if ( $when < time() ) {
		return new WP_REST_Response(
			array( 'success' => false, 'message' => 'Please choose a time in the future.' ),
			400
		);
	}

	// Keep a record in WordPress independent of whether the mail goes through.
	$post_id = wp_insert_post( array(
		'post_type'    => 'kdcv_booking',
		'post_status'  => 'private',
		'post_title'   => sprintf( '%s — %s', $data['name'], gmdate( 'Y-m-d H:i', $when ) ),
		'post_content' => wp_json_encode( $data, JSON_UNESCAPED_UNICODE ),
	), true );

	// Write the invite to a temp file so it can be attached.
	$ics  = kdcv_booking_ics( $data );
	$tmp  = trailingslashit( get_temp_dir() ) . 'kohandezh-booking-' . gmdate( 'Ymd-His', $when ) . '.ics';
	$sent = false;
	if ( false !== file_put_contents( $tmp, $ics ) ) {
		$body = sprintf(
			"New booking request from kohandezh.com\n\nName: %s\nEmail: %s\nPhone: %s\nTopic: %s\nWhen: %s (%d min)\n\nNotes:\n%s\n\nOpen the attached invite to add it to the calendar.",
			$data['name'], $data['email'], $data['phone'], $data['topic'],
			gmdate( 'D, d M Y H:i', $when ) . ' UTC', $data['duration'], $data['notes']
		);
		$sent = wp_mail(
			KDCV_BOOKING_INBOX,
			sprintf( 'Booking request — %s (%s)', $data['name'], gmdate( 'd M Y H:i', $when ) ),
			$body,
			array( 'Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $data['email'] ),
			array( $tmp )
		);
		@unlink( $tmp );
	}

	set_transient( $bucket, (int) get_transient( $bucket ) + 1, HOUR_IN_SECONDS );

	// The request is recorded even if mail delivery failed, so a booking is
	// never silently lost — the admin screen still shows it.
	return new WP_REST_Response( array(
		'success'  => true,
		'stored'   => ! is_wp_error( $post_id ),
		'notified' => (bool) $sent,
	), 200 );
}

/**
 * Bookings are a real content type so they survive mail problems and can be
 * reviewed in wp-admin.
 */
add_action( 'init', function () {
	register_post_type( 'kdcv_booking', array(
		'label'           => 'Bookings',
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'menu_icon'       => 'dashicons-calendar-alt',
		'supports'        => array( 'title', 'editor' ),
		'capability_type' => 'post',
		'map_meta_cap'    => true,
	) );
} );

/* -----------------------------------------------------------------------------
 * Contact form proxy — keeps the Web3Forms access key off the public page.
 * -----------------------------------------------------------------------------
 * The two contact forms used to carry `<input type="hidden" name="access_key">`
 * in the markup. Anyone viewing source could read it and post through the
 * owner's Web3Forms account; that is the "public form key" finding.
 *
 * The key now lives in the `kdcv_contact_access_key` option (settable in
 * Settings -> General, or via wp-config with KDCV_WEB3FORMS_KEY) and only this
 * server-side route ever sees it. The browser posts the form fields to
 * /wp-json/kohandezh/v1/contact and gets back the same {success, message}
 * shape contact-forms.js already understands, so nothing on the front end
 * changes shape.
 *
 * The key is NOT hardcoded here: functions.php ships inside a public theme zip.
 * If no key is configured the route falls back to wp_mail(), so the form keeps
 * working on a fresh install rather than silently dropping enquiries.
 *
 * Endpoint: POST /wp-json/kohandezh/v1/contact
 * -------------------------------------------------------------------------- */

define( 'KDCV_CONTACT_INBOX', 'Kohandezh@hotmail.com' );

function kdcv_contact_key() {
	if ( defined( 'KDCV_WEB3FORMS_KEY' ) && KDCV_WEB3FORMS_KEY ) {
		return (string) KDCV_WEB3FORMS_KEY;
	}
	return (string) get_option( 'kdcv_contact_access_key', '' );
}

add_action( 'admin_init', function () {
	register_setting( 'general', 'kdcv_contact_access_key', array(
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default'           => '',
		'show_in_rest'      => false,
	) );
	add_settings_field(
		'kdcv_contact_access_key',
		__( 'Web3Forms access key', 'kohandezhcv' ),
		function () {
			$key    = get_option( 'kdcv_contact_access_key', '' );
			$masked = $key ? str_repeat( '•', max( 0, strlen( $key ) - 4 ) ) . substr( $key, -4 ) : '';
			printf(
				'<input type="text" class="regular-text" name="kdcv_contact_access_key" value="%s" placeholder="%s" autocomplete="off"><p class="description">%s</p>',
				esc_attr( $key ),
				esc_attr__( 'paste the key from web3forms.com', 'kohandezhcv' ),
				$masked
					? esc_html( sprintf( __( 'Currently set (%s). Used server-side only — it is never sent to the browser.', 'kohandezhcv' ), $masked ) )
					: esc_html__( 'Not set. The contact form will fall back to wp_mail() until a key is saved.', 'kohandezhcv' )
			);
		},
		'general'
	);
} );

add_action( 'rest_api_init', function () {
	register_rest_route( 'kohandezh/v1', '/contact', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true', // public contact form
		'callback'            => 'kdcv_handle_contact',
	) );
} );

function kdcv_handle_contact( WP_REST_Request $request ) {
	// Honeypot: the markup carries a hidden "botcheck" field no human sees.
	if ( $request->get_param( 'botcheck' ) ) {
		return new WP_REST_Response( array( 'success' => true ), 200 ); // silent drop
	}

	$name    = sanitize_text_field( (string) $request->get_param( 'name' ) );
	$email   = sanitize_email( (string) $request->get_param( 'email' ) );
	$phone   = sanitize_text_field( (string) $request->get_param( 'phone' ) );
	$subject = sanitize_text_field( (string) $request->get_param( 'subject' ) );
	$message = sanitize_textarea_field( (string) $request->get_param( 'message' ) );

	// The browser already validates; this is the server-side half, because a
	// public endpoint cannot trust that the browser ran at all.
	if ( '' === $name || ! is_email( $email ) || '' === $message ) {
		return new WP_REST_Response(
			array( 'success' => false, 'message' => 'Missing or invalid fields.' ),
			422
		);
	}

	$key = kdcv_contact_key();

	if ( $key ) {
		$response = wp_remote_post( 'https://api.web3forms.com/submit', array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
			'body'    => wp_json_encode( array(
				'access_key' => $key,
				'subject'    => $subject ? $subject : 'kohandezh.com enquiry',
				'from_name'  => $name,
				'name'       => $name,
				'email'      => $email,
				'phone'      => $phone,
				'message'    => $message,
			) ),
		) );

		if ( ! is_wp_error( $response ) ) {
			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $code >= 200 && $code < 300 && ! empty( $body['success'] ) ) {
				return new WP_REST_Response( array( 'success' => true ), 200 );
			}
		}
		// Fall through to wp_mail() rather than losing the enquiry.
	}

	$sent = wp_mail(
		KDCV_CONTACT_INBOX,
		sprintf( '[kohandezh.com] %s', $subject ? $subject : 'New enquiry' ),
		sprintf(
			"Name: %s\nEmail: %s\nPhone: %s\n\n%s",
			$name, $email, $phone, $message
		),
		array( 'Reply-To: ' . $name . ' <' . $email . '>' )
	);

	return new WP_REST_Response(
		$sent
			? array( 'success' => true )
			: array( 'success' => false, 'message' => 'Delivery failed. Please email directly.' ),
		$sent ? 200 : 502
	);
}
