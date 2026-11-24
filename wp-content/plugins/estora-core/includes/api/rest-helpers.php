<?php
/**
 * REST helpers shared by controllers and tests.
 *
 * @package Estora\Core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the request carries a valid wp_rest nonce.
 *
 * @param \WP_REST_Request $request Request.
 */
function estora_rest_nonce_valid( \WP_REST_Request $request ): bool {
	$nonce = (string) $request->get_header( 'X-WP-Nonce' );
	return '' !== $nonce && (bool) wp_verify_nonce( $nonce, 'wp_rest' );
}

/**
 * Whether the request authenticates with HTTP Basic (Application Password).
 *
 * @param \WP_REST_Request $request Request.
 */
function estora_rest_uses_basic_auth( \WP_REST_Request $request ): bool {
	$auth = (string) $request->get_header( 'authorization' );
	return '' !== $auth && 0 === stripos( $auth, 'Basic ' );
}
