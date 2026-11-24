<?php
/**
 * Overview dashboard.
 *
 * @package Estora\Core
 */

defined( 'ABSPATH' ) || exit;

use Estora\Core\Admin\Admin_App;
use Estora\Core\Support\Capabilities;

$estora_eyebrow     = __( 'Overview', 'estora-core' );
$estora_title       = __( 'Dashboard', 'estora-core' );
$estora_description = __( 'Live figures from the property, CRM and appointment database, scoped to your permissions.', 'estora-core' );
$estora_actions     = '<div class="estora-dash-actions">'
	. '<div class="estora-range">'
	. '<label for="estora-dashboard-range">' . esc_html__( 'Period', 'estora-core' ) . '</label>'
	. '<select id="estora-dashboard-range" data-dashboard-range>'
	. '<option value="7">' . esc_html__( 'Last 7 days', 'estora-core' ) . '</option>'
	. '<option value="30" selected>' . esc_html__( 'Last 30 days', 'estora-core' ) . '</option>'
	. '<option value="90">' . esc_html__( 'Last 90 days', 'estora-core' ) . '</option>'
	. '<option value="month">' . esc_html__( 'This month', 'estora-core' ) . '</option>'
	. '<option value="custom">' . esc_html__( 'Custom range', 'estora-core' ) . '</option>'
	. '</select>'
	. '<input type="date" data-range-from aria-label="' . esc_attr__( 'From date', 'estora-core' ) . '" hidden>'
	. '<input type="date" data-range-to aria-label="' . esc_attr__( 'To date', 'estora-core' ) . '" hidden>'
	. '<button type="button" class="estora-btn estora-btn--ghost estora-btn--sm" data-range-apply hidden>' . esc_html__( 'Apply', 'estora-core' ) . '</button>'
	. '</div>'
	. '<button type="button" class="estora-btn estora-btn--ghost" data-refresh-dashboard>' . esc_html__( 'Refresh', 'estora-core' ) . '</button>'
	. '</div>';
