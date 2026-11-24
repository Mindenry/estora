<?php
/**
 * Remove QA fixtures created by the automated suites.
 *
 * Idempotent and safe for the demo database. Run after tests or before screenshots.
 *
 * @package Estora\Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "This script must run inside WordPress.\n" );
	exit( 1 );
}

global $wpdb;

$like = static fn( string $value ): string => '%' . $wpdb->esc_like( $value ) . '%';

$properties = get_posts(
	array(
		'post_type'   => 'property',
		'post_status' => 'any',
		'numberposts' => 200,
		'fields'      => 'ids',
		's'           => 'QA ',
	)
);
$deleted_properties = 0;
foreach ( $properties as $property_id ) {
	$title = (string) get_the_title( (int) $property_id );
	if ( 0 === strpos( $title, 'QA ' ) || false !== strpos( $title, 'QA Integration' ) ) {
		wp_delete_post( (int) $property_id, true );
		++$deleted_properties;
	}
}

$lead_table = esc_sql( estora_table( 'leads' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$deleted_leads = (int) $wpdb->query(
	$wpdb->prepare(
		"DELETE FROM `{$lead_table}` WHERE full_name LIKE %s OR full_name LIKE %s OR full_name IN ('Rate 0','Nope','Second customer','Sold property','Sold inquiry','Past','Blocked Buyer','QA Appointment Customer') OR email LIKE %s OR email LIKE %s OR phone LIKE %s OR phone LIKE %s OR phone LIKE %s",
		$like( 'QA ' ),
		$like( 'Rate' ),
		$like( '@estora-qa.test' ),
		$like( 'estora-qa' ),
		'+66 99%',
		'+66 98%',
		'+66 97%'
	)
);

$appointment_table = esc_sql( estora_table( 'appointments' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$deleted_appointments = (int) $wpdb->query(
	$wpdb->prepare(
		"DELETE FROM `{$appointment_table}` WHERE customer_name LIKE %s OR customer_name IN ('Second customer','Sold property','Past','Blocked Buyer') OR customer_email LIKE %s OR customer_phone LIKE %s OR customer_phone LIKE %s OR customer_phone LIKE %s",
		$like( 'QA ' ),
		$like( '@estora-qa.test' ),
		'+66 99%',
		'+66 98%',
		'+66 97%'
	)
);

$inquiry_table = esc_sql( estora_table( 'inquiries' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$deleted_inquiries = (int) $wpdb->query(
	$wpdb->prepare(
		"DELETE FROM `{$inquiry_table}` WHERE name LIKE %s OR name LIKE %s OR name = 'Sold inquiry' OR email LIKE %s",
		$like( 'QA ' ),
		$like( 'Rate' ),
		$like( '@estora-qa.test' )
	)
);

$note_table = esc_sql( estora_table( 'lead_notes' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$deleted_notes = (int) $wpdb->query(
	$wpdb->prepare( "DELETE FROM `{$note_table}` WHERE body LIKE %s", $like( 'QA E2E note' ) )
);

$campaign_table = esc_sql( estora_table( 'campaigns' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$deleted_campaigns = (int) $wpdb->query(
	$wpdb->prepare(
		"DELETE FROM `{$campaign_table}` WHERE name LIKE %s OR utm_campaign LIKE %s",
		$like( 'QA ' ),
		$like( 'qa-' )
	)
);

$notification_table = esc_sql( estora_table( 'notifications' ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$deleted_notifications = (int) $wpdb->query(
	$wpdb->prepare(
		"DELETE FROM `{$notification_table}` WHERE type = %s OR title LIKE %s",
		'qa.test',
		$like( 'QA ' )
	)
);

// Remove QA media and debug uploads.
$attachments = get_posts(
	array(
		'post_type'      => 'attachment',
		'post_status'    => 'any',
		'posts_per_page' => 100,
		'fields'         => 'ids',
		's'              => 'qa-',
	)
);
$deleted_media = 0;
foreach ( $attachments as $attachment_id ) {
	wp_delete_attachment( (int) $attachment_id, true );
	++$deleted_media;
}

delete_transient( 'estora_dashboard_summary' );
delete_transient( 'estora_filter_options' );

WP_CLI::log(
	sprintf(
		'QA cleanup: %d properties, %d leads, %d appointments, %d inquiries, %d campaigns, %d notifications, %d attachments.',
		$deleted_properties,
		$deleted_leads,
		$deleted_appointments,
		$deleted_inquiries,
		$deleted_campaigns,
		$deleted_notifications,
		$deleted_media
	)
);
