<?php
/**
 * Activation, deactivation and upgrade routines.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Support;

use Estora\Core\Database\Migrator;
use Estora\Core\Database\Schema;
use Estora\Core\Services\Settings;

defined( 'ABSPATH' ) || exit;

final class Installer {

	/**
	 * Activate the plugin.
	 */
	public static function activate(): void {
		Schema::create();
		Migrator::run();
		Capabilities::install();
		Settings::ensure();

		update_option( 'estora_flush_rewrite', '1' );
		update_option( 'estora_plugin_activated_at', estora_now() );

		if ( ! wp_next_scheduled( 'estora_daily_maintenance' ) ) {
			wp_schedule_event( time() + 3600, 'daily', 'estora_daily_maintenance' );
		}
	}

	/**
	 * Deactivate the plugin. Data is preserved.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'estora_daily_maintenance' );
		wp_clear_scheduled_hook( 'estora_expire_pending' );
		flush_rewrite_rules();
	}
}
