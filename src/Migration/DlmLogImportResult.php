<?php
/**
 * Standard responses for batched DLM log import.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Migration;

use Vs\Download\Database\DownloadCountSyncer;

/**
 * Builds success payloads for log migration batches.
 */
final class DlmLogImportResult {

	/**
	 * @return array<string, mixed>
	 */
	public static function finish( int $offset, bool $legacy = false ): array {
		$synced = DownloadCountSyncer::sync_from_logs();
		$label  = $legacy
			? __( 'Legacy log import complete. Synced %d download counts.', 'vs-download' )
			: __( 'Log import complete. Synced counts for %d downloads.', 'vs-download' );

		return [
			'success'  => true,
			'message'  => sprintf( $label, $synced ),
			'done'     => true,
			'imported' => 0,
			'offset'   => $offset,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function batch( int $imported, int $skipped, int $next_offset, bool $done ): array {
		return [
			'success'  => true,
			'message'  => sprintf(
				__( 'Imported %1$d log rows (%2$d skipped). Continuing…', 'vs-download' ),
				$imported,
				$skipped
			),
			'done'     => $done,
			'imported' => $imported,
			'skipped'  => $skipped,
			'offset'   => $next_offset,
		];
	}
}
