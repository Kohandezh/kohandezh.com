<?php
/**
 * Reader renderer, reader overlay and chapter pagination.
 *
 *   php _tooling/tests/ai-book-renderer.test.php Books/ai-governance
 *
 * Table policy under test: exactly one leading and one trailing pipe are row
 * delimiters, `\|` is a literal pipe, short rows are padded with empty cells,
 * and a body row wider than the header EXTENDS the header with empty heading
 * cells (no cell is dropped or merged). Headed tables of 2–5 columns carry
 * data-label for stacked mobile cards; wider or headerless tables scroll.
 *
 * Figure policy: a `[FIGURE …]` marker never reaches the page. A marker that
 * matches a `figures` entry renders a Persian note with its caption/page; a
 * marker followed by a caption on the same line renders that caption as the
 * note; a bare marker is dropped.
 */
define( 'ABSPATH', __DIR__ );
define( 'KBK_FEATURE_AI_BOOK', true );
define( 'KBK_PLUGIN_FILE', __FILE__ );
define( 'KBK_VERSION', 'test' );

$root = $argv[1] ?? '';
if ( '' === $root || ! is_dir( $root ) ) {
	fwrite( STDERR, "Usage: php ai-book-renderer.test.php /path/to/book-root\n" );
	exit( 2 );
}
define( 'KBK_AI_BOOK_ROOT', $root );
$GLOBALS['kbk_test_query'] = array();

function add_filter( $name, $callback, $priority = 10 ) {}
function add_action( $name, $callback, $priority = 10 ) {}
function add_rewrite_rule( $regex, $query, $position ) {}
function get_query_var( $name ) { return $GLOBALS['kbk_test_query'][ $name ] ?? ''; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }

require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-artifacts.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book-repository.php';
require_once __DIR__ . '/../wp-theme/kohandezh-knowledge/includes/class-kbk-ai-book.php';

$checks = 0;
function check( bool $ok, string $message ): void {
	global $checks;
	$checks++;
	if ( ! $ok ) {
		fwrite( STDERR, "FAIL {$message}\n" );
		exit( 1 );
	}
}
/** Cells of the first <tr> after $marker, as rendered inner HTML. */
function row_cells( string $html, int $row ): array {
	preg_match_all( '#<tr>(.*?)</tr>#u', $html, $rows );
	preg_match_all( '#<t[hd][^>]*>(.*?)</t[hd]>#u', $rows[1][ $row ] ?? '', $cells );
	return $cells[1];
}
$md = array( 'KBK_AI_Book', 'render_reader_markdown' );

// --- Tables -----------------------------------------------------------------
$normal = $md( "| واژه | تعریف | منبع |\n| --- | --- | --- |\n| ریسک | احتمال آسیب | NIST |" );
check( array( 'واژه', 'تعریف', 'منبع' ) === row_cells( $normal, 0 ), 'normal table header' );
check( array( 'ریسک', 'احتمال آسیب', '<span dir="ltr">NIST</span>' ) === row_cells( $normal, 1 ), 'normal table row' );
check( false !== strpos( $normal, 'ab-table-cards' ) && false !== strpos( $normal, 'data-label="تعریف"' ) && false !== strpos( $normal, 'data-cols="3"' ), 'headed 3-column table stacks as labelled cards' );

$leading = $md( "| اصطلاح | معادل | توضیح |\n| --- | --- | --- |\n| | Accountability | پاسخ‌گویی |" );
check( array( '', '<span dir="ltr">Accountability</span>', 'پاسخ‌گویی' ) === row_cells( $leading, 1 ), 'empty LEADING cell keeps its column (glossary shift bug)' );

$trailing = $md( "| اصطلاح | معادل | توضیح |\n| --- | --- | --- |\n| شفافیت | Transparency | |" );
check( array( 'شفافیت', '<span dir="ltr">Transparency</span>', '' ) === row_cells( $trailing, 1 ), 'empty TRAILING cell keeps its column' );

$short = $md( "| الف | ب | پ |\n| --- | --- | --- |\n| ۱ |" );
check( array( '۱', '', '' ) === row_cells( $short, 1 ), 'short row is padded to header width' );

$escaped = $md( "| عبارت | معنا |\n| --- | --- |\n| a \\| b | یا |" );
$escaped_cells = row_cells( $escaped, 1 );
check( 2 === count( $escaped_cells ) && false !== strpos( $escaped_cells[0], '|' ) && 'یا' === $escaped_cells[1], 'escaped pipe stays inside its cell' );

$wide_row = $md( "| الف | ب |\n| --- | --- |\n| ۱ | ۲ | ۳ |" );
check( 3 === count( row_cells( $wide_row, 0 ) ) && '' === row_cells( $wide_row, 0 )[2] && '۳' === row_cells( $wide_row, 1 )[2], 'a row wider than the header extends the header; no cell is dropped' );

