<?php
/**
 * Import legacy download_monitor_log rows.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Migration;

use Vs\Download\Database\LogRepository;

/**
 * Batch-imports from legacy Download Monitor log table.
 */
final class DlmLegacyLogImporter {

	private LogRepository $logger;

	public function __construct( ?LogRepository $logger = null ) {
		$this->logger = $logger ?? new LogRepository();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function import_batch( int $offset ): array {
		global $wpdb;

		$table = $wpdb->prefix . DlmDetector::TABLE_LEGACY_LOG;
		if ( ! DlmDetector::table_exists( $table ) ) {
			return [
				'success' => false,
				'message' => __( 'Legacy log table not found.', 'vs-download' ),
				'done'    => true,
			];
		}

		$map      = DlmMigrationMap::get();
		$imported = 0;
		$skipped  = 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` ORDER BY id ASC LIMIT %d OFFSET %d",
				DlmMigrationMap::BATCH_LOGS,
				$offset
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return DlmLogImportResult::finish( $offset, true );
		}

		foreach ( $rows as $row ) {
			$file_id = (int) ( $row['download_id'] ?? $row['file_id'] ?? 0 );
			$lwd_id  = (int) ( $map[ $file_id ] ?? $map[ 'legacy_' . $file_id ] ?? 0 );

			if ( $lwd_id <= 0 ) {
				++$skipped;
				continue;
			}

			$date = (string) ( $row['access_date'] ?? $row['download_date'] ?? $row['date'] ?? current_time( 'mysql' ) );

			if ( false !== $this->logger->insert_historical_log(
				$lwd_id,
				(int) ( $row['user_id'] ?? 0 ),
				(string) ( $row['user_ip'] ?? $row['ip'] ?? '0.0.0.0' ),
				$date
			) ) {
				++$imported;
			}
		}

		return DlmLogImportResult::batch( $imported, $skipped, $offset + DlmMigrationMap::BATCH_LOGS, false );
	}
}
