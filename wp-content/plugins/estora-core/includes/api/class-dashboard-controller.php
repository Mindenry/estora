<?php
/**
 * Dashboard analytics endpoints.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Api;

defined( 'ABSPATH' ) || exit;

use Estora\Core\Services\Report_Service;
use Estora\Core\Support\Capabilities;
use WP_REST_Request;
use WP_REST_Response;

final class Dashboard_Controller extends Rest_Controller {

	/**
	 * Routes.
	 */
	public function register_routes(): void {
		$permission = $this->can( Capabilities::VIEW_REPORTS );

		register_rest_route(
			$this->namespace,
			'/dashboard/summary',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'summary' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$this->namespace,
			'/dashboard/property-statistics',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'property_statistics' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$this->namespace,
			'/dashboard/lead-statistics',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'lead_statistics' ),
				'permission_callback' => $permission,
			)
		);

		register_rest_route(
			$this->namespace,
			'/dashboard/appointment-statistics',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'appointment_statistics' ),
				'permission_callback' => $permission,
			)
		);
	}

	/**
	 * Summary.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function summary( WP_REST_Request $request ): WP_REST_Response {
		$service = new Report_Service();
		$data    = $service->summary( (bool) $request->get_param( 'refresh' ) );

		return $this->ok( $data, array( 'cached' => (bool) ( $data['cached'] ?? false ), 'ttl' => 300 ) );
	}

	/**
	 * Property statistics.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function property_statistics( WP_REST_Request $request ): WP_REST_Response {
		return $this->ok( ( new Report_Service() )->property_statistics() );
	}

	/**
	 * Lead statistics.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function lead_statistics( WP_REST_Request $request ): WP_REST_Response {
		$days = max( 7, min( 365, (int) $request->get_param( 'days' ) ?: 30 ) );
		$data = ( new Report_Service() )->lead_statistics( $days );
		return $this->ok( $data, array( 'days' => $days ) );
	}

	/**
	 * Appointment statistics.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function appointment_statistics( WP_REST_Request $request ): WP_REST_Response {
		return $this->ok( ( new Report_Service() )->appointment_statistics() );
	}
}
