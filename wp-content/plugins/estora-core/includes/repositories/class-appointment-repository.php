<?php
/**
 * Appointment persistence.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Repositories;

use Estora\Core\Domain\Appointment_Status;
use Estora\Core\Support\Validation_Exception;

defined( 'ABSPATH' ) || exit;

final class Appointment_Repository {

	/**
	 * Table name.
	 */
	public function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'estora_appointments';
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
		return $row ?: null;
	}

	/**
	 * Paged query.
	 *
	 * @param array<string,mixed> $args Filters.
	 * @return array{items:array<int,array<string,mixed>>,total:int,pages:int,counts:array<string,int>}
	 */
	public function query( array $args = array() ): array {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'status'      => '',
				'agent_id'    => 0,
				'property_id' => 0,
				'from'        => '',
				'to'          => '',
				'page'        => 1,
				'per_page'    => 20,
				'order'       => 'ASC',
			)
		);

		$per_page = max( 1, min( 100, (int) $args['per_page'] ) );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();

		if ( $args['status'] && in_array( $args['status'], Appointment_Status::all(), true ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['agent_id'] ) ) {
			$where[]  = 'agent_id = %d';
			$params[] = (int) $args['agent_id'];
		}
		if ( ! empty( $args['property_id'] ) ) {
			$where[]  = 'property_id = %d';
			$params[] = (int) $args['property_id'];
		}
		if ( ! empty( $args['lead_id'] ) ) {
			$where[]  = 'lead_id = %d';
			$params[] = (int) $args['lead_id'];
		}
		if ( $args['from'] ) {
			$where[]  = 'requested_date >= %s';
			$params[] = (string) $args['from'];
		}
		if ( $args['to'] ) {
			$where[]  = 'requested_date <= %s';
			$params[] = (string) $args['to'];
		}

		$where_sql = implode( ' AND ', $where );
		$table     = esc_sql( $this->table() );
		$order     = 'DESC' === strtoupper( (string) $args['order'] ) ? 'DESC' : 'ASC';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var(
			$params ? $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}", $params ) : "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}"
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY requested_date {$order}, start_time {$order} LIMIT %d OFFSET %d",
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
			'counts'   => $this->counts( ! empty( $args['agent_id'] ) ? (int) $args['agent_id'] : 0 ),
		);
	}

	/**
	 * Insert an appointment.
	 *
	 * @param array<string,mixed> $data Data.
	 * @return int
	 * @throws Validation_Exception On slot collision.
	 */
	public function create( array $data ): int {
		global $wpdb;

		$status   = (string) ( $data['status'] ?? Appointment_Status::PENDING );
		$agent_id = ! empty( $data['agent_id'] ) ? (int) $data['agent_id'] : null;
		$now      = estora_now();

		$row = array(
			'public_ref'     => '',
			'property_id'    => (int) $data['property_id'],
			'lead_id'        => ! empty( $data['lead_id'] ) ? (int) $data['lead_id'] : null,
			'agent_id'       => $agent_id,
			'customer_name'  => (string) $data['customer_name'],
			'customer_email' => $data['customer_email'] ?? null,
			'customer_phone' => (string) $data['customer_phone'],
			'requested_date' => (string) $data['requested_date'],
			'start_time'     => (string) $data['start_time'],
			'end_time'       => (string) $data['end_time'],
			'status'         => $status,
			'source'         => (string) ( $data['source'] ?? 'website' ),
			'notes'          => $data['notes'] ?? null,
			'internal_notes' => $data['internal_notes'] ?? null,
			'active_key'     => Appointment_Status::is_active( $status ) && $agent_id
				? $this->active_key( $agent_id, (string) $data['requested_date'], (string) $data['start_time'] )
				: null,
			'created_by'     => (int) ( $data['created_by'] ?? 0 ),
			'created_at'     => $now,
			'updated_at'     => $now,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert(
			$this->table(),
			$row,
			array( '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $ok ) {
			if ( false !== strpos( (string) $wpdb->last_error, 'Duplicate' ) ) {
				throw new Validation_Exception( 'That slot was just taken. Choose another time.', array( 'time' => array( 'That slot was just taken.' ) ), 409 );
			}
			throw new \RuntimeException( 'Could not create the appointment.' );
		}

		$id = (int) $wpdb->insert_id;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$this->table(),
			array( 'public_ref' => 'AP-' . str_pad( (string) $id, 6, '0', STR_PAD_LEFT ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return $id;
	}

	/**
	 * Update columns.
	 *
	 * @param int                 $id   ID.
	 * @param array<string,mixed> $data Values.
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		$allowed = array(
			'property_id'    => '%d',
			'lead_id'        => '%d',
			'agent_id'       => '%d',
			'customer_name'  => '%s',
			'customer_email' => '%s',
			'customer_phone' => '%s',
			'requested_date' => '%s',
			'start_time'     => '%s',
			'end_time'       => '%s',
			'status'         => '%s',
			'notes'          => '%s',
			'internal_notes' => '%s',
			'active_key'     => '%s',
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
		$ok = $wpdb->update( $this->table(), $row, array( 'id' => $id ), $formats, array( '%d' ) );

		if ( false === $ok && false !== strpos( (string) $wpdb->last_error, 'Duplicate' ) ) {
			throw new Validation_Exception( 'That slot is already taken for this agent.', array( 'time' => array( 'That slot is already taken.' ) ), 409 );
		}

		return false !== $ok;
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
	 * Compose the active slot key.
	 *
	 * @param int    $agent_id Agent.
	 * @param string $date     Date.
	 * @param string $start    Start.
	 */
	public function active_key( int $agent_id, string $date, string $start ): string {
		return sprintf( 'agent:%d|%s|%s', $agent_id, $date, substr( $start, 0, 5 ) );
	}

	/**
	 * Server-side agent conflict check.
	 *
	 * @param int    $agent_id Agent.
	 * @param string $date     Date.
	 * @param string $start    Start.
	 * @param string $end      End.
	 * @param int    $exclude  Appointment to ignore.
	 * @return array<int,array<string,mixed>>
	 */
	public function agent_conflicts( int $agent_id, string $date, string $start, string $end, int $exclude = 0 ): array {
		global $wpdb;
		$table        = esc_sql( $this->table() );
		$placeholders = implode( ',', array_fill( 0, count( Appointment_Status::all() ), '%s' ) );
		$active       = array( Appointment_Status::PENDING, Appointment_Status::CONFIRMED, Appointment_Status::RESCHEDULED );
		$args         = array_merge( array( $agent_id, $date, $end, $start ), $active, array( $exclude ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE agent_id = %d AND requested_date = %s
				 AND start_time < %s AND end_time > %s
				 AND status IN (" . implode( ',', array_fill( 0, count( $active ), '%s' ) ) . ')
				 AND id != %d',
				$args
			),
			ARRAY_A
		);
	}

	/**
	 * Property-level conflict (two simultaneous viewings at one property).
	 *
	 * @param int    $property_id Property.
	 * @param string $date        Date.
	 * @param string $start       Start.
	 * @param string $end         End.
	 * @param int    $exclude     Excluded ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function property_conflicts( int $property_id, string $date, string $start, string $end, int $exclude = 0 ): array {
		global $wpdb;
		$table  = esc_sql( $this->table() );
		$active = array( Appointment_Status::PENDING, Appointment_Status::CONFIRMED, Appointment_Status::RESCHEDULED );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE property_id = %d AND requested_date = %s
				 AND start_time < %s AND end_time > %s
				 AND status IN (" . implode( ',', array_fill( 0, count( $active ), '%s' ) ) . ')
				 AND id != %d',
				array_merge( array( $property_id, $date, $end, $start ), $active, array( $exclude ) )
			),
			ARRAY_A
		);
	}

	/**
	 * Active bookings for a day (optionally one agent).
	 *
	 * @param string $date      Date.
	 * @param int    $agent_id  Agent (0 = all).
	 * @param int    $exclude   Excluded appointment.
	 * @return array<int,array{start:string,end:string}>
	 */
	public function booked_slots( string $date, int $agent_id = 0, int $exclude = 0 ): array {
		global $wpdb;
		$table  = esc_sql( $this->table() );
		$active = array( Appointment_Status::PENDING, Appointment_Status::CONFIRMED, Appointment_Status::RESCHEDULED );
		$args   = array_merge( array( $date ), $active );
		$sql    = "SELECT start_time, end_time FROM `{$table}` WHERE requested_date = %s
			AND status IN (" . implode( ',', array_fill( 0, count( $active ), '%s' ) ) . ')';
		if ( $agent_id ) {
			$sql   .= ' AND agent_id = %d';
			$args[] = $agent_id;
		}
		if ( $exclude ) {
			$sql   .= ' AND id != %d';
			$args[] = $exclude;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );

		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'start' => substr( (string) $row['start_time'], 0, 5 ),
				'end'   => substr( (string) $row['end_time'], 0, 5 ),
			);
		}
		return $out;
	}

	/**
	 * Counts by status.
	 *
	 * @param int $agent_id Scope.
	 * @return array<string,int>
	 */
	public function counts( int $agent_id = 0 ): array {
		global $wpdb;
		$table = esc_sql( $this->table() );
		$sql   = "SELECT status, COUNT(*) AS total FROM `{$table}`";
		$args  = array();
		if ( $agent_id ) {
			$sql   .= ' WHERE agent_id = %d';
			$args[] = $agent_id;
		}
		$sql .= ' GROUP BY status';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql, ARRAY_A );

		$out = array_fill_keys( Appointment_Status::all(), 0 );
		foreach ( $rows as $row ) {
			$out[ $row['status'] ] = (int) $row['total'];
		}
		return $out;
	}

	/**
	 * Upcoming active appointments.
	 *
	 * @param int $limit Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public function upcoming( int $limit = 8 ): array {
		global $wpdb;
		$table = esc_sql( $this->table() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE requested_date >= %s AND status IN ('pending','confirmed','rescheduled')
				 ORDER BY requested_date ASC, start_time ASC LIMIT %d",
				current_time( 'Y-m-d' ),
				$limit
			),
			ARRAY_A
		);

		$out = array();
		foreach ( $rows as $row ) {
			$out[] = $this->to_array( $row );
		}
		return $out;
	}

	/**
	 * Pending appointments older than N hours.
	 *
	 * @param int $hours Hours.
	 * @return array<int,array<string,mixed>>
	 */
	public function stale_pending( int $hours = 24 ): array {
		global $wpdb;
		$table  = esc_sql( $this->table() );
		$cutoff = estora_now( '-' . max( 1, $hours ) . ' hours' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE status = 'pending' AND created_at < %s ORDER BY created_at ASC LIMIT 50",
				$cutoff
			),
			ARRAY_A
		);
		$out  = array();
		foreach ( $rows as $row ) {
			$out[] = $this->to_array( $row );
		}
		return $out;
	}

	/**
	 * Row to API shape.
	 *
	 * @param array<string,mixed> $row Row.
	 * @return array<string,mixed>
	 */
	public function to_array( array $row ): array {
		$status = (string) $row['status'];
		return array(
			'id'             => (int) $row['id'],
			'public_ref'     => (string) $row['public_ref'],
			'property_id'    => (int) $row['property_id'],
			'property_title' => get_the_title( (int) $row['property_id'] ) ?: 'Deleted property',
			'lead_id'        => (int) ( $row['lead_id'] ?? 0 ),
			'agent_id'       => (int) ( $row['agent_id'] ?? 0 ),
			'agent_name'     => estora_agent_name( (int) ( $row['agent_id'] ?? 0 ) ),
			'customer_name'  => (string) $row['customer_name'],
			'customer_email' => (string) ( $row['customer_email'] ?? '' ),
			'customer_phone' => (string) $row['customer_phone'],
			'date'           => (string) $row['requested_date'],
			'time'           => substr( (string) $row['start_time'], 0, 5 ),
			'end_time'       => substr( (string) $row['end_time'], 0, 5 ),
			'status'         => $status,
			'status_label'   => Appointment_Status::label( $status ),
			'source'         => (string) $row['source'],
			'notes'          => (string) ( $row['notes'] ?? '' ),
			'internal_notes' => (string) ( $row['internal_notes'] ?? '' ),
			'created_at'     => get_date_from_gmt( $row['created_at'], 'c' ),
			'updated_at'     => get_date_from_gmt( $row['updated_at'], 'c' ),
		);
	}
}
