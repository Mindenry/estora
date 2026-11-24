<?php
/**
 * Audit log screen.
 *
 * @package Estora\Core
 */

defined( 'ABSPATH' ) || exit;

$estora_eyebrow     = __( 'Security', 'estora-core' );
$estora_title       = __( 'Activity log', 'estora-core' );
$estora_description = __( 'Business events recorded by the plugin. Passwords and tokens are never logged.', 'estora-core' );
require __DIR__ . '/partials/header.php';
?>
	<main class="estora-screen" data-screen="audit" aria-busy="true">
		<div class="estora-toolbar">
			<label class="estora-inline-field">
				<span><?php esc_html_e( 'Action', 'estora-core' ); ?></span>
				<select data-audit-action>
					<option value=""><?php esc_html_e( 'All actions', 'estora-core' ); ?></option>
					<option value="lead"><?php esc_html_e( 'Lead events', 'estora-core' ); ?></option>
					<option value="appointment"><?php esc_html_e( 'Appointment events', 'estora-core' ); ?></option>
					<option value="property"><?php esc_html_e( 'Property events', 'estora-core' ); ?></option>
					<option value="user"><?php esc_html_e( 'User events', 'estora-core' ); ?></option>
					<option value="settings"><?php esc_html_e( 'Settings events', 'estora-core' ); ?></option>
				</select>
			</label>
			<label class="estora-inline-field">
				<span><?php esc_html_e( 'Object', 'estora-core' ); ?></span>
				<select data-audit-object>
					<option value=""><?php esc_html_e( 'All objects', 'estora-core' ); ?></option>
					<option value="lead">Lead</option>
					<option value="appointment">Appointment</option>
					<option value="property">Property</option>
					<option value="user">User</option>
					<option value="campaign">Campaign</option>
					<option value="settings">Settings</option>
				</select>
			</label>
		</div>
		<section class="estora-panel" data-audit-table></section>
		<div class="estora-pagination" data-audit-pagination></div>
	</main>
</div>
