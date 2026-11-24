<?php
/**
 * Reports screen.
 *
 * @package Estora\Core
 */

defined( 'ABSPATH' ) || exit;

$estora_eyebrow     = __( 'Analysis', 'estora-core' );
$estora_title       = __( 'Reports', 'estora-core' );
$estora_description = __( 'Inventory, pipeline, agent and scheduling performance. Exports stream the underlying rows.', 'estora-core' );
require __DIR__ . '/partials/header.php';

$estora_exports = array(
	'properties'   => __( 'Properties', 'estora-core' ),
	'leads'        => __( 'Leads', 'estora-core' ),
	'agents'       => __( 'Agent performance', 'estora-core' ),
	'appointments' => __( 'Appointments', 'estora-core' ),
	'conversion'   => __( 'Conversion by source', 'estora-core' ),
);
?>
	<main class="estora-screen" data-screen="reports" aria-busy="true">
		<section class="estora-exports">
			<div>
				<p class="estora-eyebrow"><?php esc_html_e( 'CSV exports', 'estora-core' ); ?></p>
				<p class="estora-muted"><?php esc_html_e( 'Each file is generated from the current database rows, not from cached sample data.', 'estora-core' ); ?></p>
			</div>
			<div class="estora-exports__buttons">
				<?php foreach ( $estora_exports as $estora_key => $estora_label ) : ?>
					<?php if ( 'leads' === $estora_key && ! current_user_can( 'estora_view_leads' ) ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<a class="estora-btn estora-btn--ghost" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=estora_export&report=' . $estora_key ), 'estora_export' ) ); ?>">
						<?php echo esc_html( $estora_label ); ?> <span aria-hidden="true">↓</span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>

		<div class="estora-kpis" data-report-kpis></div>

		<div class="estora-split">
			<section class="estora-panel">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'Inventory by type', 'estora-core' ); ?></h2></div>
				<div data-report-types></div>
			</section>
			<section class="estora-panel">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'Inventory by province', 'estora-core' ); ?></h2></div>
				<div data-report-provinces></div>
			</section>
		</div>

		<section class="estora-panel">
			<div class="estora-panel__head"><h2><?php esc_html_e( 'Agent performance', 'estora-core' ); ?></h2></div>
			<div data-report-agents></div>
		</section>

		<div class="estora-split">
			<section class="estora-panel">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'Lead sources', 'estora-core' ); ?></h2></div>
				<div data-report-sources></div>
			</section>
			<section class="estora-panel">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'Appointments by weekday', 'estora-core' ); ?></h2></div>
				<div data-report-weekdays></div>
			</section>
		</div>
	</main>
</div>
