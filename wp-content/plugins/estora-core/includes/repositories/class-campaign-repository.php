<?php
/**
 * Marketing campaigns and lead attribution.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Repositories;

use Estora\Core\Support\Validator;

defined( 'ABSPATH' ) || exit;

final class Campaign_Repository {

	/**
	 * Table.
	 */
	public function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'estora_campaigns';
	}

	/**
	 * Find one.
	 *
	 * @param int $id ID.
	 * @return array<string,mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM `' . esc_sql( $this->table() ) . '` WHERE id = %d', $id ),
			ARRAY_A
		);
		return $row ? $this->to_array( $row ) : null;
	}

	/**
	 * List.
	 *
	 * @param array<string,mixed> $args Filters.
	 * @return array{items:array<int,array<string,mixed>>,total:int,pages:int}
	 */
	public function query( array $args = array() ): array {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'status'   => '',
				'page'     => 1,
				'per_page' => 20,
				'order'    => 'DESC',
			)
		);

		$per_page = max( 1, min( 100, (int) $args['per_page'] ) );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();
		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( (string) $args['status'] );
		}
		$where_sql = implode( ' AND ', $where );
		$table     = esc_sql( $this->table() );
		$order     = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}", $params ) : "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY created_at {$order} LIMIT %d OFFSET %d",
				array_merge( $params, array( $per_page, $offset ) )
			),
			ARRAY_A
		);

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = $this->to_array( $row );
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
	 * Create.
	 *
	 * @param array<string,mixed> $data Data.
	 * @return int
	 */
	public function create( array $data ): int {
		global $wpdb;
		$now = estora_now();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$this->table(),
			array(
				'name'            => (string) $data['name'],
				'slug'            => sanitize_title( (string) ( $data['slug'] ?? $data['name'] ) ),
				'channel'         => sanitize_key( (string) ( $data['channel'] ?? 'web' ) ),
				'utm_source'      => sanitize_text_field( (string) ( $data['utm_source'] ?? '' ) ),
				'utm_medium'      => sanitize_text_field( (string) ( $data['utm_medium'] ?? '' ) ),
				'utm_campaign'    => sanitize_text_field( (string) $data['utm_campaign'] ),
				'landing_page_id' => ! empty( $data['landing_page_id'] ) ? (int) $data['landing_page_id'] : null,
				'status'          => sanitize_key( (string) ( $data['status'] ?? 'draft' ) ),
				'budget'          => isset( $data['budget'] ) && '' !== $data['budget'] ? (float) $data['budget'] : null,
				'starts_at'       => ! empty( $data['starts_at'] ) ? (string) $data['starts_at'] : null,
				'ends_at'         => ! empty( $data['ends_at'] ) ? (string) $data['ends_at'] : null,
				'notes'           => sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) ),
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update.
	 *
	 * @param int                 $id   ID.
	 * @param array<string,mixed> $data Data.
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$row     = array( 'updated_at' => estora_now() );
		$formats = array( '%s' );

		$map = array(
			'name'            => '%s',
			'slug'            => '%s',
			'channel'         => '%s',
			'utm_source'      => '%s',
			'utm_medium'      => '%s',
			'utm_campaign'    => '%s',
			'landing_page_id' => '%d',
			'status'          => '%s',
			'budget'          => '%f',
			'starts_at'       => '%s',
			'ends_at'         => '%s',
			'notes'           => '%s',
		);
		foreach ( $map as $column => $format ) {
			if ( array_key_exists( $column, $data ) ) {
				$row[ $column ] = $data[ $column ];
				$formats[]      = $format;
			}
		}
		if ( isset( $row['slug'] ) ) {
			$row['slug'] = sanitize_title( (string) $row['slug'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->update( $this->table(), $row, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	/**
	 * Delete.
	 *
	 * @param int $id ID.
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Attribution counts over the last N days.
	 *
	 * @param int $days Days.
	 * @return array<string,int> utm_campaign => lead count.
	 */
	public function attribution_counts( int $days = 30 ): array {
		global $wpdb;
		$leads = esc_sql( $wpdb->prefix . 'estora_leads' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT utm_campaign, COUNT(*) AS total FROM `{$leads}` WHERE utm_campaign != '' AND created_at >= %s GROUP BY utm_campaign",
				estora_now( '-' . max( 1, $days ) . ' days' )
			),
			ARRAY_A
		);
		$out  = array();
		foreach ( $rows as $row ) {
			$out[ $row['utm_campaign'] ] = (int) $row['total'];
		}
		return $out;
	}

	/**
	 * Row to API shape.
	 *
	 * @param array<string,mixed> $row Row.
	 * @return array<string,mixed>
	 */
	private function to_array( array $row ): array {
		$attribution = $this->attribution_counts( 30 );
		return array(
			'id'               => (int) $row['id'],
			'name'             => (string) $row['name'],
			'slug'             => (string) $row['slug'],
			'channel'          => (string) $row['channel'],
			'utm_source'       => (string) ( $row['utm_source'] ?? '' ),
			'utm_medium'       => (string) ( $row['utm_medium'] ?? '' ),
			'utm_campaign'     => (string) $row['utm_campaign'],
			'landing_page_id'  => (int) ( $row['landing_page_id'] ?? 0 ),
			'status'           => (string) $row['status'],
			'budget'           => (float) ( $row['budget'] ?? 0 ),
			'starts_at'        => $row['starts_at'] ?: null,
			'ends_at'          => $row['ends_at'] ?: null,
			'notes'            => (string) ( $row['notes'] ?? '' ),
			'attribution_count' => $attribution[ $row['utm_campaign'] ] ?? 0,
			'created_at'       => get_date_from_gmt( $row['created_at'], 'c' ),
			'updated_at'       => get_date_from_gmt( $row['updated_at'], 'c' ),
		);
	}
}
