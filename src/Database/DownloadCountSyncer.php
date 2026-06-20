<?php
/**
 * Sync cached download counts from the log table.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Database;

/**
 * Rebuilds _lwd_download_count post meta from log aggregates.
 */
final class DownloadCountSyncer {

	/**
	 * @return int Number of download posts updated.
	 */
	public static function sync_from_logs(): int {
		global $wpdb;

		$logs_table = $wpdb->prefix . 'lwd_download_logs';
		$updated    = 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT download_id, COUNT(id) AS total FROM `{$logs_table}` GROUP BY download_id",
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			$download_id = (int) ( $row['download_id'] ?? 0 );
			$total       = (int) ( $row['total'] ?? 0 );
			if ( $download_id > 0 ) {
				update_post_meta( $download_id, '_lwd_download_count', $total );
				++$updated;
			}
		}

		return $updated;
	}
}
