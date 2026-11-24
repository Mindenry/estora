<?php
/**
 * PSR-4 style autoloader for the plugin.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Support;

defined( 'ABSPATH' ) || exit;

final class Autoloader {

	/**
	 * Register the autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Map a namespaced class to includes/<segment>/class-<name>.php.
	 *
	 * @param string $class Fully qualified class name.
	 */
	public static function load( string $class ): void {
		$prefix = 'Estora\\Core\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$name     = array_pop( $parts );
		$file     = 'class-' . strtolower( str_replace( '_', '-', $name ) ) . '.php';
		$dir      = '';

		if ( ! empty( $parts ) ) {
			$dir = strtolower( implode( '/', $parts ) ) . '/';
		}

		$path = ESTORA_CORE_DIR . '/includes/' . $dir . $file;
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}
