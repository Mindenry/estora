<?php
/**
 * Public inquiry capture plus staff inquiry list.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Api;

defined( 'ABSPATH' ) || exit;

use Estora\Core\Repositories\Inquiry_Repository;
use Estora\Core\Services\Lead_Service;
use Estora\Core\Support\Capabilities;
use Estora\Core\Support\Validation_Exception;
use WP_REST_Request;
use WP_REST_Response;

final class Inquiries_Controller extends Rest_Controller {

	/**
	 * Routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/inquiries',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => $this->can( Capabilities::VIEW_LEADS ),
				),
			)
		);
	}

	/**
	 * POST /inquiries.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function create_item( WP_REST_Request $request ) {
		$body  = $request->get_json_params();
		$body  = is_array( $body ) ? $body : $request->get_body_params();
		$guard = $this->public_write_guard( $request, 'inquiry', (string) ( $body['phone'] ?? $body['email'] ?? '' ), 5, 600 );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		if ( ! empty( $body['company'] ) ) {
			return $this->fail( 'The inquiry could not be processed.', array(), 422 );
		}

		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign' ) as $utm ) {
			if ( empty( $body[ $utm ] ) ) {
				$body[ $utm ] = (string) $request->get_param( $utm );
			}
		}

		try {
			$service = new Lead_Service();
			$result  = $service->capture( $body, 'website', true );

			$inquiry_id = ( new Inquiry_Repository() )->create(
				array(
					'property_id' => (int) ( $body['property_id'] ?? 0 ),
					'lead_id'     => $result['lead_id'],
					'name'        => (string) ( $body['name'] ?? $body['full_name'] ?? '' ),
					'email'       => (string) ( $body['email'] ?? '' ),
					'phone'       => (string) ( $body['phone'] ?? '' ),
					'message'     => sanitize_textarea_field( (string) ( $body['message'] ?? '' ) ),
					'source'      => 'website',
				)
			);

			return $this->ok(
				array(
					'inquiry_id' => $inquiry_id,
					'lead_ref'   => $result['public_ref'],
					'duplicate'  => $result['duplicate'],
				),
				array(),
				201,
				$result['duplicate']
					? 'We already have your details. A colleague will follow up shortly.'
					: 'Inquiry received. A colleague will contact you shortly.'
			);
		} catch ( Validation_Exception $e ) {
			return $this->from_exception( $e );
		}
	}

	/**
	 * GET /inquiries.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( WP_REST_Request $request ): WP_REST_Response {
		$result = ( new Inquiry_Repository() )->query(
			array(
				'status'      => (string) $request->get_param( 'status' ),
				'property_id' => (int) $request->get_param( 'property_id' ),
				'page'        => max( 1, (int) $request->get_param( 'page' ) ),
				'per_page'    => max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) ),
			)
		);

		return $this->ok(
			$result['items'],
			$this->pagination_meta( $result['page'], $result['per_page'], $result['total'], $result['pages'] )
		);
	}
}
