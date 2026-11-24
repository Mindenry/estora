<?php
/**
 * Appointments screen.
 *
 * @package Estora\Core
 */

defined( 'ABSPATH' ) || exit;
use Estora\Core\Domain\Appointment_Status;

$estora_eyebrow     = __( 'Schedule', 'estora-core' );
$estora_title       = __( 'Appointments', 'estora-core' );
$estora_description = __( 'Confirm, reschedule or close property viewings. Conflicts are checked on the server.', 'estora-core' );
require __DIR__ . '/partials/header.php';
?>
	<main class="estora-screen" data-screen="appointments" aria-busy="true">
		<div class="estora-toolbar">
			<label class="estora-inline-field">
				<span><?php esc_html_e( 'Status', 'estora-core' ); ?></span>
				<select data-appointment-status>
					<option value=""><?php esc_html_e( 'All', 'estora-core' ); ?></option>
					<?php foreach ( Appointment_Status::all() as $estora_status ) : ?>
						<option value="<?php echo esc_attr( $estora_status ); ?>"><?php echo esc_html( Appointment_Status::label( $estora_status ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="estora-inline-field">
				<span><?php esc_html_e( 'Agent', 'estora-core' ); ?></span>
				<select data-appointment-agent><option value=""><?php esc_html_e( 'All agents', 'estora-core' ); ?></option></select>
			</label>
			<div class="estora-toolbar__views" role="group" aria-label="<?php esc_attr_e( 'Calendar range', 'estora-core' ); ?>">
				<button type="button" class="is-active" data-calendar-view="month"><?php esc_html_e( 'Month', 'estora-core' ); ?></button>
				<button type="button" data-calendar-view="week"><?php esc_html_e( 'Week', 'estora-core' ); ?></button>
				<button type="button" data-calendar-view="day"><?php esc_html_e( 'Day', 'estora-core' ); ?></button>
			</div>
			<div class="estora-calnav">
				<button type="button" class="estora-iconbtn" data-calendar-prev aria-label="<?php esc_attr_e( 'Previous', 'estora-core' ); ?>">&larr;</button>
				<button type="button" class="estora-btn estora-btn--ghost" data-calendar-today><?php esc_html_e( 'Today', 'estora-core' ); ?></button>
				<button type="button" class="estora-iconbtn" data-calendar-next aria-label="<?php esc_attr_e( 'Next', 'estora-core' ); ?>">&rarr;</button>
			</div>
		</div>

		<section class="estora-panel">
			<div class="estora-panel__head">
				<h2 data-calendar-title><?php esc_html_e( 'Calendar', 'estora-core' ); ?></h2>
				<span class="estora-panel__meta" data-calendar-count></span>
			</div>
			<div class="estora-calendar" data-calendar></div>
		</section>

		<section class="estora-panel">
			<div class="estora-panel__head"><h2><?php esc_html_e( 'Appointment list', 'estora-core' ); ?></h2></div>
			<div data-appointment-table></div>
			<div class="estora-pagination" data-appointment-pagination></div>
		</section>
	</main>
</div>
