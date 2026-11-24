<?php
/**
 * Scheduled maintenance: follow-up reminders, stale viewings, pruning.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Integrations;

defined( 'ABSPATH' ) || exit;

use Estora\Core\Repositories\Lead_Repository;
use Estora\Core\Repositories\Notification_Repository;
use Estora\Core\Services\Notification_Service;

final class Cron {

	public const HOOK = 'estora_daily_maintenance';

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( self::HOOK, array( $this, 'run_daily' ) );
		add_action( 'init', array( $this, 'ensure_schedule' ) );
	}

	/**
	 * Ensure the event exists (activation may predate the schedule).
	 */
	public function ensure_schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 3600, 'daily', self::HOOK );
		}
	}

	/**
	 * Daily job.
	 */
	public function run_daily(): void {
		$this->remind_follow_ups();
		$this->flag_stale_appointments();
		( new Notification_Repository() )->prune( 90 );
		( new \Estora\Core\Repositories\Activity_Repository() )->log( 'system.maintenance', 'system', 0, 'Daily maintenance completed.' );
	}

	/**
	 * Notify agents about overdue follow-ups (one notification per lead per day).
	 */
	private function remind_follow_ups(): void {
		$repo   = new Lead_Repository();
		$notify = new Notification_Service();

		foreach ( $repo->overdue_follow_ups( 50 ) as $lead ) {
			$agent = (int) $lead['assigned_agent_id'];
			if ( ! $agent ) {
				continue;
			}
			$notify->dispatch(
				$agent,
				'lead.follow_up',
				sprintf( 'Follow-up due: %s', $lead['full_name'] ),
				sprintf( 'Lead %s was due for follow-up on %s.', $lead['public_ref'], estora_format_datetime( (string) $lead['next_follow_up_at'], 'M j, Y H:i' ) ),
				admin_url( 'admin.php?page=estora-lead&id=' . (int) $lead['id'] ),
				'followup:' . (int) $lead['id'] . ':' . current_time( 'Y-m-d' ),
				false
			);
		}
	}

	/**
	 * Notify managers about pending viewings older than 24 hours.
	 */
	private function flag_stale_appointments(): void {
		$repo   = new \Estora\Core\Repositories\Appointment_Repository();
		$notify = new Notification_Service();

		foreach ( $repo->stale_pending( 24 ) as $appointment ) {
			foreach ( get_users( array( 'capability' => \Estora\Core\Support\Capabilities::MANAGE_APPOINTMENTS, 'fields' => 'ID' ) ) as $user_id ) {
				$notify->dispatch(
					(int) $user_id,
					'appointment.stale',
					sprintf( 'Viewing request %s still pending', $appointment['public_ref'] ),
					sprintf( '%s requested %s %s for %s.', $appointment['customer_name'], $appointment['date'], $appointment['time'], $appointment['property_title'] ),
					admin_url( 'admin.php?page=estora-appointments&id=' . $appointment['id'] ),
					'appt.stale:' . $appointment['id'] . ':' . current_time( 'Y-m-d' ),
					false
				);
			}
		}
	}
}
