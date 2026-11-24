<?php
/**
 * Lead REST endpoints.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Api;

defined( 'ABSPATH' ) || exit;

use Estora\Core\Domain\Lead_Status;
use Estora\Core\Services\Lead_Service;
use Estora\Core\Support\Capabilities;
use Estora\Core\Support\Validation_Exception;
use WP_REST_Request;
use WP_REST_Response;

final class Leads_Controller extends Rest_Controller {

	/**
	 * Routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/leads',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => $this->can( Capabilities::VIEW_LEADS ),
					'args'                => $this->list_args(),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => $this->can( Capabilities::CREATE_LEADS ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/leads/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => $this->can( Capabilities::VIEW_LEADS ),
				),
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $this->can( Capabilities::EDIT_LEADS ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => $this->can( Capabilities::DELETE_LEADS ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/leads/(?P<id>\d+)/assign',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'assign_item' ),
				'permission_callback' => $this->can( Capabilities::ASSIGN_LEADS ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/leads/(?P<id>\d+)/notes',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'add_note' ),
				'permission_callback' => $this->can( Capabilities::EDIT_LEADS ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/leads/(?P<id>\d+)/status',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'change_status' ),
				'permission_callback' => $this->can( Capabilities::EDIT_LEADS ),
			)
		);
	}

	/**
	 * List args.
	 *
	 * @return array<string,mixed>
	 */
	private function list_args(): array {
		$args = $this->pagination_args();
		foreach ( array( 'status', 'source', 'priority', 'q', 'overdue' ) as $key ) {
			$args[ $key ] = array(
				'sanitize_callback' => static fn( $value ) => sanitize_text_field( (string) $value ),
			);
		}
		$args['agent_id']    = array( 'sanitize_callback' => 'absint' );
		$args['property_id'] = array( 'sanitize_callback' => 'absint' );
		$args['orderby']     = array( 'sanitize_callback' => static fn( $v ) => sanitize_key( (string) $v ) );
		$args['order']       = array( 'sanitize_callback' => static fn( $v ) => sanitize_key( (string) $v ) );
		return $args;
	}

	/**
	 * GET /leads.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_items( WP_REST_Request $request ) {
		$service = new Lead_Service();
		$agent   = $service->scope_agent_id();

		if ( current_user_can( Capabilities::VIEW_ALL_LEADS ) && $request->get_param( 'agent_id' ) ) {
			$agent = (int) $request->get_param( 'agent_id' );
		}

		$result = $service->repository()->query(
			array(
				'status'      => (string) $request->get_param( 'status' ),
				'source'      => (string) $request->get_param( 'source' ),
				'priority'    => (string) $request->get_param( 'priority' ),
				'q'           => (string) $request->get_param( 'q' ),
				'overdue'     => (bool) $request->get_param( 'overdue' ),
				'property_id' => (int) $request->get_param( 'property_id' ),
				'agent_id'    => $agent,
				'page'        => (int) $request->get_param( 'page' ),
				'per_page'    => (int) $request->get_param( 'per_page' ),
				'orderby'     => (string) $request->get_param( 'orderby' ) ?: 'created_at',
				'order'       => (string) $request->get_param( 'order' ) ?: 'DESC',
			)
		);

		$response = $this->ok(
			$result['items'],
			array_merge(
				$this->pagination_meta( $result['page'], $result['per_page'], $result['total'], $result['pages'] ),
				array( 'counts' => $result['counts'] + array_fill_keys( Lead_Status::all(), 0 ) )
			)
		);
		$response->header( 'X-WP-Total', (string) $result['total'] );

		return $response;
	}

	/**
	 * GET /leads/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_item( WP_REST_Request $request ) {
		try {
			return $this->ok( ( new Lead_Service() )->detail( (int) $request['id'] ) );
		} catch ( Validation_Exception $e ) {
			return $this->from_exception( $e );
		}
	}

	/**
	 * POST /leads.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function create_item( WP_REST_Request $request ) {
		try {
			$lead = ( new Lead_Service() )->create_manual( $this->body( $request ) );
			return $this->ok( $lead, array(), 201, 'Lead created.' );
		} catch ( Validation_Exception $e ) {
			return $this->from_exception( $e );
		}
	}

	/**
	 * PATCH /leads/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function update_item( WP_REST_Request $request ) {
		try {
			$lead = ( new Lead_Service() )->update( (int) $request['id'], $this->body( $request ) );
			return $this->ok( $lead, array(), 200, 'Lead updated.' );
		} catch ( Validation_Exception $e ) {
			return $this->from_exception( $e );
		}
	}

	/**
	 * DELETE /leads/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function delete_item( WP_REST_Request $request ) {
		try {
			( new Lead_Service() )->delete( (int) $request['id'] );
			return $this->ok( null, array(), 200, 'Lead deleted.' );
		} catch ( Validation_Exception $e ) {
			return $this->from_exception( $e );
		}
	}

	/**
	 * POST /leads/{id}/assign.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function assign_item( WP_REST_Request $request ) {
		$body = $this->body( $request );
		try {
			$lead = ( new Lead_Service() )->assign( (int) $request['id'], absint( $body['agent_id'] ?? 0 ), (string) ( $body['reason'] ?? '' ) );
			return $this->ok( $lead, array(), 200, 'Lead assigned.' );
		} catch ( Validation_Exception $e ) {
			return $this->from_exception( $e );
		}
	}

	/**
	 * POST /leads/{id}/notes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function add_note( WP_REST_Request $request ) {
		$body = $this->body( $request );
		try {
			$lead = ( new Lead_Service() )->add_note(
				(int) $request['id'],
				(string) ( $body['body'] ?? '' ),
				(string) ( $body['note_type'] ?? 'note' ),
				(bool) ( $body['is_internal'] ?? false )
			);
			return $this->ok( $lead, array(), 201, 'Note added.' );
		} catch ( Validation_Exception $e ) {
			return $this->from_exception( $e );
		}
	}

	/**
	 * POST /leads/{id}/status.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function change_status( WP_REST_Request $request ) {
		$body = $this->body( $request );
		try {
			$lead = ( new Lead_Service() )->change_status(
				(int) $request['id'],
				(string) ( $body['status'] ?? '' ),
				(string) ( $body['note'] ?? '' ),
				isset( $body['won_value'] ) ? (float) $body['won_value'] : null
			);
			return $this->ok( $lead, array(), 200, 'Lead status updated.' );
		} catch ( Validation_Exception $e ) {
			return $this->from_exception( $e );
		}
	}

	/**
	 * JSON or form body.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>
	 */
	private function body( WP_REST_Request $request ): array {
		$json = $request->get_json_params();
		return is_array( $json ) && ! empty( $json ) ? $json : (array) $request->get_body_params();
	}
}
