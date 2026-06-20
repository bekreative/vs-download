<?php
/**
 * Import legacy Download Monitor taxonomy relationships.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Migration;

use Vs\Download\Taxonomies\DownloadCategory;
use Vs\Download\Taxonomies\DownloadTag;

/**
 * Reads download_monitor_taxonomies / relationships tables.
 */
final class DlmLegacyTaxonomyImporter {

	public static function assign_for_file( int $legacy_file_id, int $lwd_id ): void {
		global $wpdb;

		$tax_table = $wpdb->prefix . 'download_monitor_taxonomies';
		$rel_table = $wpdb->prefix . 'download_monitor_relationships';

		if ( ! DlmDetector::table_exists( $tax_table ) || ! DlmDetector::table_exists( $rel_table ) ) {
			return;
		}

		foreach ( [ 'category' => DownloadCategory::TAXONOMY, 'tag' => DownloadTag::TAXONOMY ] as $dlm_tax => $lwd_tax ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$names = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT T.name FROM `{$tax_table}` AS T
					LEFT JOIN `{$rel_table}` AS R ON T.id = R.taxonomy_id
					WHERE R.download_id = %d AND T.taxonomy = %s",
					$legacy_file_id,
					$dlm_tax
				)
			);
			if ( $names ) {
				DlmTermAssigner::assign( $lwd_id, $names, $lwd_tax );
			}
		}
	}
}
