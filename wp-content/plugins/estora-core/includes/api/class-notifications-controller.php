<?php
/**
 * Per-user notification endpoints.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Api;

defined( 'ABSPATH' ) || exit;

use Estora\Core\Repositories\Notification_Repository;
use Estora\Core\Services\Notification_Service;
use WP_REST_Request;
use WP_REST_Response;

final class Notifications_Controller extends Rest_Controller {

	/**
	 * Routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/notifications',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => $this->logged_in(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/notifications/read-all',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'read_all' ),
				'permission_callback' => $this->logged_in(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/notifications/(?P<id>\d+)/read',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'read_one' ),
				'permission_callback' => $this->logged_in(),
			)
		);
	}

	/**
	 * GET /notifications.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( WP_REST_Request $request ): WP_REST_Response {
		$service = new Notification_Service();
		$unread  = (bool) $request->get_param( 'unread' );
		$items   = $service->for_current_user( $unread, 50 );

		return $this->ok( $items, array( 'unread' => $service->unread_count() ) );
	}

	/**
	 * POST /notifications/{id}/read.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function read_one( WP_REST_Request $request ) {
		$repo = new Notification_Repository();
		$ok   = $repo->mark_read( (int) $request['id'], get_current_user_id() );
		if ( ! $ok ) {
			return $this->fail( 'Notification not found.', array(), 404 );
		}
		return $this->ok( array( 'unread' => $repo->unread_count( get_current_user_id() ) ), array(), 200, 'Marked as read.' );
	}

	/**
	 * POST /notifications/read-all.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function read_all( WP_REST_Request $request ): WP_REST_Response {
		$repo = new Notification_Repository();
		$repo->mark_all_read( get_current_user_id() );
		return $this->ok( array( 'unread' => 0 ), array(), 200, 'All notifications marked as read.' );
	}
}
