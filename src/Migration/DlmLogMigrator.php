<?php
/**
 * Orchestrate DLM log table migration.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Migration;

/**
 * Batch-imports DLM log rows into lwd_download_logs.
 */
final class DlmLogMigrator {

	private DlmModernLogImporter $modern;
	private DlmLegacyLogImporter $legacy;

	public function __construct() {
		$this->modern = new DlmModernLogImporter();
		$this->legacy = new DlmLegacyLogImporter();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function import_batch( int $offset, bool $modern ): array {
		return $modern
			? $this->modern->import_batch( $offset )
			: $this->legacy->import_batch( $offset );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function import_all( bool $modern ): array {
		$total_imported = 0;
		$total_skipped  = 0;
		$offset         = 0;
		$message        = '';

		do {
			$result = $this->import_batch( $offset, $modern );
			$total_imported += (int) ( $result['imported'] ?? 0 );
			$total_skipped  += (int) ( $result['skipped'] ?? 0 );
			$offset          = (int) ( $result['offset'] ?? 0 );
			$message         = (string) ( $result['message'] ?? '' );
		} while ( empty( $result['done'] ) );

		return [
			'success'  => true,
			'message'  => $message,
			'imported' => $total_imported,
			'skipped'  => $total_skipped,
		];
	}
}
