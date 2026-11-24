<?php
/**
 * App forbidden.
 *
 * @package Estora\Core
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="estora-appstate">
	<p class="estora-eyebrow"><span aria-hidden="true"></span><?php esc_html_e( '403', 'estora-core' ); ?></p>
	<h1><?php esc_html_e( 'You do not have access to this module', 'estora-core' ); ?></h1>
	<p><?php esc_html_e( 'Your role does not include this permission. If you need it, ask an administrator to review your account.', 'estora-core' ); ?></p>
	<a class="estora-btn" href="<?php echo esc_url( \Estora\Core\Admin\Admin_App::url( 'overview' ) ); ?>"><?php esc_html_e( 'Back to overview', 'estora-core' ); ?></a>
</div>
