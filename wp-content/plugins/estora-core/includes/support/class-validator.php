<?php
/**
 * Dependency-free validation engine.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Support;

defined( 'ABSPATH' ) || exit;

final class Validator {

	/**
	 * Validate a flat payload against a rule map.
	 *
	 * @param array<string,mixed>    $data  Payload.
	 * @param array<string,string[]> $rules field => rules.
	 * @return array<string,string[]> Field errors, empty when valid.
	 */
	public static function validate( array $data, array $rules ): array {
		$errors = array();

		foreach ( $rules as $field => $field_rules ) {
			$value    = $data[ $field ] ?? null;
			$required = in_array( 'required', $field_rules, true );
			$empty    = self::is_empty( $value );

			if ( $required && $empty ) {
				$errors[ $field ][] = sprintf( '%s is required.', self::label( $field ) );
				continue;
			}
			if ( $empty ) {
				continue;
			}

			foreach ( $field_rules as $rule ) {
				$parts = explode( ':', $rule, 2 );
				$name  = $parts[0];
				$param = $parts[1] ?? '';
				$error = self::apply( $name, $param, $value, $field );

				if ( null !== $error ) {
					$errors[ $field ][] = $error;
				}
			}
		}

		return $errors;
	}

	/**
	 * Validate and throw on failure.
	 *
	 * @param array<string,mixed>    $data  Payload.
	 * @param array<string,string[]> $rules Rules.
	 * @param string                 $message Message.
	 * @throws Validation_Exception When invalid.
	 */
	public static function assert( array $data, array $rules, string $message = 'Validation failed' ): void {
		$errors = self::validate( $data, $rules );
		if ( ! empty( $errors ) ) {
			throw new Validation_Exception( $message, $errors, 422 );
		}
	}

	/**
	 * Empty test for scalars and arrays.
	 *
	 * @param mixed $value Value.
	 */
	public static function is_empty( $value ): bool {
		if ( null === $value ) {
			return true;
		}
		if ( is_string( $value ) ) {
			return '' === trim( $value );
		}
		if ( is_array( $value ) ) {
			return 0 === count( $value );
		}
		return false;
	}

	/**
	 * Apply a single rule. Returns an error message or null.
	 *
	 * @param string $name  Rule name.
	 * @param string $param Rule parameter.
	 * @param mixed  $value Value.
	 * @param string $field Field name.
	 */
	private static function apply( string $name, string $param, $value, string $field ): ?string {
		$label = self::label( $field );

		switch ( $name ) {
			case 'required':
				return null;
			case 'email':
				return self::is_email_shape( (string) $value ) ? null : sprintf( '%s must be a valid email address.', $label );
			case 'phone':
				$digits = preg_replace( '/\D+/', '', (string) $value );
				return preg_match( '/^[+0-9()\-\s.]{6,30}$/', (string) $value ) && strlen( (string) $digits ) >= 6
					? null : sprintf( '%s must be a valid phone number.', $label );
			case 'numeric':
				return is_numeric( $value ) ? null : sprintf( '%s must be a number.', $label );
			case 'integer':
				return ( is_numeric( $value ) && (float) $value === (float) (int) $value ) ? null : sprintf( '%s must be a whole number.', $label );
			case 'min':
				return is_numeric( $value ) && (float) $value >= (float) $param ? null : sprintf( '%s must be at least %s.', $label, $param );
			case 'max':
				return is_numeric( $value ) && (float) $value <= (float) $param ? null : sprintf( '%s must not exceed %s.', $label, $param );
			case 'max_length':
				return mb_strlen( (string) $value ) <= (int) $param ? null : sprintf( '%s must be %d characters or fewer.', $label, (int) $param );
			case 'min_length':
				return mb_strlen( (string) $value ) >= (int) $param ? null : sprintf( '%s must be at least %d characters.', $label, (int) $param );
			case 'in':
				$allowed = explode( ',', $param );
				return in_array( (string) $value, $allowed, true ) ? null : sprintf( '%s is not an allowed value.', $label );
			case 'date':
				$date = \DateTimeImmutable::createFromFormat( 'Y-m-d', (string) $value );
				return ( $date && $date->format( 'Y-m-d' ) === (string) $value ) ? null : sprintf( '%s must be a date in YYYY-MM-DD format.', $label );
			case 'time':
				return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', (string) $value ) ? null : sprintf( '%s must be a time in HH:MM format.', $label );
			case 'date_not_past':
				$today = $param ?: gmdate( 'Y-m-d' );
				return ( (string) $value >= $today ) ? null : sprintf( '%s cannot be in the past.', $label );
			case 'boolean':
				return self::is_boolean( $value ) ? null : sprintf( '%s must be true or false.', $label );
			case 'url':
				return filter_var( (string) $value, FILTER_VALIDATE_URL ) ? null : sprintf( '%s must be a valid URL.', $label );
			case 'array':
				return is_array( $value ) ? null : sprintf( '%s must be a list.', $label );
			case 'max_items':
				return is_array( $value ) && count( $value ) <= (int) $param ? null : sprintf( '%s may contain at most %d items.', $label, (int) $param );
			case 'regex':
				return preg_match( $param, (string) $value ) ? null : sprintf( '%s has an invalid format.', $label );
			default:
				return null;
		}
	}

	/**
	 * Accept the usual truthy representations.
	 *
	 * @param mixed $value Value.
	 */
	public static function is_boolean( $value ): bool {
		return in_array( $value, array( true, false, 1, 0, '1', '0', 'true', 'false', 'on', 'off', 'yes', 'no' ), true );
	}

	/**
	 * Coerce a value to bool.
	 *
	 * @param mixed $value Value.
	 */
	public static function to_bool( $value ): bool {
		return in_array( $value, array( true, 1, '1', 'true', 'on', 'yes' ), true );
	}

	/**
	 * Human label from a field key.
	 *
	 * @param string $field Field.
	 */
	private static function label( string $field ): string {
		return ucfirst( str_replace( array( '_', '-' ), ' ', $field ) );
	}

	/**
	 * Local email shape check so the validator stays WordPress-free for unit tests.
	 *
	 * @param string $email Email.
	 */
	private static function is_email_shape( string $email ): bool {
		if ( strlen( $email ) > 190 ) {
			return false;
		}
		return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
	}
}
