<?php
/**
 * Upload hardening in addition to WordPress core checks.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Integrations;

defined( 'ABSPATH' ) || exit;

final class Upload_Guard {

	private const MAX_BYTES = 5 * 1024 * 1024;

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'inspect' ) );
	}

	/**
	 * Reject executables and oversized files before they reach the uploader.
	 *
	 * @param array<string,mixed> $file Upload array.
	 * @return array<string,mixed>
	 */
	public function inspect( $file ) {
		if ( ! is_array( $file ) || empty( $file['name'] ) ) {
			return $file;
		}

		$name = sanitize_file_name( (string) $file['name'] );
		$ext  = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
		$bad  = array( 'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pl', 'py', 'sh', 'exe', 'com', 'bat', 'js', 'html', 'htm', 'svg' );

		if ( in_array( $ext, $bad, true ) ) {
			$file['error'] = __( 'This file type is not allowed. Upload a JPG, PNG or WebP image.', 'estora-core' );
			return $file;
		}

		if ( isset( $file['size'] ) && (int) $file['size'] > self::MAX_BYTES ) {
			$file['error'] = __( 'The file is too large. The maximum size is 5 MB.', 'estora-core' );
		}

		return $file;
	}
}
