<?php
/**
 * REST controller.
 *
 * Endpoint:  POST /wp-json/kdcv/v1/ask
 * Body JSON: {question, locale, facts: [{title, body}], source: 'admin'|'visitor', provider?: 'zai',
 *             page?: {url, title, kind, section: {id, title, summary}},
 *             history?: [{role: 'user'|'assistant', text}]}
 * Reply:     {available, answer, sources: [{title, url}], mode: 'llm'|'extractive'|'contact', model}
 *
 * Every answer is grounded: the question is first run against the site
 * index (KDCV_AI_Site_Index — all posts, pages, profiles and every book
 * section), the best passages go to the model as numbered context, and the
 * reply's [n] citations come back as links. With no provider configured, or
 * when every provider fails, the best passages are quoted directly instead
 * of showing an error.
 *
 * The widget doesn't know (or care) which provider answers — it just hits
 * this URL and reads `available` + `answer`. The provider is chosen here
 * based on Settings (default) or the request's `provider` field.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KDCV_AI_REST {

	const RATE_PER_MIN = 10;
	const MAX_QUESTION = 500;
	const MAX_FACTS    = 80;
	const MAX_FACT_LEN = 300;
	const ALLOWED_LOCALES = array( 'en', 'fa', 'ar', 'de', 'es', 'fr', 'tr', 'zh', 'ja', 'ru' );

	public static function register() {
		add_action( 'rest_api_init', function () {
			register_rest_route( 'kdcv/v1', '/ask', array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'question' => array( 'required' => true,  'type' => 'string' ),
					'locale'   => array( 'required' => false, 'type' => 'string' ),
					'facts'    => array( 'required' => false, 'type' => 'array' ),
					'source'   => array( 'required' => false, 'type' => 'string' ),
					'provider' => array( 'required' => false, 'type' => 'string' ),
					'page'     => array( 'required' => false, 'type' => 'object' ),
					'history'  => array( 'required' => false, 'type' => 'array' ),
				),
			) );
		} );
	}

	public static function handle( WP_REST_Request $req ) {
		$question = trim( (string) $req->get_param( 'question' ) );
		if ( $question === '' || mb_strlen( $question ) > self::MAX_QUESTION ) {
			return new WP_REST_Response( array( 'available' => false, 'reason' => 'bad-question' ), 400 );
		}
		$locale = strtolower( substr( (string) $req->get_param( 'locale' ), 0, 2 ) );
		if ( ! in_array( $locale, self::ALLOWED_LOCALES, true ) ) {
			$locale = 'en';
		}

		// Exact contact answers must work even when no model provider is configured.
		if ( preg_match( '/(contact|phone|mobile|email|تماس|شماره|موبایل|ایمیل|تواصل|اتصال|هاتف|جوال|بريد|kontakt|telefon|e-?mail|contacto|tel[eé]fono|correo|contact|t[eé]l[eé]phone|courriel|iletişim|telefon|e-?posta|联系|电话|邮箱|連絡|電話|メール)/iu', $question ) ) {
			// One number per audience: the Persian site is read from Iran, so it
			// gets the Iranian line; every other language gets the international
			// one, which is also the WhatsApp number.
			$phone  = ( 'fa' === $locale ) ? '+98 912 149 1644' : '+1 810 666 2283';
			$answer = 'Email: Kohandezh@hotmail.com | ' . $phone;
			return new WP_REST_Response( array( 'available' => true, 'answer' => $answer, 'sources' => array(), 'mode' => 'contact', 'provider' => '', 'model' => '' ), 200 );
		}

		$source  = (string) $req->get_param( 'source' ) === 'admin' ? 'admin' : 'visitor';
		$page    = self::sanitize_page( $req->get_param( 'page' ) );
		$history = self::sanitize_history( $req->get_param( 'history' ) );

		// Per-IP rate limit — before any work, model or not.
		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';
		$forwarded = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) )
			: '';
		/* Only honour X-Forwarded-For when the request actually arrived from a
		   configured proxy. Otherwise any caller sets it per request and the
		   10/min limit protecting a paid LLM key never triggers. */
		$trusted = defined( 'KDCV_TRUSTED_PROXIES' ) ? (array) KDCV_TRUSTED_PROXIES : array();
		if ( $forwarded !== '' && in_array( $ip, $trusted, true ) ) {
			$xff_parts = array_values( array_filter( array_map( 'trim', explode( ',', $forwarded ) ) ) );
			if ( ! empty( $xff_parts ) ) {
				$ip = $xff_parts[0];
			}
		}
		$ip_hash = md5( (string) $ip . wp_salt() );
		$bucket  = 'kdcv_ai_' . md5( (string) $ip );
		$count   = (int) get_transient( $bucket );
		if ( $count <= 0 ) {
			set_transient( $bucket, 1, MINUTE_IN_SECONDS );
		} elseif ( $count >= self::RATE_PER_MIN ) {
			self::log( null, $ip_hash, $locale, $source, $question, '', 'rate_limited' );
			return new WP_REST_Response( array( 'available' => false, 'reason' => 'rate-limited' ), 429 );
		} else {
			set_transient( $bucket, $count + 1, MINUTE_IN_SECONDS );
		}

		// 1. Retrieve. A follow-up ("and the second one?") is searched together
		// with the previous question, so it still finds its topic.
		$search_q = $question;
		$last_user = '';
		foreach ( array_reverse( $history ) as $turn ) {
			if ( 'user' === $turn['role'] ) {
				$last_user = $turn['text'];
				break;
			}
		}
		$hits = array();
		try {
			$hits = KDCV_AI_Site_Index::search( $search_q, $locale, $page, 6 );
			if ( count( $hits ) < 2 && '' !== $last_user ) {
				$hits = KDCV_AI_Site_Index::search( $question . ' ' . $last_user, $locale, $page, 6 );
			}
		} catch ( Throwable $e ) {
			$hits = array();
		}

		// 2. Providers, in try-order. An explicit request override wins.
		$requested_provider = sanitize_text_field( (string) $req->get_param( 'provider' ) );
		$candidate_ids      = $requested_provider !== ''
			? array( $requested_provider )
			: KDCV_AI_Provider::route_order();
		$candidates = array();
		foreach ( $candidate_ids as $cid ) {
			$built = KDCV_AI_Provider::build( $cid );
			if ( $built && $built->is_ready() ) {
				$candidates[] = $built;
			}
		}

		// 3. Ask, walking the candidate list; each failure is logged under the
		// provider that produced it and the next one gets the same prompt.
		$provider   = null;
		$result     = array( 'ok' => false, 'status' => 'no-provider', 'reason' => '' );
		$model_used = '';
		if ( ! empty( $candidates ) ) {
			$system = self::build_system_prompt( $locale );
			$user   = self::build_user_prompt( $question, $locale, $hits, $page, $history, $req->get_param( 'facts' ) );
			foreach ( $candidates as $candidate ) {
				$provider   = $candidate;
				$model_used = $provider->get_model();
				$result     = $provider->ask( $system, $user, array( 'max_tokens' => 600 ) );
				if ( ! empty( $result['ok'] ) ) {
					break;
				}
				self::log( $provider, $ip_hash, $locale, $source, $question,
					mb_substr( (string) $result['reason'], 0, 500 ),
					(string) $result['status'] );
			}
		}

		if ( ! empty( $result['ok'] ) ) {
			list( $answer, $sources ) = self::cite( (string) $result['answer'], $hits );
			self::log( $provider, $ip_hash, $locale, $source, $question, $answer, 'ok', $model_used );
			return new WP_REST_Response( array(
				'available' => true,
				'answer'    => $answer,
				'sources'   => $sources,
				'mode'      => 'llm',
				'provider'  => '',  // opaque to client
				'model'     => $model_used,
			), 200 );
		}

		// 4. No model answer: quote the site itself.
		list( $answer, $sources ) = self::extractive( $locale, $hits );
		self::log( $provider, $ip_hash, $locale, $source, $question, $answer, empty( $candidates ) ? 'extractive' : 'extractive_fallback' );
		return new WP_REST_Response( array(
			'available' => true,
			'answer'    => $answer,
			'sources'   => $sources,
			'mode'      => 'extractive',
			'provider'  => '',
			'model'     => '',
		), 200 );
	}

	/** {url, title, kind, section{id,title,summary}} — same-site URLs only. */
	private static function sanitize_page( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		$url = isset( $raw['url'] ) ? esc_url_raw( (string) $raw['url'] ) : '';
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( '' !== $url && wp_parse_url( $url, PHP_URL_HOST ) === $home ) {
			$out['url'] = $url;
		}
		foreach ( array( 'title' => 200, 'kind' => 24 ) as $k => $max ) {
			if ( isset( $raw[ $k ] ) && is_string( $raw[ $k ] ) ) {
				$out[ $k ] = mb_substr( sanitize_text_field( $raw[ $k ] ), 0, $max );
			}
		}
		if ( isset( $raw['section'] ) && is_array( $raw['section'] ) ) {
			$sec = array();
			foreach ( array( 'id' => 80, 'title' => 200, 'summary' => 400 ) as $k => $max ) {
				if ( isset( $raw['section'][ $k ] ) && is_string( $raw['section'][ $k ] ) ) {
					$sec[ $k ] = mb_substr( sanitize_text_field( $raw['section'][ $k ] ), 0, $max );
				}
			}
			if ( isset( $sec['id'] ) && ! preg_match( '/^[A-Za-z0-9_-]{1,80}$/', $sec['id'] ) ) {
				unset( $sec['id'] );
			}
			if ( $sec ) {
				$out['section'] = $sec;
			}
		}
		return $out;
	}

	/** Last 8 turns, roles user|assistant, 600 chars each. */
	private static function sanitize_history( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( array_slice( $raw, -8 ) as $turn ) {
			if ( ! is_array( $turn ) || ! isset( $turn['text'] ) || ! is_string( $turn['text'] ) ) {
				continue;
			}
			$role = ( isset( $turn['role'] ) && 'user' === $turn['role'] ) ? 'user' : 'assistant';
			$text = trim( mb_substr( wp_strip_all_tags( $turn['text'] ), 0, 600 ) );
			if ( '' !== $text ) {
				$out[] = array( 'role' => $role, 'text' => $text );
			}
		}
		return $out;
	}

	private static function build_user_prompt( $question, $locale, array $hits, array $page, array $history, $facts ) {
		$parts   = array();
		$profile = self::build_profile_block( $locale );
		if ( '' !== $profile ) {
			$parts[] = "VERIFIED PROFILE:\n" . mb_substr( $profile, 0, 4000 );
		}
		$parts[] = "VERIFIED SITE-WIDE FACTS:\n" . self::pinned_facts( $locale );
		if ( $hits ) {
			$ctx = array();
			foreach ( $hits as $i => $h ) {
				$ctx[] = '[' . ( $i + 1 ) . '] ' . $h['title'] . ' — ' . $h['url'] . "\n" . $h['excerpt'];
			}
			$parts[] = "SITE PASSAGES (cite as [n]):\n" . implode( "\n\n", $ctx );
		} else {
			$parts[] = "SITE PASSAGES: (nothing on the site matched this question)";
		}
		$facts_block = self::build_facts_block( $facts );
		if ( '' !== $facts_block ) {
			$parts[] = "FACTS FROM THE CURRENT PAGE:\n" . $facts_block;
		}
		if ( $page ) {
			$line = 'CURRENT PAGE: ' . ( $page['title'] ?? '' ) . ( isset( $page['url'] ) ? ' — ' . $page['url'] : '' );
			if ( isset( $page['section'] ) ) {
				$line .= "\nSECTION IN VIEW: " . ( $page['section']['title'] ?? '' ) . ( isset( $page['section']['summary'] ) ? ' — ' . $page['section']['summary'] : '' );
			}
			$parts[] = $line;
		}
		if ( $history ) {
			$conv = array();
			foreach ( $history as $t ) {
				$conv[] = ( 'user' === $t['role'] ? 'Visitor: ' : 'Kohan: ' ) . $t['text'];
			}
			$parts[] = "CONVERSATION SO FAR:\n" . implode( "\n", $conv );
		}
		$parts[] = 'QUESTION: ' . $question;
		return implode( "\n\n", $parts );
	}

	private static function pinned_facts( $locale ) {
		return implode( "\n", array(
			'• Official contact email: Kohandezh@hotmail.com',
			( 'fa' === $locale
				? '• Official mobile number: +98 912 149 1644 (Iran)'
				: '• Official mobile number: +1 810 666 2283 (WhatsApp, international)' ),
			'• Official website: https://kohandezh.com/',
			'• Mohammad Ali Kohandezh is the CEO of Kohan System Farda (KSF).',
		) );
	}

	/**
	 * Keep the passages the answer actually cites, renumbered 1..n in order
	 * of first mention, so the links under the answer match its markers.
	 *
	 * @return array{0:string,1:array<int,array{title:string,url:string}>}
	 */
	private static function cite( $answer, array $hits ) {
		$answer = trim( $answer );
		$map    = array();
		if ( preg_match_all( '/\[(\d{1,2})\]/', $answer, $m ) ) {
			foreach ( $m[1] as $n ) {
				$i = (int) $n - 1;
				if ( isset( $hits[ $i ] ) && ! isset( $map[ $i ] ) ) {
					$map[ $i ] = count( $map ) + 1;
				}
			}
		}
		$answer = preg_replace_callback( '/\[(\d{1,2})\]/', function ( $mm ) use ( $map ) {
			$i = (int) $mm[1] - 1;
			return isset( $map[ $i ] ) ? '[' . $map[ $i ] . ']' : '';
		}, $answer );
		$answer  = trim( preg_replace( '/[ \t]+([.,،؛!?؟])/u', '$1', $answer ) );
		$sources = array();
		foreach ( $map as $i => $n ) {
			$sources[] = array( 'title' => $hits[ $i ]['title'], 'url' => $hits[ $i ]['url'] );
		}
		// No markers: still show where the facts came from (the top passages).
		if ( ! $sources ) {
			foreach ( array_slice( $hits, 0, 2 ) as $h ) {
				$sources[] = array( 'title' => $h['title'], 'url' => $h['url'] );
			}
		}
		return array( $answer, $sources );
	}

	/**
	 * No model: the best passages, as short cards. `answer` stays readable on
	 * its own (the admin playground prints only that); the avatar's chat
	 * renders `sources[].excerpt` as cards under the lead line instead.
	 */
	private static function extractive( $locale, array $hits ) {
		$lead = array(
			'fa' => 'این‌ها را در سایت پیدا کردم:',
			'en' => 'Here is what I found on the site:',
			'ar' => 'هذا ما وجدته في الموقع:',
			'de' => 'Das habe ich auf der Website gefunden:',
			'es' => 'Esto es lo que encontré en el sitio:',
			'fr' => 'Voici ce que j’ai trouvé sur le site :',
			'tr' => 'Sitede şunları buldum:',
			'zh' => '我在网站上找到了这些：',
			'ja' => 'サイト内で見つかった内容です：',
			'ru' => 'Вот что я нашёл на сайте:',
		);
		$none = array(
			'fa' => 'در محتوای سایت چیزی دقیقاً دربارهٔ این پرسش پیدا نکردم. با واژه‌های دیگری بپرس، یا از فرم تماس استفاده کن.',
			'en' => 'I could not find this on kohandezh.com. Try other words, or use the contact form.',
			'ar' => 'لم أجد هذا في الموقع. جرّب كلمات أخرى أو استخدم نموذج التواصل.',
		);
		if ( ! $hits ) {
			return array( $none[ $locale ] ?? $none['en'], array() );
		}
		$take    = array_slice( $hits, 0, 3 );
		$lines   = array( $lead[ $locale ] ?? $lead['en'] );
		$sources = array();
		foreach ( $take as $i => $h ) {
			$snippet   = self::snippet( $h['excerpt'], 200 );
			$lines[]   = '• ' . $h['title'] . ': ' . $snippet . ' [' . ( $i + 1 ) . ']';
			$sources[] = array( 'title' => $h['title'], 'url' => $h['url'], 'excerpt' => $snippet );
		}
		return array( implode( "\n", $lines ), $sources );
	}

	/** Cut at a sentence (or word) boundary near $max characters. */
	private static function snippet( $text, $max ) {
		$text = trim( (string) $text );
		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}
		$cut = mb_substr( $text, 0, $max );
		if ( preg_match( '/^(.{60,}[.!?؟。])\s/su', $cut . ' ', $m ) ) {
			return trim( $m[1] );
		}
		return rtrim( preg_replace( '/\s+\S*$/u', '', $cut ), ' ،,:;' ) . '…';
	}

	private static function build_system_prompt( $locale ) {
		$lang_names = array(
			'en' => 'English', 'fa' => 'Persian (Farsi)', 'ar' => 'Arabic',
			'de' => 'German', 'es' => 'Spanish', 'fr' => 'French',
			'tr' => 'Turkish', 'zh' => 'Simplified Chinese', 'ja' => 'Japanese',
			'ru' => 'Russian',
		);
		$lang_name = isset( $lang_names[ $locale ] ) ? $lang_names[ $locale ] : 'English';
		return sprintf(
			'You are Kohan, the digital twin and official assistant of kohandezh.com (the site of Mohammad Ali Kohandezh). ' .
			'Answer ONLY from the verified profile, the verified site-wide facts, the numbered SITE PASSAGES and the current-page facts supplied below. ' .
			'After every sentence that uses a passage, cite it as [n] with that passage\'s number. ' .
			'Do not invent anything: if the supplied material does not contain the answer, say briefly that you could not find it on kohandezh.com and suggest the contact form. ' .
			'Use the conversation so far to understand follow-up questions, and the current page / section in view to resolve words like "this section" or "this page". ' .
			'When asked how to contact Mohammad, give the official email and mobile number exactly as supplied. ' .
			'Keep it short: 2 to 5 sentences. ' .
			'Reply in %s. Plain text only: no markdown, no bullet lists, no code blocks.',
			$lang_name
		);
	}

	private static function log( $provider, $ip_hash, $locale, $source, $question, $answer, $status, $model = '' ) {
		$provider_id = '';
		if ( $provider instanceof KDCV_AI_Provider ) {
			// Reverse-lookup provider id from registry by class name.
			foreach ( KDCV_AI_Provider::ids() as $id ) {
				$built = KDCV_AI_Provider::build( $id );
				if ( $built && get_class( $built ) === get_class( $provider ) ) {
					$provider_id = $id;
					break;
				}
			}
		}
		if ( $model === '' && $provider instanceof KDCV_AI_Provider ) {
			$model = $provider->get_model();
		}
		KDCV_AI_Logger::add( array(
			'ip_hash'  => $ip_hash,
			'locale'   => $locale,
			'source'   => $source,
			'provider' => $provider_id,
			'model'    => $model,
			'status'   => $status,
			'question' => $question,
			'answer'   => $answer,
		) );
	}
}
