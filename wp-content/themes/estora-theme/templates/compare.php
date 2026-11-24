<?php
/**
 * Template Name: Compare
 *
 * @package Estora\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<section class="estora-pagehead">
	<div class="estora-container">
		<?php estora_breadcrumbs( array( array( 'label' => get_the_title() ) ) ); ?>
		<p class="estora-eyebrow"><span aria-hidden="true"></span><?php esc_html_e( 'Side by side', 'estora-theme' ); ?></p>
		<h1><?php the_title(); ?></h1>
		<p class="estora-muted" style="max-width:600px"><?php esc_html_e( 'Up to three properties. The table is generated from the live listing records, including availability.', 'estora-theme' ); ?></p>
	</div>
</section>

<div class="estora-container estora-section">
	<div data-compare-table aria-live="polite">
		<div class="estora-loading" aria-hidden="true"><div class="estora-skeleton"></div></div>
	</div>
	<p class="estora-muted" style="margin-top:18px"><a class="estora-textlink" href="<?php echo esc_url( estora_properties_url() ); ?>"><?php esc_html_e( 'Add more properties', 'estora-theme' ); ?> <?php echo estora_icon( 'arrow', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></p>
</div>

<?php get_footer(); ?>
