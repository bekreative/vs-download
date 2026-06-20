<?php
/**
 * JSON export/import for LW Download data.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Admin;

use Vs\Download\PostTypes\Download;
use Vs\Download\Taxonomies\DownloadCategory;
use Vs\Download\Taxonomies\DownloadTag;

/**
 * Handles moving downloads (and optional logs) between WordPress sites.
 */
final class DataService {

	public const EXPORT_VERSION = '1.0';

	/**
	 * @return array<string, mixed>
	 */
	public static function export_to_json( bool $include_logs = false ): array {
		$query = new \WP_Query(
			[
				'post_type'      => Download::POST_TYPE,
				'post_status'    => [ 'publish', 'draft', 'private' ],
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			]
		);

		$downloads = [];

		foreach ( $query->posts as $post ) {
			$downloads[] = self::serialize_download( $post );
		}

		$payload = [
			'version'     => self::EXPORT_VERSION,
			'exported_at' => gmdate( 'c' ),
			'site_url'    => home_url(),
			'downloads'   => $downloads,
		];

		if ( $include_logs ) {
			$payload['logs'] = self::export_logs();
		}

		return $payload;
	}

	/**
	 * @param array<string, mixed> $payload Export payload or downloads array.
	 * @return array{success: bool, message: string, imported: int, skipped: int}
	 */
	public static function import_from_json(
		array $payload,
		string $find_url = '',
		string $replace_url = '',
		bool $skip_existing = true
	): array {
		$downloads = $payload['downloads'] ?? $payload;
		if ( ! is_array( $downloads ) ) {
			return [
				'success'  => false,
				'message'  => __( 'Invalid import file: downloads array missing.', 'vs-download' ),
				'imported' => 0,
				'skipped'  => 0,
			];
		}

		$imported     = 0;
		$skipped      = 0;
		$slug_to_id   = [];

		foreach ( $downloads as $item ) {
			if ( ! is_array( $item ) || empty( $item['slug'] ) ) {
				++$skipped;
				continue;
			}

			$slug = sanitize_title( (string) $item['slug'] );

			if ( $skip_existing ) {
				$existing = get_page_by_path( $slug, OBJECT, Download::POST_TYPE );
				if ( $existing ) {
					$slug_to_id[ $slug ] = (int) $existing->ID;
					++$skipped;
					continue;
				}
			}

			$post_id = wp_insert_post(
				[
					'post_title'   => sanitize_text_field( (string) ( $item['title'] ?? $slug ) ),
					'post_name'    => $slug,
					'post_content' => wp_kses_post( (string) ( $item['content'] ?? '' ) ),
					'post_excerpt' => sanitize_textarea_field( (string) ( $item['excerpt'] ?? '' ) ),
					'post_status'  => self::sanitize_status( (string) ( $item['status'] ?? 'publish' ) ),
					'post_type'    => Download::POST_TYPE,
				],
				true
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				++$skipped;
				continue;
			}

			self::apply_meta( (int) $post_id, $item, $find_url, $replace_url );
			self::apply_terms( (int) $post_id, $item );

			$slug_to_id[ $slug ] = (int) $post_id;
			++$imported;
		}

		if ( ! empty( $payload['logs'] ) && is_array( $payload['logs'] ) ) {
			self::import_logs( $payload['logs'], $slug_to_id );
		}

		return [
			'success'  => true,
			'message'  => sprintf(
				/* translators: 1: imported count, 2: skipped count */
				__( 'Import completed. %1$d imported, %2$d skipped.', 'vs-download' ),
				$imported,
				$skipped
			),
			'imported' => $imported,
			'skipped'  => $skipped,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function serialize_download( \WP_Post $post ): array {
		$categories = self::serialize_terms( $post->ID, DownloadCategory::TAXONOMY );
		$tags       = self::serialize_terms( $post->ID, DownloadTag::TAXONOMY );

		return [
			'title'          => $post->post_title,
			'slug'           => $post->post_name,
			'content'        => $post->post_content,
			'excerpt'        => $post->post_excerpt,
			'status'         => $post->post_status,
			'file_url'       => (string) get_post_meta( $post->ID, '_lwd_file_url', true ),
			'version'        => (string) get_post_meta( $post->ID, '_lwd_version', true ),
			'versions'       => (array) get_post_meta( $post->ID, '_lwd_versions', true ),
			'access'         => (string) get_post_meta( $post->ID, '_lwd_access', true ),
			'access_roles'   => (array) get_post_meta( $post->ID, '_lwd_access_roles', true ),
			'download_count' => (int) get_post_meta( $post->ID, '_lwd_download_count', true ),
			'categories'     => $categories,
			'tags'           => $tags,
		];
	}

	/**
	 * @return list<array{name: string, slug: string}>
	 */
	private static function serialize_terms( int $post_id, string $taxonomy ): array {
		$terms = wp_get_object_terms( $post_id, $taxonomy );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		$out = [];
		foreach ( $terms as $term ) {
			$out[] = [
				'name' => $term->name,
				'slug' => $term->slug,
			];
		}

		return $out;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function export_logs(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'lwd_download_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT l.download_id, l.user_id, l.ip_address, l.downloaded_at, p.post_name AS slug
			 FROM `{$table}` l
			 INNER JOIN {$wpdb->posts} p ON p.ID = l.download_id
			 WHERE p.post_type = '" . esc_sql( Download::POST_TYPE ) . "'
			 ORDER BY l.id ASC",
			ARRAY_A
		);

		$logs = [];
		foreach ( $rows as $row ) {
			$logs[] = [
				'slug'          => (string) ( $row['slug'] ?? '' ),
				'user_id'       => (int) ( $row['user_id'] ?? 0 ),
				'ip_address'    => (string) ( $row['ip_address'] ?? '' ),
				'downloaded_at' => (string) ( $row['downloaded_at'] ?? '' ),
			];
		}

		return $logs;
	}

	/**
	 * @param array<string, mixed> $item Download row from export.
	 */
	private static function apply_meta( int $post_id, array $item, string $find_url, string $replace_url ): void {
		$file_url = (string) ( $item['file_url'] ?? '' );
		$versions = $item['versions'] ?? [];

		if ( $find_url !== '' && $replace_url !== '' ) {
			$file_url = str_replace( $find_url, $replace_url, $file_url );
			if ( is_array( $versions ) ) {
				foreach ( $versions as $i => $ver ) {
					if ( is_array( $ver ) && isset( $ver['url'] ) ) {
						$versions[ $i ]['url'] = str_replace( $find_url, $replace_url, (string) $ver['url'] );
					}
				}
			}
		}

		update_post_meta( $post_id, '_lwd_file_url', esc_url_raw( $file_url ) );
		update_post_meta( $post_id, '_lwd_version', sanitize_text_field( (string) ( $item['version'] ?? '' ) ) );
		update_post_meta( $post_id, '_lwd_versions', is_array( $versions ) ? $versions : [] );
		update_post_meta( $post_id, '_lwd_access', sanitize_text_field( (string) ( $item['access'] ?? 'public' ) ) );
		update_post_meta( $post_id, '_lwd_access_roles', is_array( $item['access_roles'] ?? null ) ? $item['access_roles'] : [] );
		update_post_meta( $post_id, '_lwd_download_count', absint( $item['download_count'] ?? 0 ) );
	}

	/**
	 * @param array<string, mixed> $item Download row from export.
	 */
	private static function apply_terms( int $post_id, array $item ): void {
		self::assign_terms( $post_id, $item['categories'] ?? [], DownloadCategory::TAXONOMY );
		self::assign_terms( $post_id, $item['tags'] ?? [], DownloadTag::TAXONOMY );
	}

	/**
	 * @param list<array{name?: string, slug?: string}> $terms_data Term rows.
	 */
	private static function assign_terms( int $post_id, array $terms_data, string $taxonomy ): void {
		$term_ids = [];

		foreach ( $terms_data as $term_data ) {
			if ( ! is_array( $term_data ) || empty( $term_data['name'] ) ) {
				continue;
			}

			$name = sanitize_text_field( (string) $term_data['name'] );
			$slug = ! empty( $term_data['slug'] ) ? sanitize_title( (string) $term_data['slug'] ) : sanitize_title( $name );

			$term = wp_insert_term( $name, $taxonomy, [ 'slug' => $slug ] );
			if ( is_wp_error( $term ) ) {
				$existing = $term->get_error_data( 'term_exists' );
				if ( $existing ) {
					$term_ids[] = (int) $existing;
				}
				continue;
			}

			$term_ids[] = (int) $term['term_id'];
		}

		if ( $term_ids ) {
			wp_set_object_terms( $post_id, $term_ids, $taxonomy );
		}
	}

	/**
	 * @param list<array<string, mixed>>       $logs       Log rows from export.
	 * @param array<string, int>              $slug_to_id Slug → post ID map.
	 */
	private static function import_logs( array $logs, array $slug_to_id ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'lwd_download_logs';

		foreach ( $logs as $log ) {
			if ( ! is_array( $log ) || empty( $log['slug'] ) ) {
				continue;
			}

			$slug = sanitize_title( (string) $log['slug'] );
			$download_id = $slug_to_id[ $slug ] ?? 0;

			if ( ! $download_id ) {
				$existing = get_page_by_path( $slug, OBJECT, Download::POST_TYPE );
				$download_id = $existing ? (int) $existing->ID : 0;
			}

			if ( ! $download_id ) {
				continue;
			}

			$ip = (string) ( $log['ip_address'] ?? '' );
			if ( strlen( $ip ) > 45 ) {
				$ip = substr( $ip, 0, 45 );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$table,
				[
					'download_id'   => $download_id,
					'user_id'       => absint( $log['user_id'] ?? 0 ),
					'ip_address'    => $ip,
					'downloaded_at' => sanitize_text_field( (string) ( $log['downloaded_at'] ?? current_time( 'mysql' ) ) ),
				],
				[ '%d', '%d', '%s', '%s' ]
			);
		}
	}

	private static function sanitize_status( string $status ): string {
		$allowed = [ 'publish', 'draft', 'private', 'pending' ];

		return in_array( $status, $allowed, true ) ? $status : 'publish';
	}
}
