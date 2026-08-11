<?php
/**
 * Partial: hub landing (enterprise-ai | quantum).
 * Variables in scope: $title (string).
 *
 * @package Kohandezh_Knowledge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hub_slug = get_query_var( 'kbk_hub' );
$lead     = ( 'enterprise-ai' === $hub_slug )
	? 'Strategy, architecture, and governance for deploying AI across the enterprise — from foundation models to agent platforms.'
	: 'Quantum computing, quantum AI, and post-quantum security — fundamentals, hardware, algorithms, and readiness.';
$listing  = KBK_Routes::hub_listing( $hub_slug, 12 );
?>
<h1><?php echo esc_html( $title ); ?></h1>
<p class="kbk-lead"><?php echo esc_html( $lead ); ?></p>

<?php if ( $listing->have_posts() ) : ?>
	<div class="kbk-cards">
		<?php
		while ( $listing->have_posts() ) :
			$listing->the_post();
			?>
			<article class="kbk-card">
				<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
				<p><?php echo esc_html( wp_trim_words( get_post_meta( get_the_ID(), 'kbk_summary', true ) ?: get_the_excerpt(), 24 ) ); ?></p>
				<div class="kbk-tags">
					<?php
					foreach ( (array) get_the_terms( get_the_ID(), 'kbk_topic' ) as $t ) {
						echo '<span class="kbk-tag">' . esc_html( $t->name ) . '</span>';
					}
					?>
				</div>
			</article>
		<?php endwhile; ?>
	</div>
	<?php
	wp_reset_postdata();
else :
	echo '<div class="kbk-empty">No ' . esc_html( strtolower( $title ) ) . ' content yet. Seed fixtures are available via the KBK Seed admin tool.</div>';
endif;
?>
