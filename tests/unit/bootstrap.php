<?php
/**
 * PHPUnit bootstrap for dependency-free domain tests.
 *
 * @package Estora\Core
 */

define( 'ABSPATH', __DIR__ . '/../../' );
define( 'ESTORA_CORE_DIR', __DIR__ . '/../../wp-content/plugins/estora-core' );

require_once ESTORA_CORE_DIR . '/includes/support/class-validation-exception.php';
require_once ESTORA_CORE_DIR . '/includes/support/class-validator.php';
require_once ESTORA_CORE_DIR . '/includes/support/class-formatter.php';
require_once ESTORA_CORE_DIR . '/includes/domain/class-lead-status.php';
require_once ESTORA_CORE_DIR . '/includes/domain/class-appointment-status.php';
require_once ESTORA_CORE_DIR . '/includes/domain/class-property-status.php';
require_once ESTORA_CORE_DIR . '/includes/domain/class-availability.php';
