<?php
/**
 * Single article.
 *
 * @package Estora\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$estora_id         = (int) get_the_ID();
	$estora_categories = get_the_category( $estora_id );
	$estora_primary    = $estora_categories ? $estora_categories[0] : null;
	?>
	<div class="estora-container estora-property-crumbs">
		<?php
		estora_breadcrumbs(
			array(
				array( 'label' => __( 'News', 'estora-theme' ), 'url' => home_url( '/news/' ) ),
				array( 'label' => get_the_title() ),
			)
		);
		?>
	</div>
	<article class="estora-container estora-page estora-article-page">
		<header class="estora-article-page__head">
			<p class="estora-article-card__meta">
				<span><?php echo esc_html( get_the_date() ); ?></span>
				<?php if ( $estora_primary ) : ?>
					<span class="estora-article-card__cat"><?php echo esc_html( estora_news_category_label( $estora_primary->slug ) ); ?></span>
				<?php endif; ?>
				<?php /* translators: %d: reading time in minutes */ ?>
				<span><?php echo esc_html( sprintf( estora_text( '%d min read', 'อ่าน %d นาที' ), estora_reading_time( $estora_id ) ) ); ?></span>
			</p>
			<h1><?php the_title(); ?></h1>
			<p class="estora-article-page__byline"><?php echo esc_html( estora_text( 'By the ESTORA office', 'โดยทีมงาน ESTORA' ) ); ?> · <?php echo esc_html( estora_owner() ); ?></p>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="estora-article-page__media">
				<?php the_post_thumbnail( 'estora-wide', array( 'loading' => 'eager' ) ); ?>
			</figure>
		<?php endif; ?>

		<div class="estora-article__body">
			<?php the_content(); ?>
		</div>

		<footer class="estora-article-page__foot">
			<?php estora_share( get_permalink(), get_the_title() ); ?>
			<a class="estora-textlink" href="<?php echo esc_url( home_url( '/news/' ) ); ?>"><?php echo esc_html( estora_text( 'All notes', 'บันทึกทั้งหมด' ) ); ?> <?php echo estora_icon( 'arrow', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
		</footer>

		<?php
		$estora_prev = get_previous_post();
		$estora_next = get_next_post();
		if ( $estora_prev || $estora_next ) :
			?>
			<nav class="estora-article-nav" aria-label="<?php esc_attr_e( 'More notes', 'estora-theme' ); ?>">
				<?php if ( $estora_prev ) : ?>
					<a href="<?php echo esc_url( get_permalink( $estora_prev ) ); ?>">
						<span><?php echo esc_html( estora_text( 'Previous note', 'บันทึกก่อนหน้า' ) ); ?></span>
						<strong><?php echo esc_html( get_the_title( $estora_prev ) ); ?></strong>
					</a>
				<?php endif; ?>
				<?php if ( $estora_next ) : ?>
					<a class="is-next" href="<?php echo esc_url( get_permalink( $estora_next ) ); ?>">
						<span><?php echo esc_html( estora_text( 'Next note', 'บันทึกถัดไป' ) ); ?></span>
						<strong><?php echo esc_html( get_the_title( $estora_next ) ); ?></strong>
					</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	</article>
	<?php
endwhile;

get_footer();
