<?php
/**
 * Facade for Download Monitor → LW Download migration.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Migration;

use Vs\Download\Database\DownloadCountSyncer;

/**
 * Public API for DLM import (admin UI, WP-CLI).
 */
final class DownloadMonitorImporter {

	/** @deprecated Use DlmMigrationMap::OPTION_MAP */
	public const OPTION_MAP = DlmMigrationMap::OPTION_MAP;

	/** @deprecated Use DlmMigrationMap::META_LEGACY_DLM_ID */
	public const META_LEGACY_DLM_ID = DlmMigrationMap::META_LEGACY_DLM_ID;

	/** @deprecated Use DlmMigrationMap::META_LEGACY_FILE_ID */
	public const META_LEGACY_FILE_ID = DlmMigrationMap::META_LEGACY_FILE_ID;

	/** @deprecated Use DlmMigrationMap::BATCH_LOGS */
	public const BATCH_LOGS = DlmMigrationMap::BATCH_LOGS;

	private static ?DlmDownloadMigrator $downloads = null;
	private static ?DlmLogMigrator $logs = null;

	/**
	 * @return array<string, mixed>
	 */
	public static function import_downloads( bool $skip_existing = true ): array {
		$migrator = self::downloads();
		$result   = $migrator->import_modern( $skip_existing );

		if ( empty( $result['found'] ) ) {
			$legacy = $migrator->import_legacy( $skip_existing );
			return array_merge(
				[
					'success' => true,
					'message' => __( 'No modern DLM downloads found; legacy import attempted.', 'vs-download' ),
				],
				$legacy
			);
		}

		return [
			'success' => true,
			'message' => sprintf(
				__( 'Downloads imported: %1$d created, %2$d skipped, %3$d errors.', 'vs-download' ),
				(int) $result['created'],
				(int) $result['skipped'],
				(int) $result['errors']
			),
			'created' => (int) $result['created'],
			'skipped' => (int) $result['skipped'],
			'errors'  => (int) $result['errors'],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function import_legacy_files( bool $skip_existing = true ): array {
		return self::downloads()->import_legacy( $skip_existing );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function import_logs( int $offset = 0, bool $modern = true ): array {
		return self::logs()->import_batch( $offset, $modern );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function import_all_logs( bool $modern = true ): array {
		return self::logs()->import_all( $modern );
	}

	public static function sync_download_counts(): int {
		return DownloadCountSyncer::sync_from_logs();
	}

	/**
	 * @return array<int|string, int>
	 */
	public static function get_map(): array {
		return DlmMigrationMap::get();
	}

	private static function downloads(): DlmDownloadMigrator {
		if ( null === self::$downloads ) {
			self::$downloads = new DlmDownloadMigrator();
		}
		return self::$downloads;
	}

	private static function logs(): DlmLogMigrator {
		if ( null === self::$logs ) {
			self::$logs = new DlmLogMigrator();
		}
		return self::$logs;
	}
}
