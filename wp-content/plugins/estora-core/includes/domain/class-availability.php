<?php
/**
 * Slot grid and overlap maths. Pure, unit tested.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Domain;

defined( 'ABSPATH' ) || exit;

final class Availability {

	/**
	 * Build the day's slot grid.
	 *
	 * @param string                                    $date         Y-m-d.
	 * @param string                                    $open         HH:MM.
	 * @param string                                    $close        HH:MM.
	 * @param int                                       $slot_minutes Slot length.
	 * @param string|null                               $lunch_start  HH:MM or null.
	 * @param string|null                               $lunch_end    HH:MM or null.
	 * @param array<int,array{start:string,end:string}> $booked Existing bookings.
	 * @param string|null                               $now          Current site datetime Y-m-d H:i:s.
	 * @return array<int,array{start:string,end:string,available:bool,reason:string}>
	 */
	public static function slots(
		string $date,
		string $open,
		string $close,
		int $slot_minutes,
		?string $lunch_start,
		?string $lunch_end,
		array $booked,
		?string $now = null
	): array {
		$slots = array();
		if ( $slot_minutes < 5 ) {
			return $slots;
		}

		$cursor    = self::to_minutes( $open );
		$end_limit = self::to_minutes( $close );
		$lunch_a   = $lunch_start ? self::to_minutes( $lunch_start ) : null;
		$lunch_b   = $lunch_end ? self::to_minutes( $lunch_end ) : null;

		$today   = $now ? substr( $now, 0, 10 ) : null;
		$now_min = $now ? self::to_minutes( substr( $now, 11, 5 ) ) : null;

		while ( $cursor + $slot_minutes <= $end_limit ) {
			$slot_end = $cursor + $slot_minutes;

			if ( null !== $lunch_a && null !== $lunch_b && $cursor < $lunch_b && $slot_end > $lunch_a ) {
				$cursor += $slot_minutes;
				continue;
			}

			$start_label = self::from_minutes( $cursor );
			$end_label   = self::from_minutes( $slot_end );
			$available   = true;
			$reason      = '';

			if ( $today === $date && null !== $now_min && $cursor <= $now_min ) {
				$available = false;
				$reason    = 'past';
			}

			foreach ( $booked as $booking ) {
				$b_start = self::to_minutes( (string) $booking['start'] );
				$b_end   = self::to_minutes( (string) $booking['end'] );
				if ( $cursor < $b_end && $slot_end > $b_start ) {
					$available = false;
					$reason    = 'booked';
					break;
				}
			}

			$slots[] = array(
				'start'     => $start_label,
				'end'       => $end_label,
				'available' => $available,
				'reason'    => $reason,
			);

			$cursor += $slot_minutes;
		}

		return $slots;
	}

	/**
	 * Whether two time ranges overlap.
	 *
	 * @param string $start_a HH:MM.
	 * @param string $end_a   HH:MM.
	 * @param string $start_b HH:MM.
	 * @param string $end_b   HH:MM.
	 */
	public static function overlaps( string $start_a, string $end_a, string $start_b, string $end_b ): bool {
		return self::to_minutes( $start_a ) < self::to_minutes( $end_b )
			&& self::to_minutes( $end_a ) > self::to_minutes( $start_b );
	}

	/**
	 * Convert HH:MM to minutes since midnight.
	 *
	 * @param string $time Time.
	 */
	public static function to_minutes( string $time ): int {
		$parts = explode( ':', $time );
		if ( count( $parts ) < 2 || ! is_numeric( $parts[0] ) || ! is_numeric( $parts[1] ) ) {
			return 0;
		}
		return ( (int) $parts[0] ) * 60 + (int) $parts[1];
	}

	/**
	 * Convert minutes to HH:MM.
	 *
	 * @param int $minutes Minutes.
	 */
	public static function from_minutes( int $minutes ): string {
		$minutes = max( 0, min( 24 * 60 - 1, $minutes ) );
		return sprintf( '%02d:%02d', intdiv( $minutes, 60 ), $minutes % 60 );
	}

	/**
	 * End time for a start plus duration.
	 *
	 * @param string $start    HH:MM.
	 * @param int    $duration Minutes.
	 */
	public static function end_time( string $start, int $duration ): string {
		return self::from_minutes( self::to_minutes( $start ) + $duration );
	}
}
