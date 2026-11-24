<?php
/**
 * Schema versioning and idempotent upgrades.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Database;

defined( 'ABSPATH' ) || exit;

final class Migrator {

	public const OPTION = 'estora_db_version';

	/**
	 * Pending migration versions => description.
	 *
	 * @return array<string,string>
	 */
	public static function migrations(): array {
		return array(
			'1.0.0' => 'Initial schema: leads, notes, assignments, appointments, favorites, inquiries, locations, audit, notifications, campaigns.',
		);
	}

	/**
	 * Run when the stored version is behind.
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( self::OPTION ) !== ESTORA_DB_VERSION ) {
			self::run();
		}
	}

	/**
	 * Apply all migrations up to the current version.
	 *
	 * @return string[]
	 */
	public static function run(): array {
		$applied = array();
		$current = (string) get_option( self::OPTION, '0.0.0' );

		foreach ( self::migrations() as $version => $description ) {
			if ( version_compare( $current, $version, '>=' ) ) {
				continue;
			}
			Schema::create();
			update_option( self::OPTION, $version );
			$applied[] = $version . ' — ' . $description;
		}

		update_option( self::OPTION, ESTORA_DB_VERSION );

		if ( ! empty( $applied ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[ESTORA] migrations applied: ' . implode( ' | ', $applied ) );
		}

		wp_cache_delete( 'estora_schema_status', 'estora' );

		return $applied;
	}
}