$rtl = $md( "| ستون نخست | ستون دوم |\n| :--- | ---: |\n| متن فارسی | **پررنگ** |" );
check( array( 'متن فارسی', '<strong>پررنگ</strong>' ) === row_cells( $rtl, 1 ) && false !== strpos( $rtl, 'ab-table-narrow' ), 'Persian RTL table with alignment row and inline markup' );

$headless = $md( "| الف | ب |\n| پ | ت |" );
check( false === strpos( $headless, '<thead>' ) && false === strpos( $headless, 'data-label' ) && false !== strpos( $headless, 'ab-table-cards ab-table-titled' ) && array( 'پ', 'ت' ) === row_cells( $headless, 1 ), 'headingless 2-column table keeps both rows and stacks as cards, no labels' );
check( 2 === substr_count( $headless, '<td class="ab-cell-title">' ) && false !== strpos( $headless, '<td class="ab-cell-title">الف</td><td>ب</td>' ), 'the first cell of each headerless row is the card title' );
$headless3 = $md( "| الف | ب | پ |\n| ت | ث | ج |" );
check( false !== strpos( $headless3, 'ab-table-titled' ) && false === strpos( $headless3, 'ab-table-narrow' ), 'headerless 3-column tables stack as titled cards too' );
$headless4 = $md( "| a | b | c | d |\n| e | f | g | h |" );
check( false !== strpos( $headless4, 'ab-table-scroll' ) && false === strpos( $headless4, 'ab-cell-title' ), 'headerless tables of four or more columns still scroll' );

$six = $md( "| a | b | c | d | e | f |\n| --- | --- | --- | --- | --- | --- |\n| 1 | 2 | 3 | 4 | 5 | 6 |" );
check( false !== strpos( $six, 'ab-table-scroll' ) && false === strpos( $six, 'ab-table-cards' ), 'tables wider than five columns scroll instead of stacking' );

// --- Figures ----------------------------------------------------------------
$bare = $md( "پیش از تصویر.\n\n[FIGURE NIST-AI-100-1::F0001] \n\nپس از تصویر." );
check( false === stripos( $bare, '[FIGURE' ) && false === strpos( $bare, 'ab-figure-note' ) && false !== strpos( $bare, 'پیش از تصویر.' ), 'bare figure marker is hidden' );
$captioned = $md( '[FIGURE NIST-AI-100-1::F0008] شکل ۴. مشخصات سامانه‌های اعتمادپذیر' );
check( false === stripos( $captioned, '[FIGURE' ) && false !== strpos( $captioned, 'ab-figure-note' ) && false !== strpos( $captioned, 'در نسخهٔ PDF' ) && false !== strpos( $captioned, 'شکل ۴.' ), 'marker with a caption becomes a Persian figure note' );
$matched = $md( '[FIGURE NIST-AI-100-1::F0002]', array( array( 'figure_id' => 'NIST-AI-100-1::F0002', 'caption_fa' => 'چرخهٔ عمر', 'page' => 12 ) ) );
check( false !== strpos( $matched, 'صفحهٔ ۱۲ در نسخهٔ PDF' ) && false !== strpos( $matched, 'چرخهٔ عمر' ), 'marker matching a figures entry shows its caption and Persian page' );
$inline = $md( 'متن [FIGURE X::F1] ادامه' );
check( false === stripos( $inline, '[FIGURE' ) && false !== strpos( $inline, 'متن ادامه' ), 'a marker inside running text is removed' );

// --- Headings and lists -----------------------------------------------------
$headings = $md( "# یک\n\n## دو\n\n### سه\n\n###### شش" );
check( false !== strpos( $headings, '<h3>یک</h3>' ) && false !== strpos( $headings, '<h4>دو</h4>' ) && false !== strpos( $headings, '<h5>سه</h5>' ) && false !== strpos( $headings, '<h6>شش</h6>' ), 'present Markdown levels map in order to h3, h4, h5, h6' );
$only_h2 = $md( "## یک\n\nمتن\n\n## دو" );
check( false !== strpos( $only_h2, '<h3>یک</h3>' ) && false !== strpos( $only_h2, '<h3>دو</h3>' ) && false === strpos( $only_h2, '<h4' ), 'a section that only uses ## starts at h3 (no h2→h4 skip)' );
$gappy = $md( "#### عمیق\n\n## بالا\n\n##### پایین" );
check( false !== strpos( $gappy, '<h3>عمیق</h3>' ) && false !== strpos( $gappy, '<h3>بالا</h3>' ) && false !== strpos( $gappy, '<h4>پایین</h4>' ), 'a deeper heading first never skips a level below the section h2' );
$with_ids = $md( "## یک\n\n### دو", array(), 'KDJ-X-S01' );
check( false !== strpos( $with_ids, '<h3 id="KDJ-X-S01-h1">یک</h3>' ) && false !== strpos( $with_ids, '<h4 id="KDJ-X-S01-h2">دو</h4>' ), 'in-section headings get stable ids prefixed by the structural ID' );
$skips = static function ( string $html ): int {
	preg_match_all( '#<h([2-6])\b#', '<h2>' . $html, $m );
	$n = 0;
	for ( $i = 1; $i < count( $m[1] ); $i++ ) {
		if ( (int) $m[1][ $i ] > (int) $m[1][ $i - 1 ] + 1 ) {
			$n++;
		}
	}
	return $n;
};