require __DIR__ . '/partials/header.php';
?>
	<main class="estora-screen" data-screen="dashboard" aria-busy="true">
		<div class="estora-quickactions" aria-label="<?php esc_attr_e( 'Quick actions', 'estora-core' ); ?>">
			<?php if ( current_user_can( Capabilities::EDIT_PROPERTIES ) ) : ?>
				<a class="estora-quickaction" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=property' ) ); ?>">
					<?php echo Admin_App::icon( 'building', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span><?php esc_html_e( 'Add property', 'estora-core' ); ?><small><?php esc_html_e( 'Native editor with gallery', 'estora-core' ); ?></small></span>
				</a>
			<?php elseif ( current_user_can( Capabilities::VIEW_PROPERTIES ) ) : ?>
				<a class="estora-quickaction" href="<?php echo esc_url( Admin_App::url( 'properties' ) ); ?>">
					<?php echo Admin_App::icon( 'building', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span><?php esc_html_e( 'Browse properties', 'estora-core' ); ?><small><?php esc_html_e( 'Current inventory', 'estora-core' ); ?></small></span>
				</a>
			<?php endif; ?>
			<?php if ( current_user_can( Capabilities::CREATE_LEADS ) ) : ?>
				<a class="estora-quickaction" href="<?php echo esc_url( Admin_App::url( 'leads' ) . '#new' ); ?>">
					<?php echo Admin_App::icon( 'users', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span><?php esc_html_e( 'Add lead', 'estora-core' ); ?><small><?php esc_html_e( 'Capture a walk-in or call', 'estora-core' ); ?></small></span>
				</a>
			<?php endif; ?>
			<?php if ( current_user_can( Capabilities::MANAGE_APPOINTMENTS ) ) : ?>
				<a class="estora-quickaction" href="<?php echo esc_url( Admin_App::url( 'appointments' ) . '#new' ); ?>">
					<?php echo Admin_App::icon( 'calendar', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span><?php esc_html_e( 'Schedule viewing', 'estora-core' ); ?><small><?php esc_html_e( 'Server-checked slot', 'estora-core' ); ?></small></span>
				</a>
			<?php elseif ( current_user_can( Capabilities::MANAGE_CAMPAIGNS ) ) : ?>
				<a class="estora-quickaction" href="<?php echo esc_url( Admin_App::url( 'campaigns' ) ); ?>">
					<?php echo Admin_App::icon( 'megaphone', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span><?php esc_html_e( 'Campaigns', 'estora-core' ); ?><small><?php esc_html_e( 'Attribution and budget', 'estora-core' ); ?></small></span>
				</a>
			<?php endif; ?>
			<?php if ( current_user_can( Capabilities::VIEW_REPORTS ) ) : ?>
				<a class="estora-quickaction" href="<?php echo esc_url( Admin_App::url( 'reports' ) ); ?>">
					<?php echo Admin_App::icon( 'chart', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span><?php esc_html_e( 'View reports', 'estora-core' ); ?><small><?php esc_html_e( 'Export the real rows', 'estora-core' ); ?></small></span>
				</a>
			<?php endif; ?>
		</div>

		<div class="estora-rangesummary" data-range-summary hidden></div>
		<section class="estora-kpis" data-kpis aria-label="<?php esc_attr_e( 'Key figures', 'estora-core' ); ?>"></section>

		<div class="estora-chartgrid">
			<section class="estora-panel estora-chartpanel">
				<div class="estora-panel__head">
					<h2><?php esc_html_e( 'Lead acquisition', 'estora-core' ); ?></h2>
					<span class="estora-panel__meta" data-trend-total></span>
				</div>
				<div class="estora-chart" data-lead-trend></div>
			</section>
			<section class="estora-panel estora-chartpanel" data-panel="property-mix">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'Inventory status', 'estora-core' ); ?></h2></div>
				<div data-property-mix></div>
			</section>
			<section class="estora-panel estora-chartpanel" data-panel="lead-status">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'Pipeline stages', 'estora-core' ); ?></h2></div>
				<div data-lead-status-chart></div>
			</section>
			<section class="estora-panel estora-chartpanel" data-panel="types">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'Listings by type', 'estora-core' ); ?></h2></div>
				<div data-type-chart></div>
			</section>
			<section class="estora-panel estora-chartpanel" data-panel="agents">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'Agent performance', 'estora-core' ); ?></h2></div>
				<div data-agent-chart></div>
			</section>
			<section class="estora-panel estora-chartpanel" data-panel="appointments">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'Upcoming viewings', 'estora-core' ); ?></h2></div>
				<div data-appointment-chart></div>
			</section>
		</div>

		<div class="estora-split">
			<section class="estora-panel">
				<div class="estora-panel__head">
					<h2><?php esc_html_e( 'Recent activity', 'estora-core' ); ?></h2>
					<?php if ( current_user_can( Capabilities::VIEW_AUDIT ) ) : ?>
						<a class="estora-linkbtn" href="<?php echo esc_url( Admin_App::url( 'activity' ) ); ?>"><?php esc_html_e( 'Full log', 'estora-core' ); ?></a>
					<?php endif; ?>
				</div>
				<ol class="estora-timeline" data-recent-activity></ol>
			</section>
			<section class="estora-panel" data-panel="upcoming-panel">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'Next viewings', 'estora-core' ); ?></h2></div>
				<div data-upcoming-appointments></div>
			</section>
		</div>

		<div class="estora-split">
			<section class="estora-panel" data-panel="sources">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'Leads by source', 'estora-core' ); ?></h2></div>
				<div class="estora-sourcebars" data-source-bars></div>
			</section>
			<section class="estora-panel" data-panel="pipeline-health">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'Pipeline health', 'estora-core' ); ?></h2></div>
				<div data-pipeline-health></div>
			</section>
		</div>
	</main>
</div>
