<?php
/**
 * Site-wide retrieval and citation rules for the avatar's assistant.
 *
 * KDCV_AI_Site_Index (kohandezh-ai-hub) ranks every page, post, profile and
 * book section for a question; KDCV_AI_REST turns a model reply into an
 * answer whose [n] markers match the source links shown under it, or quotes
 * the passages itself when no model is available. Both are exercised here
 * without WordPress: the handful of WP helpers they touch are stubbed.
 *
 * Run: php _tooling/tests/ai-site-index.test.php
 */
define( 'KDCV_TESTING', true );
define( 'ABSPATH', __DIR__ . '/' );
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }

require_once __DIR__ . '/../wp-theme/kohandezh-ai-hub/includes/class-kdcv-site-index.php';
require_once __DIR__ . '/../wp-theme/kohandezh-ai-hub/includes/class-kdcv-rest.php';

$checks = 0;
function check( $ok, $label ) {
	global $checks;
	++$checks;
	if ( ! $ok ) {
		fwrite( STDERR, 'FAIL ' . $label . "\n" );
		exit( 1 );
	}
}
function doc( $title, $url, $text, $lang, $kind = 'book' ) {
	$text = KDCV_AI_Site_Index::clean_text( $text );
	return array(
		't' => $title, 'u' => $url, 'x' => $text, 'k' => $kind, 'l' => $lang,
		'n' => KDCV_AI_Site_Index::normalize( $title . ' ' . $text ),
		'h' => KDCV_AI_Site_Index::normalize( $title ),
	);
}

// Normalization: Arabic letter forms, Persian/Arabic digits, ZWNJ, diacritics.
check( 'کتاب یک ۱۲۳' === KDCV_AI_Site_Index::normalize( 'كتاب يك 123' ) || 'کتاب یک 123' === KDCV_AI_Site_Index::normalize( 'كتاب يك ۱۲۳' ), 'Arabic kaf/yeh become Persian' );
check( 'nist ai 100 1' === KDCV_AI_Site_Index::normalize( 'NIST AI 100-1' ), 'Latin lowercased, punctuation split' );
check( 'می شود' === KDCV_AI_Site_Index::normalize( "می\u{200C}شود" ), 'ZWNJ splits words' );
check( '2026' === KDCV_AI_Site_Index::normalize( '۲۰۲۶' ), 'Persian digits become ASCII' );
check( 'هوش' === KDCV_AI_Site_Index::normalize( 'هُوش' ), 'diacritics dropped' );

// Tokens: stopwords out, light plural stripping, capped.
$t = KDCV_AI_Site_Index::tokens( 'حاکمیت هوش مصنوعی چیست؟' );
check( array( 'حاکمیت', 'هوش', 'مصنوعی' ) === $t, 'Persian question words are stopwords: ' . implode( ',', $t ) );
$t = KDCV_AI_Site_Index::tokens( 'What are the standards for AI governance?' );
check( in_array( 'standard', $t, true ) && in_array( 'governance', $t, true ) && ! in_array( 'what', $t, true ), 'English plurals stripped, stopwords dropped' );
check( array() === KDCV_AI_Site_Index::tokens( 'چیست؟ what is the' ), 'a question of only stopwords has no tokens' );
check( count( KDCV_AI_Site_Index::tokens( str_repeat( 'alpha beta gamma delta epsilon zeta eta theta iota kappa lambda mu nu xi ', 2 ) ) ) <= 12, 'at most 12 tokens' );

// Cleaning: markdown links keep their words; bare URLs and list bullets go.
$c = KDCV_AI_Site_Index::clean_text( "- [Home / CV](https://kohandezh.com/fa): full profile\n- see https://example.com/x now" );
check( 'Home / CV: full profile see now' === $c, 'markdown links and URLs stripped: ' . $c );

// Cleaning: the source's own [n] markers and table rules never reach an answer.
$c = KDCV_AI_Site_Index::clean_text( 'AI RMF [2] و راهنما [11, 12] --- | جدول | ** پررنگ **' );
check( false === strpos( $c, '[' ) && false === strpos( $c, '---' ) && false === strpos( $c, '|' ) && false === strpos( $c, '*' ), 'reference markers, rules and markdown removed: ' . $c );

// Chunking on sentence boundaries, within budget.
$long   = str_repeat( 'This is a sentence about governance. ', 80 );
$chunks = KDCV_AI_Site_Index::chunk( $long, 300, 5 );
check( count( $chunks ) === 5, 'chunk limit respected' );
foreach ( $chunks as $ch ) {
	check( mb_strlen( $ch ) <= 300 && '.' === substr( $ch, -1 ), 'chunks end on a sentence and fit the budget' );
}

