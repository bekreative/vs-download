<?php
/**
 * Import modern DLM wp_download_log rows.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Migration;

use Vs\Download\Database\LogRepository;

/**
 * Batch-imports from DLM download_log table.
 */
final class DlmModernLogImporter {

	private LogRepository $logger;

	public function __construct( ?LogRepository $logger = null ) {
		$this->logger = $logger ?? new LogRepository();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function import_batch( int $offset ): array {
		global $wpdb;

		$table = $wpdb->prefix . DlmDetector::TABLE_LOG_MODERN;
		if ( ! DlmDetector::table_exists( $table ) ) {
			return [
				'success' => false,
				'message' => __( 'DLM download_log table not found.', 'vs-download' ),
				'done'    => true,
			];
		}

		$map      = DlmMigrationMap::get();
		$imported = 0;
		$skipped  = 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT download_id, user_id, user_ip, download_date, download_status
				FROM `{$table}`
				ORDER BY ID ASC
				LIMIT %d OFFSET %d",
				DlmMigrationMap::BATCH_LOGS,
				$offset
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return DlmLogImportResult::finish( $offset );
		}

		foreach ( $rows as $row ) {
			$lwd_id = (int) ( $map[ (int) ( $row['download_id'] ?? 0 ) ] ?? 0 );
			if ( $lwd_id <= 0 || ! get_post( $lwd_id ) ) {
				++$skipped;
				continue;
			}

			$status = (string) ( $row['download_status'] ?? '' );
			if ( in_array( $status, [ 'failed', 'cancelled' ], true ) ) {
				++$skipped;
				continue;
			}

			$date = (string) ( $row['download_date'] ?? '' );
			if ( '' === $date || '0000-00-00 00:00:00' === $date ) {
				$date = current_time( 'mysql' );
			}

			if ( false !== $this->logger->insert_historical_log(
				$lwd_id,
				(int) ( $row['user_id'] ?? 0 ),
				(string) ( $row['user_ip'] ?? '0.0.0.0' ),
				$date
			) ) {
				++$imported;
			}
		}

		return DlmLogImportResult::batch( $imported, $skipped, $offset + DlmMigrationMap::BATCH_LOGS, false );
	}
}
