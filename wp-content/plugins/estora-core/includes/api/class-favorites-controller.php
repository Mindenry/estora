<?php
/**
 * Favorites endpoints for guests and accounts.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Api;

defined( 'ABSPATH' ) || exit;

use Estora\Core\Services\Favorite_Service;
use Estora\Core\Support\Validation_Exception;
use WP_REST_Request;
use WP_REST_Response;

final class Favorites_Controller extends Rest_Controller {

	/**
	 * Routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/favorites',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'add' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/favorites/(?P<property_id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'remove' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * List.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( WP_REST_Request $request ): WP_REST_Response {
		$data = ( new Favorite_Service() )->listing( $this->visitor_token( $request ) );
		return $this->ok( $data );
	}

	/**
	 * Add (public, nonce + rate limit).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function add( WP_REST_Request $request ) {
		$guard = $this->public_write_guard( $request, 'favorite', '', 40, 600 );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$body = $request->get_json_params();
		$body = is_array( $body ) ? $body : $request->get_body_params();
		try {
			$result = ( new Favorite_Service() )->add( (int) ( $body['property_id'] ?? 0 ), $this->visitor_token( $request ) );
			return $this->ok( $result, array(), 200, 'Saved to favourites.' );
		} catch ( Validation_Exception $e ) {
			return $this->from_exception( $e );
		}
	}

	/**
	 * Remove.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function remove( WP_REST_Request $request ): WP_REST_Response {
		$result = ( new Favorite_Service() )->remove( (int) $request['property_id'], $this->visitor_token( $request ) );
		return $this->ok( $result, array(), 200, 'Removed from favourites.' );
	}
}
