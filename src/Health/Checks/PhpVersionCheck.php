<?php
/**
 * PHP version check.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Health\Checks;

use Vs\Download\Health\HealthCheckInterface;
use Vs\Download\Health\HealthCheckResult;

/**
 * Requires PHP 8.1+.
 */
final class PhpVersionCheck implements HealthCheckInterface {

	public function run(): HealthCheckResult {
		$required = '8.1.0';
		$current  = PHP_VERSION;
		$ok       = version_compare( $current, $required, '>=' );

		return new HealthCheckResult(
			'php_version',
			$ok ? HealthCheckResult::STATUS_PASS : HealthCheckResult::STATUS_FAIL,
			__( 'PHP version', 'vs-download' ),
			sprintf(
				/* translators: 1: current PHP version, 2: required version */
				__( 'Running PHP %1$s (required %2$s or newer).', 'vs-download' ),
				$current,
				$required
			),
			$ok ? '' : __( 'Upgrade PHP on your host or switch to a hosting plan that supports PHP 8.1+.', 'vs-download' )
		);
	}
}
