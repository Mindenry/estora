<?php
/**
 * In-app notifications.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Repositories;

defined( 'ABSPATH' ) || exit;

final class Notification_Repository {

	/**
	 * Table.
	 */
	public function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'estora_notifications';
	}

	/**
	 * Create a notification; a dedupe key makes it idempotent.
	 *
	 * @param int    $user_id    Recipient.
	 * @param string $type       Event type.
	 * @param string $title      Title.
	 * @param string $body       Body.
	 * @param string $link       Admin link.
	 * @param string $dedupe_key Unique key or empty.
	 * @return int Notification ID (0 when deduped).
	 */
	public function create( int $user_id, string $type, string $title, string $body = '', string $link = '', string $dedupe_key = '' ): int {
		global $wpdb;

		if ( $dedupe_key ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$existing = (int) $wpdb->get_var(
				$wpdb->prepare( 'SELECT id FROM `' . esc_sql( $this->table() ) . '` WHERE dedupe_key = %s', $dedupe_key )
			);
			if ( $existing ) {
				return 0;
			}
			$dedupe_key = substr( hash( 'sha256', $dedupe_key ), 0, 64 );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$this->table(),
			array(
				'user_id'    => $user_id,
				'type'       => substr( sanitize_key( $type ), 0, 60 ),
				'title'      => mb_substr( sanitize_text_field( $title ), 0, 190 ),
				'body'       => sanitize_textarea_field( $body ),
				'link'       => esc_url_raw( $link ),
				'is_read'    => 0,
				'dedupe_key' => $dedupe_key ?: null,
				'created_at' => estora_now(),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * List for a user.
	 *
	 * @param int  $user_id User.
	 * @param bool $unread  Only unread.
	 * @param int  $limit   Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public function for_user( int $user_id, bool $unread = false, int $limit = 30 ): array {
		global $wpdb;
		$table = esc_sql( $this->table() );
		$sql   = "SELECT * FROM `{$table}` WHERE user_id = %d";
		$args  = array( $user_id );
		if ( $unread ) {
			$sql .= ' AND is_read = 0';
		}
		$sql   .= ' ORDER BY created_at DESC, id DESC LIMIT %d';
		$args[] = max( 1, min( 100, $limit ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );

		$out = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'id'         => (int) $row['id'],
				'type'       => $row['type'],
				'title'      => $row['title'],
				'body'       => $row['body'],
				'link'       => $row['link'],
				'is_read'    => (bool) (int) $row['is_read'],
				'created_at' => get_date_from_gmt( $row['created_at'], 'c' ),
			);
		}
		return $out;
	}

	/**
	 * Unread count.
	 *
	 * @param int $user_id User.
	 */
	public function unread_count( int $user_id ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM `' . esc_sql( $this->table() ) . '` WHERE user_id = %d AND is_read = 0', $user_id )
		);
	}

	/**
	 * Mark one as read, scoped to its owner.
	 *
	 * @param int $id      Notification ID.
	 * @param int $user_id Owner.
	 */
	public function mark_read( int $id, int $user_id ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->update(
			$this->table(),
			array( 'is_read' => 1 ),
			array(
				'id'      => $id,
				'user_id' => $user_id,
			),
			array( '%d' ),
			array( '%d', '%d' )
		);
	}

	/**
	 * Mark every notification as read.
	 *
	 * @param int $user_id Owner.
	 */
	public function mark_all_read( int $user_id ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $this->table(), array( 'is_read' => 1 ), array( 'user_id' => $user_id ), array( '%d' ), array( '%d' ) );
	}

	/**
	 * Delete read notifications older than N days.
	 *
	 * @param int $days Days.
	 */
	public function prune( int $days = 90 ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM `' . esc_sql( $this->table() ) . '` WHERE is_read = 1 AND created_at < %s',
				estora_now( '-' . max( 7, $days ) . ' days' )
			)
		);
	}
}
