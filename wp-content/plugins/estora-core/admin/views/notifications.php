<?php
/**
 * Notifications module.
 *
 * @package Estora\Core
 */

defined( 'ABSPATH' ) || exit;

$estora_eyebrow     = __( 'Inbox', 'estora-core' );
$estora_title       = __( 'Notifications', 'estora-core' );
$estora_description = __( 'Business events for your account. Read state is per user and never shared.', 'estora-core' );
$estora_actions     = '<button type="button" class="estora-btn estora-btn--ghost" data-notification-read-all-page>' . esc_html__( 'Mark all read', 'estora-core' ) . '</button>';
require __DIR__ . '/partials/header.php';
?>
	<main class="estora-screen" data-screen="notifications-page">
		<div data-notifications-page>
			<div class="estora-notifications-page" data-notification-list aria-live="polite">
				<div class="estora-skeleton estora-skeleton--row"></div>
				<div class="estora-skeleton estora-skeleton--row"></div>
				<div class="estora-skeleton estora-skeleton--row"></div>
			</div>
			<div class="estora-emptyadmin" data-notification-empty hidden>
				<strong><?php esc_html_e( 'You are all caught up', 'estora-core' ); ?></strong>
				<?php esc_html_e( 'New assignments, inquiries and viewing requests will appear here.', 'estora-core' ); ?>
			</div>
		</div>
	</main>
</div>
