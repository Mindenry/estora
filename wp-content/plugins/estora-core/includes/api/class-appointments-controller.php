<?php
/**
 * Appointment REST endpoints.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Api;

defined( 'ABSPATH' ) || exit;

use Estora\Core\Services\Appointment_Service;
use Estora\Core\Services\Settings;
use Estora\Core\Support\Capabilities;
use Estora\Core\Support\Validation_Exception;
use WP_REST_Request;
use WP_REST_Response;

final class Appointments_Controller extends Rest_Controller {

	/**
	 * Routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/appointments',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => $this->can( Capabilities::MANAGE_APPOINTMENTS ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/appointments/availability',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'availability' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/appointments/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => $this->can( Capabilities::MANAGE_APPOINTMENTS ),
				),
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $this->can( Capabilities::MANAGE_APPOINTMENTS ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/appointments/(?P<id>\d+)/status',
			array(
				'methods'             => 'PATCH,POST',
				'callback'            => array( $this, 'change_status' ),
				'permission_callback' => $this->can( Capabilities::MANAGE_APPOINTMENTS ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/appointments/(?P<id>\d+)/reschedule',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reschedule' ),
				'permission_callback' => $this->can( Capabilities::MANAGE_APPOINTMENTS ),
			)
		);
	}

	/**
	 * GET /appointments.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( WP_REST_Request $request ): WP_REST_Response {
		$service = new Appointment_Service();
		$agent   = $service->scope_agent_id();
		if ( $request->get_param( 'agent_id' ) && $service->can_view_all() ) {
			$agent = (int) $request->get_param( 'agent_id' );
		}

		$result = $service->repository()->query(
			array(
				'status'      => (string) $request->get_param( 'status' ),
				'agent_id'    => $agent,
				'property_id' => (int) $request->get_param( 'property_id' ),
				'from'        => (string) $request->get_param( 'from' ),
				'to'          => (string) $request->get_param( 'to' ),
				'page'        => max( 1, (int) $request->get_param( 'page' ) ),
				'per_page'    => max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) ),
				'order'       => (string) $request->get_param( 'order' ) ?: 'ASC',
			)
		);

		return $this->ok(
			$result['items'],
			array_merge(
				$this->pagination_meta( $result['page'], $result['per_page'], $result['total'], $result['pages'] ),
				array( 'counts' => $result['counts'] )
			)
		);
	}

	/**
	 * GET /appointments/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_item( WP_REST_Request $request ) {
		try {
			return $this->ok( ( new Appointment_Service() )->detail( (int) $request['id'] ) );
		} catch ( Validation_Exception $e ) {
			return $this->from_exception( $e );
		}
	}

	/**
	 * GET /appointments/availability.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function availability( WP_REST_Request $request ) {
		$date = (string) $request->get_param( 'date' );
		if ( ! $date ) {
			return $this->fail( 'A date is required.', array( 'date' => array( 'Choose a date.' ) ), 400 );
		}

		try {
			return $this->ok(
				( new Appointment_Service() )->available_slots(
					$date,
					(int) $request->get_param( 'agent_id' ),
					(int) $request->get_param( 'property_id' ),
					(int) $request->get_param( 'exclude' )
				)
			);
		} catch ( Validation_Exception $e ) {
			return $this->from_exception( $e );
		}
	}

	/**
	 * POST /appointments (public or staff).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function create_item( WP_REST_Request $request ) {
		$body   = $this->body( $request );
		$staff  = is_user_logged_in() && current_user_can( Capabilities::MANAGE_APPOINTMENTS ) && ! empty( $body['staff'] );
		$public = ! $staff;

		if ( $public ) {
			$guard = $this->public_write_guard( $request, 'appointment', (string) ( $body['phone'] ?? '' ), 5, 600 );
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}
			if ( ! empty( $body['company'] ) ) {
				return $this->fail( 'The request could not be processed.', array(), 422 );
			}
		}

		try {
			$appointment = ( new Appointment_Service() )->request( $body, $public );
			return $this->ok(
				array(
					'id'         => $appointment['id'],
					'public_ref' => $appointment['public_ref'],
					'status'     => $appointment['status'],
					'date'       => $appointment['date'],
					'time'       => $appointment['time'],
					'property'   => $appointment['property_title'],
				),
				array(),
				201,
				'Viewing request received. The office will confirm shortly.'
			);
		} catch ( Validation_Exception $e ) {
			return $this->from_exception( $e );
		}
	}

	/**
	 * PATCH /appointments/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function update_item( WP_REST_Request $request ) {
		try {
			return $this->ok( ( new Appointment_Service() )->update_details( (int) $request['id'], $this->body( $request ) ), array(), 200, 'Appointment updated.' );
		} catch ( Validation_Exception $e ) {
			return $this->from_exception( $e );
		}
	}

	/**
	 * PATCH /appointments/{id}/status.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function change_status( WP_REST_Request $request ) {
		$body = $this->body( $request );
		try {
			return $this->ok(
				( new Appointment_Service() )->change_status(
					(int) $request['id'],
					(string) ( $body['status'] ?? '' ),
					(string) ( $body['internal_notes'] ?? '' ),
					absint( $body['agent_id'] ?? 0 )
				),
				array(),
				200,
				'Appointment status updated.'
			);
		} catch ( Validation_Exception $e ) {
			return $this->from_exception( $e );
		}
	}

	/**
	 * POST /appointments/{id}/reschedule.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function reschedule( WP_REST_Request $request ) {
		$body = $this->body( $request );
		try {
			return $this->ok(
				( new Appointment_Service() )->reschedule(
					(int) $request['id'],
					(string) ( $body['date'] ?? '' ),
					(string) ( $body['time'] ?? '' ),
					absint( $body['agent_id'] ?? 0 ),
					(string) ( $body['reason'] ?? '' )
				),
				array(),
				200,
				'Appointment rescheduled.'
			);
		} catch ( Validation_Exception $e ) {
			return $this->from_exception( $e );
		}
	}

	/**
	 * Body helper.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>
	 */
	private function body( WP_REST_Request $request ): array {
		$json = $request->get_json_params();
		return is_array( $json ) && ! empty( $json ) ? $json : (array) $request->get_body_params();
	}
}
