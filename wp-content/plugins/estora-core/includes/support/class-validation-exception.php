<?php
/**
 * Business validation failure.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Support;

defined( 'ABSPATH' ) || exit;

class Validation_Exception extends \RuntimeException {

	/**
	 * Field errors.
	 *
	 * @var array<string,string[]>
	 */
	private array $errors;

	/**
	 * HTTP status.
	 *
	 * @var int
	 */
	private int $status;

	/**
	 * Constructor.
	 *
	 * @param string                 $message Human message.
	 * @param array<string,string[]> $errors  Field errors.
	 * @param int                    $status  HTTP status.
	 */
	public function __construct( string $message, array $errors = array(), int $status = 422 ) {
		parent::__construct( $message );
		$this->errors = $errors;
		$this->status = $status;
	}

	/**
	 * Field errors.
	 *
	 * @return array<string,string[]>
	 */
	public function get_errors(): array {
		return $this->errors;
	}

	/**
	 * HTTP status code.
	 */
	public function get_status(): int {
		return $this->status;
	}
}
