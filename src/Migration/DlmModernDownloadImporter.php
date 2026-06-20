<?php
/**
 * Import modern DLM dlm_download posts.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Migration;

use Vs\Download\Download\AccessValidator;
use Vs\Download\Download\DownloadUrl;
use Vs\Download\PostTypes\Download;
use Vs\Download\Taxonomies\DownloadCategory;
use Vs\Download\Taxonomies\DownloadTag;

/**
 * Creates lwd_download posts from DLM CPT entries.
 */
final class DlmModernDownloadImporter {

	/**
	 * @return array<string, mixed>
	 */
	public function import( bool $skip_existing ): array {
		$map     = DlmMigrationMap::get();
		$created = 0;
		$skipped = 0;
		$errors  = 0;

		$posts = get_posts(
			[
				'post_type'      => DlmDetector::CPT_DOWNLOAD,
				'post_status'    => [ 'publish', 'draft', 'private', 'pending' ],
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			]
		);

		if ( empty( $posts ) ) {
			return [ 'found' => false ];
		}

		foreach ( $posts as $post ) {
			$dlm_id = (int) $post->ID;
			if ( $skip_existing && isset( $map[ $dlm_id ] ) && get_post( (int) $map[ $dlm_id ] ) ) {
				++$skipped;
				continue;
			}

			$new_id = $this->import_one( $post );
			if ( $new_id > 0 ) {
				$map[ $dlm_id ] = $new_id;
				++$created;
			} else {
				++$errors;
			}
		}

		DlmMigrationMap::save( $map );

		return [
			'found'   => true,
			'created' => $created,
			'skipped' => $skipped,
			'errors'  => $errors,
		];
	}

	private function import_one( \WP_Post $post ): int {
		$dlm_id = (int) $post->ID;

		$post_id = $this->ensure_post_with_id( $post );
		if ( $post_id <= 0 ) {
			return 0;
		}

		update_post_meta( $post_id, DlmMigrationMap::META_LEGACY_DLM_ID, $dlm_id );
		update_post_meta( $post_id, '_lwd_legacy_dlm_url', DownloadUrl::legacy_dlm_url( $dlm_id ) );

		DlmVersionImporter::apply_from_dlm_post( $dlm_id, $post_id );

		$members = get_post_meta( $dlm_id, '_members_only', true );
		update_post_meta(
			$post_id,
			'_lwd_access',
			'yes' === $members ? AccessValidator::ACCESS_LOGGED_IN : AccessValidator::ACCESS_PUBLIC
		);

		$categories = wp_get_post_terms( $dlm_id, DlmDetector::TAX_CATEGORY, [ 'fields' => 'names' ] );
		if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
			DlmTermAssigner::assign( $post_id, $categories, DownloadCategory::TAXONOMY );
		}

		$tags = wp_get_post_terms( $dlm_id, DlmDetector::TAX_TAG, [ 'fields' => 'names' ] );
		if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
			DlmTermAssigner::assign( $post_id, $tags, DownloadTag::TAXONOMY );
		}

		$thumb = (int) get_post_thumbnail_id( $dlm_id );
		if ( $thumb > 0 ) {
			set_post_thumbnail( $post_id, $thumb );
		}

		$meta_count = (int) get_post_meta( $dlm_id, '_download_count', true );
		if ( $meta_count > 0 ) {
			update_post_meta( $post_id, '_lwd_download_count', $meta_count );
		}

		return $post_id;
	}

	/**
	 * Keep the original DLM post ID so ?post_type=dlm_download&p={id} keeps working.
	 */
	private function ensure_post_with_id( \WP_Post $post ): int {
		$dlm_id = (int) $post->ID;

		if ( DlmDetector::CPT_DOWNLOAD === $post->post_type ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$updated = $wpdb->update(
				$wpdb->posts,
				[ 'post_type' => Download::POST_TYPE ],
				[
					'ID'        => $dlm_id,
					'post_type' => DlmDetector::CPT_DOWNLOAD,
				],
				[ '%s' ],
				[ '%d', '%s' ]
			);

			if ( false === $updated ) {
				return 0;
			}

			clean_post_cache( $dlm_id );
			return $dlm_id;
		}

		if ( Download::POST_TYPE === $post->post_type ) {
			return $dlm_id;
		}

		$slot = get_post( $dlm_id );
		if ( $slot && Download::POST_TYPE === $slot->post_type ) {
			return $dlm_id;
		}

		$insert = [
			'post_title'   => $post->post_title,
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_status'  => $post->post_status,
			'post_author'  => (int) $post->post_author,
			'post_date'    => $post->post_date,
			'post_name'    => $post->post_name,
			'post_type'    => Download::POST_TYPE,
		];

		if ( ! $slot ) {
			$insert['ID'] = $dlm_id;
		}

		$post_id = wp_insert_post( $insert, true );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}

		return (int) $post_id;
	}
}
