<?php
/**
 * Favorites for guests and accounts.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Services;

use Estora\Core\Repositories\Favorite_Repository;
use Estora\Core\Support\Validation_Exception;

defined( 'ABSPATH' ) || exit;

final class Favorite_Service {

	private Favorite_Repository $repo;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repo = new Favorite_Repository();
	}

	/**
	 * Owner context for the current request.
	 *
	 * @param string $token Visitor token from header or cookie.
	 * @return array{key:string,user_id:int,token:string}
	 */
	public function owner( string $token = '' ): array {
		return $this->repo->resolve_owner( $token );
	}

	/**
	 * Toggle a favorite.
	 *
	 * @param int    $property_id Property.
	 * @param string $token       Visitor token.
	 * @return array{favorited:bool,property_id:int,count:int,token:string}
	 */
	public function toggle( int $property_id, string $token = '' ): array {
		$owner = $this->owner( $token );
		$this->assert_property( $property_id );

		$ids = $this->repo->ids( $owner['key'] );
		if ( in_array( $property_id, $ids, true ) ) {
			$this->repo->remove( $owner['key'], $property_id );
			$favorited = false;
		} else {
			$this->repo->add( $owner['key'], $property_id, $owner['user_id'] ?: null, $owner['token'] ?: null );
			$favorited = true;
		}

		return array(
			'favorited'   => $favorited,
			'property_id' => $property_id,
			'count'       => count( $this->repo->ids( $owner['key'] ) ),
			'token'       => $owner['token'],
		);
	}

	/**
	 * Add explicitly (idempotent).
	 *
	 * @param int    $property_id Property.
	 * @param string $token       Visitor token.
	 */
	public function add( int $property_id, string $token = '' ): array {
		$owner = $this->owner( $token );
		$this->assert_property( $property_id );
		$this->repo->add( $owner['key'], $property_id, $owner['user_id'] ?: null, $owner['token'] ?: null );
		return array(
			'favorited'   => true,
			'property_id' => $property_id,
			'count'       => count( $this->repo->ids( $owner['key'] ) ),
			'token'       => $owner['token'],
		);
	}

	/**
	 * Remove.
	 *
	 * @param int    $property_id Property.
	 * @param string $token       Visitor token.
	 */
	public function remove( int $property_id, string $token = '' ): array {
		$owner = $this->owner( $token );
		$this->repo->remove( $owner['key'], $property_id );
		return array(
			'favorited'   => false,
			'property_id' => $property_id,
			'count'       => count( $this->repo->ids( $owner['key'] ) ),
			'token'       => $owner['token'],
		);
	}

	/**
	 * Current favorites.
	 *
	 * @param string $token Visitor token.
	 * @return array{ids:int[],properties:array<int,array<string,mixed>>,token:string}
	 */
	public function listing( string $token = '' ): array {
		$owner = $this->owner( $token );
		return array(
			'ids'        => $this->repo->ids( $owner['key'] ),
			'properties' => $this->repo->properties( $owner['key'] ),
			'token'      => $owner['token'],
		);
	}

	/**
	 * Require a published property.
	 *
	 * @param int $property_id Property.
	 * @throws Validation_Exception When missing.
	 */
	private function assert_property( int $property_id ): void {
		$post = get_post( $property_id );
		if ( ! $post || 'property' !== $post->post_type || 'publish' !== $post->post_status ) {
			throw new Validation_Exception( 'Property not found.', array( 'property_id' => array( 'That property is not available.' ) ), 404 );
		}
	}
}
