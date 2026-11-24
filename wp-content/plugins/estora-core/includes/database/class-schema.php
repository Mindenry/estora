<?php
/**
 * Database schema. All tables are created with dbDelta().
 *
 * @package Estora\Core
 */

namespace Estora\Core\Database;

defined( 'ABSPATH' ) || exit;

final class Schema {

	/**
	 * Table names used by the plugin.
	 *
	 * @return string[]
	 */
	public static function tables(): array {
		return array(
			'leads',
			'lead_notes',
			'lead_assignments',
			'appointments',
			'favorites',
			'inquiries',
			'locations',
			'activity_logs',
			'notifications',
			'campaigns',
		);
	}

	/**
	 * Create or update every table.
	 */
	public static function create(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;

		$sql = array();

		$sql[] = "CREATE TABLE {$p}estora_leads (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			public_ref varchar(20) NOT NULL DEFAULT '',
			full_name varchar(150) NOT NULL,
			email varchar(190) DEFAULT NULL,
			phone varchar(30) DEFAULT NULL,
			preferred_contact varchar(20) NOT NULL DEFAULT 'phone',
			property_id bigint(20) unsigned DEFAULT NULL,
			budget_min decimal(15,2) DEFAULT NULL,
			budget_max decimal(15,2) DEFAULT NULL,
			preferred_location varchar(190) DEFAULT NULL,
			message text DEFAULT NULL,
			source varchar(40) NOT NULL DEFAULT 'website',
			utm_source varchar(100) DEFAULT NULL,
			utm_medium varchar(100) DEFAULT NULL,
			utm_campaign varchar(100) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'new',
			priority varchar(10) NOT NULL DEFAULT 'normal',
			assigned_agent_id bigint(20) unsigned DEFAULT NULL,
			dedupe_key char(64) DEFAULT NULL,
			last_contact_at datetime DEFAULT NULL,
			next_follow_up_at datetime DEFAULT NULL,
			won_value decimal(15,2) DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY public_ref (public_ref),
			KEY status_created (status,created_at),
			KEY agent_status (assigned_agent_id,status),
			KEY property_id (property_id),
			KEY dedupe_lookup (dedupe_key,created_at),
			KEY follow_up (next_follow_up_at)
		) {$charset};";

