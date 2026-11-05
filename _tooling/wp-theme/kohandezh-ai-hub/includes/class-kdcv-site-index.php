<?php
/**
 * Site-wide retrieval for the assistant (the "R" in RAG).
 *
 * One small index of everything a visitor can read on the site:
 *   - every published post, page and public custom post type, in chunks;
 *   - the theme's llms profiles (one per language);
 *   - anything other plugins add through the `kdcv_ai_site_documents`
 *     filter — the Knowledge plugin adds every section of every book.
 *
 * The index is a JSON file in uploads/kdcv-ai/, rebuilt automatically when
 * content changes (a revision counter bumped on save/delete, plus a
 * signature other plugins extend through `kdcv_ai_site_index_signature` —
 * the book adds its corpus file's size and mtime, so uploading a new book
 * edition re-indexes on the next question).
 *
 * Scoring is BM25 over normalized text (Persian/Arabic letter forms, digits,
 * ZWNJ, diacritics), with a title bonus, a phrase bonus, a bonus for the page
 * the visitor is on, and a small one for the visitor's language.
 *
 * @package KohandezhAIHub
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'KDCV_TESTING' ) ) {
	exit;
}

class KDCV_AI_Site_Index {

	const FORMAT        = 3;
	const CHUNK_CHARS   = 900;
	const MAX_CHUNKS    = 8;
	const MAX_POSTS     = 600;
	const EXCERPT_CHARS = 300;
	const REV_OPTION    = 'kdcv_ai_index_rev';

	/** @var array|null In-request cache. */
	private static $index = null;

	public static function register() {
		$bump = array( __CLASS__, 'bump' );
		add_action( 'save_post', $bump );
		add_action( 'deleted_post', $bump );
		add_action( 'trashed_post', $bump );
		add_action( 'switch_theme', $bump );
		add_action( 'upgrader_process_complete', $bump );
	}

	/** Content changed: the next question rebuilds the index. */
	public static function bump() {
		if ( function_exists( 'update_option' ) ) {
			update_option( self::REV_OPTION, (int) get_option( self::REV_OPTION, 0 ) + 1, false );
		}
		self::$index = null;
	}

	/* ------------------------------------------------------------------ */
	/* Text normalization                                                  */
	/* ------------------------------------------------------------------ */

	public static function normalize( $s ) {
		$s = mb_strtolower( (string) $s, 'UTF-8' );
		$s = strtr( $s, array(
			'ي' => 'ی', 'ى' => 'ی', 'ئ' => 'ی', 'ك' => 'ک', 'ة' => 'ه', 'ۀ' => 'ه',
			'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ؤ' => 'و',
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			"\u{200C}" => ' ', "\u{200D}" => '', 'ـ' => '',
		) );
		$s = preg_replace( '/[\x{064B}-\x{065F}\x{0670}]/u', '', $s );
		$s = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $s );
		return trim( preg_replace( '/\s+/u', ' ', $s ) );
	}

	private static function stopwords() {
		static $set = null;
		if ( null !== $set ) {
			return $set;
		}
		$words = 'و در به از که این آن را با برای است هست بود شود شد می نمی هم یا تا بر هر چه چی چیه چیست چطور چگونه کدام کجا کی آیا ای ها های هایی یک بین روی زیر پس اگر نیز باید توان کرد کند کنید کنم کن کنه دارد دارند داری دارم داره ما من تو او شما انها ایشان اش ات ام ترین تر خود همین همان درباره دربارهی مورد لطفا بگو بگید بگویید توضیح بده بدهید بدید میخواهم میخوام خواهم بیشتر کمی رو چیزی کسی یعنی '
			. 'a an the of to in on at for from by with about as is are was were be been being do does did what which who whom whose how why when where can could would should will shall may might me my i you your he his she her it its we our they their this that these those there here and or but not no yes please tell explain give show list more some any all into than then so if also just like know '
			. 'في من على إلى الى عن ما ماذا هل هو هي هذا هذه التي الذي او ثم مع كيف لماذا متى أين اين '
			. 'der die das und ist ein eine zu von mit wie was wer le la les et est un une des du de que qui el los las es en por con como qué y ve bir bu ne nasıl и в на что как это о';
		$set = array_fill_keys( explode( ' ', self::normalize( $words ) ), true );
		return $set;
	}

	/**
	 * Query tokens: normalized, stopwords dropped, light plural stripping.
	 *
	 * @return array<int,string>
	 */
	public static function tokens( $text ) {
		$stop = self::stopwords();
		$out  = array();
		foreach ( explode( ' ', self::normalize( $text ) ) as $t ) {
			if ( '' === $t || isset( $stop[ $t ] ) || mb_strlen( $t, 'UTF-8' ) < 2 ) {
				continue;
			}
			if ( preg_match( '/^[a-z]{5,}s$/', $t ) ) {
				$t = substr( $t, 0, -1 );
			} elseif ( mb_strlen( $t, 'UTF-8' ) > 4 && preg_match( '/(های|ها)$/u', $t ) ) {
				$t = preg_replace( '/(های|ها)$/u', '', $t );
			}
			$out[ $t ] = true;
			if ( count( $out ) >= 12 ) {
				break;
			}
		}
		return array_keys( $out );
	}

	private static function lang_of( $text ) {
		$fa = preg_match_all( '/[\x{0600}-\x{06FF}]/u', (string) $text );
		$la = preg_match_all( '/[A-Za-z]/', (string) $text );
		return $fa > $la ? 'fa' : 'en';
	}

	/* ------------------------------------------------------------------ */
	/* Building                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Split text into chunks of about CHUNK_CHARS on sentence boundaries.
	 *
	 * @return array<int,string>
	 */
	public static function chunk( $text, $max = self::CHUNK_CHARS, $limit = self::MAX_CHUNKS ) {
		$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
		if ( '' === $text ) {
			return array();
		}
		$parts  = preg_split( '/(?<=[.!?؟。])\s+/u', $text );
		$chunks = array();
		$cur    = '';
		foreach ( $parts as $p ) {
			if ( '' !== $cur && mb_strlen( $cur . ' ' . $p, 'UTF-8' ) > $max ) {
				$chunks[] = $cur;
				$cur      = '';
				if ( count( $chunks ) >= $limit ) {
					break;
				}
			}
			$cur = '' === $cur ? $p : $cur . ' ' . $p;
		}
		if ( '' !== $cur && count( $chunks ) < $limit ) {
			$chunks[] = $cur;
		}
		// A single sentence longer than the budget still gets cut.
		return array_map( function ( $c ) use ( $max ) {
			return mb_strlen( $c, 'UTF-8' ) > $max * 1.6 ? mb_substr( $c, 0, (int) ( $max * 1.6 ), 'UTF-8' ) : $c;
		}, $chunks );
	}

	/**
	 * Strip what only gets in the way of retrieval and quoting: the source's
	 * own reference markers ("[11]" — they would collide with the [n] the
	 * assistant cites with), table rules and markdown punctuation.
	 */
	public static function clean_text( $text ) {
		$text = wp_strip_all_tags( (string) $text );
		// Markdown links keep their words, lose their address; bare URLs go.
		$text = preg_replace( '/!?\[([^\]]*)\]\([^)\s]*\)/u', '$1', $text );
		$text = preg_replace( '#\bhttps?://\S+#u', '', $text );
		$text = preg_replace( '/\(\s*\)/u', '', $text );
		// List bullets and heading marks at line starts.
		$text = preg_replace( '/(^|\n)\s*(?:[-*•+]|\d{1,2}[.)])\s+/u', '$1', $text );
		$text = preg_replace( '/\[\d{1,3}(?:[,\x{060C}\s\-–]+\d{1,3})*\]/u', '', $text );
		$text = preg_replace( '/(?:^|\s)[-–—=|:]{3,}(?=\s|$)/u', ' ', $text );
		$text = preg_replace( '/[|#*_`>]+/u', ' ', $text );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	private static function make_doc( $title, $url, $text, $kind, $lang = '' ) {
		$title = trim( wp_strip_all_tags( (string) $title ) );
		$text  = self::clean_text( $text );
		if ( '' === $title && '' === $text ) {
			return null;
		}
		return array(
			't' => mb_substr( $title, 0, 200, 'UTF-8' ),
			'u' => (string) $url,
			'x' => $text,
			'n' => self::normalize( $title . ' ' . $text ),
			'h' => self::normalize( $title ),
			'k' => (string) $kind,
			'l' => '' !== $lang ? $lang : self::lang_of( $title . ' ' . $text ),
		);
	}

	/** Every source, as docs. */
	public static function collect() {
		$docs = array();

		// 1. WordPress content.
		$types = array_values( array_diff( get_post_types( array( 'public' => true ), 'names' ), array( 'attachment' ) ) );
		$q     = new WP_Query( array(
			'post_type'              => $types,
			'post_status'            => 'publish',
			'posts_per_page'         => self::MAX_POSTS,
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => true,
		) );
		foreach ( $q->posts as $post ) {
			if ( post_password_required( $post ) ) {
				continue;
			}
			$title = get_the_title( $post );
			$url   = get_permalink( $post );
			$body  = strip_shortcodes( (string) $post->post_content );
			$body  = preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', ' ', $body );
			$body  = wp_strip_all_tags( $body );
			if ( '' !== trim( (string) $post->post_excerpt ) ) {
				$body = trim( $post->post_excerpt ) . ' ' . $body;
			}
			$kind   = 'page' === $post->post_type ? 'page' : $post->post_type;
			$chunks = self::chunk( $body );
			if ( array() === $chunks ) {
				$chunks = array( '' );
			}
			foreach ( $chunks as $i => $c ) {
				$d = self::make_doc( $title, $url, $c, $kind );
				if ( $d ) {
					$docs[] = $d;
				}
			}
		}
		wp_reset_postdata();

		// 2. The theme's llms profiles, one per language, split at headings.
		$dir = trailingslashit( get_template_directory() );
		foreach ( glob( $dir . '*llms.txt' ) ?: array() as $file ) {
			$base = basename( $file );
			$lang = 'llms.txt' === $base ? 'en' : substr( $base, 0, 2 );
			$txt  = (string) file_get_contents( $file );
			$secs = preg_split( '/^#{1,3}\s+/m', $txt );
			foreach ( $secs as $sec ) {
				$sec = trim( $sec );
				if ( '' === $sec ) {
					continue;
				}
				$lines = explode( "\n", $sec, 2 );
				$head  = trim( $lines[0] );
				$body  = isset( $lines[1] ) ? $lines[1] : '';
				foreach ( self::chunk( $body ) as $c ) {
					$d = self::make_doc( $head, home_url( '/' . ( 'en' === $lang ? '' : $lang . '/' ) ), $c, 'profile', $lang );
					if ( $d ) {
						$docs[] = $d;
					}
				}
			}
		}

		// 3. Other plugins (the book).
		$extra = apply_filters( 'kdcv_ai_site_documents', array() );
		if ( is_array( $extra ) ) {
			foreach ( $extra as $e ) {
				if ( ! is_array( $e ) || empty( $e['url'] ) ) {
					continue;
				}
				$d = self::make_doc( $e['title'] ?? '', $e['url'], $e['text'] ?? '', $e['kind'] ?? 'extra', $e['lang'] ?? '' );
				if ( $d ) {
					$docs[] = $d;
				}
			}
		}
		return $docs;
	}

	private static function signature() {
		$theme = get_template_directory() . '/llms.txt';
		$parts = array(
			self::FORMAT,
			defined( 'KDCV_AI_VERSION' ) ? KDCV_AI_VERSION : '',
			(int) get_option( self::REV_OPTION, 0 ),
			get_template(),
			is_readable( $theme ) ? filemtime( $theme ) : 0,
			(string) apply_filters( 'kdcv_ai_site_index_signature', '' ),
		);
		return md5( implode( '|', $parts ) );
	}

	private static function file() {
		$up = wp_upload_dir( null, false );
		if ( ! empty( $up['error'] ) ) {
			return '';
		}
		return trailingslashit( $up['basedir'] ) . 'kdcv-ai/site-index.json';
	}

	/** The index, from disk when current, rebuilt when not. */
	public static function index() {
		if ( null !== self::$index ) {
			return self::$index;
		}
		$sig  = self::signature();
		$file = self::file();
		if ( '' !== $file && is_readable( $file ) ) {
			$data = json_decode( (string) file_get_contents( $file ), true );
			if ( is_array( $data ) && ( $data['sig'] ?? '' ) === $sig && isset( $data['docs'] ) && is_array( $data['docs'] ) ) {
				return self::$index = $data;
			}
		}
		$docs = self::collect();
		$tot  = 0;
		foreach ( $docs as $d ) {
			$tot += strlen( $d['n'] );
		}
		$data = array(
			'sig'   => $sig,
			'built' => gmdate( 'c' ),
			'avg'   => $docs ? $tot / count( $docs ) : 1,
			'docs'  => $docs,
		);
		if ( '' !== $file ) {
			$dir = dirname( $file );
			if ( wp_mkdir_p( $dir ) ) {
				if ( ! file_exists( $dir . '/.htaccess' ) ) {
					// Public content only, but no reason to serve the raw index.
					@file_put_contents( $dir . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
					@file_put_contents( $dir . '/index.html', '' );
				}
				$tmp = $file . '.' . wp_generate_password( 6, false ) . '.tmp';
				if ( false !== @file_put_contents( $tmp, wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ) {
					@rename( $tmp, $file );
				}
				if ( file_exists( $tmp ) ) {
					@unlink( $tmp );
				}
			}
		}
		return self::$index = $data;
	}

	/* ------------------------------------------------------------------ */
	/* Searching                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Best passages for a question.
	 *
	 * @param string $question Visitor question.
	 * @param string $locale   Two-letter locale.
	 * @param array  $page     {url, section:{id}} of the page the visitor is on.
	 * @param int    $limit    Max hits.
	 * @return array<int,array{title:string,url:string,excerpt:string,kind:string,score:float}>
	 */
	public static function search( $question, $locale = 'en', array $page = array(), $limit = 6 ) {
		$tokens = self::tokens( $question );
		if ( array() === $tokens ) {
			return array();
		}
		$index = self::index();
		return self::rank( $index['docs'], (float) $index['avg'], $tokens, $question, $locale, $page, $limit );
	}

	/** Pure ranking step — separated so it can be tested without WordPress. */
	public static function rank( array $docs, $avg, array $tokens, $question, $locale, array $page, $limit ) {
		$n_docs = count( $docs );
		if ( 0 === $n_docs ) {
			return array();
		}
		$short  = array();
		foreach ( $tokens as $t ) {
			$short[ $t ] = mb_strlen( $t, 'UTF-8' ) <= 3;
		}
		// Pass 1: term counts and document frequency.
		$counts = array();
		$df     = array_fill_keys( $tokens, 0 );
		foreach ( $docs as $i => $d ) {
			$hay = ' ' . $d['n'] . ' ';
			foreach ( $tokens as $t ) {
				$c = $short[ $t ] ? substr_count( $hay, ' ' . $t ) : substr_count( $d['n'], $t );
				if ( $c > 0 ) {
					$counts[ $i ][ $t ] = $c;
					++$df[ $t ];
				}
			}
		}
		if ( array() === $counts ) {
			return array();
		}
		$phrase    = self::normalize( implode( ' ', $tokens ) );
		$page_path = isset( $page['url'] ) ? (string) wp_parse_url( (string) $page['url'], PHP_URL_PATH ) : '';
		$page_sec  = isset( $page['section']['id'] ) ? (string) $page['section']['id'] : '';
		$k1        = 1.2;
		$b         = 0.6;
		$avg       = max( 1.0, (float) $avg );
		$scored    = array();
		foreach ( $counts as $i => $hits ) {
			$d     = $docs[ $i ];
			$len   = strlen( $d['n'] );
			$score = 0.0;
			foreach ( $hits as $t => $c ) {
				$idf    = log( 1 + ( $n_docs - $df[ $t ] + 0.5 ) / ( $df[ $t ] + 0.5 ) );
				$tf     = ( $c * ( $k1 + 1 ) ) / ( $c + $k1 * ( 1 - $b + $b * $len / $avg ) );
				$score += $idf * $tf;
				if ( false !== strpos( $d['h'], $t ) ) {
					$score += 0.7 * $idf;
				}
			}
			$coverage = count( $hits ) / count( $tokens );
			$score   *= 0.35 + 0.65 * $coverage;
			if ( count( $tokens ) > 1 && false !== strpos( $d['n'], $phrase ) ) {
				$score += 2.5;
			}
			if ( '' !== $page_sec && false !== strpos( $d['u'], $page_sec ) ) {
				$score *= 1.6;
			} elseif ( '' !== $page_path && $page_path === (string) wp_parse_url( $d['u'], PHP_URL_PATH ) && '/' !== $page_path ) {
				$score *= 1.25;
			}
			if ( $d['l'] === $locale ) {
				$score *= 1.15;
			}
			$scored[] = array( $score, $i );
		}
		usort( $scored, function ( $a, $b ) {
			return $b[0] <=> $a[0];
		} );
		$out  = array();
		$seen = array();
		foreach ( $scored as $s ) {
			$d   = $docs[ $s[1] ];
			$key = $d['u'] . '|' . $d['t'];
			if ( isset( $seen[ $key ] ) ) {
				continue; // one chunk per page: the best one
			}
			$seen[ $key ] = true;
			$out[]        = array(
				'title'   => $d['t'],
				'url'     => $d['u'],
				'excerpt' => self::excerpt( $d['x'], $tokens ),
				'kind'    => $d['k'],
				'score'   => round( $s[0], 3 ),
			);
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/** The sentences of a chunk that carry the query, up to EXCERPT_CHARS. */
	public static function excerpt( $text, array $tokens ) {
		$sents = preg_split( '/(?<=[.!?؟。])\s+/u', (string) $text );
		$best  = array();
		foreach ( $sents as $i => $s ) {
			$n   = self::normalize( $s );
			$hit = 0;
			foreach ( $tokens as $t ) {
				if ( false !== strpos( $n, $t ) ) {
					++$hit;
				}
			}
			$best[] = array( $hit, $i );
		}
		usort( $best, function ( $a, $b ) {
			return $b[0] <=> $a[0] ?: $a[1] <=> $b[1];
		} );
		$start = isset( $best[0] ) ? $best[0][1] : 0;
		$out   = '';
		for ( $i = $start; $i < count( $sents ); $i++ ) {
			$next = '' === $out ? $sents[ $i ] : $out . ' ' . $sents[ $i ];
			if ( '' !== $out && mb_strlen( $next, 'UTF-8' ) > self::EXCERPT_CHARS ) {
				break;
			}
			$out = $next;
		}
		if ( mb_strlen( $out, 'UTF-8' ) > self::EXCERPT_CHARS + 80 ) {
			$out = rtrim( mb_substr( $out, 0, self::EXCERPT_CHARS, 'UTF-8' ) ) . '…';
		}
		return $out;
	}
}
