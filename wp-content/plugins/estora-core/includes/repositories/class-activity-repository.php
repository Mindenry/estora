<?php
/**
 * Business activity log.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Repositories;

defined( 'ABSPATH' ) || exit;

final class Activity_Repository {

	/**
	 * Table.
	 */
	public function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'estora_activity_logs';
	}

	/**
	 * Record an event.
	 *
	 * @param string              $action      Dotted action, e.g. lead.assigned.
	 * @param string              $object_type Object type.
	 * @param int                 $object_id   Object ID.
	 * @param string              $description Human description.
	 * @param array<string,mixed> $meta        Structured context (no secrets).
	 */
	public function log( string $action, string $object_type, int $object_id, string $description, array $meta = array() ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$this->table(),
			array(
				'actor_id'    => get_current_user_id(),
				'action'      => substr( sanitize_key( str_replace( '.', '_', $action ) ), 0, 60 ),
				'object_type' => substr( sanitize_key( $object_type ), 0, 40 ),
				'object_id'   => $object_id,
				'description' => mb_substr( sanitize_text_field( $description ), 0, 255 ),
				'meta'        => $meta ? wp_json_encode( $meta ) : null,
				'ip_hash'     => estora_ip_hash(),
				'created_at'  => estora_now(),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Paged query.
	 *
	 * @param array<string,mixed> $args Filters.
	 * @return array{items:array<int,array<string,mixed>>,total:int,pages:int}
	 */
	public function query( array $args = array() ): array {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'actor_id'    => 0,
				'action'      => '',
				'object_type' => '',
				'object_id'   => 0,
				'page'        => 1,
				'per_page'    => 30,
			)
		);

		$where  = array( '1=1' );
		$params = array();
		if ( $args['actor_id'] ) {
			$where[]  = 'actor_id = %d';
			$params[] = (int) $args['actor_id'];
		}
		if ( $args['action'] ) {
			$where[]  = 'action LIKE %s';
			$params[] = '%' . $wpdb->esc_like( (string) $args['action'] ) . '%';
		}
		if ( $args['object_type'] ) {
			$where[]  = 'object_type = %s';
			$params[] = sanitize_key( (string) $args['object_type'] );
		}
		if ( $args['object_id'] ) {
			$where[]  = 'object_id = %d';
			$params[] = (int) $args['object_id'];
		}

		$per_page  = max( 1, min( 100, (int) $args['per_page'] ) );
		$page      = max( 1, (int) $args['page'] );
		$offset    = ( $page - 1 ) * $per_page;
		$where_sql = implode( ' AND ', $where );
		$table     = esc_sql( $this->table() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}", $params ) : "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
				array_merge( $params, array( $per_page, $offset ) )
			),
			ARRAY_A
		);

		$items = array();
		foreach ( $rows as $row ) {
			$user    = $row['actor_id'] ? get_userdata( (int) $row['actor_id'] ) : null;
			$items[] = array(
				'id'          => (int) $row['id'],
				'actor_id'    => (int) $row['actor_id'],
				'actor'       => $user ? $user->display_name : 'System',
				'action'      => $row['action'],
				'object_type' => $row['object_type'],
				'object_id'   => (int) $row['object_id'],
				'description' => $row['description'],
				'meta'        => $row['meta'] ? json_decode( (string) $row['meta'], true ) : array(),
				'created_at'  => get_date_from_gmt( $row['created_at'], 'c' ),
			);
		}

		return array(
			'items'    => $items,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'pages'    => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Recent activity for an object.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id   Object ID.
	 * @param int    $limit       Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public function for_object( string $object_type, int $object_id, int $limit = 20 ): array {
		$result = $this->query(
			array(
				'object_type' => $object_type,
				'object_id'   => $object_id,
				'per_page'    => $limit,
			)
		);
		return $result['items'];
	}

	/**
	 * Recent activity for the dashboard.
	 *
	 * @param int $limit Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public function recent( int $limit = 8 ): array {
		$result = $this->query( array( 'per_page' => $limit ) );
		return $result['items'];
	}
}
