<?php
/**
 * PHP memory limit check.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Health\Checks;

use Vs\Download\Health\HealthCheckInterface;
use Vs\Download\Health\HealthCheckResult;

/**
 * Warns when memory_limit is below 128M.
 */
final class MemoryLimitCheck implements HealthCheckInterface {

	private const RECOMMENDED_BYTES = 134217728; // 128M.

	public function run(): HealthCheckResult {
		$raw   = (string) ini_get( 'memory_limit' );
		$bytes = wp_convert_hr_to_bytes( $raw );

		if ( $bytes >= self::RECOMMENDED_BYTES || -1 === $bytes ) {
			return new HealthCheckResult(
				'memory_limit',
				HealthCheckResult::STATUS_PASS,
				__( 'PHP memory limit', 'vs-download' ),
				sprintf(
					/* translators: %s: memory_limit value */
					__( 'memory_limit is %s.', 'vs-download' ),
					$raw ?: 'unknown'
				)
			);
		}

		return new HealthCheckResult(
			'memory_limit',
			HealthCheckResult::STATUS_WARN,
			__( 'PHP memory limit', 'vs-download' ),
			sprintf(
				/* translators: 1: current limit, 2: recommended */
				__( 'memory_limit is %1$s (recommended at least %2$s for large log imports).', 'vs-download' ),
				$raw,
				'128M'
			),
			__( 'Increase memory_limit in php.ini or your hosting panel before importing large DLM log tables.', 'vs-download' )
		);
	}
}
