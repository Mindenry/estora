<?php
/**
 * Appointment booking rules: hours, slots, conflicts, transitions.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Services;

use Estora\Core\Domain\Appointment_Status;
use Estora\Core\Domain\Availability;
use Estora\Core\Domain\Property_Status;
use Estora\Core\Repositories\Activity_Repository;
use Estora\Core\Repositories\Appointment_Repository;
use Estora\Core\Support\Capabilities;
use Estora\Core\Support\Validation_Exception;
use Estora\Core\Support\Validator;

defined( 'ABSPATH' ) || exit;

final class Appointment_Service {

	private Appointment_Repository $repo;
	private Activity_Repository $activity;
	private Notification_Service $notifications;
	private Lead_Service $leads;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repo          = new Appointment_Repository();
		$this->activity      = new Activity_Repository();
		$this->notifications = new Notification_Service();
		$this->leads         = new Lead_Service();
	}

	/**
	 * Slot grid for a date.
	 *
	 * @param string $date        Y-m-d.
	 * @param int    $agent_id    Agent (0 = best available).
	 * @param int    $property_id Property to avoid double-booking viewings.
	 * @param int    $exclude     Appointment being rescheduled.
	 * @return array<string,mixed>
	 */
	public function available_slots( string $date, int $agent_id = 0, int $property_id = 0, int $exclude = 0 ): array {
		Validator::assert( array( 'date' => $date ), array( 'date' => array( 'required', 'date' ) ) );

		if ( ! Settings::is_open_on( $date ) ) {
			return array(
				'date'    => $date,
				'agent_id' => 0,
				'slots'   => array(),
				'closed'  => true,
				'message' => 'The office is closed on this day.',
			);
		}

		if ( ! $agent_id ) {
			$agent_id = $this->choose_agent( $date );
		}
		if ( ! $agent_id ) {
			return array(
				'date'    => $date,
				'agent_id' => 0,
				'slots'   => array(),
				'closed'  => true,
				'message' => 'No agent is available for this day.',
			);
		}

		$booked = $this->repo->booked_slots( $date, $agent_id, $exclude );
		if ( $property_id ) {
			foreach ( $this->repo->booked_slots( $date, 0, $exclude ) as $booking ) {
				$booked[] = $booking;
			}
		}

		$settings = Settings::all();
		$hours    = Settings::business_hours();
		$day      = (int) gmdate( 'w', strtotime( $date . ' 12:00:00' ) );
		$window   = $hours[ $day ] ?? null;
		if ( null === $window ) {
			return array(
				'date'    => $date,
				'agent_id' => $agent_id,
				'slots'   => array(),
				'closed'  => true,
				'message' => 'The office is closed on this day.',
			);
		}

		$slots = Availability::slots(
			$date,
			$window[0],
			$window[1],
			(int) $settings['slot_minutes'],
			$settings['lunch_start'] ?: null,
			$settings['lunch_end'] ?: null,
			$booked,
			estora_now_site()
		);

		return array(
			'date'     => $date,
			'agent_id' => $agent_id,
			'slots'    => $slots,
			'closed'   => false,
			'message'  => '',
			'timezone' => wp_timezone_string(),
		);
	}

	/**
	 * Pick the least-loaded active agent for a date.
	 *
	 * @param string $date Date.
	 */
	public function choose_agent( string $date ): int {
		$users = get_users(
			array(
				'role'   => 'estora_sales_agent',
				'number' => 50,
				'fields' => 'ID',
			)
		);

		if ( empty( $users ) ) {
			$users = get_users(
				array(
					'capability' => Capabilities::EDIT_LEADS,
					'number'     => 50,
					'fields'     => 'ID',
				)
			);
		}

		$best_id    = 0;
		$best_count = PHP_INT_MAX;
		foreach ( $users as $user_id ) {
			$user_id = (int) $user_id;
			if ( '0' === (string) get_user_meta( $user_id, 'estora_active', true ) ) {
				continue;
			}
			$count = count( $this->repo->booked_slots( $date, $user_id ) );
			if ( $count < $best_count ) {
				$best_count = $count;
				$best_id    = $user_id;
			}
		}

		if ( ! $best_id ) {
			$fallback = (int) estora_setting( 'default_agent_id', 0 );
			if ( $fallback && get_userdata( $fallback ) ) {
				return $fallback;
			}
		}

		return $best_id;
	}

	/**
	 * Public viewing request.
	 *
	 * @param array<string,mixed> $input  Payload.
	 * @param bool                $public Whether it comes from the public form.
	 * @return array<string,mixed> Appointment shape.
	 * @throws Validation_Exception On invalid/conflicting input.
	 */
	public function request( array $input, bool $public = true ): array {
		$name  = mb_substr( sanitize_text_field( (string) ( $input['name'] ?? $input['customer_name'] ?? '' ) ), 0, 150 );
		$email = sanitize_email( (string) ( $input['email'] ?? '' ) );
		$phone = mb_substr( sanitize_text_field( (string) ( $input['phone'] ?? '' ) ), 0, 30 );
		$date  = sanitize_text_field( (string) ( $input['date'] ?? '' ) );
		$time  = sanitize_text_field( (string) ( $input['time'] ?? '' ) );
		$notes = mb_substr( sanitize_textarea_field( (string) ( $input['message'] ?? $input['notes'] ?? '' ) ), 0, 2000 );

		$today = current_time( 'Y-m-d' );
		Validator::assert(
			array(
				'property_id' => $input['property_id'] ?? null,
				'name'        => $name,
				'email'       => $email,
				'phone'       => $phone,
				'date'        => $date,
				'time'        => $time,
			),
			array(
				'property_id' => array( 'required', 'numeric' ),
				'name'        => array( 'required', 'max_length:150' ),
				'email'       => array( 'email', 'max_length:190' ),
				'phone'       => array( 'required', 'phone', 'max_length:30' ),
				'date'        => array( 'required', 'date', 'date_not_past:' . $today ),
				'time'        => array( 'required', 'time' ),
			),
			'Please check the viewing details.'
		);

		if ( $public && ! Validator::to_bool( $input['consent'] ?? false ) ) {
			throw new Validation_Exception( 'Consent is required.', array( 'consent' => array( 'Please agree to be contacted to arrange the viewing.' ) ) );
		}
		if ( $public && ! empty( $input['company'] ) ) {
			throw new Validation_Exception( 'The request could not be processed.', array(), 422 );
		}

		$property_id = (int) $input['property_id'];
		$property    = get_post( $property_id );
		if ( ! $property || 'property' !== $property->post_type || 'publish' !== $property->post_status ) {
			throw new Validation_Exception( 'Property not found.', array( 'property_id' => array( 'Choose an available property.' ) ), 404 );
		}
		$availability = estora_property_availability( $property_id );
		if ( ! Property_Status::accepts_inquiries( $availability ) ) {
			throw new Validation_Exception(
				'This property is no longer available for viewings.',
				array( 'property_id' => array( 'This property is sold, rented or unavailable.' ) ),
				409
			);
		}

		$agent_id = absint( $input['agent_id'] ?? 0 );
		if ( $agent_id ) {
			$agent = get_userdata( $agent_id );
			if ( ! $agent || '0' === (string) get_user_meta( $agent_id, 'estora_active', true ) ) {
				throw new Validation_Exception( 'That agent is not available.', array( 'agent_id' => array( 'Choose another agent.' ) ) );
			}
		}
		if ( ! $agent_id ) {
			$agent_id = $this->choose_agent( $date );
		}
		if ( ! $agent_id ) {
			throw new Validation_Exception( 'No agent is available for this day.', array( 'date' => array( 'Choose another day.' ) ), 409 );
		}

		$settings = Settings::all();
		$duration = (int) $settings['slot_minutes'];
		$end      = Availability::end_time( $time, $duration );
		$window   = Settings::business_hours()[ (int) gmdate( 'w', strtotime( $date . ' 12:00:00' ) ) ] ?? null;
		if ( null === $window ) {
			throw new Validation_Exception( 'The office is closed on this day.', array( 'date' => array( 'Choose a business day.' ) ) );
		}
		if ( Availability::to_minutes( $time ) < Availability::to_minutes( $window[0] )
			|| Availability::to_minutes( $end ) > Availability::to_minutes( $window[1] ) ) {
			throw new Validation_Exception( 'That time is outside business hours.', array( 'time' => array( 'Choose a time inside business hours.' ) ) );
		}
		if ( strtotime( $date . ' ' . $time ) <= current_time( 'timestamp' ) && $date === $today ) {
			throw new Validation_Exception( 'That time has already passed.', array( 'time' => array( 'Choose a later time today.' ) ) );
		}
		if ( $settings['lunch_start'] && $settings['lunch_end']
			&& Availability::overlaps( $time, $end, $settings['lunch_start'], $settings['lunch_end'] ) ) {
			throw new Validation_Exception( 'That time is during the office break.', array( 'time' => array( 'Choose a time outside 12:00–13:00.' ) ) );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( 'START TRANSACTION' );

		try {
			$agent_conflicts = $this->repo->agent_conflicts( $agent_id, $date, $time, $end );
			if ( ! empty( $agent_conflicts ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->query( 'ROLLBACK' );
				throw new Validation_Exception(
					'That time is already booked.',
					array( 'time' => array( 'Choose another slot.' ) ),
					409
				);
			}
			$property_conflicts = $this->repo->property_conflicts( $property_id, $date, $time, $end );
			if ( ! empty( $property_conflicts ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->query( 'ROLLBACK' );
				throw new Validation_Exception(
					'A viewing is already scheduled at this property at that time.',
					array( 'time' => array( 'Choose another slot.' ) ),
					409
				);
			}

			$appointment_id = $this->repo->create(
				array(
					'property_id'    => $property_id,
					'agent_id'       => $agent_id,
					'customer_name'  => $name,
					'customer_email' => $email,
					'customer_phone' => $phone,
					'requested_date' => $date,
					'start_time'     => $time,
					'end_time'       => $end,
					'status'         => Appointment_Status::PENDING,
					'source'         => $public ? 'website' : 'staff',
					'notes'          => $notes,
					'created_by'     => $public ? 0 : get_current_user_id(),
				)
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( 'COMMIT' );
		} catch ( Validation_Exception $e ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( 'ROLLBACK' );
			throw $e;
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( 'ROLLBACK' );
			throw $e;
		}

		$capture = $this->leads->capture(
			array(
				'full_name'    => $name,
				'email'        => $email,
				'phone'        => $phone,
				'property_id'  => $property_id,
				'message'      => $notes ?: 'Viewing requested.',
				'utm_source'   => sanitize_text_field( (string) ( $input['utm_source'] ?? '' ) ),
				'utm_medium'   => sanitize_text_field( (string) ( $input['utm_medium'] ?? '' ) ),
				'utm_campaign' => sanitize_text_field( (string) ( $input['utm_campaign'] ?? '' ) ),
			),
			'appointment',
			false
		);

		$this->repo->update( $appointment_id, array( 'lead_id' => (int) $capture['lead_id'] ) );

		$appointment = $this->repo->find( $appointment_id );
		$shape       = $appointment ? $this->repo->to_array( $appointment ) : array();

		$this->activity->log(
			'appointment.created',
			'appointment',
			$appointment_id,
			sprintf( 'Viewing %s requested for %s %s.', $shape['public_ref'] ?? '', $date, $time ),
			array( 'property_id' => $property_id, 'agent_id' => $agent_id )
		);
		$this->notifications->appointment_requested( $appointment_id, $shape );

		do_action( 'estora/appointment/created', $appointment_id, $shape );

		return $shape;
	}

	/**
	 * Change appointment status.
	 *
	 * @param int    $id             Appointment.
	 * @param string $status         Target.
	 * @param string $internal_notes Optional note.
	 * @param int    $agent_id       Optional agent assignment.
	 * @return array<string,mixed>
	 */
	public function change_status( int $id, string $status, string $internal_notes = '', int $agent_id = 0 ): array {
		$this->require_cap();
		$appointment = $this->repo->find( $id );
		if ( ! $appointment || ! $this->can_access( $appointment ) ) {
			throw new Validation_Exception( 'Appointment not found.', array(), 404 );
		}

		$status = sanitize_key( $status );
		$from   = (string) $appointment['status'];
		Appointment_Status::assert_transition( $from, $status );

		$update = array( 'status' => $status );

		if ( $internal_notes ) {
			$update['internal_notes'] = sanitize_textarea_field( $internal_notes );
		}

		if ( ! empty( $agent_id ) && $agent_id !== (int) $appointment['agent_id'] ) {
			if ( ! get_userdata( $agent_id ) ) {
				throw new Validation_Exception( 'Agent not found.', array( 'agent_id' => array( 'Choose an existing agent.' ) ) );
			}
			$update['agent_id'] = $agent_id;
		}

		$agent = (int) ( $update['agent_id'] ?? $appointment['agent_id'] );
		$date  = (string) $appointment['requested_date'];
		$start = substr( (string) $appointment['start_time'], 0, 5 );
		$end   = substr( (string) $appointment['end_time'], 0, 5 );

		if ( Appointment_Status::CONFIRMED === $status || Appointment_Status::RESCHEDULED === $status ) {
			if ( ! $agent ) {
				throw new Validation_Exception( 'Assign an agent before confirming.', array( 'agent_id' => array( 'Choose an agent.' ) ) );
			}
			if ( $date < current_time( 'Y-m-d' ) ) {
				throw new Validation_Exception( 'A past appointment cannot be confirmed.', array(), 422 );
			}
			$conflicts = $this->repo->agent_conflicts( $agent, $date, $start, $end, $id );
			if ( ! empty( $conflicts ) ) {
				throw new Validation_Exception( 'The agent already has a viewing in that slot.', array( 'time' => array( 'Reschedule to a free slot.' ) ), 409 );
			}
			$property_conflicts = $this->repo->property_conflicts( (int) $appointment['property_id'], $date, $start, $end, $id );
			if ( ! empty( $property_conflicts ) ) {
				throw new Validation_Exception( 'Another viewing is booked at this property in that slot.', array(), 409 );
			}
			$update['active_key'] = $this->repo->active_key( $agent, $date, $start );
		} else {
			$update['active_key'] = null;
		}

		$this->repo->update( $id, $update );

		$this->activity->log(
			'appointment.status',
			'appointment',
			$id,
			sprintf( 'Viewing %s moved from %s to %s.', $appointment['public_ref'], Appointment_Status::label( $from ), Appointment_Status::label( $status ) ),
			array( 'from' => $from, 'to' => $status )
		);
		$this->notifications->appointment_status( $id, $status, $agent );

		/**
		 * Fires after an appointment status change.
		 *
		 * @param int    $id   Appointment ID.
		 * @param string $from Previous.
		 * @param string $to   New.
		 */
		do_action( 'estora/appointment/status_changed', $id, $from, $status );

		$fresh = $this->repo->find( $id );
		return $fresh ? $this->repo->to_array( $fresh ) : array();
	}

	/**
	 * Reschedule to a new slot.
	 *
	 * @param int    $id       Appointment.
	 * @param string $date     New date.
	 * @param string $time     New start.
	 * @param int    $agent_id Optional agent.
	 * @param string $reason   Reason.
	 * @return array<string,mixed>
	 */
	public function reschedule( int $id, string $date, string $time, int $agent_id = 0, string $reason = '' ): array {
		$this->require_cap();
		$appointment = $this->repo->find( $id );
		if ( ! $appointment || ! $this->can_access( $appointment ) ) {
			throw new Validation_Exception( 'Appointment not found.', array(), 404 );
		}
		if ( ! Appointment_Status::is_active( (string) $appointment['status'] ) ) {
			throw new Validation_Exception( 'Only an active appointment can be rescheduled.', array(), 422 );
		}

		Validator::assert(
			array( 'date' => $date, 'time' => $time ),
			array(
				'date' => array( 'required', 'date', 'date_not_past:' . current_time( 'Y-m-d' ) ),
				'time' => array( 'required', 'time' ),
			),
			'Please check the new time.'
		);

		$agent = $agent_id ?: (int) $appointment['agent_id'];
		if ( ! $agent ) {
			$agent = $this->choose_agent( $date );
		}
		if ( ! $agent ) {
			throw new Validation_Exception( 'No agent is available.', array(), 409 );
		}

		$window = Settings::business_hours()[ (int) gmdate( 'w', strtotime( $date . ' 12:00:00' ) ) ] ?? null;
		if ( null === $window ) {
			throw new Validation_Exception( 'The office is closed on that day.', array( 'date' => array( 'Choose a business day.' ) ) );
		}

		$duration = (int) estora_setting( 'slot_minutes', 45 );
		$end      = Availability::end_time( $time, $duration );
		if ( Availability::to_minutes( $time ) < Availability::to_minutes( $window[0] )
			|| Availability::to_minutes( $end ) > Availability::to_minutes( $window[1] ) ) {
			throw new Validation_Exception( 'That time is outside business hours.', array( 'time' => array( 'Choose a time inside business hours.' ) ) );
		}
		if ( $this->repo->agent_conflicts( $agent, $date, $time, $end, $id ) ) {
			throw new Validation_Exception( 'The agent already has a viewing in that slot.', array( 'time' => array( 'Choose another slot.' ) ), 409 );
		}
		if ( $this->repo->property_conflicts( (int) $appointment['property_id'], $date, $time, $end, $id ) ) {
			throw new Validation_Exception( 'Another viewing is booked at this property in that slot.', array(), 409 );
		}

		$this->repo->update(
			$id,
			array(
				'requested_date' => $date,
				'start_time'     => $time,
				'end_time'       => $end,
				'agent_id'       => $agent,
				'status'         => Appointment_Status::RESCHEDULED,
				'active_key'     => $this->repo->active_key( $agent, $date, $time ),
				'internal_notes' => trim( (string) $appointment['internal_notes'] . "\n" . sprintf( '[%s] Rescheduled to %s %s by %s. %s', estora_format_datetime( estora_now() ), $date, $time, wp_get_current_user()->display_name, sanitize_text_field( $reason ) ) ),
			)
		);

		$this->activity->log(
			'appointment.rescheduled',
			'appointment',
			$id,
			sprintf( 'Viewing %s rescheduled to %s %s.', $appointment['public_ref'], $date, $time )
		);
		$this->notifications->appointment_status( $id, Appointment_Status::RESCHEDULED, $agent );

		$fresh = $this->repo->find( $id );
		return $fresh ? $this->repo->to_array( $fresh ) : array();
	}

	/**
	 * Update customer/agent details without changing the slot.
	 *
	 * @param int                 $id   Appointment.
	 * @param array<string,mixed> $data Fields.
	 * @return array<string,mixed>
	 */
	public function update_details( int $id, array $data ): array {
		$this->require_cap();
		$appointment = $this->repo->find( $id );
		if ( ! $appointment || ! $this->can_access( $appointment ) ) {
			throw new Validation_Exception( 'Appointment not found.', array(), 404 );
		}

		$update = array();
		foreach ( array( 'customer_name', 'customer_email', 'customer_phone', 'notes', 'internal_notes' ) as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$value            = (string) $data[ $field ];
				$update[ $field ] = in_array( $field, array( 'notes', 'internal_notes' ), true )
					? sanitize_textarea_field( $value )
					: sanitize_text_field( $value );
			}
		}
		if ( isset( $data['customer_email'] ) && $update['customer_email'] && ! is_email( $update['customer_email'] ) ) {
			throw new Validation_Exception( 'Validation failed.', array( 'customer_email' => array( 'Enter a valid email address.' ) ) );
		}
		if ( isset( $data['agent_id'] ) ) {
			$agent_id = absint( $data['agent_id'] );
			if ( $agent_id && ! get_userdata( $agent_id ) ) {
				throw new Validation_Exception( 'Agent not found.', array( 'agent_id' => array( 'Choose an existing agent.' ) ) );
			}
			if ( $agent_id !== (int) $appointment['agent_id'] ) {
				$conflicts = $this->repo->agent_conflicts(
					$agent_id,
					(string) $appointment['requested_date'],
					substr( (string) $appointment['start_time'], 0, 5 ),
					substr( (string) $appointment['end_time'], 0, 5 ),
					$id
				);
				if ( ! empty( $conflicts ) ) {
					throw new Validation_Exception( 'That agent is already booked in this slot.', array( 'agent_id' => array( 'Choose another agent.' ) ), 409 );
				}
				$update['agent_id'] = $agent_id;
				if ( Appointment_Status::is_active( (string) $appointment['status'] ) && $agent_id ) {
					$update['active_key'] = $this->repo->active_key( $agent_id, (string) $appointment['requested_date'], substr( (string) $appointment['start_time'], 0, 5 ) );
				}
			}
		}

		if ( empty( $update ) ) {
			throw new Validation_Exception( 'Nothing to update.', array(), 422 );
		}

		$this->repo->update( $id, $update );
		$this->activity->log( 'appointment.updated', 'appointment', $id, 'Appointment details updated.' );

		$fresh = $this->repo->find( $id );
		return $fresh ? $this->repo->to_array( $fresh ) : array();
	}

	/**
	 * Detail with scope enforcement.
	 *
	 * @param int $id Appointment.
	 * @return array<string,mixed>
	 */
	public function detail( int $id ): array {
		$appointment = $this->repo->find( $id );
		if ( ! $appointment || ! $this->can_access( $appointment ) ) {
			throw new Validation_Exception( 'Appointment not found.', array(), 404 );
		}
		return $this->repo->to_array( $appointment );
	}

	/**
	 * Whether the current user can view an appointment.
	 *
	 * @param array<string,mixed> $appointment Row.
	 */
	public function can_access( array $appointment ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( $this->can_view_all() ) {
			return true;
		}
		return (int) ( $appointment['agent_id'] ?? 0 ) === get_current_user_id();
	}

	/**
	 * Whether the current user sees every appointment.
	 */
	public function can_view_all(): bool {
		return current_user_can( Capabilities::VIEW_ALL_LEADS )
			|| current_user_can( Capabilities::EDIT_PROPERTIES )
			|| current_user_can( Capabilities::MANAGE_SETTINGS );
	}

	/**
	 * Scope filter for list queries.
	 *
	 * @return int Agent ID to filter by, 0 for all.
	 */
	public function scope_agent_id(): int {
		return $this->can_view_all() ? 0 : get_current_user_id();
	}

	/**
	 * Capability guard.
	 */
	private function require_cap(): void {
		if ( ! current_user_can( Capabilities::MANAGE_APPOINTMENTS ) ) {
			throw new Validation_Exception( 'You are not allowed to manage appointments.', array(), 403 );
		}
	}

	/**
	 * Repository accessor.
	 */
	public function repository(): Appointment_Repository {
		return $this->repo;
	}
}
