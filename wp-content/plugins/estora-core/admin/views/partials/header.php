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
			<?php echo $estora_actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plugin-built, escaped at source. ?>
		</div>
	</header>
	<div class="estora-toast" data-toast hidden role="status" aria-live="polite"></div>
