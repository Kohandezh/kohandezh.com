<?php
/**
 * REST controller.
 *
 * Endpoint:  POST /wp-json/kdcv/v1/ask
 * Body JSON: {question, locale, facts: [{title, body}], source: 'admin'|'visitor', provider?: 'zai'}
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
				),
			) );
		} );
	}

	public static function handle( WP_REST_Request $req ) {
		// Exact contact answers must work even when no model provider is configured.
		$question = trim( (string) $req->get_param( 'question' ) );
		if ( $question === '' || mb_strlen( $question ) > self::MAX_QUESTION ) {
			return new WP_REST_Response( array( 'available' => false, 'reason' => 'bad-question' ), 400 );
		}
		if ( preg_match( '/(contact|phone|mobile|email|تماس|شماره|موبایل|ایمیل|تواصل|اتصال|هاتف|جوال|بريد|kontakt|telefon|e-?mail|contacto|tel[eé]fono|correo|contact|t[eé]l[eé]phone|courriel|iletişim|telefon|e-?posta|联系|电话|邮箱|連絡|電話|メール)/iu', $question ) ) {
			// One number per audience: the Persian site is read from Iran, so it
			// gets the Iranian line; every other language gets the international
			// one, which is also the WhatsApp number.
			$loc    = strtolower( substr( (string) $req->get_param( 'locale' ), 0, 2 ) );
			$phone  = ( 'fa' === $loc ) ? '+98 912 149 1644' : '+1 810 666 2283';
			$answer = 'Email: Kohandezh@hotmail.com | ' . $phone;
			return new WP_REST_Response( array( 'available' => true, 'answer' => $answer, 'provider' => '', 'model' => '' ), 200 );
		}

		// 1. Pick the provider try-order — an explicit request override wins;
		// otherwise Settings decide (default only / random / round-robin).
		// In the rotation modes the list holds every configured provider, so
		// a failing provider is retried on the next one before giving up.
		$requested_provider = sanitize_text_field( (string) $req->get_param( 'provider' ) );
		$candidate_ids = $requested_provider !== ''
			? array( $requested_provider )
			: KDCV_AI_Provider::route_order();

		$candidates = array();
		foreach ( $candidate_ids as $cid ) {
			$built = KDCV_AI_Provider::build( $cid );
			if ( $built && $built->is_ready() ) {
				$candidates[] = $built;
			}
		}
		$provider = ! empty( $candidates ) ? $candidates[0] : null;

		// Build the standard "not available" envelope the JS expects.
		$unavailable = function ( $reason, $status = 503 ) use ( &$provider ) {
			$model_name = $provider ? ( method_exists( $provider, 'default_model' ) ? $provider->default_model() : '' ) : '';
			return new WP_REST_Response( array(
				'available' => false,
				'reason'    => $reason,
				'provider'  => $provider ? '' : '',
				'model'     => $model_name,
			), $status );
		};

		if ( empty( $candidates ) ) {
			// Distinguish "nothing registered/selected" from "selected but keyless".
			$any = KDCV_AI_Provider::build( $requested_provider !== '' ? $requested_provider : null );
			return $unavailable( $any ? 'provider-not-configured' : 'no-provider', 503 );
		}

		// 2. Sanitize input.
		$locale = (string) $req->get_param( 'locale' );
		if ( ! in_array( $locale, self::ALLOWED_LOCALES, true ) ) {
			$locale = 'en';
		}

		$source = (string) $req->get_param( 'source' ) === 'admin' ? 'admin' : 'visitor';

		// 3. Per-IP rate limit.
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
			/* Deliberately NOT named $candidates: that variable already holds the
			   built provider objects from the routing block above, and reusing
			   the name here replaced them with plain strings. The provider loop
			   further down then called ->get_model() on a string, so ANY request
			   carrying an X-Forwarded-For header returned an unauthenticated 500
			   — and would have broken the chat outright the day Cloudflare goes
			   in front, since a proxy sets that header on every request. */
			$xff_parts = array_values( array_filter( array_map( 'trim', explode( ',', $forwarded ) ) ) );
			if ( ! empty( $xff_parts ) ) {
				$ip = $xff_parts[0];
			}
		}
		$ip_hash = md5( (string) $ip . wp_salt() );

		/* 3a. Abuse gate — deterministic and local. The reply is NEVER generated
		   by the model: the system prompt's "only use verified facts" rule is the
		   single thing standing between this assistant and invented claims about
		   the site owner, and loosening it so a cheap model can improvise a
		   comeback trades that away for a troll. Three tiers, then silence —
		   an endlessly sparring bot is something trolls farm for screenshots. */
		$ab_key  = 'kdcv_ab_' . md5( (string) $ip );
		$offense = (int) get_transient( $ab_key );
		if ( self::is_abusive( $question ) ) {
			set_transient( $ab_key, $offense + 1, HOUR_IN_SECONDS );
			$reply = self::abuse_reply( $offense, $locale );
			self::log( $provider, $ip_hash, $locale, $source, $question, $reply, 'abuse_deflected' );
			return new WP_REST_Response( array(
				'available' => true, 'answer' => $reply, 'provider' => '', 'model' => '',
			), 200 );
		}
		if ( $offense >= 3 ) {
			// Already cut off: stay closed for the rest of the hour even on a
			// civil question, otherwise the tier-3 close means nothing.
			$reply = self::abuse_reply( $offense, $locale );
			self::log( $provider, $ip_hash, $locale, $source, $question, $reply, 'abuse_deflected' );
			return new WP_REST_Response( array(
				'available' => true, 'answer' => $reply, 'provider' => '', 'model' => '',
			), 200 );
		}

		/* 3b. Duplicate-submit guard. The suggestion chips fire the same question
		   7-13 times within one second (a listener re-bound on every panel
		   render), which instantly exhausts RATE_PER_MIN = 10 — that is the whole
		   of the 58% rate_limited / 13% upstream_429 in the chat log. Serving the
		   first answer back to the duplicates costs no provider call and no quota,
		   and works regardless of which frontend copy is live in production. */
		$dup_key = 'kdcv_dup_' . md5( (string) $ip . '|' . $locale . '|' . $question );
		$cached  = get_transient( $dup_key );
		if ( '__pending__' === $cached ) {
			// First copy is still waiting on the provider. Tell the duplicates to
			// hold rather than handing them the sentinel as an answer.
			return $unavailable( 'duplicate-in-flight', 429 );
		}
		if ( is_string( $cached ) && '' !== $cached ) {
			return new WP_REST_Response( array(
				'available' => true, 'answer' => $cached, 'provider' => '', 'model' => '',
			), 200 );
		}
		set_transient( $dup_key, '__pending__', 15 );

		$bucket  = 'kdcv_ai_' . md5( (string) $ip );
		$count   = (int) get_transient( $bucket );
		if ( $count <= 0 ) {
			set_transient( $bucket, 1, MINUTE_IN_SECONDS );
		} elseif ( $count >= self::RATE_PER_MIN ) {
			delete_transient( $dup_key );
			self::log( $provider, $ip_hash, $locale, $source, $question, '', 'rate_limited' );
			return $unavailable( 'rate-limited', 429 );
		} else {
			set_transient( $bucket, $count + 1, MINUTE_IN_SECONDS );
		}

		// 4. Build prompts.
		$facts_block   = self::build_facts_block( $req->get_param( 'facts' ) );
		$site_block    = self::build_site_index_block( $question, $locale );
		$profile_block = self::build_profile_block( $locale );
		$system        = self::build_system_prompt( $locale );
		$user          = ( $profile_block !== '' ? "VERIFIED PROFILE:\n" . $profile_block . "\n\n" : '' )
			. "VERIFIED SITE-WIDE FACTS:\n" . $site_block
			. "\n\nFACTS FROM THE CURRENT PAGE:\n" . ( $facts_block !== '' ? $facts_block : '(none extracted)' )
			. "\n\nQUESTION: " . $question;

		// 5. Ask, walking the candidate list. Each failure is logged under the
		// provider that produced it, then the next configured provider gets
		// the same prompts. Only when every candidate fails does the visitor
		// see an error.
		$result     = array( 'ok' => false, 'status' => 'no-provider', 'reason' => '' );
		$model_used = '';
		foreach ( $candidates as $candidate ) {
			$provider   = $candidate;
			$model_used = $provider->get_model();
			$result     = $provider->ask( $system, $user, array() );
			if ( ! empty( $result['ok'] ) ) {
				break;
			}
			self::log( $provider, $ip_hash, $locale, $source, $question,
				mb_substr( (string) $result['reason'], 0, 500 ),
				(string) $result['status'] );
		}

		if ( empty( $result['ok'] ) ) {
			// Release the sentinel so a genuine retry is not stonewalled for 15s.
			delete_transient( $dup_key );
			return $unavailable( $result['status'], 502 );
		}

		// Hold the answer briefly so same-second duplicates are served from here.
		set_transient( $dup_key, (string) $result['answer'], 5 );

		self::log( $provider, $ip_hash, $locale, $source, $question, $result['answer'], 'ok', $model_used );

		return new WP_REST_Response( array(
			'available' => true,
			'answer'    => $result['answer'],
			'provider'  => '',  // opaque to client
			'model'     => $model_used,
		), 200 );
	}

	private static function build_facts_block( $facts_in ) {
		if ( ! is_array( $facts_in ) ) {
			return '';
		}
		$out = array();
		foreach ( $facts_in as $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			$title = isset( $f['title'] )
				? mb_substr( trim( wp_strip_all_tags( (string) $f['title'] ) ), 0, self::MAX_FACT_LEN )
				: '';
			$body  = isset( $f['body'] )
				? mb_substr( trim( wp_strip_all_tags( (string) $f['body'] ) ), 0, self::MAX_FACT_LEN )
				: '';
			if ( $title === '' && $body === '' ) {
				continue;
			}
			$line = $title;
			if ( $body !== '' ) {
				$line .= ' — ' . $body;
			}
			$out[] = '• ' . $line;
			if ( count( $out ) >= self::MAX_FACTS ) {
				break;
			}
		}
		return implode( "\n", $out );
	}

	/**
	 * The always-present grounding: the theme's llms.txt profile, in the
	 * visitor's language when a localized variant exists. Without this the
	 * model only sees whatever the keyword search below happens to match,
	 * and a question like "what is his expertise?" — whose words appear in
	 * no post — got an honest but useless "I could not find it".
	 *
	 * The files live in the active theme (the sync script ships all ten),
	 * so the profile updates with the theme and needs no plugin setting.
	 * Returns '' when the theme has no llms.txt — the plugin stays
	 * theme-independent.
	 */
	private static function build_profile_block( $locale ) {
		$dir  = trailingslashit( get_template_directory() );
		$path = $dir . ( 'en' === $locale ? 'llms.txt' : $locale . '-llms.txt' );
		if ( ! is_readable( $path ) ) {
			$path = $dir . 'llms.txt';
		}
		if ( ! is_readable( $path ) ) {
			return '';
		}
		$txt = trim( (string) file_get_contents( $path ) );
		// Generous cap — the largest of the ten files is ~4.5 KB.
		return mb_substr( $txt, 0, 6000 );
	}

	/**
	 * Retrieve relevant published content from WordPress itself. This makes the
	 * chatbot site-wide: posts, pages and the public Knowledge Base entities are
	 * searchable even when the visitor is currently on a different page.
	 * Contact details are pinned so a contact answer never depends on ranking.
	 */
	private static function build_site_index_block( $question, $locale ) {
		$lines = array(
			'• Official contact email: Kohandezh@hotmail.com',
			// Same rule as the direct contact answer above: the model must not
			// offer a visitor the number meant for the other audience.
			( 'fa' === strtolower( substr( (string) $locale, 0, 2 ) )
				? '• Official mobile number: +98 912 149 1644 (Iran)'
				: '• Official mobile number: +1 810 666 2283 (WhatsApp, international)' ),
			'• Official website: https://kohandezh.com/',
			'• Mohammad Ali Kohandezh is the CEO of Kohan System Farda (KSF).',
		);

		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$post_types = array_values( array_diff( $post_types, array( 'attachment' ) ) );
		$query      = new WP_Query( array(
			'post_type'              => $post_types,
			'post_status'            => 'publish',
			's'                      => sanitize_text_field( $question ),
			'posts_per_page'         => 12,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		) );

		foreach ( $query->posts as $post ) {
			$title = trim( wp_strip_all_tags( get_the_title( $post ) ) );
			$body  = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
			$body  = preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( $body ) ) );
			$body  = mb_substr( trim( $body ), 0, 420 );
			$url   = get_permalink( $post );
			if ( $title !== '' || $body !== '' ) {
				$lines[] = sprintf( '• %s — %s — Source: %s', $title, $body, esc_url_raw( $url ) );
			}
		}
		wp_reset_postdata();

		return implode( "\n", $lines );
	}

	/**
	 * Fold a question down to bare tokens before matching.
	 *
	 * Persian needs this more than English: the same word arrives with Arabic
	 * ي/ك instead of Persian ی/ک, with a ZWNJ inside it, with Arabic-Indic
	 * digits, or with a letter held down (کییییر). Matching the raw string
	 * catches only whichever form the author happened to type.
	 */
	private static function normalize_for_match( $text ) {
		$text = mb_strtolower( $text, 'UTF-8' );
		$text = str_replace(
			array( "\xE2\x80\x8C", "\xE2\x80\x8D", 'ي', 'ك', 'ۀ', 'ﻻ', 'أ', 'إ', 'آ' ),
			array( ' ',            '',             'ی', 'ک', 'ه', 'لا', 'ا', 'ا', 'ا' ),
			$text
		);
		// Collapse runs of the same letter: "kiiiir" and "کییییر" fold to one.
		$text = preg_replace( '/(.)\1{2,}/u', '$1', $text );
		// Punctuation and separators become spaces so tokens stand alone.
		$text = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Whole-token profanity check.
	 *
	 * Deliberately NOT a substring scan. "کس" is a substring of "کسب" — and
	 * "کسب و کار" (business) is a core topic of a CEO's site, so a substring
	 * filter would reject the site's most legitimate questions. A filter that
	 * turns real visitors away is worse than no filter. Same trap in Latin:
	 * "kos" sits inside "kosher", "kir" inside "kirkuk".
	 *
	 * Kept short on purpose. Every added root widens the false-positive
	 * surface; extend it from the real `abuse_deflected` log rows, not by
	 * guessing.
	 */
	private static function is_abusive( $question ) {
		$norm   = self::normalize_for_match( $question );
		$tokens = $norm === '' ? array() : explode( ' ', $norm );

		// Tokens that are a swear outright, in Persian script and in Pinglish.
		$exact = array(
			'کیر', 'کیرم', 'کیری', 'کس', 'کصو', 'جنده', 'کونی', 'گوه', 'عوضی', 'حروم زاده',
			'kir', 'kiram', 'kiri', 'kos', 'koso', 'koskesh', 'jende', 'kooni', 'kuni',
			'goh', 'avazi', 'madarjende', 'pedarsag',
			'fuck', 'fucking', 'fucker', 'shit', 'bitch', 'asshole', 'cunt', 'dick', 'bastard',
		);
		foreach ( $tokens as $t ) {
			if ( in_array( $t, $exact, true ) ) {
				return true;
			}
		}

		/* Compounds that only read as abuse together. "دهن" and "مادر" are both
		   perfectly ordinary words; "کیرم دهنت" is not. Matching the pair keeps
		   the innocent halves usable. */
		$phrases = array(
			'کیرم دهنت', 'کیر دهنت', 'مادرت', 'مادر جنده', 'کس نگو', 'کس شعر',
			'kiram dahanet', 'kiram dahanat', 'kir dahanet', 'madarjende', 'madar jende',
			'kos nago', 'kos sher', 'koss sher',
		);
		foreach ( $phrases as $p ) {
			if ( false !== mb_strpos( $norm, self::normalize_for_match( $p ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Tiered, pre-written reply. $offense is the count BEFORE this message.
	 *
	 * Tier 1 lands the rebuke and then points back at the job — the aim is to
	 * recover the conversation, not to win it. Tier 2 states the consequence.
	 * Tier 3 stops: a bot that trades insults indefinitely is a toy trolls farm.
	 *
	 * Only fa and en carry the idiom (together they are 99% of logged traffic,
	 * and "do you kiss your mother with that mouth" happens to exist in both).
	 * The other eight locales get a neutral line — a translated joke nobody can
	 * QA across Arabic, Chinese and Japanese is risk with no upside.
	 */
	private static function abuse_reply( $offense, $locale ) {
		$tier = $offense < 1 ? 1 : ( $offense < 2 ? 2 : 3 );

		if ( 'fa' === $locale ) {
			$by_tier = array(
				1 => 'مادرت رو با همین دهن بوس می‌کنی؟ حالا اگر سؤال واقعی داری، در خدمتم.',
				2 => 'بیا سالم ادامه بدیم. بار بعد جواب نمی‌دم.',
				3 => 'این گفتگو بسته شد.',
			);
			return $by_tier[ $tier ];
		}

		if ( 'en' === $locale ) {
			$by_tier = array(
				1 => "Do you kiss your mother with that mouth? If you have a real question, I'm here.",
				2 => "Let's keep this civil. One more and I stop replying.",
				3 => 'This conversation is closed.',
			);
			return $by_tier[ $tier ];
		}

		return 3 === $tier
			? 'This conversation is closed.'
			: "Let's keep this civil — I'm here for questions about the CV.";
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
			'You are Kohan, the official website assistant embedded on kohandezh.com. ' .
			'Answer the visitor question using ONLY the verified site-wide facts and current-page facts supplied below. ' .
			'Do not invent information. If the facts do not contain the answer, say briefly that you could not find it on kohandezh.com. ' .
			/* The log shows the model inventing spellings of the owner's own name
			   — "محمد علی کوهنده" is a different person's name, and bare "کهندژ"
			   drops the ZWNJ. On a site whose entire asset is this one name, an
			   instruction to "spell it correctly" is not enough: the allowed form
			   and the forbidden forms both have to be spelled out. */
			'The subject\'s name is written exactly "Mohammad Ali Kohandezh" in Latin script, and exactly "محمدعلی کهن‌دژ" in Persian ' .
			'— with a zero-width non-joiner (U+200C) between کهن and دژ, never a space and never nothing. ' .
			'Never write "کوهنده", "کهندژ", "کهن دژ", "Kouhandeh", or any other spelling of the name. ' .
			'When asked how to contact Mohammad, always provide the official email and both official mobile numbers exactly as supplied. ' .
			'Keep the answer concise (1 to 4 short sentences). ' .
			'Reply in %s. Do not use markdown, bullet lists, or code blocks.',
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
