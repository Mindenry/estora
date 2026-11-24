<?php
/**
 * Mail transport wrapper with honest failure logging.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Integrations;

defined( 'ABSPATH' ) || exit;

final class Mailer {

	/**
	 * Send a plain-text email.
	 *
	 * @param string   $to      Recipient.
	 * @param string   $subject Subject.
	 * @param string[] $lines   Body lines.
	 * @return bool Whether WordPress accepted the message.
	 */
	public static function send( string $to, string $subject, array $lines ): bool {
		$to = sanitize_email( $to );
		if ( ! $to || ! is_email( $to ) ) {
			return false;
		}

		$body    = implode( "\n", array_map( 'sanitize_textarea_field', $lines ) ) . "\n\n— ESTORA";
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		$sent = wp_mail( $to, sanitize_text_field( $subject ), $body, $headers );

		if ( ! $sent ) {
			self::log_failure( $to, $subject, 'wp_mail returned false' );
		}

		return (bool) $sent;
	}

	/**
	 * Append a failure record to uploads/estora-mail.log.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $reason  Reason.
	 */
	public static function log_failure( string $to, string $subject, string $reason ): void {
		$uploads = wp_upload_dir();
		$path    = trailingslashit( $uploads['basedir'] ) . 'estora-mail.log';
		$line    = sprintf(
			"[%s] to=%s subject=%s reason=%s\n",
			estora_now(),
			$to,
			$subject,
			$reason
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		@file_put_contents( $path, $line, FILE_APPEND | LOCK_EX );
		update_option( 'estora_last_mail_error', estora_now(), false );
	}

	/**
	 * Latest failure timestamp, for the admin notice.
	 */
	public static function last_failure(): string {
		return (string) get_option( 'estora_last_mail_error', '' );
	}
}
