<?php
/**
 * Agent directory endpoints.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Api;

defined( 'ABSPATH' ) || exit;

use Estora\Core\Repositories\Lead_Repository;
use Estora\Core\Support\Capabilities;
use WP_REST_Request;
use WP_REST_Response;

final class Agents_Controller extends Rest_Controller {

	/**
	 * Routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/agents',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => $this->can( Capabilities::MANAGE_APPOINTMENTS ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/agents/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => $this->can( Capabilities::MANAGE_APPOINTMENTS ),
			)
		);
	}

	/**
	 * Agent list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( WP_REST_Request $request ): WP_REST_Response {
		$users = get_users(
			array(
				'capability' => Capabilities::EDIT_LEADS,
				'number'     => 100,
				'orderby'    => 'display_name',
			)
		);

		$lead_counts = current_user_can( Capabilities::VIEW_ALL_LEADS ) ? ( new Lead_Repository() )->agent_counts() : array();
		$map         = array();
		foreach ( $lead_counts as $row ) {
			$map[ $row['agent_id'] ] = $row;
		}

		$items = array();
		foreach ( $users as $user ) {
			if ( ! $user instanceof \WP_User ) {
				continue;
			}
			$profile               = estora_agent_profile( $user->ID );
			$profile['open_leads'] = $map[ $user->ID ]['total'] ?? null;
			$profile['won_leads']  = $map[ $user->ID ]['won'] ?? null;
			$items[]               = $profile;
		}

		return $this->ok( $items );
	}

	/**
	 * Single agent.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_item( WP_REST_Request $request ) {
		$user = get_userdata( (int) $request['id'] );
		if ( ! $user || ! user_can( $user->ID, Capabilities::EDIT_LEADS ) ) {
			return $this->fail( 'Agent not found.', array(), 404 );
		}
		return $this->ok( estora_agent_profile( $user->ID ) );
	}
}
