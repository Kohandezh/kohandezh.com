<?php
/**
 * KBK_Seed — controlled, removable seed fixtures for the Layer B MVP.
 *
 * Reads declarative fixtures from fixtures/seed.json. Every seeded post is tagged
 * with protected meta '_kbk_fixture' = 1 so remove() can purge cleanly. Drafts are
 * post_status=draft (never published as real content). Glossary definitions are
 * factual/widely-documented (evidence_status=secondary).
 *
 * Operability: Tools → KBK Seed admin page (nonce + manage_options).
 *
 * @package Kohandezh_Knowledge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KBK_Seed {

	const FIXTURE_FILE = __DIR__ . '/../fixtures/seed.json';

	public static function hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_post_kbk_seed_install', array( __CLASS__, 'handle_install' ) );
		add_action( 'admin_post_kbk_seed_remove', array( __CLASS__, 'handle_remove' ) );
	}

	public static function admin_menu() {
		add_management_page(
			'KBK Seed Fixtures',
			'KBK Seed',
			'manage_options',
			'kbk-seed',
			array( __CLASS__, 'render_admin' )
		);
	}

	public static function fixtures() {
		if ( ! file_exists( self::FIXTURE_FILE ) ) {
			return null;
		}
		$raw  = file_get_contents( self::FIXTURE_FILE ); // local plugin file, safe
		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : null;
	}

	public static function fixture_count() {
		$q = new WP_Query( array(
			'post_type'      => array( 'kbk_knowledge', 'kbk_news', 'kbk_glossary', 'kbk_case', 'kbk_research' ),
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_kbk_fixture',
			'meta_value'     => '1',
			'no_found_rows'  => false,
		) );
		return (int) $q->found_posts;
	}

	public static function install() {
		$data = self::fixtures();
		if ( ! $data ) {
			return array( 'ok' => false, 'msg' => 'fixtures/seed.json missing or invalid.' );
		}
		$report = array( 'glossary' => 0, 'drafts' => 0, 'skipped' => 0 );

		foreach ( ( $data['glossary'] ?? array() ) as $item ) {
			$res = self::upsert_fixture( $item, 'kbk_glossary', 'publish' );
			$res ? $report['glossary']++ : $report['skipped']++;
		}
		foreach ( ( $data['knowledge_drafts'] ?? array() ) as $item ) {
			$res = self::upsert_fixture( $item, 'kbk_knowledge', 'draft' );
			$res ? $report['drafts']++ : $report['skipped']++;
		}

		return array(
			'ok'   => true,
			'msg'  => sprintf( 'Installed: %d glossary (published), %d drafts (unpublished), %d skipped.', $report['glossary'], $report['drafts'], $report['skipped'] ),
		);
	}

	private static function upsert_fixture( $item, $post_type, $status ) {
		$slug = isset( $item['slug'] ) ? sanitize_title( $item['slug'] ) : '';
		if ( ! $slug ) {
			return false;
		}
		$existing = get_page_by_path( $slug, OBJECT, $post_type );
		if ( $existing ) {
			return false; // idempotent: do not overwrite real content.
		}
		$post_id = wp_insert_post( array(
			'post_type'    => $post_type,
			'post_status'  => $status,
			'post_name'    => $slug,
			'post_title'   => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : $slug,
			'post_content' => isset( $item['body'] ) ? wp_kses_post( $item['body'] ) : '',
			'post_excerpt' => isset( $item['summary'] ) ? sanitize_text_field( $item['summary'] ) : '',
		), true );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return false;
		}

		// Marker + model meta.
		update_post_meta( $post_id, '_kbk_fixture', '1' );
		if ( isset( $item['summary'] ) ) { update_post_meta( $post_id, 'kbk_summary', sanitize_text_field( $item['summary'] ) ); }
		if ( isset( $item['language'] ) ) { update_post_meta( $post_id, 'kbk_language', sanitize_text_field( $item['language'] ) ); }
		if ( isset( $item['evidence_status'] ) ) { update_post_meta( $post_id, 'kbk_evidence_status', sanitize_text_field( $item['evidence_status'] ) ); }
		if ( isset( $item['editorial_status'] ) ) { update_post_meta( $post_id, 'kbk_editorial_status', sanitize_text_field( $item['editorial_status'] ) ); }
		update_post_meta( $post_id, 'kbk_schema_type', 'kbk_glossary' === $post_type ? 'DefinedTerm' : 'TechArticle' );

		// Terms.
		if ( ! empty( $item['topics'] ) ) {
			wp_set_object_terms( $post_id, array_map( 'sanitize_title', $item['topics'] ), 'kbk_topic', false );
		}
		if ( ! empty( $item['domains'] ) ) {
			foreach ( $item['domains'] as $tax => $slugs ) {
				if ( taxonomy_exists( $tax ) ) {
					wp_set_object_terms( $post_id, array_map( 'sanitize_title', $slugs ), $tax, false );
				}
			}
		}
		return true;
	}

	public static function remove() {
		$q = new WP_Query( array(
			'post_type'      => array( 'kbk_knowledge', 'kbk_news', 'kbk_glossary', 'kbk_case', 'kbk_research' ),
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_kbk_fixture',
			'meta_value'     => '1',
			'no_found_rows'  => false,
		) );
		$n = 0;
		foreach ( $q->posts as $id ) {
			wp_delete_post( $id, true );
			$n++;
		}
		return array( 'ok' => true, 'msg' => sprintf( 'Removed %d fixture(s).', $n ) );
	}

	public static function handle_install() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'kbk_seed' ) ) {
			wp_die( 'Permission denied.' );
		}
		$r = self::install();
		self::redirect( $r );
	}

	public static function handle_remove() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'kbk_seed' ) ) {
			wp_die( 'Permission denied.' );
		}
		$r = self::remove();
		self::redirect( $r );
	}

	private static function redirect( $r ) {
		$url = add_query_arg( array(
			'page'    => 'kbk-seed',
			'kbk_msg' => rawurlencode( $r['msg'] ?? '' ),
		), admin_url( 'tools.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	public static function render_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$msg   = isset( $_GET['kbk_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['kbk_msg'] ) ) : '';
		$count = self::fixture_count();
		?>
		<div class="wrap">
			<h1>KBK Seed Fixtures</h1>
			<?php if ( $msg ) : ?><div id="message" class="updated notice"><p><?php echo esc_html( $msg ); ?></p></div><?php endif; ?>
			<p>Controlled Layer B seed content. Every seeded item carries a <code>_kbk_fixture</code> marker and can be purged in one click. Glossary definitions are published (factual); knowledge drafts are <strong>unpublished</strong>.</p>
			<p>Currently installed fixtures: <strong><?php echo (int) $count; ?></strong></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kbk_seed_install' ), 'kbk_seed' ) ); ?>">Install / refresh fixtures</a>
				<a class="button button-link-delete" style="margin-left:8px" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kbk_seed_remove' ), 'kbk_seed' ) ); ?>" onclick="return confirm('Remove all KBK fixtures? This deletes only _kbk_fixture posts.');">Remove all fixtures</a>
			</p>
		</div>
		<?php
	}
}
