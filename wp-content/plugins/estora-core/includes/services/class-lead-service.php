<?php
/**
 * Lead business rules: capture, dedupe, assignment, pipeline, notes.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Services;

use Estora\Core\Domain\Lead_Status;
use Estora\Core\Repositories\Activity_Repository;
use Estora\Core\Repositories\Lead_Repository;
use Estora\Core\Support\Capabilities;
use Estora\Core\Support\Formatter;
use Estora\Core\Support\Validation_Exception;
use Estora\Core\Support\Validator;

defined( 'ABSPATH' ) || exit;

final class Lead_Service {

	private Lead_Repository $leads;
	private Activity_Repository $activity;
	private Notification_Service $notifications;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->leads         = new Lead_Repository();
		$this->activity      = new Activity_Repository();
		$this->notifications = new Notification_Service();
	}

	/**
	 * Public lead capture used by inquiries, appointments and external clients.
	 *
	 * @param array<string,mixed> $input           Raw input.
	 * @param string              $source          Source key.
	 * @param bool                $require_consent Require explicit consent.
	 * @return array{lead_id:int,duplicate:bool,public_ref:string,lead:array<string,mixed>}
	 * @throws Validation_Exception On invalid input or unavailable property.
	 */
	public function capture( array $input, string $source = 'website', bool $require_consent = false ): array {
		$data                       = $this->sanitize_contact( $input );
		$data['message']            = mb_substr( sanitize_textarea_field( (string) ( $input['message'] ?? '' ) ), 0, 5000 );
		$data['preferred_location'] = sanitize_text_field( (string) ( $input['preferred_location'] ?? '' ) );
		$data['source']             = sanitize_key( $source );

		$rules = array(
			'full_name' => array( 'required', 'max_length:150' ),
			'email'     => array( 'email', 'max_length:190' ),
			'phone'     => array( 'phone', 'max_length:30' ),
			'message'   => array( 'max_length:5000' ),
		);
		Validator::assert( $data, $rules, 'Please check the contact details.' );

		if ( empty( $data['email'] ) && empty( $data['phone'] ) ) {
			throw new Validation_Exception(
				'A phone number or email address is required.',
				array( 'phone' => array( 'Enter a phone number or an email address.' ) )
			);
		}

		if ( $require_consent && ! Validator::to_bool( $input['consent'] ?? false ) ) {
			throw new Validation_Exception(
				'Consent is required.',
				array( 'consent' => array( 'Please agree to be contacted about this property.' ) )
			);
		}

		$property_id = absint( $input['property_id'] ?? 0 );
		if ( $property_id ) {
			$property = get_post( $property_id );
			if ( ! $property || 'property' !== $property->post_type || 'publish' !== $property->post_status ) {
				throw new Validation_Exception( 'Property not found.', array( 'property_id' => array( 'That property is not available.' ) ), 404 );
			}
			if ( ! estora_accepts_inquiries( $property_id ) ) {
				throw new Validation_Exception(
					'This property is no longer accepting inquiries.',
					array( 'property_id' => array( 'This property is sold, rented or unavailable.' ) ),
					409
				);
			}
		}

		$contact    = $data['phone'] ? Formatter::normalize_phone( $data['phone'] ) : strtolower( $data['email'] );
		$dedupe_key = hash( 'sha256', $contact . '|' . $property_id );

		$existing = $this->leads->find_by_dedupe( $dedupe_key, estora_now( '-30 days' ) );
		if ( $existing ) {
			if ( $data['message'] ) {
				$this->leads->add_note(
					(int) $existing['id'],
					0,
					sprintf( 'Repeat inquiry (%s): %s', $data['source'], $data['message'] ),
					'inquiry'
				);
			}
			$this->leads->update( (int) $existing['id'], array( 'last_contact_at' => estora_now() ) );
			$this->activity->log( 'lead.duplicate', 'lead', (int) $existing['id'], 'Repeat inquiry merged into existing lead.' );

			return array(
				'lead_id'    => (int) $existing['id'],
				'duplicate'  => true,
				'public_ref' => (string) $existing['public_ref'],
				'lead'       => $this->leads->to_array( $existing ),
			);
		}

		$agent_id = absint( $input['assigned_agent_id'] ?? 0 );
		if ( ! $agent_id && $property_id ) {
			$agent_id = (int) estora_meta( $property_id, 'agent_id', 0 );
		}
		if ( ! $agent_id ) {
			$agent_id = (int) estora_setting( 'default_agent_id', 0 );
		}
		if ( $agent_id && ! $this->is_assignable_agent( $agent_id ) ) {
			$agent_id = 0;
		}

		$row = array(
			'full_name'         => $data['full_name'],
			'email'             => $data['email'],
			'phone'             => $data['phone'],
			'preferred_contact' => in_array( $data['preferred_contact'], array( 'phone', 'email', 'whatsapp', 'line' ), true ) ? $data['preferred_contact'] : 'phone',
			'property_id'       => $property_id,
			'message'           => $data['message'],
			'source'            => $data['source'],
			'utm_source'        => sanitize_text_field( (string) ( $input['utm_source'] ?? '' ) ),
			'utm_medium'        => sanitize_text_field( (string) ( $input['utm_medium'] ?? '' ) ),
			'utm_campaign'      => sanitize_text_field( (string) ( $input['utm_campaign'] ?? '' ) ),
			'priority'          => 'normal',
			'assigned_agent_id' => $agent_id,
			'dedupe_key'        => $dedupe_key,
			'status'            => Lead_Status::NEW,
		);

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( 'START TRANSACTION' );

		try {
			$lead_id = $this->leads->create( $row );
			if ( $data['message'] ) {
				$this->leads->add_note( $lead_id, 0, $data['message'], 'inquiry', true );
			}
			if ( $agent_id ) {
				$this->leads->add_assignment( $lead_id, 0, $agent_id, 0, 'Automatic assignment on capture' );
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( 'ROLLBACK' );
			throw $e;
		}

		$lead = $this->leads->find( $lead_id );
		$out  = $lead ? $this->leads->to_array( $lead ) : array();

		$this->activity->log(
			'lead.created',
			'lead',
			$lead_id,
			sprintf( 'Lead %s captured from %s.', $out['public_ref'] ?? (string) $lead_id, $data['source'] ),
			array(
				'source'      => $data['source'],
				'property_id' => $property_id,
			)
		);
		$this->notifications->new_lead( $lead_id, $out );

		/**
		 * Fires after a lead is captured.
		 *
		 * @param int   $lead_id Lead ID.
		 * @param array $out     Lead data.
		 */
		do_action( 'estora/lead/created', $lead_id, $out );

		return array(
			'lead_id'    => $lead_id,
			'duplicate'  => false,
			'public_ref' => (string) ( $out['public_ref'] ?? '' ),
			'lead'       => $out,
		);
	}

	/**
	 * Staff-created lead.
	 *
	 * @param array<string,mixed> $input Payload.
	 * @return array<string,mixed>
	 */
	public function create_manual( array $input ): array {
		$this->require_cap( Capabilities::CREATE_LEADS );

		$data = $this->sanitize_contact( $input );
		Validator::assert(
			$data,
			array(
				'full_name' => array( 'required', 'max_length:150' ),
				'email'     => array( 'email', 'max_length:190' ),
				'phone'     => array( 'phone', 'max_length:30' ),
			),
			'Please check the contact details.'
		);
		if ( empty( $data['email'] ) && empty( $data['phone'] ) ) {
			throw new Validation_Exception( 'A phone number or email address is required.', array( 'phone' => array( 'Enter a phone or email.' ) ) );
		}

		$agent_id = absint( $input['assigned_agent_id'] ?? 0 );
		if ( $agent_id && ! $this->is_assignable_agent( $agent_id ) ) {
			throw new Validation_Exception( 'The selected agent is not active.', array( 'assigned_agent_id' => array( 'Choose an active agent.' ) ) );
		}

		$priority = sanitize_key( (string) ( $input['priority'] ?? 'normal' ) );
		if ( ! in_array( $priority, array( 'low', 'normal', 'high', 'urgent' ), true ) ) {
			$priority = 'normal';
		}

		$property_id = absint( $input['property_id'] ?? 0 );
		if ( $property_id && ! get_post( $property_id ) ) {
			throw new Validation_Exception( 'Property not found.', array( 'property_id' => array( 'Choose an existing property.' ) ), 404 );
		}

		$row = array(
			'full_name'         => $data['full_name'],
			'email'             => $data['email'],
			'phone'             => $data['phone'],
			'preferred_contact' => 'phone',
			'property_id'       => $property_id,
			'budget_min'        => isset( $input['budget_min'] ) ? (float) $input['budget_min'] : null,
			'budget_max'        => isset( $input['budget_max'] ) ? (float) $input['budget_max'] : null,
			'preferred_location' => sanitize_text_field( (string) ( $input['preferred_location'] ?? '' ) ),
			'message'           => sanitize_textarea_field( (string) ( $input['message'] ?? '' ) ),
			'source'            => 'manual',
			'priority'          => $priority,
			'assigned_agent_id' => $agent_id,
			'next_follow_up_at' => $this->parse_follow_up( (string) ( $input['next_follow_up_at'] ?? '' ) ),
			'status'            => Lead_Status::NEW,
		);

		$lead_id = $this->leads->create( $row );
		if ( $agent_id ) {
			$this->leads->add_assignment( $lead_id, 0, $agent_id, get_current_user_id(), 'Assigned on creation' );
			$this->notifications->lead_assigned( $lead_id, $agent_id, get_current_user_id() );
		}
		$this->activity->log( 'lead.created', 'lead', $lead_id, 'Lead created manually.' );

		$lead = $this->leads->find( $lead_id );
		return $lead ? $this->decorate( $lead ) : array();
	}

	/**
	 * Sanitised contact fields.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,string>
	 */
	private function sanitize_contact( array $input ): array {
		return array(
			'full_name'         => mb_substr( sanitize_text_field( (string) ( $input['full_name'] ?? $input['name'] ?? '' ) ), 0, 150 ),
			'email'             => sanitize_email( (string) ( $input['email'] ?? '' ) ),
			'phone'             => mb_substr( sanitize_text_field( (string) ( $input['phone'] ?? '' ) ), 0, 30 ),
			'preferred_contact' => sanitize_key( (string) ( $input['preferred_contact'] ?? 'phone' ) ),
		);
	}

	/**
	 * Lead with notes, history and allowed transitions.
	 *
	 * @param int $id Lead.
	 * @return array<string,mixed>
	 * @throws Validation_Exception When missing or out of scope.
	 */
	public function get( int $id ): array {
		$lead = $this->leads->find( $id );
		if ( ! $lead || ! $this->can_access( $lead ) ) {
			throw new Validation_Exception( 'Lead not found.', array(), 404 );
		}
		return $this->decorate( $lead );
	}

	/**
	 * Add derived fields.
	 *
	 * @param array<string,mixed> $lead Row.
	 * @return array<string,mixed>
	 */
	private function decorate( array $lead ): array {
		$out = $this->leads->to_array( $lead );
		return $out;
	}

	/**
	 * Update editable fields.
	 *
	 * @param int                 $id   Lead.
	 * @param array<string,mixed> $data Payload.
	 * @return array<string,mixed>
	 */
	public function update( int $id, array $data ): array {
		$this->require_cap( Capabilities::EDIT_LEADS );
		$lead = $this->leads->find( $id );
		if ( ! $lead || ! $this->can_access( $lead ) ) {
			throw new Validation_Exception( 'Lead not found.', array(), 404 );
		}
		if ( Lead_Status::WON === $lead['status'] ) {
			throw new Validation_Exception( 'A won lead can no longer be edited.', array(), 422 );
		}

		$clean = array();
		if ( isset( $data['full_name'] ) ) {
			$clean['full_name'] = sanitize_text_field( (string) $data['full_name'] );
		}
		if ( isset( $data['email'] ) ) {
			$clean['email'] = sanitize_email( (string) $data['email'] );
			if ( $clean['email'] && ! is_email( $clean['email'] ) ) {
				throw new Validation_Exception( 'Validation failed.', array( 'email' => array( 'Enter a valid email address.' ) ) );
			}
		}
		if ( isset( $data['phone'] ) ) {
			$clean['phone'] = sanitize_text_field( (string) $data['phone'] );
		}
		if ( isset( $data['preferred_location'] ) ) {
			$clean['preferred_location'] = sanitize_text_field( (string) $data['preferred_location'] );
		}
		foreach ( array( 'budget_min', 'budget_max' ) as $money ) {
			if ( isset( $data[ $money ] ) ) {
				$clean[ $money ] = (float) $data[ $money ];
			}
		}
		if ( isset( $data['priority'] ) ) {
			$priority = sanitize_key( (string) $data['priority'] );
			if ( ! in_array( $priority, array( 'low', 'normal', 'high', 'urgent' ), true ) ) {
				throw new Validation_Exception( 'Validation failed.', array( 'priority' => array( 'Choose a valid priority.' ) ) );
			}
			$clean['priority'] = $priority;
		}
		if ( array_key_exists( 'next_follow_up_at', $data ) ) {
			$clean['next_follow_up_at'] = $this->parse_follow_up( (string) $data['next_follow_up_at'] );
		}

		if ( empty( $clean ) ) {
			throw new Validation_Exception( 'Nothing to update.', array(), 422 );
		}

		$this->leads->update( $id, $clean );
		$this->activity->log( 'lead.updated', 'lead', $id, 'Lead details updated.' );
		$fresh = $this->leads->find( $id );
		return $fresh ? $this->decorate( $fresh ) : array();
	}

	/**
	 * Assign or reassign a lead.
	 *
	 * @param int    $id       Lead.
	 * @param int    $agent_id Agent.
	 * @param string $reason   Reason.
	 * @return array<string,mixed>
	 */
	public function assign( int $id, int $agent_id, string $reason = '' ): array {
		$this->require_cap( Capabilities::ASSIGN_LEADS );
		$lead = $this->leads->find( $id );
		if ( ! $lead ) {
			throw new Validation_Exception( 'Lead not found.', array(), 404 );
		}
		if ( ! $this->is_assignable_agent( $agent_id ) ) {
			throw new Validation_Exception( 'The selected agent is not active.', array( 'agent_id' => array( 'Choose an active sales agent.' ) ) );
		}

		$from = (int) ( $lead['assigned_agent_id'] ?? 0 );
		$this->leads->update( $id, array( 'assigned_agent_id' => $agent_id ) );
		$this->leads->add_assignment( $id, $from, $agent_id, get_current_user_id(), sanitize_text_field( $reason ) );
		$this->activity->log(
			'lead.assigned',
			'lead',
			$id,
			sprintf( 'Lead reassigned from %s to %s.', $from ? estora_agent_name( $from ) : 'nobody', estora_agent_name( $agent_id ) ),
			array( 'from' => $from, 'to' => $agent_id )
		);
		$this->notifications->lead_assigned( $id, $agent_id, get_current_user_id() );

		$fresh = $this->leads->find( $id );
		return $fresh ? $this->decorate( $fresh ) : array();
	}

	/**
	 * Add a note.
	 *
	 * @param int    $id       Lead.
	 * @param string $body     Note body.
	 * @param string $type     Note type.
	 * @param bool   $internal Internal only.
	 * @return array<string,mixed>
	 */
	public function add_note( int $id, string $body, string $type = 'note', bool $internal = false ): array {
		$this->require_cap( Capabilities::EDIT_LEADS );
		$lead = $this->leads->find( $id );
		if ( ! $lead || ! $this->can_access( $lead ) ) {
			throw new Validation_Exception( 'Lead not found.', array(), 404 );
		}

		$body = sanitize_textarea_field( $body );
		Validator::assert(
			array( 'body' => $body, 'note_type' => $type ),
			array(
				'body'      => array( 'required', 'max_length:5000' ),
				'note_type' => array( 'in:note,call,email,meeting,status' ),
			),
			'Please enter a note.'
		);

		$this->leads->add_note( $id, get_current_user_id(), $body, $type, $internal );
		$this->leads->update( $id, array( 'last_contact_at' => estora_now() ) );
		$this->activity->log( 'lead.note', 'lead', $id, 'Note added to lead.' );

		$fresh = $this->leads->find( $id );
		return $fresh ? $this->decorate( $fresh ) : array();
	}

	/**
	 * Change pipeline status.
	 *
	 * @param int        $id        Lead.
	 * @param string     $status    Target.
	 * @param string     $note      Optional note.
	 * @param float|null $won_value Value when won.
	 * @return array<string,mixed>
	 */
	public function change_status( int $id, string $status, string $note = '', ?float $won_value = null ): array {
		$this->require_cap( Capabilities::EDIT_LEADS );
		$lead = $this->leads->find( $id );
		if ( ! $lead || ! $this->can_access( $lead ) ) {
			throw new Validation_Exception( 'Lead not found.', array(), 404 );
		}

		$status = sanitize_key( $status );
		$from   = (string) $lead['status'];
		Lead_Status::assert_transition( $from, $status );

		if ( Lead_Status::LOST === $from && Lead_Status::CONTACTED === $status && ! current_user_can( Capabilities::ASSIGN_LEADS ) ) {
			throw new Validation_Exception( 'Only a sales manager can reopen a lost lead.', array(), 403 );
		}

		$update = array(
			'status'         => $status,
			'last_contact_at' => estora_now(),
		);
		if ( Lead_Status::WON === $status && null !== $won_value ) {
			$update['won_value'] = $won_value;
		}
		$this->leads->update( $id, $update );

		if ( Lead_Status::WON === $status || Lead_Status::LOST === $status ) {
			$this->leads->update( $id, array( 'next_follow_up_at' => null ) );
		}

		$note_text = $note ? sanitize_textarea_field( $note ) : sprintf( 'Status changed from %s to %s.', Lead_Status::label( $from ), Lead_Status::label( $status ) );
		$this->leads->add_note( $id, get_current_user_id(), $note_text, 'status', true );
		$this->activity->log(
			'lead.status',
			'lead',
			$id,
			sprintf( 'Lead moved from %s to %s.', Lead_Status::label( $from ), Lead_Status::label( $status ) ),
			array( 'from' => $from, 'to' => $status )
		);

		$agent = (int) ( $lead['assigned_agent_id'] ?? 0 );
		if ( $agent && $agent !== get_current_user_id() ) {
			$this->notifications->dispatch(
				$agent,
				'lead.status',
				sprintf( 'Lead %s moved to %s', $lead['public_ref'], Lead_Status::label( $status ) ),
				$note_text,
				admin_url( 'admin.php?page=estora-lead&id=' . $id ),
				'lead.status:' . $id . ':' . $status . ':' . estora_now(),
				false
			);
		}

		do_action( 'estora/lead/status_changed', $id, $from, $status, $lead );

		$fresh = $this->leads->find( $id );
		return $fresh ? $this->decorate( $fresh ) : array();
	}

	/**
	 * Full detail payload.
	 *
	 * @param int $id Lead.
	 * @return array<string,mixed>
	 */
	public function detail( int $id ): array {
		$lead = $this->leads->find( $id );
		if ( ! $lead || ! $this->can_access( $lead ) ) {
			throw new Validation_Exception( 'Lead not found.', array(), 404 );
		}

		$out                 = $this->leads->to_array( $lead );
		$out['notes']        = $this->leads->notes( $id );
		$out['assignments']  = $this->leads->assignments( $id );
		$out['timeline']     = $this->activity->for_object( 'lead', $id, 20 );
		$out['allowed_next'] = Lead_Status::transitions()[ $out['status'] ] ?? array();
		$out['appointments'] = $this->appointments_for_lead( $id );

		return $out;
	}

	/**
	 * Appointments linked to a lead.
	 *
	 * @param int $lead_id Lead.
	 * @return array<int,array<string,mixed>>
	 */
	private function appointments_for_lead( int $lead_id ): array {
		$repo   = new \Estora\Core\Repositories\Appointment_Repository();
		$result = $repo->query( array( 'lead_id' => $lead_id, 'per_page' => 100 ) );
		return $result['items'];
	}

	/**
	 * Delete a lead (manager only).
	 *
	 * @param int $id Lead.
	 */
	public function delete( int $id ): bool {
		$this->require_cap( Capabilities::DELETE_LEADS );
		$lead = $this->leads->find( $id );
		if ( ! $lead ) {
			throw new Validation_Exception( 'Lead not found.', array(), 404 );
		}
		$ok = $this->leads->delete( $id );
		if ( $ok ) {
			$this->activity->log( 'lead.deleted', 'lead', $id, 'Lead deleted.' );
		}
		return $ok;
	}

	/**
	 * Whether the current user may touch this lead.
	 *
	 * @param array<string,mixed> $lead Lead row.
	 */
	public function can_access( array $lead ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( Capabilities::VIEW_ALL_LEADS ) ) {
			return true;
		}
		return (int) ( $lead['assigned_agent_id'] ?? 0 ) === get_current_user_id();
	}

	/**
	 * Agent scope for list queries.
	 *
	 * @return int 0 means all.
	 */
	public function scope_agent_id(): int {
		if ( current_user_can( Capabilities::VIEW_ALL_LEADS ) ) {
			return 0;
		}
		return get_current_user_id();
	}

	/**
	 * Lead statistics.
	 *
	 * @param int $days Window.
	 * @return array<string,mixed>
	 */
	public function stats( int $days = 30 ): array {
		$counts = $this->leads->status_counts( $this->scope_agent_id() );
		$total  = array_sum( $counts );
		$won    = $counts[ Lead_Status::WON ] ?? 0;

		return array(
			'counts'         => $counts,
			'total'          => $total,
			'won'            => $won,
			'lost'           => $counts[ Lead_Status::LOST ] ?? 0,
			'conversion_rate' => $total > 0 ? round( ( $won / $total ) * 100, 1 ) : 0.0,
			'by_source'      => $this->leads->source_counts(),
			'by_agent'       => $this->leads->agent_counts(),
			'daily'          => $this->leads->daily_series( $days, $this->scope_agent_id() ),
		);
	}

	/**
	 * Allowed agent check.
	 *
	 * @param int $user_id User.
	 */
	public function is_assignable_agent( int $user_id ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user || '0' === (string) get_user_meta( $user_id, 'estora_active', true ) ) {
			return false;
		}
		return user_can( $user_id, Capabilities::EDIT_LEADS ) || user_can( $user_id, 'manage_options' );
	}

	/**
	 * Parse a site-local follow-up input to UTC.
	 *
	 * @param string $value Y-m-d\TH:i or Y-m-d H:i.
	 */
	private function parse_follow_up( string $value ): ?string {
		if ( '' === trim( $value ) ) {
			return null;
		}
		$value = str_replace( 'T', ' ', $value );
		$value = substr( $value, 0, 19 );
		$ts    = strtotime( $value );
		if ( ! $ts ) {
			throw new Validation_Exception( 'Validation failed.', array( 'next_follow_up_at' => array( 'Enter a valid follow-up date and time.' ) ) );
		}
		return get_gmt_from_date( gmdate( 'Y-m-d H:i:s', $ts ) );
	}

	/**
	 * Capability guard.
	 *
	 * @param string $cap Capability.
	 * @throws Validation_Exception When missing.
	 */
	private function require_cap( string $cap ): void {
		if ( ! current_user_can( $cap ) ) {
			throw new Validation_Exception( 'You are not allowed to do that.', array(), 403 );
		}
	}

	/**
	 * Repository accessor.
	 */
	public function repository(): Lead_Repository {
		return $this->leads;
	}
}
