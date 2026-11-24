<?php
/**
 * Fallback index.
 *
 * @package Estora\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="estora-container estora-section">
	<?php if ( have_posts() ) : ?>
		<div class="estora-grid estora-grid--3">
			<?php while ( have_posts() ) : ?>
				<?php the_post(); ?>
				<article class="estora-article-card">
					<p class="estora-article-card__meta"><?php echo esc_html( get_the_date() ); ?></p>
					<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
					<p><?php echo esc_html( get_the_excerpt() ); ?></p>
				</article>
			<?php endwhile; ?>
		</div>
		<?php the_posts_pagination( array( 'prev_text' => '←', 'next_text' => '→' ) ); ?>
	<?php else : ?>
		<div class="estora-empty">
			<h1><?php esc_html_e( 'Nothing here yet', 'estora-theme' ); ?></h1>
			<p><?php esc_html_e( 'This section has no published content.', 'estora-theme' ); ?></p>
			<a class="estora-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return home', 'estora-theme' ); ?></a>
		</div>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
