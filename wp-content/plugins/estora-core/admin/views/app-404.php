<?php
/**
 * App 404.
 *
 * @package Estora\Core
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="estora-appstate">
	<p class="estora-eyebrow"><span aria-hidden="true"></span><?php esc_html_e( '404', 'estora-core' ); ?></p>
	<h1><?php esc_html_e( 'This back-office page does not exist', 'estora-core' ); ?></h1>
	<p><?php esc_html_e( 'The route may have been mistyped or the module is no longer available. Your session is still active.', 'estora-core' ); ?></p>
	<a class="estora-btn" href="<?php echo esc_url( \Estora\Core\Admin\Admin_App::url( 'overview' ) ); ?>"><?php esc_html_e( 'Back to overview', 'estora-core' ); ?></a>
</div>
