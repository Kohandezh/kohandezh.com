<?php
/**
 * Partial: CPT archive / taxonomy listing (e.g. /knowledge/, /news/, /glossary/, topic archives).
 *
 * @package Kohandezh_Knowledge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$q = $GLOBALS['wp_query'];
$archive_title = '';
if ( is_post_type_archive() ) {
	$qo = get_queried_object();
	$archive_title = $qo->labels->name ?? 'Knowledge';
} elseif ( is_tax() ) {
	$qo = get_queried_object();
	$archive_title = $qo->name ?? 'Topic';
}
?>
<h1><?php echo esc_html( $archive_title ?: $title ); ?></h1>
<p class="kbk-lead"><?php echo esc_html( get_the_archive_description() ?: 'Knowledge platform archive.' ); ?></p>

<?php if ( $q->have_posts() ) : ?>
	<div class="kbk-cards">
		<?php
		while ( $q->have_posts() ) :
			$q->the_post();
			?>
			<article class="kbk-card">
				<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
				<p><?php echo esc_html( wp_trim_words( get_post_meta( get_the_ID(), 'kbk_summary', true ) ?: get_the_excerpt(), 24 ) ); ?></p>
				<div class="kbk-meta">
					<?php
					$es = get_post_meta( get_the_ID(), 'kbk_evidence_status', true );
					echo esc_html( ucfirst( $es ?: 'primary' ) ) . ' · ' . esc_html( get_the_date() );
					?>
				</div>
			</article>
		<?php endwhile; ?>
	</div>
	<?php
	wp_reset_postdata();
else :
	echo '<div class="kbk-empty">No items yet.</div>';
endif;
?>
