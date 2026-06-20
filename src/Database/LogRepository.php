<?php
/**
 * Log Repository for Tracking Downloads.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Database;

use Vs\Download\Download\BotDetector;

/**
 * LogRepository handles inserting logs into the custom table.
 */
class LogRepository {

	/**
	 * Insert a new log entry (live download).
	 *
	 * @param int    $download_id Target Download Post ID.
	 * @param int    $user_id     ID of the downloader, 0 if guest.
	 * @param string $ip          IP address of the downloader.
	 * @return int|false ID of row inserted or false.
	 */
	public function insert_log( int $download_id, int $user_id, string $ip ): int|false {
		if ( BotDetector::should_skip_logging() ) {
			return false;
		}

		return $this->insert_row( $download_id, $user_id, $ip, current_time( 'mysql' ), true );
	}

	/**
	 * Insert a historical log row (migration); skips bot detection and optional count bump.
	 *
	 * @param int    $download_id   LW Download post ID.
	 * @param int    $user_id       User ID.
	 * @param string $ip            IP address (max 45 chars).
	 * @param string $downloaded_at MySQL datetime.
	 * @return int|false Insert ID or false.
	 */
	public function insert_historical_log(
		int $download_id,
		int $user_id,
		string $ip,
		string $downloaded_at
	): int|false {
		return $this->insert_row( $download_id, $user_id, $ip, $downloaded_at, false );
	}

	/**
	 * @param bool $bump_count When true, increments _lwd_download_count meta.
	 */
	private function insert_row(
		int $download_id,
		int $user_id,
		string $ip,
		string $downloaded_at,
		bool $bump_count
	): int|false {
		global $wpdb;

		if ( strlen( $ip ) > 45 ) {
			$ip = substr( $ip, 0, 45 );
		}

		$table_name = $wpdb->prefix . 'lwd_download_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table_name,
			[
				'download_id'   => $download_id,
				'user_id'       => $user_id,
				'ip_address'    => $ip,
				'downloaded_at' => $downloaded_at,
			],
			[ '%d', '%d', '%s', '%s' ]
		);

		if ( ! $result ) {
			return false;
		}

		$log_id = (int) $wpdb->insert_id;

		if ( $bump_count ) {
			$current_count = (int) get_post_meta( $download_id, '_lwd_download_count', true );
			update_post_meta( $download_id, '_lwd_download_count', $current_count + 1 );
		}

		/**
		 * Fires after a download log row is inserted.
		 *
		 * @param int  $download_id Download post ID.
		 * @param int  $user_id     User ID (0 for guests).
		 * @param int  $log_id      Inserted log row ID.
		 * @param bool $bump_count  Whether count meta was incremented.
		 */
		do_action( 'lwd_after_download_logged', $download_id, $user_id, $log_id );

		return $log_id;
	}
}
