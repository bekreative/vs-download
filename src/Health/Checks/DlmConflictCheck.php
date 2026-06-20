<?php
/**
 * Download Monitor coexistence check.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Health\Checks;

use Vs\Download\Health\HealthCheckInterface;
use Vs\Download\Health\HealthCheckResult;
use Vs\Download\Migration\DlmDetector;

/**
 * Warns when DLM is still active after migration window.
 */
final class DlmConflictCheck implements HealthCheckInterface {

	public function run(): HealthCheckResult {
		$scan = DlmDetector::scan();

		if ( empty( $scan['ready'] ) ) {
			return new HealthCheckResult(
				'dlm_conflict',
				HealthCheckResult::STATUS_PASS,
				__( 'Download Monitor', 'vs-download' ),
				__( 'No Download Monitor data detected on this site.', 'vs-download' )
			);
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$dlm_active = is_plugin_active( 'download-monitor/download-monitor.php' )
			|| class_exists( 'WP_DLM', false );

		if ( ! $dlm_active && ! post_type_exists( DlmDetector::CPT_DOWNLOAD ) ) {
			return new HealthCheckResult(
				'dlm_conflict',
				HealthCheckResult::STATUS_PASS,
				__( 'Download Monitor', 'vs-download' ),
				__( 'DLM data exists but the DLM plugin does not appear active.', 'vs-download' )
			);
		}

		return new HealthCheckResult(
			'dlm_conflict',
			HealthCheckResult::STATUS_WARN,
			__( 'Download Monitor', 'vs-download' ),
			sprintf(
				/* translators: %d: number of DLM downloads */
				__( 'Download Monitor data is present (%d downloads). Both plugins may handle similar URLs and menus.', 'vs-download' ),
				(int) $scan['modern_downloads'] + (int) $scan['legacy_files']
			),
			__( 'Import data via Tools → Migration, then deactivate Download Monitor to avoid duplicate download handling.', 'vs-download' )
		);
	}
}
