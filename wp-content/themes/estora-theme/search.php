<?php
/**
 * Search results.
 *
 * @package Estora\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<section class="estora-pagehead">
	<div class="estora-container">
		<p class="estora-eyebrow"><span aria-hidden="true"></span><?php esc_html_e( 'Search', 'estora-theme' ); ?></p>
		<?php /* translators: %s: search query */ ?>
		<h1><?php echo esc_html( sprintf( __( 'Results for “%s”', 'estora-theme' ), get_search_query() ) ); ?></h1>
		<?php /* translators: %s: number of results */ ?>
		<p class="estora-muted"><?php echo esc_html( sprintf( _n( '%s result', '%s results', (int) $wp_query->found_posts, 'estora-theme' ), number_format_i18n( (int) $wp_query->found_posts ) ) ); ?></p>
		<div style="max-width:720px;margin-top:18px"><?php get_search_form(); ?></div>
	</div>
</section>
<div class="estora-container estora-section">
	<?php if ( have_posts() ) : ?>
		<div class="estora-grid estora-grid--3">
			<?php while ( have_posts() ) : ?>
				<?php the_post(); ?>
				<article class="estora-card">
					<div class="estora-card__body">
						<p class="estora-article-card__meta"><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ?? '' ); ?></p>
						<h3 class="estora-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<p class="estora-muted"><?php echo esc_html( get_the_excerpt() ); ?></p>
					</div>
				</article>
			<?php endwhile; ?>
		</div>
		<?php the_posts_pagination( array( 'prev_text' => '←', 'next_text' => '→' ) ); ?>
	<?php else : ?>
		<div class="estora-empty">
			<h2><?php esc_html_e( 'No matches', 'estora-theme' ); ?></h2>
			<p><?php esc_html_e( 'Try a different term, or browse the current listings.', 'estora-theme' ); ?></p>
			<a class="estora-btn" href="<?php echo esc_url( estora_properties_url() ); ?>"><?php esc_html_e( 'Browse properties', 'estora-theme' ); ?></a>
		</div>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
