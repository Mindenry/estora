<?php
/**
 * Public configuration, settings and audit endpoints.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Api;

defined( 'ABSPATH' ) || exit;

use Estora\Core\Database\Schema;
use Estora\Core\Repositories\Activity_Repository;
use Estora\Core\Services\Settings;
use Estora\Core\Support\Capabilities;
use Estora\Core\Support\Validation_Exception;
use WP_REST_Request;
use WP_REST_Response;

final class System_Controller extends Rest_Controller {

	/**
	 * Routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/public/config',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'public_config' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'health' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => $this->can( Capabilities::MANAGE_SETTINGS ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => $this->can( Capabilities::MANAGE_SETTINGS ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/audit',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'audit' ),
				'permission_callback' => $this->can( Capabilities::VIEW_AUDIT ),
			)
		);
	}

	/**
	 * Non-sensitive public configuration, including a REST nonce for forms.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function public_config( WP_REST_Request $request ): WP_REST_Response {
		// Resolve a cookie session before minting the nonce: REST boots with no
		// current user, and a nonce created for user 0 would fail once the
		// cookie is validated on the next request.
		if ( ! is_user_logged_in() ) {
			$cookie_user = wp_validate_auth_cookie( '', 'logged_in' );
			if ( $cookie_user ) {
				wp_set_current_user( (int) $cookie_user );
			}
		}

		$settings = Settings::all();

		return $this->ok(
			array(
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'site'         => array(
					'name'    => get_bloginfo( 'name' ),
					'url'     => home_url( '/' ),
					'locale'  => get_locale(),
				),
				'company'      => array(
					'name'    => $settings['company_name'],
					'tagline' => $settings['company_tagline'],
					'email'   => $settings['company_email'],
					'phone'   => $settings['company_phone'],
					'address' => $settings['company_address'],
					'hours'   => $settings['company_hours_text'],
				),
				'currency'     => $settings['currency'],
				'timezone'     => wp_timezone_string(),
				'business'     => array(
					'open'        => $settings['open_time'],
					'close'       => $settings['close_time'],
					'closed_days' => array_map( 'intval', (array) $settings['closed_days'] ),
					'slot_minutes' => (int) $settings['slot_minutes'],
					'lunch_start' => $settings['lunch_start'],
					'lunch_end'   => $settings['lunch_end'],
				),
				'compare_limit' => 3,
				'version'      => ESTORA_CORE_VERSION,
			)
		);
	}

	/**
	 * Lightweight health probe.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function health( WP_REST_Request $request ): WP_REST_Response {
		$tables = Schema::status();
		$ok     = ! in_array( false, $tables, true );

		return $this->ok(
			array(
				'plugin_version' => ESTORA_CORE_VERSION,
				'db_version'     => (string) get_option( 'estora_db_version', '' ),
				'tables'         => $tables,
				'environment'    => wp_get_environment_type(),
			),
			array(),
			$ok ? 200 : 500,
			$ok ? 'OK' : 'Schema incomplete'
		);
	}

	/**
	 * GET /settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		$settings = Settings::all();
		$agents   = array();
		foreach ( get_users( array( 'capability' => Capabilities::EDIT_LEADS, 'number' => 100 ) ) as $user ) {
			if ( $user instanceof \WP_User ) {
				$agents[] = array(
					'id'   => $user->ID,
					'name' => $user->display_name,
				);
			}
		}

		return $this->ok( $settings, array( 'agents' => $agents, 'timezone' => wp_timezone_string() ) );
	}

	/**
	 * POST /settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function update_settings( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		$body = is_array( $body ) ? $body : $request->get_body_params();
		if ( ! is_array( $body ) || empty( $body ) ) {
			return $this->fail( 'No settings supplied.', array(), 422 );
		}

		$before = Settings::all();
		$after  = Settings::update( $body );

		( new Activity_Repository() )->log( 'settings.updated', 'settings', 0, 'Settings updated.', array( 'changed' => array_keys( array_diff_assoc( array_map( 'strval', $after ), array_map( 'strval', $before ) ) ) ) );

		return $this->ok( $after, array(), 200, 'Settings saved.' );
	}

	/**
	 * GET /audit.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function audit( WP_REST_Request $request ): WP_REST_Response {
		$result = ( new Activity_Repository() )->query(
			array(
				'actor_id'    => (int) $request->get_param( 'actor_id' ),
				'action'      => (string) $request->get_param( 'action' ),
				'object_type' => (string) $request->get_param( 'object_type' ),
				'object_id'   => (int) $request->get_param( 'object_id' ),
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
