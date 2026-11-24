<?php
/**
 * Lead persistence.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Repositories;

use Estora\Core\Domain\Lead_Status;

defined( 'ABSPATH' ) || exit;

final class Lead_Repository {

	/**
	 * Table name.
	 */
	public function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'estora_leads';
	}

	/**
	 * Find by ID.
	 *
	 * @param int $id Lead ID.
	 * @return array<string,mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . esc_sql( $this->table() ) . '` WHERE id = %d', $id ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Find an existing lead by dedupe key after a cutoff.
	 *
	 * @param string $key   Dedupe key.
	 * @param string $since UTC datetime.
	 * @return array<string,mixed>|null
	 */
	public function find_by_dedupe( string $key, string $since ): ?array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM `' . esc_sql( $this->table() ) . '` WHERE dedupe_key = %s AND created_at >= %s ORDER BY id DESC LIMIT 1',
				$key,
				$since
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Paged list query.
	 *
	 * @param array<string,mixed> $args Filters.
	 * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int,counts:array<string,int>}
	 */
	public function query( array $args = array() ): array {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'status'      => '',
				'source'      => '',
				'agent_id'    => 0,
				'unaassigned' => false,
				'priority'    => '',
				'q'           => '',
				'overdue'     => false,
				'property_id' => 0,
				'date_from'   => '',
				'date_to'     => '',
				'page'        => 1,
				'per_page'    => 20,
				'orderby'     => 'created_at',
				'order'       => 'DESC',
			)
		);

		$per_page = max( 1, min( 100, (int) $args['per_page'] ) );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();

		$valid_statuses = Lead_Status::all();
		if ( $args['status'] && in_array( $args['status'], $valid_statuses, true ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( $args['source'] ) {
			$where[]  = 'source = %s';
			$params[] = sanitize_key( (string) $args['source'] );
		}
		if ( ! empty( $args['agent_id'] ) ) {
			$where[]  = 'assigned_agent_id = %d';
			$params[] = (int) $args['agent_id'];
		}
		if ( $args['unaassigned'] ) {
			$where[] = '(assigned_agent_id IS NULL OR assigned_agent_id = 0)';
		}
		if ( $args['priority'] ) {
			$where[]  = 'priority = %s';
			$params[] = sanitize_key( (string) $args['priority'] );
		}
		if ( ! empty( $args['property_id'] ) ) {
			$where[]  = 'property_id = %d';
			$params[] = (int) $args['property_id'];
		}
		if ( $args['q'] ) {
			$like     = '%' . $wpdb->esc_like( (string) $args['q'] ) . '%';
			$where[]  = '(full_name LIKE %s OR email LIKE %s OR phone LIKE %s OR public_ref LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( $args['overdue'] ) {
			$placeholders = implode( ',', array_fill( 0, count( Lead_Status::active() ), '%s' ) );
			$where[]      = "next_follow_up_at IS NOT NULL AND next_follow_up_at < %s AND status IN ({$placeholders})";
			$params[]     = estora_now();
			foreach ( Lead_Status::active() as $status ) {
				$params[] = $status;
			}
		}
		if ( $args['date_from'] ) {
			$where[]  = 'created_at >= %s';
			$params[] = $args['date_from'];
		}
		if ( $args['date_to'] ) {
			$where[]  = 'created_at <= %s';
			$params[] = $args['date_to'];
		}

		$where_sql = implode( ' AND ', $where );

		$order_map = array(
			'created_at'   => 'created_at',
			'updated_at'   => 'updated_at',
			'next_follow_up_at' => 'next_follow_up_at',
			'full_name'    => 'full_name',
			'status'       => 'status',
		);
		$orderby   = $order_map[ $args['orderby'] ] ?? 'created_at';
		$order     = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

		$table = esc_sql( $this->table() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows_sql = "SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d";
		$rows     = $wpdb->get_results( $wpdb->prepare( $rows_sql, array_merge( $params, array( $per_page, $offset ) ) ), ARRAY_A );

		$counts = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$group_sql = "SELECT status, COUNT(*) AS total FROM `{$table}` WHERE {$where_sql} GROUP BY status";
		foreach ( $wpdb->get_results( $params ? $wpdb->prepare( $group_sql, $params ) : $group_sql, ARRAY_A ) as $row ) {
			$counts[ $row['status'] ] = (int) $row['total'];
		}

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
			'counts'   => $counts,
		);
	}

	/**
	 * Insert a lead. Caller guarantees sanitised values.
	 *
	 * @param array<string,mixed> $data Data.
	 * @return int New lead ID.
	 */
	public function create( array $data ): int {
		global $wpdb;

		$now = estora_now();
		$row = array(
			'public_ref'         => '',
			'full_name'          => (string) $data['full_name'],
			'email'              => $data['email'] ?? null,
			'phone'              => $data['phone'] ?? null,
			'preferred_contact'  => $data['preferred_contact'] ?? 'phone',
			'property_id'        => ! empty( $data['property_id'] ) ? (int) $data['property_id'] : null,
			'budget_min'         => isset( $data['budget_min'] ) && '' !== $data['budget_min'] ? (float) $data['budget_min'] : null,
			'budget_max'         => isset( $data['budget_max'] ) && '' !== $data['budget_max'] ? (float) $data['budget_max'] : null,
			'preferred_location' => $data['preferred_location'] ?? null,
			'message'            => $data['message'] ?? null,
			'source'             => $data['source'] ?? 'website',
			'utm_source'         => $data['utm_source'] ?? null,
			'utm_medium'         => $data['utm_medium'] ?? null,
			'utm_campaign'       => $data['utm_campaign'] ?? null,
			'status'             => $data['status'] ?? Lead_Status::NEW,
			'priority'           => $data['priority'] ?? 'normal',
			'assigned_agent_id'  => ! empty( $data['assigned_agent_id'] ) ? (int) $data['assigned_agent_id'] : null,
			'dedupe_key'         => $data['dedupe_key'] ?? null,
			'last_contact_at'    => $data['last_contact_at'] ?? null,
			'next_follow_up_at'  => $data['next_follow_up_at'] ?? null,
			'won_value'          => isset( $data['won_value'] ) && '' !== $data['won_value'] ? (float) $data['won_value'] : null,
			'created_at'         => $now,
			'updated_at'         => $now,
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%f', '%s', '%s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert( $this->table(), $row, $formats );
		if ( ! $ok ) {
			throw new \RuntimeException( 'Could not create the lead.' );
		}

		$id  = (int) $wpdb->insert_id;
		$ref = 'LD-' . str_pad( (string) $id, 6, '0', STR_PAD_LEFT );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $this->table(), array( 'public_ref' => $ref ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );

		return $id;
	}

	/**
	 * Update columns.
	 *
	 * @param int                 $id   Lead ID.
	 * @param array<string,mixed> $data Column values.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		$allowed = array(
			'full_name'          => '%s',
			'email'              => '%s',
			'phone'              => '%s',
			'preferred_contact'  => '%s',
			'property_id'        => '%d',
			'budget_min'         => '%f',
			'budget_max'         => '%f',
			'preferred_location' => '%s',
			'message'            => '%s',
			'source'             => '%s',
			'status'             => '%s',
			'priority'           => '%s',
			'assigned_agent_id'  => '%d',
			'last_contact_at'    => '%s',
			'next_follow_up_at'  => '%s',
			'won_value'          => '%f',
		);

		$row     = array();
		$formats = array();
		foreach ( $allowed as $column => $format ) {
			if ( array_key_exists( $column, $data ) ) {
				$row[ $column ] = $data[ $column ];
				$formats[]      = $format;
			}
		}
		if ( empty( $row ) ) {
			return false;
		}
		$row['updated_at'] = estora_now();
		$formats[]         = '%s';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->update( $this->table(), $row, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	/**
	 * Delete a lead and its dependent rows.
	 *
	 * @param int $id Lead ID.
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Add a note.
	 *
	 * @param int    $lead_id     Lead.
	 * @param int    $author_id   Author.
	 * @param string $body        Body.
	 * @param string $type        Note type.
	 * @param bool   $is_internal Internal flag.
	 * @return int Note ID.
	 */
	public function add_note( int $lead_id, int $author_id, string $body, string $type = 'note', bool $is_internal = false ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$wpdb->prefix . 'estora_lead_notes',
			array(
				'lead_id'     => $lead_id,
				'author_id'   => $author_id,
				'note_type'   => $type,
				'body'        => $body,
				'is_internal' => $is_internal ? 1 : 0,
				'created_at'  => estora_now(),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Notes for a lead.
	 *
	 * @param int $lead_id Lead.
	 * @return array<int,array<string,mixed>>
	 */
	public function notes( int $lead_id ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM `' . esc_sql( $wpdb->prefix . 'estora_lead_notes' ) . '` WHERE lead_id = %d ORDER BY created_at ASC, id ASC',
				$lead_id
			),
			ARRAY_A
		);

		$out = array();
		foreach ( $rows as $row ) {
			$user  = get_userdata( (int) $row['author_id'] );
			$out[] = array(
				'id'          => (int) $row['id'],
				'note_type'   => $row['note_type'],
				'body'        => $row['body'],
				'is_internal' => (bool) (int) $row['is_internal'],
				'author'      => $user ? $user->display_name : 'System',
				'author_id'   => (int) $row['author_id'],
				'created_at'  => get_date_from_gmt( $row['created_at'], 'c' ),
			);
		}
		return $out;
	}

	/**
	 * Record an assignment.
	 *
	 * @param int    $lead_id       Lead.
	 * @param int    $from_agent_id Previous agent.
	 * @param int    $to_agent_id   New agent.
	 * @param int    $assigned_by   Actor.
	 * @param string $reason        Reason.
	 */
	public function add_assignment( int $lead_id, int $from_agent_id, int $to_agent_id, int $assigned_by, string $reason = '' ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$wpdb->prefix . 'estora_lead_assignments',
			array(
				'lead_id'       => $lead_id,
				'from_agent_id' => $from_agent_id ?: null,
				'to_agent_id'   => $to_agent_id,
				'assigned_by'   => $assigned_by,
				'reason'        => $reason,
				'created_at'    => estora_now(),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Assignment history.
	 *
	 * @param int $lead_id Lead.
	 * @return array<int,array<string,mixed>>
	 */
	public function assignments( int $lead_id ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM `' . esc_sql( $wpdb->prefix . 'estora_lead_assignments' ) . '` WHERE lead_id = %d ORDER BY created_at ASC',
				$lead_id
			),
			ARRAY_A
		);

		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'id'            => (int) $row['id'],
				'from_agent_id' => (int) $row['from_agent_id'],
				'from_agent'    => estora_agent_name( (int) $row['from_agent_id'] ),
				'to_agent_id'   => (int) $row['to_agent_id'],
				'to_agent'      => estora_agent_name( (int) $row['to_agent_id'] ),
				'assigned_by'   => estora_agent_name( (int) $row['assigned_by'] ),
				'reason'        => (string) $row['reason'],
				'created_at'    => get_date_from_gmt( $row['created_at'], 'c' ),
			);
		}
		return $out;
	}

	/**
	 * Counts per status, optionally scoped to an agent.
	 *
	 * @param int $agent_id Agent scope (0 = all).
	 * @return array<string,int>
	 */
	public function status_counts( int $agent_id = 0 ): array {
		global $wpdb;
		$table = esc_sql( $this->table() );
		$sql   = "SELECT status, COUNT(*) AS total FROM `{$table}`";
		$args  = array();
		if ( $agent_id ) {
			$sql   .= ' WHERE assigned_agent_id = %d';
			$args[] = $agent_id;
		}
		$sql .= ' GROUP BY status';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql, ARRAY_A );

		$counts = array_fill_keys( Lead_Status::all(), 0 );
		foreach ( $rows as $row ) {
			$counts[ $row['status'] ] = (int) $row['total'];
		}
		return $counts;
	}

	/**
	 * Counts by source.
	 *
	 * @param string $since UTC datetime.
	 * @return array<string,int>
	 */
	public function source_counts( string $since = '' ): array {
		global $wpdb;
		$table = esc_sql( $this->table() );
		$sql   = "SELECT source, COUNT(*) AS total FROM `{$table}`";
		$args  = array();
		if ( $since ) {
			$sql   .= ' WHERE created_at >= %s';
			$args[] = $since;
		}
		$sql .= ' GROUP BY source ORDER BY total DESC';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql, ARRAY_A );

		$out = array();
		foreach ( $rows as $row ) {
			$out[ $row['source'] ] = (int) $row['total'];
		}
		return $out;
	}

	/**
	 * Counts by agent.
	 *
	 * @return array<int,array{agent_id:int,name:string,total:int,won:int}>
	 */
	public function agent_counts(): array {
		global $wpdb;
		$table = esc_sql( $this->table() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT assigned_agent_id AS agent_id, COUNT(*) AS total, SUM(status = 'won') AS won
			 FROM `{$table}` GROUP BY assigned_agent_id ORDER BY total DESC",
			ARRAY_A
		);
		$out  = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'agent_id' => (int) $row['agent_id'],
				'name'     => estora_agent_name( (int) $row['agent_id'] ) ?: 'Unassigned',
				'total'    => (int) $row['total'],
				'won'      => (int) $row['won'],
			);
		}
		return $out;
	}

	/**
	 * Daily lead series.
	 *
	 * @param int $days     Days back.
	 * @param int $agent_id Scope.
	 * @return array<int,array{date:string,count:int}>
	 */
	public function daily_series( int $days = 30, int $agent_id = 0 ): array {
		global $wpdb;
		$days  = max( 1, min( 365, $days ) );
		$since = gmdate( 'Y-m-d 00:00:00', strtotime( estora_now() . ' -' . ( $days - 1 ) . ' days' ) );
		$table = esc_sql( $this->table() );
		$sql   = "SELECT DATE(created_at) AS day, COUNT(*) AS total FROM `{$table}` WHERE created_at >= %s";
		$args  = array( $since );
		if ( $agent_id ) {
			$sql   .= ' AND assigned_agent_id = %d';
			$args[] = $agent_id;
		}
		$sql .= ' GROUP BY DATE(created_at) ORDER BY day ASC';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );

		$map = array();
		foreach ( $rows as $row ) {
			$map[ $row['day'] ] = (int) $row['total'];
		}

		$out = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$day   = gmdate( 'Y-m-d', strtotime( estora_now() . ' -' . $i . ' days' ) );
			$out[] = array(
				'date'  => $day,
				'count' => $map[ $day ] ?? 0,
			);
		}
		return $out;
	}

	/**
	 * Overdue follow-ups.
	 *
	 * @param int $limit Max rows.
	 * @return array<int,array<string,mixed>>
	 */
	public function overdue_follow_ups( int $limit = 50 ): array {
		global $wpdb;
		$table        = esc_sql( $this->table() );
		$placeholders = implode( ',', array_fill( 0, count( Lead_Status::active() ), '%s' ) );
		$args         = array_merge( array( estora_now() ), Lead_Status::active() );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE next_follow_up_at IS NOT NULL AND next_follow_up_at < %s AND status IN ({$placeholders}) AND assigned_agent_id IS NOT NULL ORDER BY next_follow_up_at ASC LIMIT 50",
				$args
			),
			ARRAY_A
		);
	}

	/**
	 * Export rows for CSV.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @return array<int,array<string,mixed>>
	 */
	public function export_rows( array $filters = array() ): array {
		$filters['page']     = 1;
		$filters['per_page'] = 100;
		$out                 = array();
		$guard               = 0;

		do {
			$result = $this->query( $filters );
			$out    = array_merge( $out, $result['items'] );
			$pages  = $result['pages'];
			++$filters['page'];
			++$guard;
		} while ( $filters['page'] <= $pages && $guard < 100 );

		return $out;
	}

	/**
	 * Convert a row into the API shape.
	 *
	 * @param array<string,mixed> $row Row.
	 * @return array<string,mixed>
	 */
	public function to_array( array $row ): array {
		$next = (string) ( $row['next_follow_up_at'] ?? '' );
		return array(
			'id'                 => (int) $row['id'],
			'public_ref'         => (string) $row['public_ref'],
			'full_name'          => (string) $row['full_name'],
			'email'              => (string) ( $row['email'] ?? '' ),
			'phone'              => (string) ( $row['phone'] ?? '' ),
			'preferred_contact'  => (string) $row['preferred_contact'],
			'property_id'        => (int) ( $row['property_id'] ?? 0 ),
			'property'           => $row['property_id'] ? array(
				'id'    => (int) $row['property_id'],
				'title' => get_the_title( (int) $row['property_id'] ) ?: 'Deleted property',
				'url'   => get_permalink( (int) $row['property_id'] ) ?: '',
			) : null,
			'budget_min'         => (float) ( $row['budget_min'] ?? 0 ),
			'budget_max'         => (float) ( $row['budget_max'] ?? 0 ),
			'preferred_location' => (string) ( $row['preferred_location'] ?? '' ),
			'message'            => (string) ( $row['message'] ?? '' ),
			'source'             => (string) $row['source'],
			'utm_source'         => (string) ( $row['utm_source'] ?? '' ),
			'utm_medium'         => (string) ( $row['utm_medium'] ?? '' ),
			'utm_campaign'       => (string) ( $row['utm_campaign'] ?? '' ),
			'status'             => (string) $row['status'],
			'status_label'       => Lead_Status::label( (string) $row['status'] ),
			'priority'           => (string) $row['priority'],
			'assigned_agent_id'  => (int) ( $row['assigned_agent_id'] ?? 0 ),
			'agent_name'         => estora_agent_name( (int) ( $row['assigned_agent_id'] ?? 0 ) ),
			'last_contact_at'    => $row['last_contact_at'] ? get_date_from_gmt( $row['last_contact_at'], 'c' ) : null,
			'next_follow_up_at'  => $next ? get_date_from_gmt( $next, 'c' ) : null,
			'follow_up_local'    => $next ? estora_format_datetime( $next, 'Y-m-d\TH:i' ) : '',
			'is_overdue'         => $next && $next < estora_now() && in_array( $row['status'], Lead_Status::active(), true ),
			'won_value'          => (float) ( $row['won_value'] ?? 0 ),
			'created_at'         => get_date_from_gmt( $row['created_at'], 'c' ),
			'updated_at'         => get_date_from_gmt( $row['updated_at'], 'c' ),
		);
	}
}