// --- Footnotes ----------------------------------------------------------------
$fn = $md( "متن با ارجاع[^1] و دوباره[^1] و یتیم[^7].\n\n[^1]: توضیح **پانوشت**.", array(), 'KDJ-X-S02' );
check( false === strpos( $fn, '[^' ), 'no raw footnote mark survives' );
check( 2 === substr_count( $fn, '<sup class="ab-fnref">' ) && false !== strpos( $fn, 'id="KDJ-X-S02-fnref-1-1"' ) && false !== strpos( $fn, 'id="KDJ-X-S02-fnref-1-2"' ) && false !== strpos( $fn, 'href="#KDJ-X-S02-fn-1"' ) && false !== strpos( $fn, '>۱</a></sup>' ), 'a defined reference becomes a Persian-digit superscript link, ids unique per occurrence' );
check( false !== strpos( $fn, '<aside class="ab-footnotes"' ) && false !== strpos( $fn, '<li id="KDJ-X-S02-fn-1" value="1" dir="auto">توضیح <strong>پانوشت</strong>.' ) && 2 === substr_count( $fn, 'class="ab-fn-back"' ) && false !== strpos( $fn, 'href="#KDJ-X-S02-fnref-1-2"' ), 'the definition renders once in the closing list with a back-link per reference' );
check( false === strpos( $fn, 'یتیم<sup' ) && false !== strpos( $fn, 'یتیم.' ) && false === strpos( $fn, 'fn-7' ), 'an orphan reference (no definition) is removed entirely' );
check( 1 === substr_count( $fn, 'توضیح' ), 'the definition line leaves the body flow' );
$legacy = $md( "واژه[^۱۴] و دیگری[^15]\n\n[^]: > ۱۴ نخستین توضیح > 15 دومین توضیح", array(), 'S' );
check( false !== strpos( $legacy, 'id="S-fn-14" value="14"' ) && false !== strpos( $legacy, 'نخستین توضیح' ) && false !== strpos( $legacy, 'id="S-fn-15"' ) && false !== strpos( $legacy, '>۱۴</a></sup>' ) && false === strpos( $legacy, '[^' ) && false === strpos( $legacy, '&gt;' ), 'the converter\'s packed "[^]: > n text > m text" definitions are recognised' );
$table_fn = $md( "| ستون | توضیح |\n| --- | --- |\n| الف[^2] | ب[^9] |\n\n[^2]: پانوشت جدول.", array(), 'T' );
check( false !== strpos( $table_fn, 'الف<sup class="ab-fnref">' ) && false === strpos( $table_fn, '[^9]' ) && false !== strpos( $table_fn, 'پانوشت جدول.' ), 'references inside table cells link too; orphans vanish there as well' );
$no_notes = $md( "بدون پانوشت." );
check( false === strpos( $no_notes, 'ab-footnotes' ), 'no footnote list without footnotes' );
$ordered = $md( "- 3. سوم\n- 4. چهارم\n\n۱. یکم\n۲. دوم\n\n- بی‌شماره" );
check( false !== strpos( $ordered, '<ol><li dir="auto" value="3">سوم</li><li dir="auto" value="4">چهارم</li></ol>' ), 'bullet+number items become one ordered list without the bullet' );
check( false !== strpos( $ordered, '<ol><li dir="auto" value="1">یکم</li><li dir="auto" value="2">دوم</li></ol>' ), 'Persian-digit numbered items are ordered list items' );
check( false !== strpos( $ordered, '<ul><li dir="auto">بی‌شماره</li></ul>' ), 'plain bullets stay an unordered list' );

