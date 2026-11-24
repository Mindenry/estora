<?php
/**
 * Publication and availability state machines for properties.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Domain;

use Estora\Core\Support\Validation_Exception;

defined( 'ABSPATH' ) || exit;

final class Property_Status {

	public const DRAFT     = 'draft';
	public const PENDING   = 'pending';
	public const PUBLISHED = 'published';
	public const ARCHIVED  = 'archived';

	public const AVAILABLE   = 'available';
	public const RESERVED    = 'reserved';
	public const SOLD        = 'sold';
	public const RENTED      = 'rented';
	public const UNAVAILABLE = 'unavailable';

	/**
	 * Publication transitions.
	 *
	 * @return array<string,string[]>
	 */
	public static function publication_transitions(): array {
		return array(
			self::DRAFT     => array( self::PENDING, self::PUBLISHED, self::ARCHIVED ),
			self::PENDING   => array( self::PUBLISHED, self::DRAFT, self::ARCHIVED ),
			self::PUBLISHED => array( self::DRAFT, self::ARCHIVED ),
			self::ARCHIVED  => array( self::DRAFT, self::PUBLISHED ),
		);
	}

	/**
	 * Availability transitions.
	 *
	 * @return array<string,string[]>
	 */
	public static function availability_transitions(): array {
		return array(
			self::AVAILABLE   => array( self::RESERVED, self::SOLD, self::RENTED, self::UNAVAILABLE ),
			self::RESERVED    => array( self::AVAILABLE, self::SOLD, self::RENTED, self::UNAVAILABLE ),
			self::SOLD        => array( self::AVAILABLE ),
			self::RENTED      => array( self::AVAILABLE ),
			self::UNAVAILABLE => array( self::AVAILABLE ),
		);
	}

	/**
	 * Valid availability values.
	 *
	 * @return string[]
	 */
	public static function availability_values(): array {
		return array_keys( self::availability_transitions() );
	}

	/**
	 * Whether a new inquiry is allowed.
	 *
	 * @param string $availability Availability.
	 */
	public static function accepts_inquiries( string $availability ): bool {
		return in_array( $availability, array( self::AVAILABLE, self::RESERVED ), true );
	}

	/**
	 * Validate an availability transition.
	 *
	 * @param string $from Current.
	 * @param string $to   Target.
	 * @throws Validation_Exception When illegal.
	 */
	public static function assert_availability( string $from, string $to ): void {
		if ( ! in_array( $to, self::availability_values(), true ) ) {
			throw new Validation_Exception( 'Unknown availability value.', array( 'availability' => array( 'Unknown availability value.' ) ) );
		}
		$map = self::availability_transitions();
		if ( isset( $map[ $from ] ) && ! in_array( $to, $map[ $from ], true ) ) {
			throw new Validation_Exception(
				sprintf( 'A property cannot move from %s to %s.', self::availability_label( $from ), self::availability_label( $to ) ),
				array( 'availability' => array( 'That availability change is not allowed.' ) )
			);
		}
	}

	/**
	 * Availability label.
	 *
	 * @param string $value Value.
	 */
	public static function availability_label( string $value ): string {
		$labels = array(
			self::AVAILABLE   => 'Available',
			self::RESERVED    => 'Reserved',
			self::SOLD        => 'Sold',
			self::RENTED      => 'Rented',
			self::UNAVAILABLE => 'Unavailable',
		);
		return $labels[ $value ] ?? ucfirst( $value );
	}

	/**
	 * Availability badge class.
	 *
	 * @param string $value Value.
	 */
	public static function availability_badge( string $value ): string {
		$map = array(
			self::AVAILABLE   => 'badge-ok',
			self::RESERVED    => 'badge-warning',
			self::SOLD        => 'badge-neutral',
			self::RENTED      => 'badge-neutral',
			self::UNAVAILABLE => 'badge-neutral',
		);
		return $map[ $value ] ?? 'badge-neutral';
	}

	/**
	 * Publication label.
	 *
	 * @param string $value Value.
	 */
	public static function publication_label( string $value ): string {
		$labels = array(
			self::DRAFT     => 'Draft',
			self::PENDING   => 'Pending review',
			self::PUBLISHED => 'Published',
			self::ARCHIVED  => 'Archived',
		);
		return $labels[ $value ] ?? ucfirst( $value );
	}
}
