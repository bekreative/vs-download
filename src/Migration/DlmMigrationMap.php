<?php
/**
 * DLM → LW Download ID mapping storage.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Migration;

/**
 * Persists and reads the DLM import ID map option.
 */
final class DlmMigrationMap {

	public const OPTION_MAP          = 'lwd_dlm_migration_map';
	public const META_LEGACY_DLM_ID  = '_lwd_imported_from_dlm';
	public const META_LEGACY_FILE_ID = '_lwd_imported_from_dlm_legacy';
	public const BATCH_LOGS          = 500;

	/**
	 * @return array<int|string, int> dlm_id => lwd_post_id
	 */
	public static function get(): array {
		$map = get_option( self::OPTION_MAP, [] );
		return is_array( $map ) ? $map : [];
	}

	/**
	 * @param array<int|string, int> $map dlm_id => lwd_post_id.
	 */
	public static function save( array $map ): void {
		update_option( self::OPTION_MAP, $map, false );
	}
}
