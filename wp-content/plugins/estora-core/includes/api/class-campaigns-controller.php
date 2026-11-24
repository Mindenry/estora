<?php
/**
 * Campaign endpoints.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Api;

defined( 'ABSPATH' ) || exit;

use Estora\Core\Repositories\Activity_Repository;
use Estora\Core\Repositories\Campaign_Repository;
use Estora\Core\Support\Capabilities;
use Estora\Core\Support\Validation_Exception;
use Estora\Core\Support\Validator;
use WP_REST_Request;
use WP_REST_Response;

final class Campaigns_Controller extends Rest_Controller {

	/**
	 * Routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/campaigns',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => $this->can( Capabilities::MANAGE_CAMPAIGNS ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => $this->can( Capabilities::MANAGE_CAMPAIGNS ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/campaigns/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $this->can( Capabilities::MANAGE_CAMPAIGNS ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => $this->can( Capabilities::MANAGE_CAMPAIGNS ),
				),
			)
		);
	}

	/**
	 * GET /campaigns.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( WP_REST_Request $request ): WP_REST_Response {
		$result = ( new Campaign_Repository() )->query(
			array(
				'status'   => (string) $request->get_param( 'status' ),
				'page'     => max( 1, (int) $request->get_param( 'page' ) ),
				'per_page' => max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) ),
			)
		);

		return $this->ok(
			$result['items'],
			$this->pagination_meta( $result['page'], $result['per_page'], $result['total'], $result['pages'] )
		);
	}

	/**
	 * POST /campaigns.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function create_item( WP_REST_Request $request ) {
		$body = $this->body( $request );
		try {
			$this->validate( $body );
			$repo = new Campaign_Repository();
			$id   = $repo->create( $body );
			( new Activity_Repository() )->log( 'campaign.created', 'campaign', $id, 'Campaign created.' );
			return $this->ok( $repo->find( $id ), array(), 201, 'Campaign created.' );
		} catch ( Validation_Exception $e ) {
			return $this->from_exception( $e );
		}
	}

	/**
	 * PATCH /campaigns/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function update_item( WP_REST_Request $request ) {
		$body = $this->body( $request );
		$repo = new Campaign_Repository();
		$id   = (int) $request['id'];
		if ( ! $repo->find( $id ) ) {
			return $this->fail( 'Campaign not found.', array(), 404 );
		}

		try {
			$repo->update( $id, $body );
			( new Activity_Repository() )->log( 'campaign.updated', 'campaign', $id, 'Campaign updated.' );
			return $this->ok( $repo->find( $id ), array(), 200, 'Campaign updated.' );
		} catch ( Validation_Exception $e ) {
			return $this->from_exception( $e );
		}
	}

	/**
	 * DELETE /campaigns/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function delete_item( WP_REST_Request $request ) {
		$id   = (int) $request['id'];
		$repo = new Campaign_Repository();
		if ( ! $repo->find( $id ) ) {
			return $this->fail( 'Campaign not found.', array(), 404 );
		}
		$repo->delete( $id );
		( new Activity_Repository() )->log( 'campaign.deleted', 'campaign', $id, 'Campaign deleted.' );
		return $this->ok( null, array(), 200, 'Campaign deleted.' );
	}

	/**
	 * Validate a campaign.
	 *
	 * @param array<string,mixed> $body Body.
	 * @throws Validation_Exception When invalid.
	 */
	private function validate( array $body ): void {
		Validator::assert(
			array(
				'name'         => $body['name'] ?? '',
				'utm_campaign' => $body['utm_campaign'] ?? '',
				'status'       => $body['status'] ?? 'draft',
				'starts_at'    => $body['starts_at'] ?? '',
				'ends_at'      => $body['ends_at'] ?? '',
			),
			array(
				'name'         => array( 'required', 'max_length:190' ),
				'utm_campaign' => array( 'required', 'max_length:100' ),
				'status'       => array( 'in:draft,active,paused,ended' ),
				'starts_at'    => array( 'date' ),
				'ends_at'      => array( 'date' ),
			),
			'Please check the campaign details.'
		);

		if ( ! empty( $body['starts_at'] ) && ! empty( $body['ends_at'] ) && $body['ends_at'] < $body['starts_at'] ) {
			throw new Validation_Exception( 'Validation failed.', array( 'ends_at' => array( 'The end date must be after the start date.' ) ) );
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
