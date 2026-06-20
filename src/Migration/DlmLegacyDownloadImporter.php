<?php
/**
 * Import legacy Download Monitor file rows.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Migration;

use Vs\Download\Download\AccessValidator;
use Vs\Download\PostTypes\Download;

/**
 * Creates lwd_download posts from download_monitor_files table.
 */
final class DlmLegacyDownloadImporter {

	/**
	 * @return array<string, mixed>
	 */
	public function import( bool $skip_existing ): array {
		global $wpdb;

		$table = $wpdb->prefix . DlmDetector::TABLE_LEGACY_FILES;
		if ( ! DlmDetector::table_exists( $table ) ) {
			return [
				'success' => false,
				'message' => __( 'Legacy Download Monitor files table not found.', 'vs-download' ),
				'created' => 0,
			];
		}

		$map     = DlmMigrationMap::get();
		$created = 0;
		$skipped = 0;
		$errors  = 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY id ASC", ARRAY_A );

		foreach ( $rows as $row ) {
			$legacy_id = (int) ( $row['id'] ?? 0 );
			if ( ! $legacy_id ) {
				continue;
			}

			$map_key = 'legacy_' . $legacy_id;
			if ( $skip_existing && isset( $map[ $map_key ] ) && get_post( (int) $map[ $map_key ] ) ) {
				++$skipped;
				continue;
			}

			$new_id = $this->import_row( $row, $legacy_id );
			if ( $new_id > 0 ) {
				$map[ $map_key ]   = $new_id;
				$map[ $legacy_id ] = $new_id;
				++$created;
			} else {
				++$errors;
			}
		}

		DlmMigrationMap::save( $map );

		return [
			'success' => true,
			'message' => sprintf(
				__( 'Legacy files imported: %1$d created, %2$d skipped, %3$d errors.', 'vs-download' ),
				$created,
				$skipped,
				$errors
			),
			'created' => $created,
			'skipped' => $skipped,
			'errors'  => $errors,
		];
	}

	/**
	 * @param array<string, mixed> $row Legacy file row.
	 */
	private function import_row( array $row, int $legacy_id ): int {
		$author = 1;
		if ( ! empty( $row['user'] ) ) {
			$user = get_user_by( 'login', (string) $row['user'] );
			if ( $user ) {
				$author = (int) $user->ID;
			}
		}

		$post_id = wp_insert_post(
			[
				'post_title'   => (string) ( $row['title'] ?? __( 'Download', 'vs-download' ) ),
				'post_content' => (string) ( $row['file_description'] ?? '' ),
				'post_status'  => 'publish',
				'post_author'  => $author,
				'post_date'    => ! empty( $row['postDate'] ) ? (string) $row['postDate'] : current_time( 'mysql' ),
				'post_type'    => Download::POST_TYPE,
			],
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		$post_id = (int) $post_id;
		update_post_meta( $post_id, DlmMigrationMap::META_LEGACY_FILE_ID, $legacy_id );
		update_post_meta( $post_id, DlmMigrationMap::META_LEGACY_DLM_ID, $legacy_id );

		$urls = [];
		if ( ! empty( $row['filename'] ) ) {
			$urls[] = (string) $row['filename'];
		}
		if ( ! empty( $row['mirrors'] ) ) {
			$urls = array_merge( $urls, array_filter( array_map( 'trim', explode( "\n", (string) $row['mirrors'] ) ) ) );
		}

		$label = ! empty( $row['dlversion'] ) ? (string) $row['dlversion'] : '1.0';
		DlmVersionImporter::from_paths( $post_id, $urls, $label );

		if ( ! empty( $row['members'] ) && 1 === (int) $row['members'] ) {
			update_post_meta( $post_id, '_lwd_access', AccessValidator::ACCESS_LOGGED_IN );
		}

		$hits = (int) ( $row['hits'] ?? 0 );
		if ( $hits > 0 ) {
			update_post_meta( $post_id, '_lwd_download_count', $hits );
		}

		DlmLegacyTaxonomyImporter::assign_for_file( $legacy_id, $post_id );

		return $post_id;
	}
}
