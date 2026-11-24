<?php
/**
 * Notification fan-out: in-app rows plus optional email.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Services;

use Estora\Core\Integrations\Mailer;
use Estora\Core\Repositories\Notification_Repository;

defined( 'ABSPATH' ) || exit;

final class Notification_Service {

	/**
	 * Repository.
	 *
	 * @var Notification_Repository
	 */
	private Notification_Repository $repo;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repo = new Notification_Repository();
	}

	/**
	 * Dispatch to one user.
	 *
	 * @param int    $user_id    Recipient.
	 * @param string $type       Event type.
	 * @param string $title      Title.
	 * @param string $body       Body.
	 * @param string $link       Admin link.
	 * @param string $dedupe_key Dedupe key.
	 * @param bool   $email      Also send email.
	 * @return bool Whether an in-app row was created.
	 */
	public function dispatch( int $user_id, string $type, string $title, string $body = '', string $link = '', string $dedupe_key = '', bool $email = false ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		$created = $this->repo->create( $user_id, $type, $title, $body, $link, $dedupe_key );

		if ( $email && estora_setting( 'notify_email_enabled', true ) ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$recipient = estora_setting( 'notification_email', '' ) ?: $user->user_email;
				if ( $recipient ) {
					Mailer::send( $recipient, $title, array( $body, $link ? 'Open: ' . $link : '' ) );
				}
			}
		}

		return $created > 0;
	}

	/**
	 * Dispatch to every user holding a capability.
	 *
	 * @param string $capability Capability.
	 * @param string $type       Type.
	 * @param string $title      Title.
	 * @param string $body       Body.
	 * @param string $link       Link.
	 * @param string $dedupe_key Dedupe base key.
	 * @param bool   $email      Email too.
	 * @return int Recipients notified.
	 */
	public function dispatch_to_capability( string $capability, string $type, string $title, string $body = '', string $link = '', string $dedupe_key = '', bool $email = false ): int {
		$users = get_users(
			array(
				'capability' => $capability,
				'fields'     => 'ID',
				'number'     => 20,
			)
		);

		$count = 0;
		foreach ( $users as $user_id ) {
			$key = $dedupe_key ? $dedupe_key . ':' . (int) $user_id : '';
			if ( $this->dispatch( (int) $user_id, $type, $title, $body, $link, $key, $email ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * New lead arrived.
	 *
	 * @param int                 $lead_id Lead.
	 * @param array<string,mixed> $lead    Lead data.
	 */
	public function new_lead( int $lead_id, array $lead ): void {
		if ( ! estora_setting( 'notify_new_lead', true ) ) {
			return;
		}

		$agent = (int) ( $lead['assigned_agent_id'] ?? 0 );
		$title = sprintf( 'New lead %s — %s', $lead['public_ref'] ?? ( '#' . $lead_id ), $lead['full_name'] ?? '' );
		$body  = sprintf(
			"%s\nSource: %s\nProperty: %s",
			$lead['phone'] ?? $lead['email'] ?? '',
			$lead['source'] ?? 'website',
			! empty( $lead['property_id'] ) ? get_the_title( (int) $lead['property_id'] ) : 'General inquiry'
		);
		$link  = admin_url( 'admin.php?page=estora-lead&id=' . $lead_id );

		if ( $agent ) {
			$this->dispatch( $agent, 'lead.created', $title, $body, $link, 'lead.new:' . $lead_id, true );
		}

		$this->dispatch_to_capability( 'estora_view_all_leads', 'lead.created', $title, $body, $link, 'lead.new:' . $lead_id, true );
	}

	/**
	 * Lead reassigned.
	 *
	 * @param int $lead_id  Lead.
	 * @param int $agent_id New agent.
	 * @param int $actor_id Actor.
	 */
	public function lead_assigned( int $lead_id, int $agent_id, int $actor_id ): void {
		$actor = get_userdata( $actor_id );
		$this->dispatch(
			$agent_id,
			'lead.assigned',
			'Lead assigned to you',
			sprintf( 'Lead #%d was assigned by %s.', $lead_id, $actor ? $actor->display_name : 'the system' ),
			admin_url( 'admin.php?page=estora-lead&id=' . $lead_id ),
			'lead.assigned:' . $lead_id . ':' . $agent_id . ':' . estora_now(),
			true
		);
	}

	/**
	 * Appointment requested by the public.
	 *
	 * @param int                 $appointment_id Appointment.
	 * @param array<string,mixed> $appointment    Row.
	 */
	public function appointment_requested( int $appointment_id, array $appointment ): void {
		if ( ! estora_setting( 'notify_new_appointment', true ) ) {
			return;
		}
		$link  = admin_url( 'admin.php?page=estora-appointments&id=' . $appointment_id );
		$title = sprintf( 'Viewing request %s', $appointment['public_ref'] ?? '' );
		$body  = sprintf(
			"%s\n%s at %s\nProperty: %s",
			$appointment['customer_name'] ?? '',
			$appointment['date'] ?? '',
			$appointment['time'] ?? '',
			get_the_title( (int) ( $appointment['property_id'] ?? 0 ) )
		);

		if ( ! empty( $appointment['agent_id'] ) ) {
			$this->dispatch( (int) $appointment['agent_id'], 'appointment.requested', $title, $body, $link, 'appt.new:' . $appointment_id, true );
		}
		$this->dispatch_to_capability( 'estora_manage_appointments', 'appointment.requested', $title, $body, $link, 'appt.new:' . $appointment_id, false );
	}

	/**
	 * Appointment status changed.
	 *
	 * @param int    $id     Appointment.
	 * @param string $status New status.
	 * @param int    $agent  Agent.
	 */
	public function appointment_status( int $id, string $status, int $agent ): void {
		$link = admin_url( 'admin.php?page=estora-appointments&id=' . $id );
		if ( $agent ) {
			$this->dispatch(
				$agent,
				'appointment.status',
				sprintf( 'Viewing %s: %s', '#' . $id, str_replace( '_', ' ', $status ) ),
				'The appointment status changed.',
				$link,
				'appt.status:' . $id . ':' . $status,
				false
			);
		}
	}

	/**
	 * Current user's notifications.
	 *
	 * @param bool $unread Unread only.
	 * @param int  $limit  Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public function for_current_user( bool $unread = false, int $limit = 30 ): array {
		return $this->repo->for_user( get_current_user_id(), $unread, $limit );
	}

	/**
	 * Unread count for the current user.
	 */
	public function unread_count(): int {
		return $this->repo->unread_count( get_current_user_id() );
	}

	/**
	 * Repository accessor (admin screens).
	 */
	public function repository(): Notification_Repository {
		return $this->repo;
	}
}