// --- Bidi and Persian digits --------------------------------------------------
$title = KBK_AI_Book::bidi_html( 'چارچوب مدیریت ریسک هوش مصنوعی (AI RMF 1.0)' );
check( 'چارچوب مدیریت ریسک هوش مصنوعی (<span dir="ltr">AI RMF 1.0</span>)' === $title, 'Latin run inside a Persian title is isolated so the parentheses stay put' );
check( '&lt;<span dir="ltr">b</span>&gt;' === KBK_AI_Book::bidi_html( '<b>' ), 'bidi_html escapes markup' );
check( 'بخش ۵، فصل ۳۸' === KBK_AI_Book::location_label( 'P05', 'C38' ) && '۱۲۳' === KBK_AI_Book::fa_digits( 123 ) && '۱۰٬۴۷۴' === KBK_AI_Book::fa_digits( 10474 ), 'UI numbers use Persian digits' );
check( 'تعریف‌شده در' === KBK_AI_Book::relation_label( 'DEFINED_IN' ) && 'روش ارزیابی' === KBK_AI_Book::entity_type_label( 'EvaluationMethod' ), 'relation codes and entity types have Persian labels' );

// --- Section splitting ----------------------------------------------------------
$para = static function ( int $n ): string {
	return str_repeat( 'واژه ', $n );
};
$long = "## الف\n\n" . $para( 900 ) . "\n\n### الف-۱\n\n" . $para( 900 ) . "\n\n## ب\n\n| ستون | مقدار |\n| --- | --- |\n" . str_repeat( '| ' . $para( 60 ) . " | ۱ |\n", 40 ) . "\n## پ\n\n" . $para( 300 );
$doc = KBK_AI_Book::reader_document( $long, 'L' );
$parts = KBK_AI_Book::section_parts( $doc, 8000 );
check( count( $parts ) >= 3, 'a body over the budget is cut into parts' );
check( 0 === $parts[0][0] && count( $doc['lines'] ) === $parts[ count( $parts ) - 1 ][1], 'parts cover the whole body' );
foreach ( $parts as $i => $range ) {
	if ( $i > 0 ) {
		check( $range[0] === $parts[ $i - 1 ][1], 'parts are contiguous' );
		$before = $range[0] - 1;
		while ( $before > 0 && '' === trim( $doc['lines'][ $before ] ) ) {
			$before--;
		}
		check( isset( $doc['headings'][ $range[0] ] ) || ( '' === trim( $doc['lines'][ $range[0] - 1 ] ) && ! isset( $doc['headings'][ $before ] ) ), 'a cut falls on a heading, or between blocks but never right after a heading' );
	}
	$table_rows = 0;
	for ( $l = $range[0]; $l < $range[1]; $l++ ) {
		$table_rows += 0 === strpos( trim( $doc['lines'][ $l ] ), '|' ) ? 1 : 0;
	}
	check( 0 === $table_rows || 42 === $table_rows, 'a table is never split across parts' );
}
$top_cuts = array_map( static function ( $r ) use ( $doc ) { return $doc['headings'][ $r[0] ]['level'] ?? 0; }, array_slice( $parts, 1 ) );
check( in_array( 3, $top_cuts, true ), 'the top-most heading level is cut first' );
$flat = KBK_AI_Book::section_parts( KBK_AI_Book::reader_document( $para( 4000 ), 'F' ), 8000 );
check( 1 === count( $flat ), 'one block larger than the budget stays whole' );
$entries = "## کتاب‌شناسی\n\n" . implode( "\n\n", array_map( static function ( $n ) use ( $para ) { return '- [' . $n . '] ' . $para( 40 ); }, range( 1, 120 ) ) ) . "\n\n- الف\n- ب\n- پ";
$edoc = KBK_AI_Book::reader_document( $entries, 'E' );
$eparts = KBK_AI_Book::section_parts( $edoc, 8000 );
check( count( $eparts ) > 2, 'a headingless run of blank-separated entries is cut between entries' );
foreach ( $eparts as $i => $range ) {
	check( $range[2] <= 8000, 'each entry part fits the budget' );
	if ( $i > 0 ) {
		check( '' === trim( $edoc['lines'][ $range[0] - 1 ] ) && '' !== trim( $edoc['lines'][ $range[0] ] ), 'entry cuts fall only after a blank line (never inside a list run)' );
	}
}
$cont = KBK_AI_Book::reader_section_html( array( 'structural_id' => 'L', 'fa_text' => $long ), array( $parts[1][0], $parts[1][1] ), true );
check( 1 === preg_match( '#^<h3 id="L-h\d+">#', $cont ) && 0 === $skips( $cont ), 'a continuation part opens with an h3 and never skips levels' );

// --- Definitions and English names -----------------------------------------------
$def_en = KBK_AI_Book::definition_html( '', 'The National Institute of Standards & Technology.' );
check( false !== strpos( $def_en, 'تعریف در منبع (انگلیسی)' ) && false !== strpos( $def_en, '<p class="ab-definition-en" lang="en" dir="ltr">The National Institute of Standards &amp; Technology.</p>' ), 'an English-only definition is labelled as the source\'s English and isolated LTR' );
$def_fa = KBK_AI_Book::definition_html( 'مؤسسهٔ ملی استاندارد', 'English' );
check( false !== strpos( $def_fa, 'مؤسسهٔ ملی استاندارد' ) && false === strpos( $def_fa, 'English' ) && false === strpos( $def_fa, 'انگلیسی' ), 'a Persian definition in the data wins' );
check( '' === KBK_AI_Book::definition_html( '', ' ' ) && '<p class="ab-title-en" lang="en" dir="ltr">AI RMF</p>' === KBK_AI_Book::english_html( 'AI RMF' ), 'empty definitions render nothing; English names are lang=en LTR' );

