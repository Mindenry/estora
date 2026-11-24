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
	?>
	<div class="estora-container">
		<?php
		estora_breadcrumbs(
			array(
				array( 'label' => __( 'News', 'estora-theme' ), 'url' => home_url( '/news/' ) ),
				array( 'label' => get_the_title() ),
			)
		);
		?>
	</div>
	<article class="estora-container estora-page">
		<header>
			<p class="estora-eyebrow"><span aria-hidden="true"></span><?php echo esc_html( get_the_date() ); ?></p>
			<h1><?php the_title(); ?></h1>
			<p class="estora-muted"><?php echo esc_html( get_the_author() ); ?></p>
		</header>
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'estora-large', array( 'style' => 'width:100%;height:auto;margin-bottom:28px;border-radius:3px' ) ); ?>
		<?php endif; ?>
		<div class="estora-article__body">
			<?php the_content(); ?>
		</div>
		<footer style="margin-top:40px;border-top:1px solid var(--line);padding-top:20px">
			<?php estora_share( get_permalink(), get_the_title() ); ?>
		</footer>
	</article>
	<?php
endwhile;

get_footer();
