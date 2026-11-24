<?php
/**
 * Appointment state machine.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Domain;

use Estora\Core\Support\Validation_Exception;

defined( 'ABSPATH' ) || exit;

final class Appointment_Status {

	public const PENDING     = 'pending';
	public const CONFIRMED   = 'confirmed';
	public const RESCHEDULED = 'rescheduled';
	public const COMPLETED   = 'completed';
	public const CANCELLED   = 'cancelled';
	public const REJECTED    = 'rejected';
	public const NO_SHOW     = 'no_show';

	/**
	 * Allowed transitions.
	 *
	 * @return array<string,string[]>
	 */
	public static function transitions(): array {
		return array(
			self::PENDING     => array( self::CONFIRMED, self::REJECTED, self::CANCELLED ),
			self::CONFIRMED   => array( self::COMPLETED, self::CANCELLED, self::NO_SHOW, self::RESCHEDULED ),
			self::RESCHEDULED => array( self::CONFIRMED, self::COMPLETED, self::CANCELLED, self::NO_SHOW ),
			self::COMPLETED   => array(),
			self::CANCELLED   => array(),
			self::REJECTED    => array(),
			self::NO_SHOW     => array(),
		);
	}

	/**
	 * Valid statuses.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array_keys( self::transitions() );
	}

	/**
	 * Transition test.
	 *
	 * @param string $from Current.
	 * @param string $to   Target.
	 */
	public static function can( string $from, string $to ): bool {
		return isset( self::transitions()[ $from ] ) && in_array( $to, self::transitions()[ $from ], true );
	}

	/**
	 * Throw on an invalid transition.
	 *
	 * @param string $from Current.
	 * @param string $to   Target.
	 * @throws Validation_Exception When illegal.
	 */
	public static function assert_transition( string $from, string $to ): void {
		if ( ! isset( self::transitions()[ $from ] ) || ! in_array( $to, self::all(), true ) ) {
			throw new Validation_Exception( 'Unknown appointment status.', array( 'status' => array( 'Unknown appointment status.' ) ) );
		}
		if ( ! self::can( $from, $to ) ) {
			throw new Validation_Exception(
				sprintf( 'An appointment cannot move from %s to %s.', self::label( $from ), self::label( $to ) ),
				array( 'status' => array( 'That status change is not allowed for this appointment.' ) )
			);
		}
	}

	/**
	 * Whether the status occupies a slot.
	 *
	 * @param string $status Status.
	 */
	public static function is_active( string $status ): bool {
		return in_array( $status, array( self::PENDING, self::CONFIRMED, self::RESCHEDULED ), true );
	}

	/**
	 * Human label.
	 *
	 * @param string $status Status.
	 */
	public static function label( string $status ): string {
		$labels = array(
			self::PENDING     => 'Pending',
			self::CONFIRMED   => 'Confirmed',
			self::RESCHEDULED => 'Rescheduled',
			self::COMPLETED   => 'Completed',
			self::CANCELLED   => 'Cancelled',
			self::REJECTED    => 'Rejected',
			self::NO_SHOW     => 'No show',
		);
		return $labels[ $status ] ?? ucfirst( $status );
	}

	/**
	 * Badge modifier.
	 *
	 * @param string $status Status.
	 */
	public static function badge( string $status ): string {
		$map = array(
			self::PENDING     => 'badge-warning',
			self::CONFIRMED   => 'badge-new',
			self::RESCHEDULED => 'badge-info',
			self::COMPLETED   => 'badge-neutral',
			self::CANCELLED   => 'badge-danger',
			self::REJECTED    => 'badge-danger',
			self::NO_SHOW     => 'badge-danger',
		);
		return $map[ $status ] ?? 'badge-neutral';
	}
}
