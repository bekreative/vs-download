<?php
/**
 * Detect Download Monitor data available for migration.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Migration;

/**
 * Scans the database for DLM modern and legacy sources.
 */
final class DlmDetector {

	public const CPT_DOWNLOAD         = 'dlm_download';
	public const CPT_VERSION            = 'dlm_download_version';
	public const TAX_CATEGORY           = 'dlm_download_category';
	public const TAX_TAG                = 'dlm_download_tag';
	public const TABLE_LOG_MODERN       = 'download_log';
	public const TABLE_LEGACY_FILES     = 'download_monitor_files';
	public const TABLE_LEGACY_LOG       = 'download_monitor_log';

	/**
	 * @return array<string, mixed>
	 */
	public static function scan(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$modern_downloads = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != 'trash'",
				self::CPT_DOWNLOAD
			)
		);

		$modern_versions = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != 'trash'",
				self::CPT_VERSION
			)
		);

		$log_table   = $wpdb->prefix . self::TABLE_LOG_MODERN;
		$log_count   = self::table_exists( $log_table )
			? (int) $wpdb->get_var( "SELECT COUNT(ID) FROM `{$log_table}`" )
			: 0;

		$legacy_files_table = $wpdb->prefix . self::TABLE_LEGACY_FILES;
		$legacy_files       = self::table_exists( $legacy_files_table )
			? (int) $wpdb->get_var( "SELECT COUNT(id) FROM `{$legacy_files_table}`" )
			: 0;

		$legacy_log_table = $wpdb->prefix . self::TABLE_LEGACY_LOG;
		$legacy_log       = self::table_exists( $legacy_log_table )
			? (int) $wpdb->get_var( "SELECT COUNT(id) FROM `{$legacy_log_table}`" )
			: 0;

		$mapped = count( (array) get_option( DownloadMonitorImporter::OPTION_MAP, [] ) );

		return [
			'modern_downloads'  => $modern_downloads,
			'modern_versions'   => $modern_versions,
			'modern_log_table'  => $log_table,
			'modern_log_count'  => $log_count,
			'legacy_files'      => $legacy_files,
			'legacy_log_count'  => $legacy_log,
			'already_mapped'    => $mapped,
			'can_import_modern' => $modern_downloads > 0 || $modern_versions > 0,
			'can_import_legacy' => $legacy_files > 0 && 0 === $modern_downloads,
			'ready'             => $modern_downloads > 0 || $legacy_files > 0,
		];
	}

	/**
	 * @param string $table Full table name with prefix.
	 */
	public static function table_exists( string $table ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}
}
