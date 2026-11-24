<?php
/**
 * Settings screen.
 *
 * @package Estora\Core
 */

defined( 'ABSPATH' ) || exit;
use Estora\Core\Services\Settings;

$estora_settings = Settings::all();
$estora_agents   = get_users( array( 'capability' => 'estora_edit_leads', 'number' => 100 ) );
$estora_days     = array( 0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday' );

$estora_eyebrow     = __( 'Configuration', 'estora-core' );
$estora_title       = __( 'Settings', 'estora-core' );
$estora_description = __( 'Business identity, viewing hours, notifications and defaults.', 'estora-core' );
require __DIR__ . '/partials/header.php';
?>
	<main class="estora-screen" data-screen="settings" aria-busy="true">
		<form class="estora-settings" data-settings-form>
			<section class="estora-panel">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'Company', 'estora-core' ); ?></h2></div>
				<div class="estora-formgrid">
					<label><?php esc_html_e( 'Company name', 'estora-core' ); ?><input name="company_name" value="<?php echo esc_attr( $estora_settings['company_name'] ); ?>" required></label>
					<label><?php esc_html_e( 'Tagline', 'estora-core' ); ?><input name="company_tagline" value="<?php echo esc_attr( $estora_settings['company_tagline'] ); ?>"></label>
					<label><?php esc_html_e( 'Public email', 'estora-core' ); ?><input type="email" name="company_email" value="<?php echo esc_attr( $estora_settings['company_email'] ); ?>"></label>
					<label><?php esc_html_e( 'Public phone', 'estora-core' ); ?><input name="company_phone" value="<?php echo esc_attr( $estora_settings['company_phone'] ); ?>"></label>
					<label class="estora-formgrid__wide"><?php esc_html_e( 'Office address', 'estora-core' ); ?><textarea name="company_address" rows="2"><?php echo esc_textarea( $estora_settings['company_address'] ); ?></textarea></label>
					<label class="estora-formgrid__wide"><?php esc_html_e( 'Hours (display text)', 'estora-core' ); ?><input name="company_hours_text" value="<?php echo esc_attr( $estora_settings['company_hours_text'] ); ?>"></label>
				</div>
			</section>

			<section class="estora-panel">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'Viewing hours', 'estora-core' ); ?></h2></div>
				<div class="estora-formgrid">
					<label><?php esc_html_e( 'Opens', 'estora-core' ); ?><input type="time" name="open_time" value="<?php echo esc_attr( $estora_settings['open_time'] ); ?>"></label>
					<label><?php esc_html_e( 'Closes', 'estora-core' ); ?><input type="time" name="close_time" value="<?php echo esc_attr( $estora_settings['close_time'] ); ?>"></label>
					<label><?php esc_html_e( 'Break starts', 'estora-core' ); ?><input type="time" name="lunch_start" value="<?php echo esc_attr( $estora_settings['lunch_start'] ); ?>"></label>
					<label><?php esc_html_e( 'Break ends', 'estora-core' ); ?><input type="time" name="lunch_end" value="<?php echo esc_attr( $estora_settings['lunch_end'] ); ?>"></label>
					<label><?php esc_html_e( 'Slot length', 'estora-core' ); ?>
						<select name="slot_minutes">
							<?php foreach ( array( 30, 45, 60, 90 ) as $estora_slot ) : ?>
								<option value="<?php echo esc_attr( (string) $estora_slot ); ?>" <?php selected( (int) $estora_settings['slot_minutes'], $estora_slot ); ?>><?php echo esc_html( $estora_slot . ' minutes' ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label><?php esc_html_e( 'Listings per page', 'estora-core' ); ?><input type="number" name="listing_per_page" min="3" max="48" value="<?php echo esc_attr( (string) $estora_settings['listing_per_page'] ); ?>"></label>
				</div>
				<fieldset class="estora-days">
					<legend><?php esc_html_e( 'Closed days', 'estora-core' ); ?></legend>
					<?php foreach ( $estora_days as $estora_index => $estora_label ) : ?>
						<label class="estora-check">
							<input type="checkbox" name="closed_days[]" value="<?php echo esc_attr( (string) $estora_index ); ?>" <?php checked( in_array( $estora_index, array_map( 'intval', (array) $estora_settings['closed_days'] ), true ) ); ?>>
							<?php echo esc_html( $estora_label ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
			</section>

			<section class="estora-panel">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'Notifications & defaults', 'estora-core' ); ?></h2></div>
				<div class="estora-formgrid">
					<label><?php esc_html_e( 'Notification recipient', 'estora-core' ); ?><input type="email" name="notification_email" value="<?php echo esc_attr( $estora_settings['notification_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"></label>
					<label><?php esc_html_e( 'Default agent', 'estora-core' ); ?>
						<select name="default_agent_id">
							<option value="0"><?php esc_html_e( 'Unassigned', 'estora-core' ); ?></option>
							<?php foreach ( $estora_agents as $estora_agent ) : ?>
								<option value="<?php echo esc_attr( (string) $estora_agent->ID ); ?>" <?php selected( (int) $estora_settings['default_agent_id'], $estora_agent->ID ); ?>><?php echo esc_html( $estora_agent->display_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</div>
				<div class="estora-checks">
					<label class="estora-check"><input type="checkbox" name="notify_new_lead" value="1" <?php checked( (bool) $estora_settings['notify_new_lead'] ); ?>> <?php esc_html_e( 'Notify staff about new leads', 'estora-core' ); ?></label>
					<label class="estora-check"><input type="checkbox" name="notify_new_appointment" value="1" <?php checked( (bool) $estora_settings['notify_new_appointment'] ); ?>> <?php esc_html_e( 'Notify staff about viewing requests', 'estora-core' ); ?></label>
					<label class="estora-check"><input type="checkbox" name="notify_email_enabled" value="1" <?php checked( (bool) $estora_settings['notify_email_enabled'] ); ?>> <?php esc_html_e( 'Send email alongside in-app notifications', 'estora-core' ); ?></label>
				</div>
			</section>

			<p class="estora-form-error" data-form-error role="alert" hidden></p>
			<div class="estora-settings__actions">
				<button type="submit" class="estora-btn"><?php esc_html_e( 'Save settings', 'estora-core' ); ?></button>
				<span class="estora-muted"><?php esc_html_e( 'Timezone follows WordPress.', 'estora-core' ); ?> <?php echo esc_html( wp_timezone_string() ); ?></span>
			</div>
		</form>
	</main>
</div>
