<?php
/**
 * WP-CLI commands for LW Download.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\CLI;

use Vs\Download\Health\HealthCheckRunner;
use Vs\Download\Health\HealthReportStore;
use Vs\Download\Migration\DlmDetector;
use Vs\Download\Migration\DownloadMonitorImporter;
use WP_CLI;

/**
 * WP-CLI integration.
 */
final class CLI {

	/**
	 * Register commands when WP-CLI is available.
	 */
	public static function register(): void {
		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		WP_CLI::add_command( 'vs-download dlm-status', [ self::class, 'dlm_status' ] );
		WP_CLI::add_command( 'vs-download import-dlm', [ self::class, 'import_dlm' ] );
		WP_CLI::add_command( 'vs-download health', [ self::class, 'health' ] );
	}

	/**
	 * Run host environment checks.
	 *
	 * ## EXAMPLES
	 *
	 *     wp vs-download health
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public static function health( array $args, array $assoc_args ): void {
		$runner = new HealthCheckRunner();
		$report = $runner->run_and_store();

		foreach ( $report as $row ) {
			$status = strtoupper( (string) ( $row['status'] ?? '' ) );
			$label  = (string) ( $row['label'] ?? '' );
			$msg    = (string) ( $row['message'] ?? '' );
			WP_CLI::line( "[{$status}] {$label}: {$msg}" );
			$rec = (string) ( $row['recommendation'] ?? '' );
			if ( '' !== $rec ) {
				WP_CLI::line( '  → ' . $rec );
			}
		}

		$counts = HealthReportStore::count_statuses( $report );
		if ( (int) $counts['fail'] > 0 ) {
			WP_CLI::error( sprintf( 'Environment checks finished with %d failure(s).', (int) $counts['fail'] ) );
		}
		if ( (int) $counts['warn'] > 0 ) {
			WP_CLI::warning( sprintf( 'Environment checks finished with %d warning(s).', (int) $counts['warn'] ) );
			return;
		}
		WP_CLI::success( 'All environment checks passed.' );
	}

	/**
	 * Show Download Monitor data detected on this site.
	 *
	 * ## EXAMPLES
	 *
	 *     wp vs-download dlm-status
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public static function dlm_status( array $args, array $assoc_args ): void {
		$scan = DlmDetector::scan();

		WP_CLI::line( 'Download Monitor migration scan:' );
		WP_CLI::line( '- Modern downloads (dlm_download): ' . (int) $scan['modern_downloads'] );
		WP_CLI::line( '- Modern versions (dlm_download_version): ' . (int) $scan['modern_versions'] );
		WP_CLI::line( '- Modern log table: ' . (string) $scan['modern_log_table'] );
		WP_CLI::line( '- Modern log rows: ' . (int) $scan['modern_log_count'] );
		WP_CLI::line( '- Legacy files table rows: ' . (int) $scan['legacy_files'] );
		WP_CLI::line( '- Legacy log rows: ' . (int) $scan['legacy_log_count'] );
		WP_CLI::line( '- Already mapped to LW Download: ' . (int) $scan['already_mapped'] );

		if ( empty( $scan['ready'] ) ) {
			WP_CLI::warning( 'No Download Monitor data found.' );
			return;
		}

		WP_CLI::success( 'DLM data is available for import.' );
	}

	/**
	 * Import downloads and/or logs from Download Monitor.
	 *
	 * ## OPTIONS
	 *
	 * [--downloads-only]
	 * : Import only download posts and file URLs (skip logs).
	 *
	 * [--logs-only]
	 * : Import only download logs (requires prior download import).
	 *
	 * [--legacy-logs]
	 * : Use legacy download_monitor_log table instead of modern download_log.
	 *
	 * [--force]
	 * : Re-import downloads even if already mapped (may create duplicates).
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp vs-download import-dlm
	 *     wp vs-download import-dlm --downloads-only
	 *     wp vs-download import-dlm --logs-only --legacy-logs
	 *     wp vs-download import-dlm --yes
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public static function import_dlm( array $args, array $assoc_args ): void {
		$scan = DlmDetector::scan();

		if ( empty( $scan['ready'] ) ) {
			WP_CLI::error( 'No Download Monitor data found on this site.' );
		}

		$downloads_only = isset( $assoc_args['downloads-only'] );
		$logs_only      = isset( $assoc_args['logs-only'] );
		$legacy_logs    = isset( $assoc_args['legacy-logs'] );
		$skip_existing  = ! isset( $assoc_args['force'] );
		$yes            = isset( $assoc_args['yes'] );

		if ( $downloads_only && $logs_only ) {
			WP_CLI::error( 'Use only one of --downloads-only or --logs-only.' );
		}

		if ( ! $yes && ! $logs_only ) {
			WP_CLI::confirm( 'Import Download Monitor downloads into LW Download?' );
		}

		if ( ! $logs_only ) {
			WP_CLI::log( 'Importing downloads and file metadata…' );
			$result = DownloadMonitorImporter::import_downloads( $skip_existing );

			if ( empty( $result['success'] ) ) {
				WP_CLI::error( (string) ( $result['message'] ?? 'Download import failed.' ) );
			}

			WP_CLI::success( (string) $result['message'] );
		}

		if ( $downloads_only ) {
			return;
		}

		if ( ! $yes ) {
			WP_CLI::confirm( 'Import all download log rows (statistics)? This may take a while.' );
		}

		$modern = ! $legacy_logs;
		$source = $modern ? 'download_log' : 'download_monitor_log';
		WP_CLI::log( "Importing logs from {$source}…" );

		$log_result = DownloadMonitorImporter::import_all_logs( $modern );

		if ( empty( $log_result['success'] ) ) {
			WP_CLI::error( (string) ( $log_result['message'] ?? 'Log import failed.' ) );
		}

		WP_CLI::success( (string) $log_result['message'] );

		if ( isset( $log_result['imported'], $log_result['skipped'] ) ) {
			WP_CLI::line(
				sprintf(
					'Log rows: %d imported, %d skipped.',
					(int) $log_result['imported'],
					(int) $log_result['skipped']
				)
			);
		}
	}
}
