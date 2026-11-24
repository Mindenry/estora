<?php
/**
 * WP-CLI commands.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Integrations;

defined( 'ABSPATH' ) || exit;

use Estora\Core\Database\Migrator;
use Estora\Core\Database\Schema;

/**
 * Manage ESTORA from the command line.
 */
final class Cli {

	/**
	 * Apply database migrations.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation.
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function migrate( array $args, array $assoc_args ): void {
		$applied = Migrator::run();
		if ( empty( $applied ) ) {
			\WP_CLI::success( 'Schema already at version ' . ESTORA_DB_VERSION . '.' );
			return;
		}
		foreach ( $applied as $line ) {
			\WP_CLI::log( 'Applied ' . $line );
		}
		\WP_CLI::success( 'Migrations complete.' );
	}

	/**
	 * Show table status and row counts.
	 */
	public function status(): void {
		global $wpdb;
		$status = Schema::status();
		$rows   = array();
		foreach ( $status as $table => $exists ) {
			$count = 0;
			if ( $exists ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . esc_sql( estora_table( $table ) ) . '`' );
			}
			$rows[] = array(
				'table' => estora_table( $table ),
				'exists' => $exists ? 'yes' : 'no',
				'rows'  => $count,
			);
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'table', 'exists', 'rows' ) );
		\WP_CLI::log( 'DB version: ' . get_option( Migrator::OPTION, 'none' ) . ' / code ' . ESTORA_DB_VERSION );
	}

	/**
	 * Seed development data.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Seed even when demo data already exists.
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function seed( array $args, array $assoc_args ): void {
		if ( 'production' === wp_get_environment_type() ) {
			\WP_CLI::error( 'Refusing to seed demo data in production.' );
		}
		$force  = isset( $assoc_args['force'] );
		$seeder = new \Estora\Core\Integrations\Seeder();
		$result = $seeder->run( $force );
		if ( ! $result['ran'] ) {
			\WP_CLI::warning( $result['message'] );
			return;
		}
		\WP_CLI::success( $result['message'] );
	}

	/**
	 * Anonymise inquiries and leads older than N days.
	 *
	 * ## OPTIONS
	 *
	 * [--older-than=<days>]
	 * : Days to keep. Default 365.
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function purge_inquiries( array $args, array $assoc_args ): void {
		global $wpdb;
		$days = isset( $assoc_args['older-than'] ) ? absint( $assoc_args['older-than'] ) : 365;
		if ( $days < 30 ) {
			\WP_CLI::error( 'Refusing to purge data younger than 30 days.' );
		}
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( estora_now() . ' -' . $days . ' days' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inquiries = (int) $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . esc_sql( estora_table( 'inquiries' ) ) . ' SET name = %s, email = NULL, phone = NULL, message = NULL WHERE created_at < %s',
				'Purged',
				$cutoff
			)
		);

		\WP_CLI::success( sprintf( 'Anonymised %d inquiries older than %d days.', $inquiries, $days ) );
	}
}
