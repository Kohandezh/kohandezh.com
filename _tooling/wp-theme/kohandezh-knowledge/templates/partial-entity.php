<?php
/**
 * Partial: single entity (route /entity/{slug} or single CPT view).
 * Variable in scope: $entity (WP_Post).
 *
 * @package Kohendezh_Knowledge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$eid     = KBK_Post_Types::canonical_entity_id( $entity->ID );
$summary = get_post_meta( $entity->ID, 'kbk_summary', true );
$lang    = get_post_meta( $entity->ID, 'kbk_language', true ) ?: 'en';
$es      = get_post_meta( $entity->ID, 'kbk_evidence_status', true ) ?: 'primary';
$topics  = (array) get_the_terms( $entity->ID, 'kbk_topic' );
?>
<h1><?php echo esc_html( get_the_title( $entity ) ); ?></h1>
<?php if ( $summary ) : ?>
	<p class="kbk-lead"><?php echo esc_html( $summary ); ?></p>
<?php endif; ?>

<div class="kbk-meta">
	Entity ID: <code><?php echo esc_html( $eid ); ?></code> ·
	Evidence: <?php echo esc_html( ucfirst( $es ) ); ?> ·
	Language: <?php echo esc_html( strtoupper( $lang ) ); ?> ·
	Updated: <?php echo esc_html( mysql2date( 'Y-m-d', $entity->post_modified ) ); ?>
</div>

<?php if ( $topics ) : ?>
	<div class="kbk-tags">
		<?php foreach ( $topics as $t ) : ?>
			<a class="kbk-tag" href="<?php echo esc_url( get_term_link( $t ) ); ?>"><?php echo esc_html( $t->name ); ?></a>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<div class="kbk-body">
	<?php
	echo wp_kses_post( apply_filters( 'the_content', $entity->post_content ) );
	?>
</div>

<?php
$refs = array_filter( array_map( 'esc_url_raw', (array) get_post_meta( $entity->ID, 'kbk_source_refs' ) ) );
if ( $refs ) :
	?>
	<section class="kbk-body">
		<h2>Sources</h2>
		<ul>
			<?php foreach ( $refs as $r ) : ?>
				<li><a href="<?php echo esc_url( $r ); ?>" rel="nofollow noopener"><?php echo esc_html( $r ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>