		$sql[] = "CREATE TABLE {$p}estora_lead_notes (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) unsigned NOT NULL,
			author_id bigint(20) unsigned NOT NULL DEFAULT 0,
			note_type varchar(20) NOT NULL DEFAULT 'note',
			body text NOT NULL,
			is_internal tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY lead_created (lead_id,created_at),
			KEY author_id (author_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$p}estora_lead_assignments (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lead_id bigint(20) unsigned NOT NULL,
			from_agent_id bigint(20) unsigned DEFAULT NULL,
			to_agent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			assigned_by bigint(20) unsigned NOT NULL DEFAULT 0,
			reason varchar(255) DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY lead_created (lead_id,created_at),
			KEY to_agent (to_agent_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$p}estora_appointments (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			public_ref varchar(20) NOT NULL DEFAULT '',
			property_id bigint(20) unsigned NOT NULL DEFAULT 0,
			lead_id bigint(20) unsigned DEFAULT NULL,
			agent_id bigint(20) unsigned DEFAULT NULL,
			customer_name varchar(150) NOT NULL,
			customer_email varchar(190) DEFAULT NULL,
			customer_phone varchar(30) NOT NULL,
			requested_date date NOT NULL,
			start_time time NOT NULL,
			end_time time NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			source varchar(40) NOT NULL DEFAULT 'website',
			notes text DEFAULT NULL,
			internal_notes text DEFAULT NULL,
			active_key varchar(80) DEFAULT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY public_ref (public_ref),
			UNIQUE KEY active_key (active_key),
			KEY agent_date (agent_id,requested_date,status),
			KEY property_date (property_id,requested_date),
			KEY status_date (status,requested_date),
			KEY lead_id (lead_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$p}estora_favorites (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			owner_key varchar(80) NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			visitor_token char(64) DEFAULT NULL,
			property_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY owner_property (owner_key,property_id),
			KEY property_id (property_id),
			KEY user_id (user_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$p}estora_inquiries (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			property_id bigint(20) unsigned NOT NULL DEFAULT 0,
			lead_id bigint(20) unsigned DEFAULT NULL,
			name varchar(150) NOT NULL,
			email varchar(190) DEFAULT NULL,
			phone varchar(30) DEFAULT NULL,
			message text DEFAULT NULL,
			source varchar(40) NOT NULL DEFAULT 'website',
			status varchar(20) NOT NULL DEFAULT 'new',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY property_created (property_id,created_at),
			KEY status (status),
			KEY lead_id (lead_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$p}estora_locations (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(150) NOT NULL,
			slug varchar(190) NOT NULL,
			type varchar(20) NOT NULL DEFAULT 'province',
			parent_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug_type (slug,type),
			KEY parent_id (parent_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$p}estora_activity_logs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(60) NOT NULL,
			object_type varchar(40) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			description varchar(255) NOT NULL DEFAULT '',
			meta longtext DEFAULT NULL,
			ip_hash char(64) DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY object_lookup (object_type,object_id),
			KEY actor_created (actor_id,created_at),
			KEY action (action),
			KEY created_at (created_at)
		) {$charset};";

		$sql[] = "CREATE TABLE {$p}estora_notifications (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			type varchar(60) NOT NULL,
			title varchar(190) NOT NULL,
			body text DEFAULT NULL,
			link varchar(255) DEFAULT NULL,
			is_read tinyint(1) NOT NULL DEFAULT 0,
			dedupe_key char(64) DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY dedupe_key (dedupe_key),
			KEY user_unread (user_id,is_read,created_at)
		) {$charset};";

		$sql[] = "CREATE TABLE {$p}estora_campaigns (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			slug varchar(190) NOT NULL,
			channel varchar(40) NOT NULL DEFAULT 'web',
			utm_source varchar(100) DEFAULT NULL,
			utm_medium varchar(100) DEFAULT NULL,
			utm_campaign varchar(100) NOT NULL,
			landing_page_id bigint(20) unsigned DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			budget decimal(15,2) DEFAULT NULL,
			starts_at date DEFAULT NULL,
			ends_at date DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status_start (status,starts_at),
			KEY utm_campaign (utm_campaign)
		) {$charset};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		self::ensure_foreign_keys();
	}

	/**
	 * Add same-domain foreign keys that dbDelta cannot express.
	 */
	private static function ensure_foreign_keys(): void {
		global $wpdb;

		$constraints = array(
			array(
				'table'  => $wpdb->prefix . 'estora_lead_notes',
				'name'   => 'fk_estora_notes_lead',
				'column' => 'lead_id',
				'ref'    => $wpdb->prefix . 'estora_leads',
			),
			array(
				'table'  => $wpdb->prefix . 'estora_lead_assignments',
				'name'   => 'fk_estora_assignments_lead',
				'column' => 'lead_id',
				'ref'    => $wpdb->prefix . 'estora_leads',
			),
		);

		foreach ( $constraints as $constraint ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
					 WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = %s AND CONSTRAINT_NAME = %s',
					$constraint['table'],
					$constraint['name']
				)
			);

			if ( $exists ) {
				continue;
			}

			$table  = esc_sql( $constraint['table'] );
			$ref    = esc_sql( $constraint['ref'] );
			$name   = esc_sql( $constraint['name'] );
			$column = esc_sql( $constraint['column'] );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- identifiers are allow-listed and esc_sql()'d.
			$wpdb->query(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- identifiers are allow-listed and esc_sql()'d.
				"ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` FOREIGN KEY (`{$column}`) REFERENCES `{$ref}` (`id`) ON DELETE CASCADE"
			);
		}
	}

	/**
	 * Whether all expected tables exist.
	 *
	 * @return array<string,bool>
	 */
	public static function status(): array {
		global $wpdb;
		$out = array();
		foreach ( self::tables() as $table ) {
			$name = $wpdb->prefix . 'estora_' . $table;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$out[ $table ] = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) );
		}
		return $out;
	}
}
