<?php
/**
 * News archive.
 *
 * @package Estora\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<section class="estora-pagehead">
	<div class="estora-container">
		<?php estora_breadcrumbs( array( array( 'label' => __( 'News', 'estora-theme' ) ) ) ); ?>
		<p class="estora-eyebrow"><span aria-hidden="true"></span><?php esc_html_e( 'Journal', 'estora-theme' ); ?></p>
		<h1><?php esc_html_e( 'News & notes', 'estora-theme' ); ?></h1>
		<p class="estora-muted" style="max-width:560px"><?php esc_html_e( 'Market observations and practical guides from the office. No listings disguised as articles.', 'estora-theme' ); ?></p>
	</div>
</section>

<div class="estora-container estora-section">
	<?php if ( have_posts() ) : ?>
		<div class="estora-grid estora-grid--3">
			<?php while ( have_posts() ) : ?>
				<?php the_post(); ?>
				<article class="estora-article-card">
					<p class="estora-article-card__meta"><?php echo esc_html( get_the_date() ); ?> · <?php echo esc_html( get_the_category_list( ', ' ) ? wp_strip_all_tags( get_the_category_list( ', ' ) ) : __( 'Journal', 'estora-theme' ) ); ?></p>
					<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
					<p><?php echo esc_html( get_the_excerpt() ); ?></p>
					<a class="estora-textlink" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read the note', 'estora-theme' ); ?> <?php echo estora_icon( 'arrow', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				</article>
			<?php endwhile; ?>
		</div>
		<?php
		the_posts_pagination(
			array(
				'prev_text' => '←',
				'next_text' => '→',
				'class'     => 'estora-pagination',
			)
		);
		?>
	<?php else : ?>
		<div class="estora-empty">
			<h2><?php esc_html_e( 'No articles yet', 'estora-theme' ); ?></h2>
			<p><?php esc_html_e( 'Practical notes will appear here as the office publishes them.', 'estora-theme' ); ?></p>
		</div>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
