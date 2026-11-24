<?php
/**
 * Plugin container and hook wiring.
 *
 * @package Estora\Core
 */

namespace Estora\Core;

use Estora\Core\Api\Rest;
use Estora\Core\Admin\Admin_App;
use Estora\Core\Integrations\Admin_UI;
use Estora\Core\Integrations\Content_Types;
use Estora\Core\Integrations\Cron;
use Estora\Core\Integrations\Lifecycle;
use Estora\Core\Integrations\Login_Guard;
use Estora\Core\Integrations\SEO;
use Estora\Core\Integrations\Upload_Guard;
use Estora\Core\Support\Capabilities;
use Estora\Core\Database\Migrator;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/**
	 * Singleton.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Boot guard.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Instance accessor.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire hooks once.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		( new Content_Types() )->hooks();
		( new Admin_App() )->hooks();
		( new Admin_UI() )->hooks();
		( new Rest() )->hooks();
		( new SEO() )->hooks();
		( new Lifecycle() )->hooks();
		( new Login_Guard() )->hooks();
		( new Upload_Guard() )->hooks();
		( new Cron() )->hooks();

		add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 99 );
		add_action( 'init', array( Capabilities::class, 'maybe_install' ), 4 );
		add_action( 'init', array( Migrator::class, 'maybe_upgrade' ), 3 );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'estora', \Estora\Core\Integrations\Cli::class );
		}
	}

	/**
	 * Flush rewrite rules once after activation.
	 */
	public function maybe_flush_rewrites(): void {
		if ( '1' !== get_option( 'estora_flush_rewrite' ) ) {
			return;
		}
		flush_rewrite_rules( false );
		delete_option( 'estora_flush_rewrite' );
	}
}
