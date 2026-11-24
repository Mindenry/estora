<?php
/**
 * Property REST endpoints.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Api;

defined( 'ABSPATH' ) || exit;

use Estora\Core\Repositories\Property_Repository;
use Estora\Core\Services\Property_Service;
use Estora\Core\Support\Capabilities;
use Estora\Core\Support\Validation_Exception;
use WP_REST_Request;
use WP_REST_Response;

final class Properties_Controller extends Rest_Controller {

	/**
	 * Routes.
	 */
	public function register_routes(): void {
		$collection = array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_items' ),
			'permission_callback' => '__return_true',
			'args'                => $this->collection_args(),
		);

		register_rest_route( $this->namespace, '/properties', array( $collection ) );
		register_rest_route( $this->namespace, '/properties/search', array( $collection ) );

		register_rest_route(
			$this->namespace,
			'/properties/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $this->can( Capabilities::EDIT_PROPERTIES ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => $this->can( Capabilities::DELETE_PROPERTIES ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/properties',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => $this->can( Capabilities::EDIT_PROPERTIES ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/properties/(?P<id>\d+)/duplicate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'duplicate_item' ),
				'permission_callback' => $this->can( Capabilities::EDIT_PROPERTIES ),
			)
		);
	}

	/**
	 * Collection arguments.
	 *
	 * @return array<string,mixed>
	 */
	private function collection_args(): array {
		$args = $this->pagination_args();

		$text = static function ( $value ) {
			return is_string( $value ) ? sanitize_text_field( $value ) : '';
		};

		$args['purpose']      = array( 'sanitize_callback' => $text );
		$args['type']         = array( 'sanitize_callback' => $text );
		$args['location']     = array( 'sanitize_callback' => $text );
		$args['province']     = array( 'sanitize_callback' => $text );
		$args['district']     = array( 'sanitize_callback' => $text );
		$args['q']            = array( 'sanitize_callback' => $text );
		$args['min_price']    = array( 'sanitize_callback' => $text );
		$args['max_price']    = array( 'sanitize_callback' => $text );
		$args['bedrooms']     = array( 'sanitize_callback' => 'absint' );
		$args['bathrooms']    = array( 'sanitize_callback' => 'absint' );
		$args['min_area']     = array( 'sanitize_callback' => $text );
		$args['max_area']     = array( 'sanitize_callback' => $text );
		$args['availability'] = array( 'sanitize_callback' => $text );
		$args['featured']     = array( 'sanitize_callback' => $text );
		$args['sort']         = array(
			'default'           => 'newest',
			'sanitize_callback' => $text,
			'validate_callback' => static fn( $value ) => in_array( $value, array( 'newest', 'oldest', 'price_asc', 'price_desc', 'area_asc', 'area_desc' ), true ),
		);
		$args['status']       = array( 'sanitize_callback' => $text );

		return $args;
	}

	/**
	 * List properties.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_items( WP_REST_Request $request ) {
		$repo = new Property_Repository();

		$status           = 'publish';
		$requested_status = (string) $request->get_param( 'status' );
		if ( $requested_status && current_user_can( Capabilities::VIEW_PROPERTIES ) ) {
			$status = 'any' === $requested_status
				? array( 'publish', 'draft', 'pending', 'archived' )
				: array( $requested_status === 'published' ? 'publish' : $requested_status );
		}

		$args = array(
			'purpose'      => (string) $request->get_param( 'purpose' ),
			'type'         => (string) $request->get_param( 'type' ),
			'location'     => (string) $request->get_param( 'location' ),
			'province'     => (string) $request->get_param( 'province' ),
			'district'     => (string) $request->get_param( 'district' ),
			'q'            => (string) $request->get_param( 'q' ),
			'min_price'    => (string) $request->get_param( 'min_price' ),
			'max_price'    => (string) $request->get_param( 'max_price' ),
			'bedrooms'     => (string) $request->get_param( 'bedrooms' ),
			'bathrooms'    => (string) $request->get_param( 'bathrooms' ),
			'min_area'     => (string) $request->get_param( 'min_area' ),
			'max_area'     => (string) $request->get_param( 'max_area' ),
			'availability' => (string) $request->get_param( 'availability' ),
			'featured'     => (string) $request->get_param( 'featured' ),
			'sort'         => (string) $request->get_param( 'sort' ),
			'page'         => max( 1, (int) $request->get_param( 'page' ) ),
			'per_page'     => max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) ),
			'status'       => $status,
		);

		$result   = $repo->public_query( $args );
		$response = $this->ok(
			$result['items'],
			array_merge(
				$this->pagination_meta( $result['page'], $result['per_page'], $result['total'], $result['pages'] ),
				array( 'filters' => array_filter( $args, static fn( $value ) => '' !== $value && 0 !== $value ) )
			)
		);
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) $result['pages'] );

		return $response;
	}

	/**
	 * Single property.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_item( WP_REST_Request $request ) {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );

		if ( ! $post || 'property' !== $post->post_type ) {
			return $this->fail( 'Property not found.', array(), 404 );
		}
		if ( 'publish' !== $post->post_status && ! current_user_can( Capabilities::EDIT_PROPERTIES ) ) {
			return $this->fail( 'Property not found.', array(), 404 );
		}

		$data = ( new Property_Repository() )->to_array( $post_id );
		if ( ! $data ) {
			return $this->fail( 'Property not found.', array(), 404 );
		}

		return $this->ok( $data );
	}

	/**
	 * Create.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function create_item( WP_REST_Request $request ) {
		try {
			$payload = $this->payload( $request );
			$shape   = ( new Property_Service() )->save( $payload );
			return $this->ok( $shape, array(), 201, 'Property created.' );
		} catch ( Validation_Exception $e ) {
			return $this->from_exception( $e );
		}
	}

	/**
	 * Update.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function update_item( WP_REST_Request $request ) {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );
		if ( ! $post || 'property' !== $post->post_type ) {
			return $this->fail( 'Property not found.', array(), 404 );
		}

		try {
			$existing = ( new Property_Repository() )->to_array( $post_id );
			$payload  = $this->payload( $request, $existing ?: array() );
			$shape    = ( new Property_Service() )->save( $payload, $post_id );
			return $this->ok( $shape, array(), 200, 'Property updated.' );
		} catch ( Validation_Exception $e ) {
			return $this->from_exception( $e );
		}
	}

	/**
	 * Delete (trash by default, permanent with force=true).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function delete_item( WP_REST_Request $request ) {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );
		if ( ! $post || 'property' !== $post->post_type ) {
			return $this->fail( 'Property not found.', array(), 404 );
		}

		$force = (bool) $request->get_param( 'force' );
		try {
			if ( $force ) {
				( new Property_Service() )->delete( $post_id );
			} else {
				wp_trash_post( $post_id );
			}
			return $this->ok( null, array(), 204, 'Property deleted.' );
		} catch ( Validation_Exception $e ) {
			return $this->from_exception( $e );
		}
	}

	/**
	 * Duplicate.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function duplicate_item( WP_REST_Request $request ) {
		try {
			$shape = ( new Property_Service() )->duplicate( (int) $request['id'] );
			return $this->ok( $shape, array(), 201, 'Property duplicated as a draft.' );
		} catch ( Validation_Exception $e ) {
			return $this->from_exception( $e );
		}
	}

	/**
	 * Normalise the request into a repository payload.
	 *
	 * @param WP_REST_Request     $request  Request.
	 * @param array<string,mixed> $existing Existing shape.
	 * @return array<string,mixed>
	 */
	private function payload( WP_REST_Request $request, array $existing = array() ): array {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_body_params();
		}
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$fallback = static function ( string $key, $default = '' ) use ( $params, $existing ) {
			if ( array_key_exists( $key, $params ) ) {
				return $params[ $key ];
			}
			return $existing[ $key ] ?? $default;
		};

		$coerce_bool = static function ( $value ): bool {
			return in_array( $value, array( true, 1, '1', 'true', 'on' ), true );
		};

		$price = $fallback( 'price', 0 );
		if ( '' === $price && isset( $existing['price'] ) ) {
			$price = $existing['price'];
		}

		$payload = array(
			'title'        => (string) $fallback( 'title' ),
			'description'  => (string) $fallback( 'description' ),
			'excerpt'      => (string) $fallback( 'excerpt' ),
			'purpose'      => (string) $fallback( 'purpose' ),
			'type'         => (string) $fallback( 'type' ),
			'location'     => (string) ( $params['location'] ?? '' ),
			'price'        => is_numeric( $price ) ? (float) $price : $price,
			'rent_period'  => (string) $fallback( 'rent_period', 'month' ),
			'address'      => (string) $fallback( 'address' ),
			'province'     => (string) ( $params['province'] ?? ( $existing['location']['province'] ?? '' ) ),
			'district'     => (string) ( $params['district'] ?? ( $existing['location']['district'] ?? '' ) ),
			'subdistrict'  => (string) ( $params['subdistrict'] ?? ( $existing['location']['subdistrict'] ?? '' ) ),
			'postal_code'  => (string) ( $params['postal_code'] ?? ( $existing['location']['postal_code'] ?? '' ) ),
			'latitude'     => $params['latitude'] ?? 0,
			'longitude'    => $params['longitude'] ?? 0,
			'land_area'    => $params['land_area'] ?? 0,
			'usable_area'  => $params['usable_area'] ?? ( $existing['usable_area'] ?? 0 ),
			'bedrooms'     => $params['bedrooms'] ?? ( $existing['bedrooms'] ?? 0 ),
			'bathrooms'    => $params['bathrooms'] ?? ( $existing['bathrooms'] ?? 0 ),
			'parking'      => $params['parking'] ?? ( $existing['parking'] ?? 0 ),
			'floors'       => $params['floors'] ?? ( $existing['floors'] ?? 0 ),
			'year_built'   => $params['year_built'] ?? ( $existing['year_built'] ?? 0 ),
			'amenities'    => array_key_exists( 'amenities', $params ) ? $params['amenities'] : ( $existing['amenities'] ?? array() ),
			'gallery'      => array_key_exists( 'gallery', $params ) ? $params['gallery'] : array_map( static fn( $image ) => (int) $image['id'], $existing['gallery'] ?? array() ),
			'agent_id'     => absint( $params['agent_id'] ?? ( $existing['agent']['id'] ?? 0 ) ),
			'availability' => (string) ( $params['availability'] ?? ( $existing['availability'] ?? '' ) ),
			'status'       => (string) ( $params['status'] ?? ( $existing['status'] ?? 'draft' ) ),
			'featured'     => $coerce_bool( $params['featured'] ?? ( $existing['featured'] ?? false ) ),
			'seo_title'    => (string) ( $params['seo_title'] ?? ( $existing['seo']['title'] ?? '' ) ),
			'seo_description' => (string) ( $params['seo_description'] ?? ( $existing['seo']['description'] ?? '' ) ),
			'video_url'    => (string) ( $params['video_url'] ?? ( $existing['video_url'] ?? '' ) ),
			'placeholder'  => (string) ( $params['placeholder'] ?? '' ),
		);

		if ( isset( $payload['status'] ) && 'publish' === $payload['status'] ) {
			$payload['status'] = 'published';
		}

		return $payload;
	}
}
