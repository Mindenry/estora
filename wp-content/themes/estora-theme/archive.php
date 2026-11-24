<?php
/**
 * Generic archive (taxonomy, date, author).
 *
 * @package Estora\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<section class="estora-pagehead">
	<div class="estora-container">
		<?php estora_breadcrumbs( array( array( 'label' => wp_strip_all_tags( get_the_archive_title() ) ) ) ); ?>
		<p class="estora-eyebrow"><span aria-hidden="true"></span><?php esc_html_e( 'Archive', 'estora-theme' ); ?></p>
		<h1><?php echo esc_html( wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
		<?php the_archive_description( '<div class="estora-muted">', '</div>' ); ?>
	</div>
</section>
<div class="estora-container estora-section">
	<?php if ( have_posts() ) : ?>
		<div class="estora-grid estora-grid--3">
			<?php while ( have_posts() ) : ?>
				<?php the_post(); ?>
				<article class="estora-card">
					<?php if ( has_post_thumbnail() ) : ?>
						<a class="estora-card__media" href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'estora-card' ); ?></a>
					<?php endif; ?>
					<div class="estora-card__body">
						<p class="estora-article-card__meta"><?php echo esc_html( get_the_date() ); ?></p>
						<h3 class="estora-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<p class="estora-muted"><?php echo esc_html( get_the_excerpt() ); ?></p>
					</div>
				</article>
			<?php endwhile; ?>
		</div>
		<?php the_posts_pagination( array( 'prev_text' => '←', 'next_text' => '→' ) ); ?>
	<?php else : ?>
		<div class="estora-empty"><h2><?php esc_html_e( 'No entries', 'estora-theme' ); ?></h2></div>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
