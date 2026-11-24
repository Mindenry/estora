<?php
/**
 * Aggregated reporting. All numbers come from SQL, never from mocked data.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Services;

use Estora\Core\Domain\Appointment_Status;
use Estora\Core\Domain\Lead_Status;
use Estora\Core\Repositories\Activity_Repository;
use Estora\Core\Repositories\Appointment_Repository;
use Estora\Core\Repositories\Lead_Repository;
use Estora\Core\Repositories\Property_Repository;
use Estora\Core\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

final class Report_Service {

	private Property_Repository $properties;
	private Lead_Repository $leads;
	private Appointment_Repository $appointments;
	private Activity_Repository $activity;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->properties   = new Property_Repository();
		$this->leads        = new Lead_Repository();
		$this->appointments = new Appointment_Repository();
		$this->activity     = new Activity_Repository();
	}

	/**
	 * Dashboard summary (default range cached for 5 minutes).
	 *
	 * @param bool   $refresh Bypass cache.
	 * @param int    $days    Range length in days.
	 * @param string $from    Optional Y-m-d start.
	 * @param string $to      Optional Y-m-d end.
	 * @return array<string,mixed>
	 */
	public function summary( bool $refresh = false, int $days = 30, string $from = '', string $to = '' ): array {
		$days       = max( 1, min( 365, $days ) );
		$end_date   = $this->valid_date( $to ) ? $to : current_time( 'Y-m-d' );
		$start_date = $this->valid_date( $from ) ? $from : gmdate( 'Y-m-d', strtotime( $end_date . ' -' . ( $days - 1 ) . ' days' ) );
		if ( $start_date > $end_date ) {
			$swap       = $start_date;
			$start_date = $end_date;
			$end_date   = $swap;
		}

		$cacheable = ( 30 === $days && ! $from && ! $to );
		if ( $cacheable ) {
			$cached = get_transient( 'estora_dashboard_summary' );
			if ( ! $refresh && is_array( $cached ) ) {
				$cached['cached'] = true;
				return $this->filter_summary_for_role( $cached );
			}
		}

		$property_stats    = $this->properties->stats();
		$lead_counts       = $this->leads->status_counts();
		$lead_total        = array_sum( $lead_counts );
		$appointment_stats = $this->appointment_statistics();
		$trend             = $this->leads->daily_between( $start_date, $end_date );
		$range_total       = $this->leads->count_between( $start_date, $end_date );
		$range_won         = $this->leads->count_between( $start_date, $end_date, Lead_Status::WON );

		$summary = array(
			'range'           => array(
				'from'            => $start_date,
				'to'              => $end_date,
				'days'            => $days,
				'total'           => $range_total,
				'won'             => $range_won,
				'conversion_rate' => $range_total > 0 ? round( ( $range_won / $range_total ) * 100, 1 ) : 0.0,
			),
			'properties'      => array(
				'total'         => $property_stats['total'],
				'published'     => $property_stats['published'],
				'available'     => $property_stats['available'],
				'reserved'      => $property_stats['reserved'],
				'sold'          => $property_stats['sold'],
				'rented'        => $property_stats['rented'],
				'rentals'       => (int) ( $property_stats['by_purpose']['rent'] ?? 0 ),
				'average_price' => round( $property_stats['average_price'], 2 ),
				'stale'         => $property_stats['stale'],
				'by_type'       => $this->property_type_counts(),
			),
			'leads'           => array(
				'total'           => $lead_total,
				'new'             => $lead_counts[ Lead_Status::NEW ] ?? 0,
				'contacted'       => $lead_counts[ Lead_Status::CONTACTED ] ?? 0,
				'qualified'       => $lead_counts[ Lead_Status::QUALIFIED ] ?? 0,
				'negotiation'     => $lead_counts[ Lead_Status::NEGOTIATION ] ?? 0,
				'won'             => $lead_counts[ Lead_Status::WON ] ?? 0,
				'lost'            => $lead_counts[ Lead_Status::LOST ] ?? 0,
				'conversion_rate' => $lead_total > 0 ? round( ( ( $lead_counts[ Lead_Status::WON ] ?? 0 ) / $lead_total ) * 100, 1 ) : 0.0,
				'range_total'     => $range_total,
				'range_won'       => $range_won,
				'by_agent'        => $this->leads->agent_counts(),
			),
			'appointments'    => array(
				'upcoming' => $appointment_stats['upcoming'],
				'pending'  => $appointment_stats['counts'][ Appointment_Status::PENDING ] ?? 0,
				'this_week' => $appointment_stats['this_week'],
				'today'    => $appointment_stats['today'],
				'by_weekday' => $appointment_stats['by_weekday'],
			),
			'recent_activity' => $this->activity->recent( 10 ),
			'lead_trend'      => $trend,
			'by_source'       => $this->leads->source_counts( $start_date . ' 00:00:00' ),
		);

		if ( $cacheable ) {
			set_transient( 'estora_dashboard_summary', $summary, 300 );
		}
		return $this->filter_summary_for_role( $summary );
	}

	/**
	 * Remove sections the current role may not see.
	 *
	 * @param array<string,mixed> $summary Summary.
	 * @return array<string,mixed>
	 */
	private function filter_summary_for_role( array $summary ): array {
		$summary['cached'] = (bool) ( $summary['cached'] ?? false );

		if ( ! current_user_can( Capabilities::VIEW_LEADS ) ) {
			unset( $summary['leads'], $summary['lead_trend'], $summary['by_source'] );
			unset( $summary['range']['total'], $summary['range']['won'], $summary['range']['conversion_rate'] );
		}
		if ( ! current_user_can( Capabilities::MANAGE_APPOINTMENTS ) ) {
			unset( $summary['appointments'] );
		}
		if ( ! empty( $summary['recent_activity'] ) && is_array( $summary['recent_activity'] ) ) {
			$summary['recent_activity'] = array_values(
				array_filter(
					$summary['recent_activity'],
					static function ( $row ) {
						$type = $row['object_type'] ?? '';
						if ( 'lead' === $type && ! current_user_can( Capabilities::VIEW_LEADS ) ) {
							return false;
						}
						if ( 'appointment' === $type && ! current_user_can( Capabilities::MANAGE_APPOINTMENTS ) ) {
							return false;
						}
						return true;
					}
				)
			);
		}
		return $summary;
	}

	/**
	 * Validate a Y-m-d date.
	 *
	 * @param string $date Date.
	 */
	private function valid_date( string $date ): bool {
		$parsed = \DateTimeImmutable::createFromFormat( 'Y-m-d', $date );
		return $parsed && $parsed->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Published property counts by type.
	 *
	 * @return array<int,array{name:string,total:int}>
	 */
	private function property_type_counts(): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.name, COUNT(*) AS total FROM {$wpdb->term_relationships} tr
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				 INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
				 INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
				 WHERE tt.taxonomy = 'property_type' AND p.post_type = %s AND p.post_status = 'publish'
				 GROUP BY t.name ORDER BY total DESC",
				'property'
			),
			ARRAY_A
		);
		return array_map(
			static fn( $row ) => array( 'name' => $row['name'], 'total' => (int) $row['total'] ),
			(array) $rows
		);
	}

	/**
	 * Scoped dashboard for a sales agent (or any user working their own file).
	 *
	 * @param int    $agent_id Agent.
	 * @param int    $days     Range.
	 * @param string $from     Optional start.
	 * @param string $to       Optional end.
	 * @return array<string,mixed>
	 */
	public function agent_dashboard( int $agent_id, int $days = 30, string $from = '', string $to = '' ): array {
		$days       = max( 1, min( 365, $days ) );
		$end_date   = $this->valid_date( $to ) ? $to : current_time( 'Y-m-d' );
		$start_date = $this->valid_date( $from ) ? $from : gmdate( 'Y-m-d', strtotime( $end_date . ' -' . ( $days - 1 ) . ' days' ) );
		$counts     = $this->leads->status_counts( $agent_id );
		$total      = array_sum( $counts );
		$won        = (int) ( $counts[ Lead_Status::WON ] ?? 0 );
		$range      = $this->leads->count_between( $start_date, $end_date, '', $agent_id );
		$range_won  = $this->leads->count_between( $start_date, $end_date, Lead_Status::WON, $agent_id );

		$appointment_counts = $this->appointments->counts( $agent_id );
		$upcoming_rows      = $this->appointments->query(
			array(
				'agent_id' => $agent_id,
				'from'     => current_time( 'Y-m-d' ),
				'per_page' => 100,
				'order'    => 'ASC',
			)
		);
		$upcoming = array_values(
			array_filter(
				$upcoming_rows['items'],
				static fn( $row ) => in_array( $row['status'], array( Appointment_Status::PENDING, Appointment_Status::CONFIRMED, Appointment_Status::RESCHEDULED ), true )
			)
		);

		$properties = new \WP_Query(
			array(
				'post_type'      => 'property',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_estora_agent_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $agent_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return array(
			'range'        => array(
				'from'            => $start_date,
				'to'              => $end_date,
				'days'            => $days,
				'total'           => $range,
				'won'             => $range_won,
				'conversion_rate' => $range > 0 ? round( ( $range_won / $range ) * 100, 1 ) : 0.0,
			),
			'properties'   => array(
				'assigned' => (int) $properties->found_posts,
			),
			'leads'        => array(
				'total'           => $total,
				'new'             => (int) ( $counts[ Lead_Status::NEW ] ?? 0 ),
				'contacted'       => (int) ( $counts[ Lead_Status::CONTACTED ] ?? 0 ),
				'qualified'       => (int) ( $counts[ Lead_Status::QUALIFIED ] ?? 0 ),
				'negotiation'     => (int) ( $counts[ Lead_Status::NEGOTIATION ] ?? 0 ),
				'won'             => $won,
				'lost'            => (int) ( $counts[ Lead_Status::LOST ] ?? 0 ),
				'conversion_rate' => $total > 0 ? round( ( $won / $total ) * 100, 1 ) : 0.0,
				'range_total'     => $range,
				'range_won'       => $range_won,
				'by_status'       => $counts,
			),
			'appointments' => array(
				'upcoming_count' => count( $upcoming ),
				'pending'        => (int) ( $appointment_counts[ Appointment_Status::PENDING ] ?? 0 ),
				'next'           => array_slice( $upcoming, 0, 6 ),
			),
			'lead_trend'   => $this->leads->daily_between( $start_date, $end_date, $agent_id ),
		);
	}

	/**
	 * Property statistics.
	 *
	 * @return array<string,mixed>
	 */
	public function property_statistics(): array {
		global $wpdb;
		$stats = $this->properties->stats();

		$by_type = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.name, COUNT(*) AS total FROM {$wpdb->term_relationships} tr
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				 INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
				 INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
				 WHERE tt.taxonomy = 'property_type' AND p.post_type = %s AND p.post_status = 'publish'
				 GROUP BY t.name ORDER BY total DESC",
				'property'
			),
			ARRAY_A
		);
		foreach ( $rows as $row ) {
			$by_type[] = array( 'name' => $row['name'], 'total' => (int) $row['total'] );
		}

		$by_province = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS province, COUNT(*) AS total
				 FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_estora_province' AND p.post_type = %s AND p.post_status = 'publish' AND pm.meta_value != ''
				 GROUP BY pm.meta_value ORDER BY total DESC LIMIT 10",
				'property'
			),
			ARRAY_A
		);
		foreach ( $rows as $row ) {
			$by_province[] = array( 'name' => $row['province'], 'total' => (int) $row['total'] );
		}

		return array_merge( $stats, array( 'by_type' => $by_type, 'by_province' => $by_province ) );
	}

	/**
	 * Lead statistics.
	 *
	 * @param int $days Window for daily series.
	 * @return array<string,mixed>
	 */
	public function lead_statistics( int $days = 30 ): array {
		global $wpdb;
		$counts = $this->leads->status_counts();
		$total  = array_sum( $counts );
		$won    = $counts[ Lead_Status::WON ] ?? 0;

		$response = 0.0;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$avg = $wpdb->get_var(
			'SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, last_contact_at)) FROM `' . esc_sql( estora_table( 'leads' ) ) . '` WHERE last_contact_at IS NOT NULL'
		);
		if ( null !== $avg ) {
			$response = round( (float) $avg / 60, 1 );
		}

		return array(
			'counts'             => $counts,
			'total'              => $total,
			'conversion_rate'    => $total > 0 ? round( ( $won / $total ) * 100, 1 ) : 0.0,
			'by_source'          => $this->leads->source_counts(),
			'by_agent'           => $this->leads->agent_counts(),
			'daily'              => $this->leads->daily_series( $days ),
			'avg_response_hours' => $response,
		);
	}

	/**
	 * Appointment statistics.
	 *
	 * @return array<string,mixed>
	 */
	public function appointment_statistics(): array {
		global $wpdb;
		$counts = $this->appointments->counts();
		$table  = esc_sql( estora_table( 'appointments' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$today = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE requested_date = %s AND status IN ('pending','confirmed','rescheduled')", current_time( 'Y-m-d' ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$week = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE requested_date BETWEEN %s AND %s AND status IN ('pending','confirmed','rescheduled')",
				current_time( 'Y-m-d' ),
				gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +7 days' ) )
			)
		);

		$upcoming = count( $this->appointments->upcoming( 100 ) );

		$by_agent = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT agent_id, COUNT(*) AS total FROM `{$table}` WHERE agent_id IS NOT NULL GROUP BY agent_id ORDER BY total DESC LIMIT 10",
			ARRAY_A
		);
		foreach ( $rows as $row ) {
			$by_agent[] = array(
				'agent_id' => (int) $row['agent_id'],
				'name'     => estora_agent_name( (int) $row['agent_id'] ) ?: 'Unassigned',
				'total'    => (int) $row['total'],
			);
		}

		$by_weekday = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results(
			"SELECT DAYOFWEEK(requested_date) AS dow, COUNT(*) AS total FROM `{$table}` WHERE status != 'cancelled' GROUP BY DAYOFWEEK(requested_date) ORDER BY dow",
			ARRAY_A
		);
		$names = array( 1 => 'Sunday', 2 => 'Monday', 3 => 'Tuesday', 4 => 'Wednesday', 5 => 'Thursday', 6 => 'Friday', 7 => 'Saturday' );
		foreach ( $rows as $row ) {
			$by_weekday[] = array( 'name' => $names[ (int) $row['dow'] ] ?? '', 'total' => (int) $row['total'] );
		}

		$upcoming_daily = array();
		$start_day      = current_time( 'Y-m-d' );
		$end_day        = gmdate( 'Y-m-d', strtotime( $start_day . ' +13 days' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT requested_date, COUNT(*) AS total FROM `{$table}`
				 WHERE requested_date BETWEEN %s AND %s AND status IN ('pending','confirmed','rescheduled')
				 GROUP BY requested_date ORDER BY requested_date ASC",
				$start_day,
				$end_day
			),
			ARRAY_A
		);
		$daily_map = array();
		foreach ( $rows as $row ) {
			$daily_map[ $row['requested_date'] ] = (int) $row['total'];
		}
		for ( $i = 0; $i < 14; $i++ ) {
			$day            = gmdate( 'Y-m-d', strtotime( $start_day . ' +' . $i . ' days' ) );
			$upcoming_daily[] = array(
				'date'  => $day,
				'count' => $daily_map[ $day ] ?? 0,
			);
		}

		$total     = array_sum( $counts );
		$completed = $counts[ Appointment_Status::COMPLETED ] ?? 0;

		return array(
			'counts'          => $counts,
			'total'           => $total,
			'upcoming'        => $upcoming,
			'this_week'       => $week,
			'today'           => $today,
			'completion_rate' => $total > 0 ? round( ( $completed / $total ) * 100, 1 ) : 0.0,
			'by_agent'        => $by_agent,
			'by_weekday'      => $by_weekday,
			'upcoming_daily'  => $upcoming_daily,
			'stale_pending'   => count( $this->appointments->stale_pending( 24 ) ),
		);
	}

	/**
	 * Agent performance.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function agent_performance(): array {
		global $wpdb;

		$users = get_users(
			array(
				'role'   => 'estora_sales_agent',
				'number' => 100,
			)
		);

		$lead_counts = $this->leads->agent_counts();
		$lead_map    = array();
		foreach ( $lead_counts as $row ) {
			$lead_map[ $row['agent_id'] ] = $row;
		}

		$appointment_table = esc_sql( estora_table( 'appointments' ) );
		$out               = array();

		foreach ( $users as $user ) {
			if ( ! $user instanceof \WP_User ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$active_appointments = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$appointment_table}` WHERE agent_id = %d AND status IN ('pending','confirmed','rescheduled') AND requested_date >= %s",
					$user->ID,
					current_time( 'Y-m-d' )
				)
			);

			$total = (int) ( $lead_map[ $user->ID ]['total'] ?? 0 );
			$won   = (int) ( $lead_map[ $user->ID ]['won'] ?? 0 );

			$out[] = array(
				'agent_id'            => $user->ID,
				'name'                => $user->display_name,
				'email'               => $user->user_email,
				'active'              => '0' !== (string) get_user_meta( $user->ID, 'estora_active', true ),
				'leads'               => $total,
				'won'                 => $won,
				'conversion_rate'     => $total > 0 ? round( ( $won / $total ) * 100, 1 ) : 0.0,
				'upcoming_appointments' => $active_appointments,
			);
		}

		usort(
			$out,
			static fn( $a, $b ) => $b['won'] <=> $a['won']
		);

		return $out;
	}

	/**
	 * Export dataset.
	 *
	 * @param string $report Report key.
	 * @return array{headers:string[],rows:array<int,array<int,string>>}
	 * @throws \InvalidArgumentException On unknown report.
	 */
	public function export( string $report ): array {
		switch ( $report ) {
			case 'properties':
				$rows = array();
				foreach ( $this->properties->export_rows() as $row ) {
					$rows[] = array_map( 'strval', array_values( $row ) );
				}
				return array(
					'headers' => array( 'Reference', 'Title', 'Type', 'Purpose', 'Status', 'Availability', 'Price', 'Bedrooms', 'Bathrooms', 'Usable area', 'Province', 'District', 'Agent', 'Updated' ),
					'rows'    => $rows,
				);

			case 'leads':
				$result = $this->leads->query( array( 'per_page' => 100, 'page' => 1 ) );
				$rows   = array();
				foreach ( $result['items'] as $lead ) {
					$rows[] = array(
						$lead['public_ref'],
						$lead['full_name'],
						$lead['email'],
						$lead['phone'],
						$lead['status_label'],
						$lead['source'],
						$lead['agent_name'],
						$lead['property']['title'] ?? '',
						number_format( $lead['budget_max'], 2, '.', '' ),
						$lead['created_at'],
					);
				}
				return array(
					'headers' => array( 'Reference', 'Name', 'Email', 'Phone', 'Status', 'Source', 'Agent', 'Property', 'Budget max', 'Created' ),
					'rows'    => $rows,
				);

			case 'agents':
				$rows = array();
				foreach ( $this->agent_performance() as $agent ) {
					$rows[] = array(
						(string) $agent['name'],
						(string) $agent['email'],
						(string) $agent['leads'],
						(string) $agent['won'],
						(string) $agent['conversion_rate'],
						(string) $agent['upcoming_appointments'],
						$agent['active'] ? 'active' : 'inactive',
					);
				}
				return array(
					'headers' => array( 'Agent', 'Email', 'Leads', 'Won', 'Conversion %', 'Upcoming viewings', 'Status' ),
					'rows'    => $rows,
				);

			case 'appointments':
				$result = $this->appointments->query( array( 'per_page' => 100, 'order' => 'DESC' ) );
				$rows   = array();
				foreach ( $result['items'] as $row ) {
					$rows[] = array(
						$row['public_ref'],
						$row['date'],
						$row['time'],
						$row['customer_name'],
						$row['customer_phone'],
						$row['property_title'],
						$row['agent_name'],
						$row['status_label'],
					);
				}
				return array(
					'headers' => array( 'Reference', 'Date', 'Time', 'Customer', 'Phone', 'Property', 'Agent', 'Status' ),
					'rows'    => $rows,
				);

			case 'conversion':
				$rows  = array();
				$stats = $this->lead_statistics( 30 );
				foreach ( $stats['by_source'] as $source => $count ) {
					$rows[] = array( (string) $source, (string) $count );
				}
				return array(
					'headers' => array( 'Source', 'Leads (all time)' ),
					'rows'    => $rows,
				);

			default:
				throw new \InvalidArgumentException( 'Unknown report.' );
		}
	}
}
