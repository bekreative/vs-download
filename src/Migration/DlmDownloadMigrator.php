<?php
/**
 * Facade for DLM download post migration.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Migration;

/**
 * Delegates modern CPT and legacy table imports.
 */
final class DlmDownloadMigrator {

	private DlmModernDownloadImporter $modern;
	private DlmLegacyDownloadImporter $legacy;

	public function __construct() {
		$this->modern = new DlmModernDownloadImporter();
		$this->legacy = new DlmLegacyDownloadImporter();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function import_modern( bool $skip_existing ): array {
		return $this->modern->import( $skip_existing );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function import_legacy( bool $skip_existing ): array {
		return $this->legacy->import( $skip_existing );
	}
}
