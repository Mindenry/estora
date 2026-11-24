<?php
/**
 * Cleanup when WordPress objects are deleted or users leave.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Integrations;

defined( 'ABSPATH' ) || exit;

use Estora\Core\Domain\Appointment_Status;
use Estora\Core\Repositories\Activity_Repository;

final class Lifecycle {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'before_delete_post', array( $this, 'before_delete_post' ) );
		add_action( 'deleted_user', array( $this, 'deleted_user' ) );
	}

	/**
	 * Preserve history when a property or attachment is deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public function before_delete_post( $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		if ( 'property' === $post->post_type ) {
			global $wpdb;

			$leads        = esc_sql( estora_table( 'leads' ) );
			$appointments = esc_sql( estora_table( 'appointments' ) );
			$inquiries    = esc_sql( estora_table( 'inquiries' ) );
			$favorites    = esc_sql( estora_table( 'favorites' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$leads}` SET property_id = NULL, updated_at = %s WHERE property_id = %d",
					estora_now(),
					$post_id
				)
			);

			// Active viewings are cancelled; the row keeps the historical attachment text in notes.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$appointments}` SET status = %s, active_key = NULL, internal_notes = CONCAT(COALESCE(internal_notes,''), %s), updated_at = %s
					 WHERE property_id = %d AND status IN ('pending','confirmed','rescheduled')",
					Appointment_Status::CANCELLED,
					"\n[system] Property deleted; viewing cancelled.",
					estora_now(),
					$post_id
				)
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $wpdb->prepare( "UPDATE `{$inquiries}` SET property_id = 0 WHERE property_id = %d", $post_id ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $wpdb->prepare( "DELETE FROM `{$favorites}` WHERE property_id = %d", $post_id ) );

			$activity = new Activity_Repository();
			$activity->log( 'property.deleted', 'property', (int) $post_id, 'Property deleted; related records detached.' );
			delete_transient( 'estora_dashboard_summary' );
			delete_transient( 'estora_filter_options' );
		}

		if ( 'attachment' === $post->post_type ) {
			$this->remove_attachment_from_galleries( (int) $post_id );
		}
	}

	/**
	 * Remove a deleted attachment ID from every property gallery.
	 *
	 * @param int $attachment_id Attachment.
	 */
	private function remove_attachment_from_galleries( int $attachment_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_estora_gallery' AND meta_value LIKE %s",
				'%' . $wpdb->esc_like( (string) $attachment_id ) . '%'
			),
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			$ids = maybe_unserialize( $row['meta_value'] );
			if ( ! is_array( $ids ) ) {
				continue;
			}
			$ids = array_values( array_filter( array_map( 'absint', $ids ), static fn( $id ) => $id !== $attachment_id ) );
			update_post_meta( (int) $row['post_id'], '_estora_gallery', $ids );
		}
	}

	/**
	 * Reassign or clear agent references when a user is deleted.
	 *
	 * @param int $user_id Deleted user ID.
	 */
	public function deleted_user( $user_id ): void {
		global $wpdb;

		$leads         = esc_sql( estora_table( 'leads' ) );
		$appointments  = esc_sql( estora_table( 'appointments' ) );
		$notifications = esc_sql( estora_table( 'notifications' ) );
		$favorites     = esc_sql( estora_table( 'favorites' ) );

		$fallback = (int) estora_setting( 'default_agent_id', 0 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$leads}` SET assigned_agent_id = NULL, updated_at = %s WHERE assigned_agent_id = %d",
				estora_now(),
				$user_id
			)
		);

		if ( $fallback ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$leads}` SET assigned_agent_id = %d WHERE assigned_agent_id IS NULL AND status IN ('new','contacted','qualified','negotiation')",
					$fallback
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "UPDATE `{$appointments}` SET agent_id = NULL, active_key = NULL WHERE agent_id = %d AND status IN ('pending','confirmed','rescheduled')", $user_id ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$notifications}` WHERE user_id = %d", $user_id ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$favorites}` WHERE user_id = %d", $user_id ) );

		$activity = new Activity_Repository();
		$activity->log( 'user.deleted', 'user', (int) $user_id, 'User deleted; records detached and favourites removed.' );
	}
}
