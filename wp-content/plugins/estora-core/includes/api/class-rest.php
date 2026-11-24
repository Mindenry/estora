<?php
/**
 * REST route registry.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Api;

defined( 'ABSPATH' ) || exit;

final class Rest {

	/**
	 * Controllers.
	 *
	 * @return Rest_Controller[]
	 */
	private function controllers(): array {
		require_once ESTORA_CORE_DIR . '/includes/api/rest-helpers.php';

		return array(
			new Properties_Controller(),
			new Leads_Controller(),
			new Appointments_Controller(),
			new Agents_Controller(),
			new Dashboard_Controller(),
			new Favorites_Controller(),
			new Inquiries_Controller(),
			new Notifications_Controller(),
			new Campaigns_Controller(),
			new System_Controller(),
		);
	}

	/**
	 * Register all routes.
	 */
	public function hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	/**
	 * Register routes and normalise error responses.
	 */
	public function register(): void {
		foreach ( $this->controllers() as $controller ) {
			$controller->register_routes();
		}

		add_filter( 'rest_post_dispatch', array( $this, 'shape_error' ), 10, 3 );
	}

	/**
	 * Keep the ESTORA error envelope for WP_Error responses.
	 *
	 * @param \WP_HTTP_Response $response Response.
	 * @param \WP_REST_Server   $server   Server.
	 * @param \WP_REST_Request  $request  Request.
	 * @return \WP_HTTP_Response
	 */
	public function shape_error( $response, $server, $request ) {
		if ( ! $response instanceof \WP_REST_Response ) {
			return $response;
		}
		if ( 0 !== strpos( (string) $request->get_route(), '/estora/v1' ) ) {
			return $response;
		}

		$data = $response->get_data();
		if ( ! is_array( $data ) || ! isset( $data['code'] ) || isset( $data['success'] ) ) {
			return $response;
		}

		// WP_Error data comes back nested under $data['data'].
		$inner  = is_array( $data['data'] ?? null ) ? $data['data'] : array();
		$status = (int) ( $inner['status'] ?? $response->get_status() );
		$body   = array(
			'success' => false,
			'data'    => null,
			'message' => (string) ( $inner['message'] ?? $data['message'] ?? 'Request failed.' ),
		);
		if ( isset( $inner['errors'] ) ) {
			$body['errors'] = $inner['errors'];
		}
		if ( isset( $inner['meta'] ) ) {
			$body['meta'] = $inner['meta'];
		}

		$response->set_data( $body );
		$response->set_status( $status >= 400 ? $status : 400 );

		return $response;
	}
}
