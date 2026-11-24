<?php
/**
 * Login hardening: inactive accounts and attempt throttling.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Integrations;

defined( 'ABSPATH' ) || exit;

final class Login_Guard {

	public const MAX_ATTEMPTS = 10;
	public const WINDOW       = 900;

	/**
	 * Register hooks.
	 */
	public function hooks(): void {
		add_filter( 'authenticate', array( $this, 'block_inactive' ), 40, 3 );
		add_filter( 'authenticate', array( $this, 'block_locked_out' ), 5, 3 );
		add_action( 'wp_login_failed', array( $this, 'record_failure' ) );
		add_action( 'wp_login', array( $this, 'clear_failures' ), 10, 2 );
	}

	/**
	 * Reject deactivated staff accounts.
	 *
	 * @param \WP_User|\WP_Error|null $user     User or error.
	 * @param string                  $username Username.
	 * @param string                  $password Password.
	 * @return \WP_User|\WP_Error|null
	 */
	public function block_inactive( $user, $username, $password ) {
		if ( $user instanceof \WP_User && '0' === (string) get_user_meta( $user->ID, 'estora_active', true ) ) {
			return new \WP_Error( 'estora_inactive', __( 'This account has been deactivated. Contact your administrator.', 'estora-core' ) );
		}
		return $user;
	}

	/**
	 * Reject when the IP has too many recent failures.
	 *
	 * @param \WP_User|\WP_Error|null $user     User or error.
	 * @param string                  $username Username.
	 * @param string                  $password Password.
	 * @return \WP_User|\WP_Error|null
	 */
	public function block_locked_out( $user, $username, $password ) {
		if ( empty( $username ) || empty( $password ) ) {
			return $user;
		}
		$attempts = (int) get_transient( $this->key() );
		if ( $attempts >= self::MAX_ATTEMPTS ) {
			return new \WP_Error(
				'estora_locked',
				__( 'Too many failed sign-in attempts. Try again in a few minutes.', 'estora-core' )
			);
		}
		return $user;
	}

	/**
	 * Increment the failure counter.
	 */
	public function record_failure(): void {
		$key      = $this->key();
		$attempts = (int) get_transient( $key );
		set_transient( $key, $attempts + 1, self::WINDOW );
	}

	/**
	 * Clear the counter after a successful login.
	 *
	 * @param string   $user_login Login.
	 * @param \WP_User $user       User.
	 */
	public function clear_failures( $user_login, $user ): void {
		delete_transient( $this->key() );
		if ( $user instanceof \WP_User && '0' === (string) get_user_meta( $user->ID, 'estora_active', true ) ) {
			wp_logout();
		}
	}

	/**
	 * Per-IP transient key.
	 */
	private function key(): string {
		return 'estora_login_fail_' . substr( estora_ip_hash(), 0, 20 );
	}
}
