<?php
/**
 * Template Name: Favorites
 *
 * @package Estora\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<section class="estora-pagehead">
	<div class="estora-container">
		<?php estora_breadcrumbs( array( array( 'label' => get_the_title() ) ) ); ?>
		<p class="estora-eyebrow"><span aria-hidden="true"></span><?php esc_html_e( 'Your shortlist', 'estora-theme' ); ?></p>
		<h1><?php the_title(); ?></h1>
		<p class="estora-muted" style="max-width:560px"><?php esc_html_e( 'Saved on this device for guests, or to your account when signed in. Nothing is sent to an agent until you inquire.', 'estora-theme' ); ?></p>
	</div>
</section>

<div class="estora-container estora-section">
	<div data-favorites-list aria-live="polite">
		<div class="estora-loading" aria-hidden="true"><div class="estora-skeleton"></div><div class="estora-skeleton"></div></div>
	</div>
</div>

<?php get_footer(); ?>
