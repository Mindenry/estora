<?php
/**
 * Plugin Name:       ESTORA Core
 * Plugin URI:        https://example.com/estora
 * Description:       Property inventory, CRM, appointments, reporting and the ESTORA REST API.
 * Version:           1.2.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            ESTORA Engineering
 * License:           GPL-2.0-or-later
 * Text Domain:       estora-core
 * Domain Path:       /languages
 *
 * @package Estora\Core
 */

defined( 'ABSPATH' ) || exit;

define( 'ESTORA_CORE_VERSION', '1.2.0' );
define( 'ESTORA_DB_VERSION', '1.0.0' );
define( 'ESTORA_CORE_FILE', __FILE__ );
define( 'ESTORA_CORE_DIR', __DIR__ );
define( 'ESTORA_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once ESTORA_CORE_DIR . '/includes/support/class-autoloader.php';
Estora\Core\Support\Autoloader::register();
require_once ESTORA_CORE_DIR . '/includes/support/helpers.php';

register_activation_hook( __FILE__, array( Estora\Core\Support\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Estora\Core\Support\Installer::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		Estora\Core\Plugin::instance()->boot();
	},
	5
);
