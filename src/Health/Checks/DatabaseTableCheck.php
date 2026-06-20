<?php
/**
 * Log database table check.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Health\Checks;

use Vs\Download\Database\Activator;
use Vs\Download\Health\HealthCheckInterface;
use Vs\Download\Health\HealthCheckResult;

/**
 * Verifies wp_lwd_download_logs exists.
 */
final class DatabaseTableCheck implements HealthCheckInterface {

	public function run(): HealthCheckResult {
		global $wpdb;

		$table    = $wpdb->prefix . 'lwd_download_logs';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
		$db_ver   = (string) get_option( Activator::DB_VERSION_OPTION, '' );

		if ( $exists ) {
			return new HealthCheckResult(
				'database_table',
				HealthCheckResult::STATUS_PASS,
				__( 'Download log table', 'vs-download' ),
				sprintf(
					/* translators: 1: table name, 2: schema version */
					__( 'Table %1$s exists (schema %2$s).', 'vs-download' ),
					$table,
					$db_ver ?: Activator::DB_VERSION
				)
			);
		}

		return new HealthCheckResult(
			'database_table',
			HealthCheckResult::STATUS_FAIL,
			__( 'Download log table', 'vs-download' ),
			sprintf(
				/* translators: %s: table name */
				__( 'Table %s was not found.', 'vs-download' ),
				$table
			),
			__( 'Deactivate and reactivate LW Download, or visit Tools → Environment and run checks again to trigger schema creation.', 'vs-download' )
		);
	}
}
