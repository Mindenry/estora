<?php
/**
 * Default page template.
 *
 * @package Estora\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<div class="estora-container">
		<?php estora_breadcrumbs( array( array( 'label' => get_the_title() ) ) ); ?>
	</div>
	<article class="estora-container estora-page">
		<header>
			<p class="estora-eyebrow"><span aria-hidden="true"></span><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
			<h1><?php the_title(); ?></h1>
		</header>
		<div class="estora-page__body">
			<?php the_content(); ?>
		</div>
	</article>
	<?php
endwhile;

get_footer();