// --- Markdown links -------------------------------------------------------------
$links_html = KBK_AI_Book::render_reader_markdown( "مقاله [LINK](https://ssrn.com/abstract=4237460) و **پررنگ** و [خطر](javascript:alert(1)) و [نسبی](/fa/books/) و [ایمیل](mailto:a@b.c)" );
check( false !== strpos( $links_html, '<a href="https://ssrn.com/abstract=4237460" rel="noopener noreferrer" target="_blank"><span dir="ltr">LINK</span></a>' ), 'an http(s) Markdown link becomes a new-tab link with noopener noreferrer' );
check( false !== strpos( $links_html, '<strong>پررنگ</strong>' ) && false === strpos( $links_html, 'href="javascript' ) && false === strpos( $links_html, 'href="/fa' ) && false === strpos( $links_html, 'href="mailto' ) && 1 === substr_count( $links_html, '<a ' ), 'non-http(s) links stay literal text; other inline markup still renders' );
check( false !== strpos( $links_html, '[خطر](<span dir="ltr">javascript:alert</span>(1))' ), 'a refused link is printed escaped, as written' );
$quoted = KBK_AI_Book::render_reader_markdown( '[x](https://e.test/a"onmouseover=alert(1))' );
check( false === strpos( $quoted, 'onmouseover=alert(1)"' ) && false === strpos( $quoted, '"onmouseover' ), 'a quote can never break out of the href' );

// --- Pagination -------------------------------------------------------------
$repository = KBK_AI_Book::repository();
check( null !== $repository, 'repository loads' );
$giant = $repository->find_chapter( 'P05', 'C38' );
$pages = KBK_AI_Book::chapter_pages( $giant, 30000 );
$slices = KBK_AI_Book::chapter_page_slices( $giant, 30000 );
check( count( $pages ) > 1, 'a 235K-character chapter is split into pages' );
check( range( 0, count( $giant['sections'] ) - 1 ) === array_values( array_unique( array_merge( ...$pages ) ) ), 'every section appears, in order' );
foreach ( $slices as $page ) {
	$chars = 0;
	foreach ( $page as $slice ) {
		$section = $giant['sections'][ $slice['index'] ];
		if ( null === $slice['range'] ) {
			$chars += KBK_AI_Book::is_omitted_section( $section ) ? 0 : mb_strlen( $section['fa_text'], 'UTF-8' );
		} else {
			$sdoc = KBK_AI_Book::reader_document( KBK_AI_Book::reader_section_body( $section ), $section['structural_id'] );
			$chars += $sdoc['offsets'][ $slice['range'][1] ] - $sdoc['offsets'][ $slice['range'][0] ];
		}
	}
	check( 1 === count( $page ) || $chars <= 30000, 'a page only exceeds the budget when it holds a single indivisible slice' );
}
// The largest section in the book (P03-C22-S11, ~141K characters) is read in parts.
$c22 = $repository->find_chapter( 'P03', 'C22' );
$c22_slices = KBK_AI_Book::chapter_page_slices( $c22, 30000 );
$c22_map = KBK_AI_Book::chapter_section_pages( $c22, 30000 );
$s11 = 'KDJ-AI-2026E1-P03-C22-S11';
$s11_parts = array();
foreach ( $c22_slices as $p => $page ) {
	foreach ( $page as $slice ) {
		if ( $s11 === $c22['sections'][ $slice['index'] ]['structural_id'] ) {
			$s11_parts[ $slice['part'] ] = $p + 1;
			check( null !== $slice['range'] && $slice['parts'] > 1, 'P03-C22-S11 is split into parts' );
		}
	}
}
check( count( $s11_parts ) >= 4 && $c22_map[ $s11 ] === $s11_parts[1] && $c22_map[ $s11 . '-p2' ] === $s11_parts[2], 'the section anchor maps to part 1; continuation anchors to their pages' );
$s11_doc = KBK_AI_Book::reader_document( KBK_AI_Book::reader_section_body( $repository->find_section( $s11 ) ), $s11 );
$last_heading = end( $s11_doc['headings'] );
check( isset( $c22_map[ $last_heading['id'] ] ) && $c22_map[ $last_heading['id'] ] === end( $s11_parts ), 'in-section heading ids are in the page map and land on the page holding them' );
$map = KBK_AI_Book::chapter_section_pages( $giant, 30000 );
$last = $giant['sections'][ count( $giant['sections'] ) - 1 ]['structural_id'];
$GLOBALS['kbk_test_query'] = array();
$url = KBK_AI_Book::section_url( 'P05', 'C38', $last );
check( $map[ $last ] > 1 && false !== strpos( $url, 'kbk_page=' . $map[ $last ] ) && '#' . $last === substr( $url, -strlen( '#' . $last ) ), 'section_url points at the page holding the section' );
$first = $giant['sections'][0]['structural_id'];
check( false === strpos( KBK_AI_Book::section_url( 'P05', 'C38', $first ), 'kbk_page=' ), 'page 1 keeps the historical chapter address' );
$small = $repository->find_chapter( 'P01', 'C01' );
check( 1 === count( KBK_AI_Book::chapter_pages( $small, 30000 ) ), 'a short chapter is one page' );
$GLOBALS['kbk_test_query'] = array( 'kbk_page' => '99' );
$state = KBK_AI_Book::current_reader_page( $giant, null );
check( $state['page'] === $state['pages'], 'an out-of-range page clamps to the last page' );
$GLOBALS['kbk_test_query'] = array();

