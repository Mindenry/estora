<?php
/**
 * Pure formatting helpers (no WordPress dependency, unit tested).
 *
 * @package Estora\Core
 */

namespace Estora\Core\Support;

defined( 'ABSPATH' ) || exit;

final class Formatter {

	/**
	 * Format money. Whole amounts drop decimals.
	 *
	 * @param float|int|string $amount  Amount.
	 * @param string           $currency Currency code.
	 * @param bool             $compact Use 12.5M style.
	 */
	public static function price( $amount, string $currency = 'THB', bool $compact = false ): string {
		$amount = (float) $amount;
		$symbol = 'THB' === $currency ? '฿' : $currency . ' ';

		if ( $compact && $amount >= 1000000 ) {
			return $symbol . rtrim( rtrim( number_format( $amount / 1000000, 2, '.', '' ), '0' ), '.' ) . 'M';
		}
		if ( $compact && $amount >= 1000 ) {
			return $symbol . rtrim( rtrim( number_format( $amount / 1000, 1, '.', '' ), '0' ), '.' ) . 'K';
		}

		$decimals = ( abs( $amount - round( $amount ) ) < 0.005 ) ? 0 : 2;
		return $symbol . number_format( $amount, $decimals, '.', ',' );
	}

	/**
	 * Area label.
	 *
	 * @param float|int|string $value Square metres.
	 */
	public static function area( $value ): string {
		$value = (float) $value;
		if ( $value <= 0 ) {
			return '—';
		}
		$decimals = ( abs( $value - round( $value ) ) < 0.05 ) ? 0 : 1;
		return number_format( $value, $decimals, '.', ',' ) . ' m²';
	}

	/**
	 * Property reference from post ID.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function reference( int $post_id ): string {
		return 'EST-' . ( 1000 + $post_id );
	}

	/**
	 * Normalise a phone number to digits (keeping a leading +).
	 *
	 * @param string $phone Raw phone.
	 */
	public static function normalize_phone( string $phone ): string {
		$plus   = str_starts_with( trim( $phone ), '+' );
		$digits = (string) preg_replace( '/\D+/', '', $phone );

		// Strip the Thai trunk prefix after +66 so +66 (0) 81… and +66 81… match.
		if ( $plus && str_starts_with( $digits, '660' ) ) {
			$digits = '66' . substr( $digits, 3 );
		}

		return ( $plus ? '+' : '' ) . $digits;
	}

	/**
	 * Initials for avatars.
	 *
	 * @param string $name Name.
	 */
	public static function initials( string $name ): string {
		$parts = preg_split( '/\s+/', trim( $name ) ) ?: array();
		$out   = '';
		foreach ( array_slice( $parts, 0, 2 ) as $part ) {
			$out .= strtoupper( substr( $part, 0, 1 ) );
		}
		return $out ?: '?';
	}

	/**
	 * Relative time, e.g. "3 hours ago".
	 *
	 * @param string $datetime MySQL datetime (site local).
	 * @param int    $now      Reference timestamp.
	 */
	public static function time_ago( string $datetime, int $now ): string {
		$ts   = strtotime( $datetime );
		$diff = $now - $ts;
		if ( $diff < 60 ) {
			return 'just now';
		}
		if ( $diff < 3600 ) {
			$n = (int) floor( $diff / 60 );
			return $n . ' min ago';
		}
		if ( $diff < 86400 ) {
			$n = (int) floor( $diff / 3600 );
			return $n . ( 1 === $n ? ' hour ago' : ' hours ago' );
		}
		$n = (int) floor( $diff / 86400 );
		return $n . ( 1 === $n ? ' day ago' : ' days ago' );
	}
}
