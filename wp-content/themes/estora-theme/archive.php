<?php
/**
 * Generic archive (category, taxonomy, date, author).
 *
 * @package Estora\Theme
 */

defined( 'ABSPATH' ) || exit;

$estora_title = wp_strip_all_tags( get_the_archive_title() );
if ( is_category() ) {
	$estora_term  = get_queried_object();
	$estora_title = $estora_term instanceof WP_Term ? estora_news_category_label( $estora_term->slug ) : $estora_title;
}

get_header();
?>
<section class="estora-pagehead estora-pagehead--journal">
	<div class="estora-container">
		<?php estora_breadcrumbs( array( array( 'label' => $estora_title ) ) ); ?>
		<div class="estora-pagehead__split">
			<div>
				<p class="estora-eyebrow"><span aria-hidden="true"></span><?php echo esc_html( estora_text( 'Journal', 'บทความ' ) ); ?></p>
				<h1><?php echo esc_html( $estora_title ); ?></h1>
			</div>
			<p class="estora-pagehead__lede"><?php echo esc_html( estora_text( 'Notes filed under this topic. Written by the office team.', 'บันทึกในหัวข้อนี้ เขียนโดยทีมงานสำนักงาน' ) ); ?></p>
		</div>
	</div>
</section>

<div class="estora-container estora-section">
	<?php if ( have_posts() ) : ?>
		<div class="estora-journal-grid">
			<?php while ( have_posts() ) : ?>
				<?php
				the_post();
				if ( 'post' === get_post_type() ) {
					estora_article_card( (int) get_the_ID() );
					continue;
				}
				?>
				<article class="estora-card">
					<?php if ( has_post_thumbnail() ) : ?>
						<a class="estora-card__media" href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'estora-card' ); ?></a>
					<?php endif; ?>
					<div class="estora-card__body">
						<h3 class="estora-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<p class="estora-muted"><?php echo esc_html( get_the_excerpt() ); ?></p>
					</div>
				</article>
			<?php endwhile; ?>
		</div>
		<?php the_posts_pagination( array( 'prev_text' => '←', 'next_text' => '→' ) ); ?>
	<?php else : ?>
		<div class="estora-empty">
			<h2><?php esc_html_e( 'No entries', 'estora-theme' ); ?></h2>
			<p><?php echo esc_html( estora_text( 'Nothing has been filed under this topic yet.', 'ยังไม่มีบันทึกในหัวข้อนี้' ) ); ?></p>
		</div>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