// Ranking.
$docs = array(
	doc( 'کارکرد حاکمیت (GOVERN)', 'https://x.test/read/?s=KDJ-AI-2026E2-P02-C08-S05#KDJ-AI-2026E2-P02-C08-S05', 'کارکرد حاکمیت در چارچوب مدیریت ریسک هوش مصنوعی سیاست‌ها و نقش‌ها را تعیین می‌کند.', 'fa' ),
	doc( 'سنجش (MEASURE)', 'https://x.test/read/?s=KDJ-AI-2026E2-P02-C08-S07#KDJ-AI-2026E2-P02-C08-S07', 'کارکرد سنجش ریسک‌ها را با روش‌های کمّی و کیفی ارزیابی می‌کند.', 'fa' ),
	doc( 'About Mohammad', 'https://x.test/', 'Mohammad Ali Kohandezh is the CEO of Kohan System Farda (KSF), an enterprise IT and AI company.', 'en', 'profile' ),
	doc( 'About Mohammad', 'https://x.test/', 'He also leads quantum readiness work.', 'en', 'profile' ),
	doc( 'Blog: Quantum readiness', 'https://x.test/blog/quantum/', 'Post-quantum cryptography migration starts with an inventory of keys.', 'en', 'post' ),
);
$avg = 0;
foreach ( $docs as $d ) {
	$avg += strlen( $d['n'] );
}
$avg /= count( $docs );
$rank = function ( $q, $locale = 'fa', $page = array() ) use ( $docs, $avg ) {
	return KDCV_AI_Site_Index::rank( $docs, $avg, KDCV_AI_Site_Index::tokens( $q ), $q, $locale, $page, 6 );
};
$r = $rank( 'کارکرد حاکمیت چیست؟' );
check( 'کارکرد حاکمیت (GOVERN)' === $r[0]['title'], 'the section about GOVERN ranks first' );
$r = $rank( 'كاركرد حاكميت' );
check( 'کارکرد حاکمیت (GOVERN)' === $r[0]['title'], 'Arabic-keyboard spelling still finds it' );
$r = $rank( 'Who is the CEO of KSF?', 'en' );
check( 'About Mohammad' === $r[0]['title'] && 'https://x.test/' === $r[0]['url'], 'profile answers who-is questions' );
check( 1 === count( array_filter( $r, function ( $h ) { return 'https://x.test/' === $h['url']; } ) ), 'one hit per page: the best chunk only' );
$r = $rank( 'post quantum cryptography', 'en' );
check( 'Blog: Quantum readiness' === $r[0]['title'], 'a blog post is found' );
check( false !== mb_stripos( $r[0]['excerpt'], 'quantum' ), 'the excerpt carries the query' );
// "ریسک" appears once in each section's body: the section in view decides.
$r = $rank( 'ریسک', 'fa', array( 'section' => array( 'id' => 'KDJ-AI-2026E2-P02-C08-S07' ) ) );
check( 'سنجش (MEASURE)' === $r[0]['title'], 'the section in view wins a tie (S07)' );
$r = $rank( 'ریسک', 'fa', array( 'section' => array( 'id' => 'KDJ-AI-2026E2-P02-C08-S05' ) ) );
check( 'کارکرد حاکمیت (GOVERN)' === $r[0]['title'], 'the section in view wins a tie (S05)' );
check( array() === $rank( 'xylophone zebra' ), 'nothing matches -> no hits' );

// Citations: kept, renumbered by first mention, unknown markers dropped.
$hits = array(
	array( 'title' => 'A', 'url' => 'https://x.test/a', 'excerpt' => 'a' ),
	array( 'title' => 'B', 'url' => 'https://x.test/b', 'excerpt' => 'b' ),
	array( 'title' => 'C', 'url' => 'https://x.test/c', 'excerpt' => 'c' ),
);
$cite = new ReflectionMethod( 'KDCV_AI_REST', 'cite' );
if ( PHP_VERSION_ID < 80100 ) { $cite->setAccessible( true ); } // PHP 7.4 needs it; 8.5 deprecates it
list( $answer, $sources ) = $cite->invoke( null, 'First from C [3]. Then A [1]. Made up [9].', $hits );
check( 'First from C [1]. Then A [2]. Made up.' === $answer, 'renumbered by first mention, unknown marker dropped: ' . $answer );
check( array( 'C', 'A' ) === array_column( $sources, 'title' ), 'sources follow the new numbering' );
list( $answer, $sources ) = $cite->invoke( null, 'No markers at all.', $hits );
check( 'No markers at all.' === $answer && array( 'A', 'B' ) === array_column( $sources, 'title' ), 'no markers: the top passages are still shown' );
list( $answer, $sources ) = $cite->invoke( null, 'Nothing.', array() );
check( array() === $sources, 'no hits: no sources' );

// No model: quote the site, never invent.
$ex = new ReflectionMethod( 'KDCV_AI_REST', 'extractive' );
if ( PHP_VERSION_ID < 80100 ) { $ex->setAccessible( true ); }
list( $answer, $sources ) = $ex->invoke( null, 'fa', $hits );
check( 0 === strpos( $answer, 'این‌ها را در سایت پیدا کردم:' ) && false !== strpos( $answer, '• A: a [1]' ) && 3 === count( $sources ), 'extractive answer lists the top passages with links' );
check( 'a' === $sources[0]['excerpt'], 'each extractive source carries its snippet for the answer card' );
list( $answer, $sources ) = $ex->invoke( null, 'en', array() );
check( false !== strpos( $answer, 'could not find' ) && array() === $sources, 'nothing found is said plainly' );

echo "PASS ai-site-index: normalization, tokens, cleaning, chunking, ranking, citations and extractive answers ({$checks} checks)\n";
