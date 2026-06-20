<?php
/**
 * Runs all environment health checks.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Health;

use Vs\Download\Health\Checks\AutoloaderCheck;
use Vs\Download\Health\Checks\CronCheck;
use Vs\Download\Health\Checks\DatabaseTableCheck;
use Vs\Download\Health\Checks\DlmConflictCheck;
use Vs\Download\Health\Checks\ExternalUrlCheck;
use Vs\Download\Health\Checks\MemoryLimitCheck;
use Vs\Download\Health\Checks\PhpVersionCheck;
use Vs\Download\Health\Checks\RestApiCheck;

/**
 * Orchestrates host compatibility checks.
 */
final class HealthCheckRunner {

	/**
	 * @return list<HealthCheckInterface>
	 */
	private function checks(): array {
		return [
			new PhpVersionCheck(),
			new AutoloaderCheck(),
			new DatabaseTableCheck(),
			new CronCheck(),
			new DlmConflictCheck(),
			new ExternalUrlCheck(),
			new RestApiCheck(),
			new MemoryLimitCheck(),
		];
	}

	/**
	 * @return list<HealthCheckResult>
	 */
	public function run_all(): array {
		$results = [];
		foreach ( $this->checks() as $check ) {
			$results[] = $check->run();
		}
		return $results;
	}

	/**
	 * Run checks, persist report, clear pending flag.
	 *
	 * @return list<array<string, string>>
	 */
	public function run_and_store(): array {
		$serialized = HealthReportStore::serialize( $this->run_all() );
		HealthReportStore::save_report( $serialized );
		HealthReportStore::clear_pending();
		return $serialized;
	}
}
