<?php
/**
 * Dashboard screen.
 *
 * @package Estora\Core
 */

defined( 'ABSPATH' ) || exit;
$estora_eyebrow     = __( 'Overview', 'estora-core' );
$estora_title       = __( 'Dashboard', 'estora-core' );
$estora_description = __( 'Live counts from the property, CRM and appointment database.', 'estora-core' );
$estora_actions     = '<button type="button" class="estora-btn estora-btn--ghost" data-refresh-dashboard>' . esc_html__( 'Refresh', 'estora-core' ) . '</button>';
require __DIR__ . '/partials/header.php';
?>
	<main class="estora-screen" data-screen="dashboard" aria-busy="true">
		<section class="estora-kpis" data-kpis aria-label="<?php esc_attr_e( 'Key figures', 'estora-core' ); ?>"></section>

		<div class="estora-split">
			<section class="estora-panel estora-panel--chart">
				<div class="estora-panel__head">
					<h2><?php esc_html_e( 'Leads, last 14 days', 'estora-core' ); ?></h2>
					<span class="estora-panel__meta" data-trend-total></span>
				</div>
				<div class="estora-chart" data-lead-trend></div>
			</section>
			<section class="estora-panel">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'Upcoming viewings', 'estora-core' ); ?></h2></div>
				<div data-upcoming-appointments></div>
			</section>
		</div>

		<div class="estora-split">
			<section class="estora-panel">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'Recent activity', 'estora-core' ); ?></h2></div>
				<ol class="estora-timeline" data-recent-activity></ol>
			</section>
			<section class="estora-panel">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'Leads by source', 'estora-core' ); ?></h2></div>
				<div class="estora-sourcebars" data-source-bars></div>
			</section>
		</div>
	</main>
</div>
