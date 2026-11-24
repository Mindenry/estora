<?php
/**
 * Shared admin page header.
 *
 * @package Estora\Core
 * @var string $estora_eyebrow
 * @var string $estora_title
 * @var string $estora_description
 * @var string $estora_actions
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="estora-admin">
	<header class="estora-head">
		<div class="estora-head__text">
			<p class="estora-eyebrow"><?php echo esc_html( $estora_eyebrow ?? 'ESTORA' ); ?></p>
			<h1><?php echo esc_html( $estora_title ?? '' ); ?></h1>
			<?php if ( ! empty( $estora_description ) ) : ?>
				<p class="estora-head__desc"><?php echo esc_html( $estora_description ); ?></p>
			<?php endif; ?>
		</div>
		<div class="estora-head__actions">
			<?php echo wp_kses_post( $estora_actions ?? '' ); ?>
			<div class="estora-notifications">
				<button type="button" class="estora-iconbtn" data-notifications-toggle aria-label="<?php esc_attr_e( 'Notifications', 'estora-core' ); ?>" aria-expanded="false">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
					<span class="estora-notifications__count" data-notifications-count hidden>0</span>
				</button>
				<div class="estora-notifications__panel" data-notifications-panel hidden>
					<div class="estora-notifications__head">
						<strong><?php esc_html_e( 'Notifications', 'estora-core' ); ?></strong>
						<button type="button" class="estora-linkbtn" data-notifications-read-all><?php esc_html_e( 'Mark all read', 'estora-core' ); ?></button>
					</div>
					<ul data-notifications-list></ul>
				</div>
			</div>
		</div>
	</header>
	<div class="estora-toast" data-toast hidden role="status" aria-live="polite"></div>
