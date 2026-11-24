<?php
/**
 * Base REST controller: envelope, guards, rate limiting.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Api;

defined( 'ABSPATH' ) || exit;

use Estora\Core\Support\Validation_Exception;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

abstract class Rest_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected string $namespace = 'estora/v1';

	/**
	 * Register routes.
	 */
	abstract public function register_routes(): void;

	/**
	 * Success envelope.
	 *
	 * @param mixed               $data    Payload.
	 * @param array<string,mixed> $meta    Meta.
	 * @param int                 $status  HTTP status.
	 * @param string              $message Message.
	 * @return WP_REST_Response
	 */
	protected function ok( $data, array $meta = array(), int $status = 200, string $message = 'Operation completed successfully' ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
				'meta'    => $meta,
				'message' => $message,
			),
			$status
		);
	}

	/**
	 * Error envelope as a WP_Error that still carries our shape.
	 *
	 * @param string              $message Message.
	 * @param array<string,mixed> $errors  Field errors.
	 * @param int                 $status  HTTP status.
	 * @param array<string,mixed> $meta    Extra context.
	 * @return WP_Error
	 */
	protected function fail( string $message, array $errors = array(), int $status = 400, array $meta = array() ): WP_Error {
		$data = array(
			'success' => false,
			'data'    => null,
			'message' => $message,
		);
		if ( ! empty( $errors ) ) {
			$data['errors'] = $errors;
		}
		if ( ! empty( $meta ) ) {
			$data['meta'] = $meta;
		}
		return new WP_Error( 'estora_error', $message, array_merge( array( 'status' => $status ), $data ) );
	}

	/**
	 * Convert a service exception to an error response.
	 *
	 * @param Validation_Exception $e Exception.
	 * @return WP_Error
	 */
	protected function from_exception( Validation_Exception $e ): WP_Error {
		return $this->fail( $e->getMessage() ?: 'Request could not be processed.', $e->get_errors(), $e->get_status() );
	}

	/**
	 * Capability + nonce guard.
	 *
	 * @param string $capability Capability.
	 * @return callable
	 */
	protected function can( string $capability ): callable {
		return static function ( WP_REST_Request $request ) use ( $capability ) {
			if ( ! is_user_logged_in() ) {
				return new WP_Error( 'estora_unauthorized', 'Authentication required.', array( 'status' => 401 ) );
			}
			if ( ! current_user_can( $capability ) ) {
				return new WP_Error( 'estora_forbidden', 'You are not allowed to do that.', array( 'status' => 403 ) );
			}
			if ( ! estora_rest_nonce_valid( $request ) && ! estora_rest_uses_basic_auth( $request ) ) {
				return new WP_Error( 'estora_nonce', 'A valid REST nonce is required.', array( 'status' => 403 ) );
			}
			return true;
		};
	}

	/**
	 * Logged-in guard without a specific capability.
	 *
	 * @return callable
	 */
	protected function logged_in(): callable {
		return static function ( WP_REST_Request $request ) {
			if ( ! is_user_logged_in() ) {
				return new WP_Error( 'estora_unauthorized', 'Authentication required.', array( 'status' => 401 ) );
			}
			if ( ! estora_rest_nonce_valid( $request ) && ! estora_rest_uses_basic_auth( $request ) ) {
				return new WP_Error( 'estora_nonce', 'A valid REST nonce is required.', array( 'status' => 403 ) );
			}
			return true;
		};
	}

	/**
	 * Public write guard: nonce + rate limit.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $bucket  Bucket.
	 * @param string          $subject Optional subject (phone/email) to scope the budget.
	 * @param int             $max     Max requests per window.
	 * @param int             $window  Window seconds.
	 * @return true|WP_Error
	 */
	protected function public_write_guard( WP_REST_Request $request, string $bucket, string $subject = '', int $max = 5, int $window = 600 ) {
		if ( ! estora_rest_nonce_valid( $request ) ) {
			return new WP_Error( 'estora_nonce', 'A valid form nonce is required. Reload the page and try again.', array( 'status' => 403 ) );
		}

		$key  = 'estora_rl_' . md5( $bucket . '|' . estora_ip_hash() . '|' . strtolower( trim( $subject ) ) );
		$hits = (int) get_transient( $key );
		if ( $hits >= $max ) {
			return new WP_Error( 'estora_rate_limited', 'Too many requests. Please try again in a few minutes.', array( 'status' => 429 ) );
		}
		set_transient( $key, $hits + 1, $window );

		return true;
	}

	/**
	 * Common pagination args.
	 *
	 * @return array<string,mixed>
	 */
	protected function pagination_args(): array {
		return array(
			'page'     => array(
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => static fn( $value ) => is_numeric( $value ) && (int) $value >= 1,
			),
			'per_page' => array(
				'type'              => 'integer',
				'default'           => 20,
				'sanitize_callback' => 'absint',
				'validate_callback' => static fn( $value ) => is_numeric( $value ) && (int) $value >= 1 && (int) $value <= 100,
			),
		);
	}

	/**
	 * Pagination meta.
	 *
	 * @param int $page     Page.
	 * @param int $per_page Per page.
	 * @param int $total    Total.
	 * @param int $pages    Pages.
	 * @return array<string,mixed>
	 */
	protected function pagination_meta( int $page, int $per_page, int $total, int $pages ): array {
		return array(
			'pagination' => array(
				'page'        => $page,
				'per_page'    => $per_page,
				'total'       => $total,
				'total_pages' => $pages,
			),
		);
	}

	/**
	 * Visitor token from header or cookie.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	protected function visitor_token( WP_REST_Request $request ): string {
		$token = (string) $request->get_header( 'X-Estora-Visitor' );
		if ( ! $token && isset( $_COOKIE['estora_visitor'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_COOKIE['estora_visitor'] ) );
		}
		return preg_replace( '/[^a-f0-9]/i', '', $token ) ?: '';
	}
}
