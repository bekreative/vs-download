<?php
/**
 * WP-Cron check.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Health\Checks;

use Vs\Download\Database\LogPruner;
use Vs\Download\Health\HealthCheckInterface;
use Vs\Download\Health\HealthCheckResult;

/**
 * Warns when WP-Cron is disabled without a system cron substitute.
 */
final class CronCheck implements HealthCheckInterface {

	public function run(): HealthCheckResult {
		$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$scheduled = (bool) wp_next_scheduled( LogPruner::CRON_HOOK );

		if ( ! $disabled ) {
			return new HealthCheckResult(
				'wp_cron',
				HealthCheckResult::STATUS_PASS,
				__( 'WP-Cron', 'vs-download' ),
				$scheduled
					? __( 'WP-Cron is enabled and log pruning is scheduled.', 'vs-download' )
					: __( 'WP-Cron is enabled (log pruning will schedule on next request).', 'vs-download' )
			);
		}

		return new HealthCheckResult(
			'wp_cron',
			HealthCheckResult::STATUS_WARN,
			__( 'WP-Cron', 'vs-download' ),
			__( 'DISABLE_WP_CRON is set; log retention pruning may not run automatically.', 'vs-download' ),
			__( 'Configure a system cron job to call wp-cron.php on a schedule, or remove DISABLE_WP_CRON.', 'vs-download' )
		);
	}
}
