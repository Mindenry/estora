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
$estora_actions     = '<button type="button" class="estora-btn" data-new-appointment>' . esc_html__( 'New viewing', 'estora-core' ) . '</button>';
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

		<dialog class="estora-dialog" data-staff-appointment-dialog>
			<form method="dialog" data-staff-appointment-form>
				<h2><?php esc_html_e( 'Schedule a viewing', 'estora-core' ); ?></h2>
				<div class="estora-formgrid">
					<label class="estora-formgrid__wide"><?php esc_html_e( 'Property', 'estora-core' ); ?>
						<select name="property_id" data-staff-property required><option value=""><?php esc_html_e( 'Loading properties…', 'estora-core' ); ?></option></select>
					</label>
					<label><?php esc_html_e( 'Date', 'estora-core' ); ?><input type="date" name="date" required></label>
					<label><?php esc_html_e( 'Agent', 'estora-core' ); ?><select name="agent_id" data-staff-agent><option value="0"><?php esc_html_e( 'Auto-assign', 'estora-core' ); ?></option></select></label>
				</div>
				<div data-staff-slots class="estora-slots"><p class="estora-slots__empty"><?php esc_html_e( 'Choose a date and property to load slots.', 'estora-core' ); ?></p></div>
				<input type="hidden" name="time" data-staff-time>
				<div class="estora-formgrid">
					<label><?php esc_html_e( 'Customer name', 'estora-core' ); ?><input name="name" required></label>
					<label><?php esc_html_e( 'Phone', 'estora-core' ); ?><input name="phone" required></label>
					<label><?php esc_html_e( 'Email', 'estora-core' ); ?><input name="email" type="email"></label>
					<label><?php esc_html_e( 'Notes', 'estora-core' ); ?><input name="message"></label>
				</div>
				<p class="estora-form-error" data-form-error role="alert" hidden></p>
				<div class="estora-dialog__actions">
					<button type="button" class="estora-btn estora-btn--ghost" data-close-appointment-dialog><?php esc_html_e( 'Cancel', 'estora-core' ); ?></button>
					<button type="submit" class="estora-btn"><?php esc_html_e( 'Create viewing', 'estora-core' ); ?></button>
				</div>
			</form>
		</dialog>
	</main>
</div>
