<?php
/**
 * Plugin settings with sane defaults.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Services;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION = 'estora_settings';

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'company_name'          => 'ESTORA',
			'company_tagline'       => 'Residential property, handled properly.',
			'company_email'         => 'hello@estora.test',
			'company_phone'         => '+66 53 000 000',
			'company_address'       => '14 Nimmanhaemin Road, Suthep, Chiang Mai 50200',
			'company_hours_text'    => 'Monday to Saturday, 09:00–18:00',
			'currency'              => 'THB',
			'open_time'             => '09:00',
			'close_time'            => '18:00',
			'closed_days'           => array( 0 ),
			'lunch_start'           => '12:00',
			'lunch_end'             => '13:00',
			'slot_minutes'          => 45,
			'notification_email'    => '',
			'notify_new_lead'       => true,
			'notify_new_appointment' => true,
			'notify_email_enabled'  => true,
			'default_agent_id'      => 0,
			'listing_per_page'      => 9,
			'company_registration'  => '',
			'facebook_url'          => '',
			'instagram_url'         => '',
			'line_url'              => '',
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * Single setting.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Default when missing.
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$all = self::all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return $default;
	}

	/**
	 * Persist sanitised settings.
	 *
	 * @param array<string,mixed> $values Raw values.
	 * @return array<string,mixed>
	 */
	public static function update( array $values ): array {
		$current  = self::all();
		$defaults = self::defaults();
		$clean    = $current;

		$text_keys = array(
			'company_name',
			'company_tagline',
			'company_hours_text',
			'currency',
			'open_time',
			'close_time',
			'lunch_start',
			'lunch_end',
			'company_registration',
			'facebook_url',
			'instagram_url',
			'line_url',
		);
		foreach ( $text_keys as $key ) {
			if ( array_key_exists( $key, $values ) ) {
				$clean[ $key ] = sanitize_text_field( (string) $values[ $key ] );
			}
		}

		if ( array_key_exists( 'company_email', $values ) ) {
			$clean['company_email'] = sanitize_email( (string) $values['company_email'] );
		}
		if ( array_key_exists( 'notification_email', $values ) ) {
			$clean['notification_email'] = sanitize_email( (string) $values['notification_email'] );
		}
		if ( array_key_exists( 'company_phone', $values ) ) {
			$clean['company_phone'] = sanitize_text_field( (string) $values['company_phone'] );
		}
		if ( array_key_exists( 'company_address', $values ) ) {
			$clean['company_address'] = sanitize_textarea_field( (string) $values['company_address'] );
		}
		if ( array_key_exists( 'closed_days', $values ) ) {
			$days                 = is_array( $values['closed_days'] ) ? $values['closed_days'] : array();
			$clean['closed_days'] = array_values( array_unique( array_filter( array_map( 'absint', $days ), static fn( $d ) => $d >= 0 && $d <= 6 ) ) );
		}
		if ( array_key_exists( 'slot_minutes', $values ) ) {
			$slot                  = absint( $values['slot_minutes'] );
			$clean['slot_minutes'] = in_array( $slot, array( 30, 45, 60, 90 ), true ) ? $slot : 45;
		}
		if ( array_key_exists( 'default_agent_id', $values ) ) {
			$clean['default_agent_id'] = absint( $values['default_agent_id'] );
		}
		if ( array_key_exists( 'listing_per_page', $values ) ) {
			$per_page                  = absint( $values['listing_per_page'] );
			$clean['listing_per_page'] = ( $per_page >= 3 && $per_page <= 48 ) ? $per_page : 9;
		}
		foreach ( array( 'notify_new_lead', 'notify_new_appointment', 'notify_email_enabled' ) as $flag ) {
			if ( array_key_exists( $flag, $values ) ) {
				$clean[ $flag ] = \Estora\Core\Support\Validator::to_bool( $values[ $flag ] );
			}
		}

		foreach ( array( 'open_time', 'close_time', 'lunch_start', 'lunch_end' ) as $time_key ) {
			if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', (string) $clean[ $time_key ] ) ) {
				$clean[ $time_key ] = $defaults[ $time_key ];
			}
		}
		if ( strtotime( $clean['open_time'] ) >= strtotime( $clean['close_time'] ) ) {
			$clean['open_time']  = $defaults['open_time'];
			$clean['close_time'] = $defaults['close_time'];
		}

		update_option( self::OPTION, $clean );
		delete_transient( 'estora_dashboard_summary' );
		return $clean;
	}

	/**
	 * Ensure the option exists.
	 */
	public static function ensure(): void {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults() );
		}
	}

	/**
	 * Atomic settings write for a single key.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 */
	public static function set( string $key, $value ): void {
		self::update( array( $key => $value ) );
	}

	/**
	 * Business hours per ISO weekday index (0 = Sunday).
	 *
	 * @return array<int,array{0:string,1:string}|null>
	 */
	public static function business_hours(): array {
		$all    = self::all();
		$closed = array_map( 'intval', (array) $all['closed_days'] );
		$hours  = array();
		for ( $day = 0; $day <= 6; $day++ ) {
			$hours[ $day ] = in_array( $day, $closed, true ) ? null : array( $all['open_time'], $all['close_time'] );
		}
		return $hours;
	}

	/**
	 * Whether the office is open on a Y-m-d date.
	 *
	 * @param string $date Date.
	 */
	public static function is_open_on( string $date ): bool {
		$ts = strtotime( $date . ' 12:00:00' );
		if ( ! $ts ) {
			return false;
		}
		$hours = self::business_hours();
		return null !== $hours[ (int) gmdate( 'w', $ts ) ];
	}
}
