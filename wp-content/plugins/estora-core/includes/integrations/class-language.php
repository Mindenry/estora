<?php
/**
 * Public language switch (English / Thai).
 *
 * @package Estora\Core
 */

namespace Estora\Core\Integrations;

defined( 'ABSPATH' ) || exit;

final class Language {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'init', array( $this, 'persist' ), 1 );
		add_filter( 'determine_locale', array( $this, 'locale' ), 1 );
	}

	/**
	 * Persist ?lang= as a cookie for subsequent navigation.
	 */
	public function persist(): void {
		if ( ! isset( $_GET['lang'] ) ) {
			return;
		}
		$lang = sanitize_key( wp_unslash( $_GET['lang'] ) );
		if ( ! in_array( $lang, array( 'en', 'th' ), true ) ) {
			return;
		}
		if ( headers_sent() ) {
			return;
		}
		$path = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		setcookie( 'estora_lang', $lang, time() + 30 * DAY_IN_SECONDS, $path, COOKIE_DOMAIN, is_ssl(), true );
		$_COOKIE['estora_lang'] = $lang;
	}

	/**
	 * Tell WordPress to load Thai core strings on the public site.
	 *
	 * @param string $locale Current locale.
	 * @return string
	 */
	public function locale( $locale ): string {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return (string) $locale;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( str_contains( $uri, '/estora-admin/' ) || str_contains( $uri, '/wp-json/' ) || str_contains( $uri, '/wp-login.php' ) ) {
			return (string) $locale;
		}
		return estora_is_thai() ? 'th' : 'en_US';
	}
}