// --- Graph layout -------------------------------------------------------------
$nodes = array();
foreach ( range( 1, 12 ) as $n ) {
	$nodes[] = array( 'label' => str_repeat( 'مفهوم ', 1 + $n % 4 ) . $n, 'sub' => 'ارجاع می‌دهد به' );
}
$nodes[] = array( 'label' => 'NIST Artificial Intelligence Risk Management Framework (AI RMF 1.0)', 'sub' => 'تعریف‌شده در' );
$nodes[] = array( 'label' => 'رهبری ایالات متحده در هوش مصنوعی: برنامه‌ای برای مشارکت فدرال در توسعهٔ استانداردهای فنی و ابزارهای مرتبط', 'sub' => 'منتشر کرده است' );
$layout = KBK_AI_Book::graph_layout( 'مؤسسهٔ ملی استانداردها و فناوری ایالات متحده (NIST)', $nodes );
check( count( $layout['center']['lines'] ) <= 2 && implode( ' ', $layout['center']['lines'] ) === 'مؤسسهٔ ملی استانداردها و فناوری ایالات متحده (NIST)', 'the centre label wraps onto at most two lines and loses nothing' );
foreach ( $layout['nodes'] as $node ) {
	check( count( $node['lines'] ) <= 2 && implode( ' ', $node['lines'] ) === trim( preg_replace( '/\s+/u', ' ', $node['label'] ) ) && false === strpos( implode( '', $node['lines'] ), '…' ), 'a graph label wraps onto at most two lines, never truncated' );
	$longest = max( array_map( static function ( $l ) { return mb_strlen( $l, 'UTF-8' ); }, $node['lines'] ) );
	check( $node['w'] >= $longest * 14 * 0.56 + 30 - 0.01 && $node['h'] >= 18 * count( $node['lines'] ) + 21, 'each box is sized to its own lines' );
}
check( 'NIST Artificial Intelligence Risk' === KBK_AI_Book::graph_label_lines( 'NIST Artificial Intelligence Risk Management Framework (AI RMF 1.0)' )[0] || 2 === count( KBK_AI_Book::graph_label_lines( 'NIST Artificial Intelligence Risk Management Framework (AI RMF 1.0)' ) ), 'long English labels break between words' );
check( array( 'مفهوم کوتاه' ) === KBK_AI_Book::graph_label_lines( 'مفهوم کوتاه' ), 'a short label stays on one line' );
list( $vx, $vy, $vw, $vh ) = $layout['view'];
foreach ( $layout['nodes'] as $i => $a ) {
	check( $a['x'] - $a['w'] / 2 >= $vx && $a['x'] + $a['w'] / 2 <= $vx + $vw && $a['y'] - $a['h'] / 2 >= $vy && $a['y'] + $a['h'] / 2 <= $vy + $vh, 'graph label box inside the viewBox' );
	foreach ( $layout['nodes'] as $j => $b ) {
		if ( $j > $i ) {
			check( abs( $a['x'] - $b['x'] ) * 2 >= $a['w'] + $b['w'] || abs( $a['y'] - $b['y'] ) * 2 >= $a['h'] + $b['h'], 'graph label boxes never overlap' );
		}
	}
}

