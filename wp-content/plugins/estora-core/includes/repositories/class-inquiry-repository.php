<?php
/**
 * Inquiry log rows.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Repositories;

defined( 'ABSPATH' ) || exit;

final class Inquiry_Repository {

	/**
	 * Table.
	 */
	public function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'estora_inquiries';
	}

	/**
	 * Insert.
	 *
	 * @param array<string,mixed> $data Data.
	 * @return int
	 */
	public function create( array $data ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$this->table(),
			array(
				'property_id' => (int) $data['property_id'],
				'lead_id'     => ! empty( $data['lead_id'] ) ? (int) $data['lead_id'] : null,
				'name'        => (string) $data['name'],
				'email'       => $data['email'] ?: null,
				'phone'       => $data['phone'] ?: null,
				'message'     => $data['message'] ?? null,
				'source'      => (string) ( $data['source'] ?? 'website' ),
				'status'      => 'new',
				'created_at'  => estora_now(),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Paged list.
	 *
	 * @param array<string,mixed> $args Filters.
	 * @return array{items:array<int,array<string,mixed>>,total:int,pages:int,page:int,per_page:int}
	 */
	public function query( array $args = array() ): array {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'status'      => '',
				'property_id' => 0,
				'page'        => 1,
				'per_page'    => 20,
			)
		);

		$where  = array( '1=1' );
		$params = array();
		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( (string) $args['status'] );
		}
		if ( $args['property_id'] ) {
			$where[]  = 'property_id = %d';
			$params[] = (int) $args['property_id'];
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
				"SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				array_merge( $params, array( $per_page, $offset ) )
			),
			ARRAY_A
		);

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = array(
				'id'             => (int) $row['id'],
				'property_id'    => (int) $row['property_id'],
				'property_title' => get_the_title( (int) $row['property_id'] ) ?: 'Deleted property',
				'lead_id'        => (int) ( $row['lead_id'] ?? 0 ),
				'name'           => (string) $row['name'],
				'email'          => (string) ( $row['email'] ?? '' ),
				'phone'          => (string) ( $row['phone'] ?? '' ),
				'message'        => (string) ( $row['message'] ?? '' ),
				'source'         => (string) $row['source'],
				'status'         => (string) $row['status'],
				'created_at'     => get_date_from_gmt( $row['created_at'], 'c' ),
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
}
