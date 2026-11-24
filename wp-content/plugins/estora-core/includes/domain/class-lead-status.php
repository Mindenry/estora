<?php
/**
 * Lead pipeline state machine.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Domain;

use Estora\Core\Support\Validation_Exception;

defined( 'ABSPATH' ) || exit;

final class Lead_Status {

	public const NEW         = 'new';
	public const CONTACTED   = 'contacted';
	public const QUALIFIED   = 'qualified';
	public const NEGOTIATION = 'negotiation';
	public const WON         = 'won';
	public const LOST        = 'lost';

	/**
	 * Allowed transitions.
	 *
	 * @return array<string,string[]>
	 */
	public static function transitions(): array {
		return array(
			self::NEW         => array( self::CONTACTED, self::LOST ),
			self::CONTACTED   => array( self::QUALIFIED, self::LOST ),
			self::QUALIFIED   => array( self::NEGOTIATION, self::LOST ),
			self::NEGOTIATION => array( self::WON, self::LOST ),
			self::WON         => array(),
			self::LOST        => array( self::CONTACTED ),
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
	 * Whether a transition is legal.
	 *
	 * @param string $from Current.
	 * @param string $to   Target.
	 */
	public static function can( string $from, string $to ): bool {
		$map = self::transitions();
		return isset( $map[ $from ] ) && in_array( $to, $map[ $from ], true );
	}

	/**
	 * Assert a transition or throw.
	 *
	 * @param string $from Current status.
	 * @param string $to   Target status.
	 * @throws Validation_Exception When illegal.
	 */
	public static function assert_transition( string $from, string $to ): void {
		if ( ! isset( self::transitions()[ $from ] ) ) {
			throw new Validation_Exception( 'Unknown current lead status.', array( 'status' => array( 'Unknown current lead status.' ) ), 422 );
		}
		if ( ! in_array( $to, self::all(), true ) ) {
			throw new Validation_Exception( 'Unknown target lead status.', array( 'status' => array( 'Unknown target lead status.' ) ), 422 );
		}
		if ( ! self::can( $from, $to ) ) {
			throw new Validation_Exception(
				sprintf( 'A lead cannot move from %s to %s.', self::label( $from ), self::label( $to ) ),
				array( 'status' => array( sprintf( 'Allowed next stages from %s: %s.', self::label( $from ), implode( ', ', array_map( array( self::class, 'label' ), self::transitions()[ $from ] ) ) ?: 'none' ) ) ),
				422
			);
		}
	}

	/**
	 * Human label.
	 *
	 * @param string $status Status.
	 */
	public static function label( string $status ): string {
		$labels = array(
			self::NEW         => 'New',
			self::CONTACTED   => 'Contacted',
			self::QUALIFIED   => 'Qualified',
			self::NEGOTIATION => 'Negotiation',
			self::WON         => 'Won',
			self::LOST        => 'Lost',
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
			self::NEW         => 'badge-new',
			self::CONTACTED   => 'badge-neutral',
			self::QUALIFIED   => 'badge-info',
			self::NEGOTIATION => 'badge-warning',
			self::WON         => 'badge-ok',
			self::LOST        => 'badge-danger',
		);
		return $map[ $status ] ?? 'badge-neutral';
	}

	/**
	 * Statuses considered still in play.
	 *
	 * @return string[]
	 */
	public static function active(): array {
		return array( self::NEW, self::CONTACTED, self::QUALIFIED, self::NEGOTIATION );
	}
}