// --- Governance titles keep their subject with and without the overlay --------
$gov = array( 'part_id' => 'P02', 'chapter_id' => 'C09', 'section_id' => 'S02' );
check( 'GOVERN 1.1 — الزامات حقوقی و مقرراتی هوش مصنوعی' === KBK_AI_Book::reader_section_title( $gov + array( 'title_fa' => 'GOVERN 1.1', 'reader_overlay_title' => true ) ), 'overlay title GOVERN n.m keeps the hand-written subject' );
check( 'AI Governance 1.1 — الزامات حقوقی و مقرراتی هوش مصنوعی' === KBK_AI_Book::reader_section_title( $gov + array( 'title_fa' => 'AI Governance 1.1 — حاکمیت هوش مصنوعی ۱.۱' ) ), 'canonical AI Governance n.m title keeps the hand-written subject' );
check( 'MEASURE 2.7' === KBK_AI_Book::reader_section_title( array( 'part_id' => 'P02', 'chapter_id' => 'C11', 'section_id' => 'S02', 'title_fa' => 'MEASURE 2.7' ) ), 'other chapters show overlay titles as they are' );

// --- The real overlay, when the book root ships one ----------------------------
$real_overlay = rtrim( $root, '/' ) . '/master/reader-overlay.json';
if ( is_file( $real_overlay ) ) {
	$real = KBK_AI_Book::repository();
	$real_state = $real->overlay_state();
	check( $real_state['applied'] && $real_state['sections'] > 0, 'the shipped reader overlay matches book.json and applies' );
	$ids = json_decode( (string) file_get_contents( rtrim( $root, '/' ) . '/provenance/content_ids.json' ), true );
	foreach ( $ids['ids'] as $content_id => $entry ) {
		$found = $real->find_section( $entry['structural_id'] );
		check( null !== $found && $found['content_id'] === $content_id, 'overlay keeps every content_id ↔ structural_id pair' );
	}
	foreach ( array( 'KDJ-AI-2026E1-P02-C08-S02', 'KDJ-AI-2026E1-P02-C13-S01' ) as $debris ) {
		check( KBK_AI_Book::is_omitted_section( $real->find_section( $debris ) ), "{$debris} is omitted as source debris" );
	}
	check( 'GOVERN 1.1 — الزامات حقوقی و مقرراتی هوش مصنوعی' === KBK_AI_Book::reader_section_title( $real->find_section( 'KDJ-AI-2026E1-P02-C09-S02' ) ), 'C09 headings keep their subject after the overlay merge' );
	// Every footnote link on every reading page resolves on that same page,
	// including the parts of split sections.
	$fn_refs = 0;
	foreach ( $real->parts() as $real_part ) {
		foreach ( $real_part['chapters'] as $real_chapter ) {
			foreach ( KBK_AI_Book::chapter_page_slices( $real_chapter ) as $page_slices ) {
				$page_html = '';
				foreach ( $page_slices as $slice ) {
					$slice_section = $real_chapter['sections'][ $slice['index'] ];
					if ( ! KBK_AI_Book::is_omitted_section( $slice_section ) ) {
						$page_html .= KBK_AI_Book::reader_section_html( $slice_section, $slice['range'], $slice['part'] > 1 );
					}
				}
				preg_match_all( '#href="\#([^"]+-fn(?:ref)?-[0-9-]+)"#', $page_html, $links );
				foreach ( $links[1] as $target ) {
					$fn_refs++;
					check( false !== strpos( $page_html, 'id="' . $target . '"' ), "footnote link #{$target} resolves on its own page" );
				}
				check( false === strpos( $page_html, '[^' ), 'no raw footnote mark on a reading page' );
			}
		}
	}
	check( $fn_refs > 0 || ! $real_state['applied'], 'the book renders footnote links' );
	$c08 = $real->find_chapter( 'P02', 'C08' );
	$c08_pages = KBK_AI_Book::chapter_section_pages( $c08 );
	check( isset( $c08_pages['KDJ-AI-2026E1-P02-C08-S02'] ), 'an omitted section keeps a page (its anchor still lands)' );
	foreach ( $real->parts() as $real_part ) {
		foreach ( $real_part['chapters'] as $real_chapter ) {
			foreach ( $real_chapter['sections'] as $real_section ) {
				$html = KBK_AI_Book::render_reader_markdown( KBK_AI_Book::reader_section_body( $real_section ), is_array( $real_section['figures'] ?? null ) ? $real_section['figures'] : array() );
				check( false === stripos( $html, '[FIGURE' ), 'no raw figure marker in ' . $real_section['structural_id'] );
				check( false === strpos( $html, '[^' ), 'no raw footnote mark in ' . $real_section['structural_id'] );
				check( 0 === $skips( $html ), 'no heading level skip in ' . $real_section['structural_id'] );
			}
		}
	}
}

