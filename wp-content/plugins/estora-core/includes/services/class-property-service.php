<?php
/**
 * Property business rules and audit.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Services;

use Estora\Core\Domain\Property_Status;
use Estora\Core\Repositories\Activity_Repository;
use Estora\Core\Repositories\Property_Repository;
use Estora\Core\Support\Capabilities;
use Estora\Core\Support\Validation_Exception;

defined( 'ABSPATH' ) || exit;

final class Property_Service {

	private Property_Repository $repo;
	private Activity_Repository $activity;
	private Notification_Service $notifications;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repo          = new Property_Repository();
		$this->activity      = new Activity_Repository();
		$this->notifications = new Notification_Service();
	}

	/**
	 * Create or update.
	 *
	 * @param array<string,mixed> $data    Payload.
	 * @param int                 $post_id Existing ID.
	 * @return array<string,mixed>
	 */
	public function save( array $data, int $post_id = 0 ): array {
		$this->require_cap( $post_id ? Capabilities::EDIT_PROPERTIES : Capabilities::EDIT_PROPERTIES );

		if ( ! empty( $data['status'] ) && 'published' === $data['status'] && ! current_user_can( Capabilities::PUBLISH_PROPERTIES ) ) {
			throw new Validation_Exception( 'You are not allowed to publish properties.', array(), 403 );
		}

		$id = $this->repo->save( $data, $post_id );
		$this->flush_caches();

		$shape = $this->repo->to_array( $id );
		if ( ! $shape ) {
			throw new Validation_Exception( 'The property could not be loaded after saving.' );
		}

		$this->activity->log(
			$post_id ? 'property.updated' : 'property.created',
			'property',
			$id,
			sprintf( 'Property %s %s.', $shape['reference'], $post_id ? 'updated' : 'created' ),
			array( 'status' => $shape['status'] )
		);

		if ( ! $post_id && 'publish' === get_post_status( $id ) ) {
			$this->notifications->dispatch_to_capability(
				Capabilities::VIEW_PROPERTIES,
				'property.published',
				sprintf( 'Property published: %s', $shape['title'] ),
				$shape['reference'] . ' is now live.',
				admin_url( 'post.php?post=' . $id . '&action=edit' ),
				'property.published:' . $id,
				false
			);
		}

		return $shape;
	}

	/**
	 * Change availability.
	 *
	 * @param int    $post_id Property.
	 * @param string $value   Availability.
	 * @return array<string,mixed>
	 */
	public function set_availability( int $post_id, string $value ): array {
		$this->require_cap( Capabilities::EDIT_PROPERTIES );
		$before = estora_property_availability( $post_id );
		$this->repo->set_availability( $post_id, sanitize_key( $value ) );
		$this->flush_caches();
		$this->activity->log(
			'property.availability',
			'property',
			$post_id,
			sprintf( 'Availability changed from %s to %s.', Property_Status::availability_label( $before ), Property_Status::availability_label( sanitize_key( $value ) ) )
		);
		$shape = $this->repo->to_array( $post_id );
		return $shape ?: array();
	}

	/**
	 * Duplicate.
	 *
	 * @param int $post_id Source.
	 * @return array<string,mixed>
	 */
	public function duplicate( int $post_id ): array {
		$this->require_cap( Capabilities::EDIT_PROPERTIES );
		$new_id = $this->repo->duplicate( $post_id );
		$this->activity->log( 'property.duplicated', 'property', $new_id, sprintf( 'Duplicated from property #%d.', $post_id ) );
		$shape = $this->repo->to_array( $new_id );
		return $shape ?: array();
	}

	/**
	 * Delete permanently.
	 *
	 * @param int $post_id Property.
	 */
	public function delete( int $post_id ): bool {
		$this->require_cap( Capabilities::DELETE_PROPERTIES );
		$post = get_post( $post_id );
		if ( ! $post || 'property' !== $post->post_type ) {
			throw new Validation_Exception( 'Property not found.', array(), 404 );
		}
		$ok = (bool) wp_delete_post( $post_id, true );
		if ( $ok ) {
			$this->flush_caches();
			$this->activity->log( 'property.deleted', 'property', $post_id, 'Property deleted permanently.' );
		}
		return $ok;
	}

	/**
	 * Fields required before a property may be published.
	 *
	 * @param int $post_id Property.
	 * @return string[] Missing field labels.
	 */
	public function publish_requirements( int $post_id ): array {
		$missing = array();
		if ( '' === trim( (string) get_the_title( $post_id ) ) ) {
			$missing[] = 'Title';
		}
		if ( '' === (string) estora_property_purpose( $post_id ) ) {
			$missing[] = 'Purpose';
		}
		$types = wp_get_post_terms( $post_id, 'property_type' );
		if ( is_wp_error( $types ) || empty( $types ) ) {
			$missing[] = 'Property type';
		}
		if ( estora_property_price( $post_id ) <= 0 ) {
			$missing[] = 'Price';
		}
		return $missing;
	}

	/**
	 * Clear caches touched by property changes.
	 */
	public function flush_caches(): void {
		delete_transient( 'estora_dashboard_summary' );
		delete_transient( 'estora_filter_options' );
		wp_cache_delete( 'estora_dashboard_summary', 'estora' );
	}

	/**
	 * Repository accessor.
	 */
	public function repository(): Property_Repository {
		return $this->repo;
	}

	/**
	 * Capability guard.
	 *
	 * @param string $cap Capability.
	 */
	private function require_cap( string $cap ): void {
		if ( ! current_user_can( $cap ) ) {
			throw new Validation_Exception( 'You are not allowed to manage properties.', array(), 403 );
		}
	}
}
