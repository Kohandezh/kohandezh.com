<?php
/**
 * KBK_AI_Book_Settings — admin defaults for the book reader.
 *
 * Every reader preference a visitor can change in the «Aa» panel has its
 * site-wide default here, plus the chapter page budget the server uses to
 * split long chapters. Stored in the single option `kbk_reader_settings`.
 *
 * The «کتاب‌ها» section edits the book registry (option `kbk_books`, see
 * KBK_AI_Book_Registry): one row per title, each a bundle in the same format.
 *
 * @package Kohandezh_Knowledge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KBK_AI_Book_Settings {

	const OPTION = 'kbk_reader_settings';
	const PAGE   = 'kbk-reader';

	public static function hooks(): void {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	public static function admin_menu(): void {
		add_options_page( 'کتاب‌خوان کهن‌دژ', 'کتاب‌خوان کهن‌دژ', 'manage_options', self::PAGE, array( __CLASS__, 'render' ) );
	}

	public static function register(): void {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(
					'page_chars'    => KBK_AI_Book::DEFAULT_PAGE_CHARS,
					'default_wide'  => 0,
					'default_focus' => 0,
				),
			)
		);
		add_settings_section( 'kbk_reader_defaults', 'پیش‌فرض‌های کتاب‌خوان', array( __CLASS__, 'section_intro' ), self::PAGE );
		add_settings_field( 'kbk_reader_page_chars', 'بودجهٔ هر صفحهٔ فصل (نویسه)', array( __CLASS__, 'field_page_chars' ), self::PAGE, 'kbk_reader_defaults', array( 'label_for' => 'kbk_reader_page_chars' ) );
		add_settings_field( 'kbk_reader_default_wide', 'عرض پیش‌فرض متن', array( __CLASS__, 'field_wide' ), self::PAGE, 'kbk_reader_defaults', array( 'label_for' => 'kbk_reader_default_wide' ) );
		add_settings_field( 'kbk_reader_default_focus', 'حالت مطالعه در ورود نخست', array( __CLASS__, 'field_focus' ), self::PAGE, 'kbk_reader_defaults', array( 'label_for' => 'kbk_reader_default_focus' ) );

		register_setting(
			self::PAGE,
			KBK_AI_Book_Registry::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'KBK_AI_Book_Registry', 'sanitize_option' ),
				'default'           => KBK_AI_Book_Registry::default_option_rows(),
			)
		);
		add_settings_section( 'kbk_books', 'کتاب‌ها', array( __CLASS__, 'books_intro' ), self::PAGE );
		add_settings_field( 'kbk_books_table', 'فهرست کتاب‌ها', array( __CLASS__, 'field_books' ), self::PAGE, 'kbk_books' );
	}

	public static function books_intro(): void {
		echo '<p>هر کتاب یک بستهٔ داده با همان قالب کتاب حاکمیت هوش مصنوعی است (master/book.json، provenance/، release/ …) و در نشانی <code dir="ltr">/fa/books/{نامک}/</code> با همان طراحی نمایش داده می‌شود. ردیف «منتشرشده» به مسیر ریشه‌ای روی سرور نیاز دارد که master/book.json داشته باشد؛ ردیف «به‌زودی» فقط کارت قفسه را بدون پیوند نشان می‌دهد. ردیف‌های تعریف‌شده در wp-config.php (KBK_AI_BOOK_ROOT و KBK_BOOKS) اینجا فقط خواندنی‌اند.</p>';
	}

	public static function field_books(): void {
		$name   = KBK_AI_Book_Registry::OPTION;
		$status = static function ( array $row ): string {
			if ( ! $row['routable'] && KBK_AI_Book_Registry::STATUS_PUBLISHED === $row['status'] ) {
				return '<span style="color:#b32d2e;font-weight:600">ریشه نامعتبر — منتشر نمی‌شود</span>';
			}
			if ( $row['routable'] ) {
				$state = KBK_AI_Book_Registry::repository_status( $row['slug'] );
				return 'READY' === $state
					? '<span style="color:#008a20;font-weight:600">منتشرشده</span> · <a href="' . esc_url( home_url( '/fa/books/' . $row['slug'] . '/' ) ) . '" target="_blank" rel="noopener">مشاهده</a>'
					: '<span style="color:#b32d2e;font-weight:600">' . esc_html( $state ) . '</span>';
			}
			return '<span>به‌زودی (بدون پیوند)</span>';
		};
		echo '<table class="widefat striped" style="max-width:1200px"><thead><tr><th>نامک (slug)</th><th>عنوان کوتاه فارسی</th><th>مسیر ریشه روی سرور</th><th>نشانی جلد (اختیاری)</th><th>وضعیت</th><th>زیرعنوان / نویسندگان / یادداشت</th><th>حالت</th><th>حذف</th></tr></thead><tbody>';
		$index = 0;
		foreach ( KBK_AI_Book_Registry::rows() as $row ) {
			if ( $row['locked'] ) {
				printf(
					'<tr><td><code dir="ltr">%1$s</code></td><td>%2$s</td><td><code dir="ltr">%3$s</code></td><td dir="ltr">%4$s</td><td>%5$s</td><td>—</td><td>%6$s</td><td>wp-config</td></tr>',
					esc_html( $row['slug'] ),
					esc_html( $row['title_fa'] ),
					esc_html( $row['root'] ),
					esc_html( $row['cover'] ),
					esc_html( KBK_AI_Book_Registry::STATUS_PUBLISHED === $row['status'] ? 'منتشرشده' : 'به‌زودی' ),
					$status( $row ) // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
				);
				continue;
			}
			self::book_row( $name, $index++, $row, $status( $row ) );
		}
		for ( $blank = 0; $blank < 2; $blank++ ) {
			self::book_row( $name, $index++, array(), '' );
		}
		echo '</tbody></table><p class="description">برای افزودن کتاب یکی از ردیف‌های خالی را پر کنید؛ نامک فقط حروف کوچک لاتین، رقم و خط تیره است (حداکثر ۴۰ نویسه) و نشانی کتاب از آن ساخته می‌شود. جلد پیش‌فرض: <code dir="ltr">assets/books/{slug}-cover-w320.webp</code> و <code dir="ltr">-w640.webp</code> در افزونه.</p>';
	}

	/** One editable registry row (option rows and the blank "add" rows). */
	private static function book_row( string $name, int $index, array $row, string $state ): void {
		$field = static function ( string $key ) use ( $name, $index ): string {
			return esc_attr( $name . '[' . $index . '][' . $key . ']' );
		};
		$value = static function ( string $key ) use ( $row ): string {
			return esc_attr( (string) ( $row[ $key ] ?? '' ) );
		};
		$status = (string) ( $row['status'] ?? KBK_AI_Book_Registry::STATUS_PUBLISHED );
		printf(
			'<tr><td><input type="text" dir="ltr" name="%1$s" value="%2$s" class="regular-text" style="width:11em" pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="40"></td>'
			. '<td><input type="text" name="%3$s" value="%4$s" style="width:14em"></td>'
			. '<td><input type="text" dir="ltr" name="%5$s" value="%6$s" style="width:16em" placeholder="/opt/books/slug"></td>'
			. '<td><input type="url" dir="ltr" name="%7$s" value="%8$s" style="width:12em"></td>'
			. '<td><select name="%9$s"><option value="published"%10$s>منتشرشده</option><option value="announced"%11$s>به‌زودی</option></select></td>'
			. '<td><input type="text" name="%12$s" value="%13$s" placeholder="زیرعنوان" style="width:14em"><br><input type="text" name="%14$s" value="%15$s" placeholder="نویسندگان / تاریخ" style="width:14em"><br><input type="text" name="%16$s" value="%17$s" placeholder="یادداشت کارت" style="width:14em"></td>'
			. '<td>%18$s</td><td>%19$s</td></tr>',
			$field( 'slug' ),
			$value( 'slug' ),
			$field( 'title_fa' ),
			$value( 'title_fa' ),
			$field( 'root' ),
			$value( 'root' ),
			$field( 'cover' ),
			$value( 'cover' ),
			$field( 'status' ),
			KBK_AI_Book_Registry::STATUS_PUBLISHED === $status ? ' selected' : '',
			KBK_AI_Book_Registry::STATUS_ANNOUNCED === $status ? ' selected' : '',
			$field( 'subtitle_fa' ),
			$value( 'subtitle_fa' ),
			$field( 'meta_fa' ),
			$value( 'meta_fa' ),
			$field( 'note_fa' ),
			$value( 'note_fa' ),
			$state, // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
			array() === $row ? '' : '<label><input type="checkbox" name="' . $field( 'remove' ) . '" value="1"> حذف</label>'
		);
	}

	/**
	 * @param mixed $input
	 * @return array{page_chars:int,default_wide:int,default_focus:int}
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$chars = isset( $input['page_chars'] ) && is_numeric( $input['page_chars'] ) ? (int) $input['page_chars'] : KBK_AI_Book::DEFAULT_PAGE_CHARS;
		return array(
			'page_chars'    => max( KBK_AI_Book::MIN_PAGE_CHARS, min( KBK_AI_Book::MAX_PAGE_CHARS, $chars ) ),
			'default_wide'  => ! empty( $input['default_wide'] ) ? 1 : 0,
			'default_focus' => ! empty( $input['default_focus'] ) ? 1 : 0,
		);
	}

	public static function section_intro(): void {
		echo '<p>این مقادیر پیش‌فرض همهٔ خوانندگان است؛ انتخابی که هر خواننده در پنل «Aa» انجام دهد در مرورگر خودش ذخیره می‌شود و بر این مقادیر مقدم است. بخش‌ها هرگز میان دو صفحه شکسته نمی‌شوند.</p>';
	}

	public static function field_page_chars(): void {
		$settings = KBK_AI_Book::reader_settings();
		printf(
			'<input type="number" id="kbk_reader_page_chars" name="%1$s[page_chars]" value="%2$d" min="%3$d" max="%4$d" step="1000" class="small-text"> <p class="description">فصل‌های طولانی در مرز بخش‌ها به صفحه‌هایی با حدود این تعداد نویسه تقسیم می‌شوند (پیش‌فرض %5$d).</p>',
			esc_attr( self::OPTION ),
			(int) $settings['page_chars'],
			(int) KBK_AI_Book::MIN_PAGE_CHARS,
			(int) KBK_AI_Book::MAX_PAGE_CHARS,
			(int) KBK_AI_Book::DEFAULT_PAGE_CHARS
		);
	}

	public static function field_wide(): void {
		$settings = KBK_AI_Book::reader_settings();
		printf(
			'<select id="kbk_reader_default_wide" name="%1$s[default_wide]"><option value="0"%2$s>استاندارد (حدود ۶۵ تا ۷۵ نویسه در هر سطر)</option><option value="1"%3$s>باز</option></select>',
			esc_attr( self::OPTION ),
			$settings['default_wide'] ? '' : ' selected',
			$settings['default_wide'] ? ' selected' : ''
		);
	}

	public static function field_focus(): void {
		$settings = KBK_AI_Book::reader_settings();
		printf(
			'<label><input type="checkbox" id="kbk_reader_default_focus" name="%1$s[default_focus]" value="1"%2$s> پنهان‌کردن سرصفحه و فهرست سایت هنگام ورود به فصل</label>',
			esc_attr( self::OPTION ),
			$settings['default_focus'] ? ' checked' : ''
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap" dir="rtl"><h1>کتاب‌خوان کهن‌دژ</h1><form method="post" action="options.php">';
		settings_fields( self::PAGE );
		do_settings_sections( self::PAGE );
		submit_button();
		echo '</form></div>';
	}
}