// --- Reader overlay -----------------------------------------------------------
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kbk-overlay-' . getmypid();
@mkdir( $tmp . '/master', 0777, true );
@mkdir( $tmp . '/provenance', 0777, true );
foreach ( array( 'master/book.json', 'provenance/content_ids.json', 'provenance/citation-registry.json' ) as $file ) {
	copy( rtrim( $root, '/' ) . '/' . $file, $tmp . '/' . $file );
}
$book_hash = hash_file( 'sha256', $tmp . '/master/book.json' );
$target    = 'KDJ-AI-2026E1-P01-C01-S01';
$omitted   = 'KDJ-AI-2026E1-P01-C01-S02';
$write = static function ( array $overlay ) use ( $tmp ): void {
	file_put_contents( $tmp . '/master/reader-overlay.json', json_encode( $overlay, JSON_UNESCAPED_UNICODE ) );
};
$base = array(
	'schema'      => 'kbk-reader-overlay/1',
	'edition_id'  => '2026E1',
	'book_sha256' => $book_hash,
	'generator'   => 'tools/reader_overlay.py',
	'chapters'    => array( 'C01' => array( 'title_fa' => 'عنوان نمایشی فصل' ), 'C99' => array( 'title_fa' => 'ناشناخته' ) ),
	'sections'    => array(
		$target            => array( 'title_fa' => 'عنوان نمایشی', 'fa_text' => 'متن نمایشی.' ),
		$omitted           => array( 'omit' => true ),
		'KDJ-AI-2026E1-P01-C01-S03' => array( 'title_fa' => '', 'fa_text' => 42 ),
		'KDJ-AI-2026E1-P99-C99-S99' => array( 'title_fa' => 'ناشناخته' ),
	),
);
$canonical = new KBK_AI_Book_Repository( $root );
$canonical_section = $canonical->find_section( $target );

$write( $base );
$applied = new KBK_AI_Book_Repository( $tmp );
$section = $applied->find_section( $target );
$state   = $applied->overlay_state();
check( $state['applied'] && 1 === $state['chapters'] && 2 === $state['sections'] && 1 === $state['omitted'], 'valid overlay applies; unknown IDs and empty/non-string values are skipped' );
check( 'عنوان نمایشی' === $section['title_fa'] && 'متن نمایشی.' === $section['fa_text'] && 'عنوان نمایشی' === KBK_AI_Book::reader_section_title( $section ), 'overlay display text reaches the reader' );
check( $section['content_id'] === $canonical_section['content_id'] && $section['structural_id'] === $canonical_section['structural_id'] && $section['content_hash'] === $canonical_section['content_hash'], 'content_id, structural_id and content_hash are never changed' );
check( $applied->find_citation( $target ) === $canonical->find_citation( $target ) && $applied->verify( $target )['sha256'] === $canonical->verify( $target )['sha256'], 'citations and verification hashes are untouched' );
check( KBK_AI_Book::is_omitted_section( $applied->find_section( $omitted ) ) && 'عنوان نمایشی فصل' === $applied->find_chapter( 'P01', 'C01' )['title_fa'], 'omit flag and chapter title applied' );
check( $applied->find_section( 'KDJ-AI-2026E1-P01-C01-S03' )['title_fa'] === $canonical->find_section( 'KDJ-AI-2026E1-P01-C01-S03' )['title_fa'], 'empty overlay values keep canonical text' );

foreach ( array(
	'wrong hash'    => array( 'book_sha256' => str_repeat( '0', 64 ) ),
	'wrong schema'  => array( 'schema' => 'kbk-reader-overlay/2' ),
	'wrong edition' => array( 'edition_id' => '2025E1' ),
	'missing hash'  => array( 'book_sha256' => null ),
) as $case => $patch ) {
	$write( array_merge( $base, $patch ) );
	$ignored = new KBK_AI_Book_Repository( $tmp );
	check( ! $ignored->overlay_state()['applied'] && $ignored->find_section( $target )['title_fa'] === $canonical_section['title_fa'], "overlay with {$case} is ignored (fail closed to canonical)" );
}
file_put_contents( $tmp . '/master/reader-overlay.json', '{not json' );
$broken = new KBK_AI_Book_Repository( $tmp );
check( ! $broken->overlay_state()['applied'] && null !== $broken->find_section( $target ), 'malformed overlay JSON is ignored, never fatal' );
foreach ( array( 'master/reader-overlay.json', 'master/book.json', 'provenance/content_ids.json', 'provenance/citation-registry.json' ) as $file ) {
	@unlink( $tmp . '/' . $file );
}
@rmdir( $tmp . '/master' );
@rmdir( $tmp . '/provenance' );
@rmdir( $tmp );

echo "PASS ai-book-renderer: {$checks} checks — tables (empty leading/trailing cell, short row, escaped pipe, wide row, RTL, headingless, >5 cols), figure markers (hidden/caption/matched/inline), heading levels, ordered lists, bidi isolation, Persian UI digits and labels, footnotes (ref/definition/orphan/legacy/table), relative heading levels with stable ids, section splitting at headings (table never split, continuation h3), slice pagination with page-aware links and heading map, collision-free graph layout with two-line labels, reader overlay apply/ignore paths\n";
