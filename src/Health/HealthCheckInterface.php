<?php
/**
 * Health check contract.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Health;

/**
 * One host/environment compatibility check.
 */
interface HealthCheckInterface {

	/**
	 * Run the check.
	 */
	public function run(): HealthCheckResult;
}
