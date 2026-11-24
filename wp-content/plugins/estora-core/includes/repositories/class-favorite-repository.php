<?php
/**
 * Saved properties for guests and users.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Repositories;

use Estora\Core\Support\Validator;

defined( 'ABSPATH' ) || exit;

final class Favorite_Repository {

	/**
	 * Table.
	 */
	public function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'estora_favorites';
	}

	/**
	 * Owner key for a logged-in user.
	 *
	 * @param int $user_id User.
	 */
	public static function user_key( int $user_id ): string {
		return 'u:' . $user_id;
	}

	/**
	 * Owner key for a guest token. Returns '' when the token is invalid.
	 *
	 * @param string $token Hex token.
	 */
	public static function visitor_key( string $token ): string {
		$token = strtolower( trim( $token ) );
		if ( ! preg_match( '/^[a-f0-9]{32,64}$/', $token ) ) {
			return '';
		}
		return 'v:' . $token;
	}

	/**
	 * Add a favorite (idempotent).
	 *
	 * @param string      $owner_key Owner.
	 * @param int         $property_id Property.
	 * @param int|null    $user_id User.
	 * @param string|null $token Guest token.
	 */
	public function add( string $owner_key, int $property_id, ?int $user_id = null, ?string $token = null ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO `' . esc_sql( $this->table() ) . '` (owner_key, user_id, visitor_token, property_id, created_at) VALUES (%s, %d, %s, %d, %s)',
				$owner_key,
				$user_id ?: 0,
				$token,
				$property_id,
				estora_now()
			)
		);
		return false !== $result;
	}

	/**
	 * Remove a favorite.
	 *
	 * @param string $owner_key Owner.
	 * @param int    $property_id Property.
	 */
	public function remove( string $owner_key, int $property_id ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->delete(
			$this->table(),
			array(
				'owner_key'   => $owner_key,
				'property_id' => $property_id,
			),
			array( '%s', '%d' )
		);
	}

	/**
	 * Property IDs for an owner.
	 *
	 * @param string $owner_key Owner.
	 * @return int[]
	 */
	public function ids( string $owner_key ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT property_id FROM `' . esc_sql( $this->table() ) . '` WHERE owner_key = %s ORDER BY created_at DESC',
				$owner_key
			)
		);
		return array_map( 'intval', $ids ?: array() );
	}

	/**
	 * Count for an owner.
	 *
	 * @param string $owner_key Owner.
	 */
	public function count( string $owner_key ): int {
		return count( $this->ids( $owner_key ) );
	}

	/**
	 * Favorites for display, published only.
	 *
	 * @param string $owner_key Owner.
	 * @return array<int,array<string,mixed>>
	 */
	public function properties( string $owner_key ): array {
		$ids = $this->ids( $owner_key );
		if ( empty( $ids ) ) {
			return array();
		}

		$query = new \WP_Query(
			array(
				'post_type'      => Property_Repository::POST_TYPE,
				'post__in'       => $ids,
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, count( $ids ) ),
				'orderby'        => 'post__in',
				'no_found_rows'  => true,
			)
		);

		$repo  = new Property_Repository();
		$items = array();
		foreach ( $query->posts as $post ) {
			$data = $repo->to_array( $post->ID );
			if ( $data ) {
				$items[] = $data;
			}
		}
		return $items;
	}

	/**
	 * Delete every favorite pointing at a property.
	 *
	 * @param int $property_id Property.
	 */
	public function delete_for_property( int $property_id ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $this->table(), array( 'property_id' => $property_id ), array( '%d' ) );
	}

	/**
	 * Delete favorites for a removed user.
	 *
	 * @param int $user_id User.
	 */
	public function delete_for_user( int $user_id ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $this->table(), array( 'user_id' => $user_id ), array( '%d' ) );
	}

	/**
	 * Resolve an owner key from the request context.
	 *
	 * @param string $visitor_token Raw token from header/cookie.
	 * @return array{key:string,user_id:int,token:string}
	 */
	public function resolve_owner( string $visitor_token = '' ): array {
		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			return array(
				'key'     => self::user_key( $user_id ),
				'user_id' => $user_id,
				'token'   => '',
			);
		}

		$token = strtolower( preg_replace( '/[^a-f0-9]/i', '', $visitor_token ) );
		if ( strlen( $token ) < 32 ) {
			$token = bin2hex( random_bytes( 24 ) );
		}
		$key = self::visitor_key( $token );

		return array(
			'key'     => $key,
			'user_id' => 0,
			'token'   => $token,
		);
	}
}
