<?php
/**
 * 404.
 *
 * @package Estora\Theme
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<div class="estora-container estora-section">
	<div class="estora-empty" style="padding-block:clamp(60px,10vw,140px)">
		<p class="estora-eyebrow" style="justify-content:center"><span aria-hidden="true"></span>404</p>
		<h1 style="font-size:clamp(2rem,5vw,3.25rem)"><?php esc_html_e( 'This page has moved on', 'estora-theme' ); ?></h1>
		<p><?php esc_html_e( 'The listing may have been archived or the address mistyped. Current properties are one click away.', 'estora-theme' ); ?></p>
		<p style="margin-top:22px">
			<a class="estora-btn" href="<?php echo esc_url( estora_properties_url() ); ?>"><?php esc_html_e( 'Browse properties', 'estora-theme' ); ?></a>
			<a class="estora-btn estora-btn--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return home', 'estora-theme' ); ?></a>
		</p>
	</div>
</div>
<?php get_footer(); ?>
