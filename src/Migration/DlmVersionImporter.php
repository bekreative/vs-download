<?php
/**
 * Import DLM file versions into LW Download meta.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Migration;

/**
 * Maps DLM version posts and _files meta to _lwd_versions.
 */
final class DlmVersionImporter {

	/**
	 * @param list<array{version:string,url:string}> $versions
	 */
	public static function save( int $post_id, array $versions ): void {
		update_post_meta( $post_id, '_lwd_versions', $versions );

		if ( ! empty( $versions ) ) {
			update_post_meta( $post_id, '_lwd_file_url', $versions[0]['url'] );
			update_post_meta( $post_id, '_lwd_version', $versions[0]['version'] );
		}
	}

	/**
	 * @return list<array{version:string,url:string}>
	 */
	public static function build_from_dlm_post( int $dlm_id ): array {
		$version_posts = get_posts(
			[
				'post_type'      => DlmDetector::CPT_VERSION,
				'post_parent'    => $dlm_id,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			]
		);

		$versions = [];
		foreach ( $version_posts as $vpost ) {
			$label = (string) get_post_meta( $vpost->ID, '_version', true );
			if ( '' === $label ) {
				$label = (string) $vpost->post_title;
			}
			$url = DlmFileUrlResolver::first_url( get_post_meta( $vpost->ID, '_files', true ) );
			if ( '' !== $url ) {
				$versions[] = [
					'version' => $label ?: '1.0',
					'url'     => $url,
				];
			}
		}

		if ( empty( $versions ) ) {
			$url = DlmFileUrlResolver::first_url( get_post_meta( $dlm_id, '_files', true ) );
			if ( '' !== $url ) {
				$versions[] = [
					'version' => (string) get_post_meta( $dlm_id, '_version', true ) ?: '1.0',
					'url'     => $url,
				];
			}
		}

		return $versions;
	}

	public static function apply_from_dlm_post( int $dlm_id, int $lwd_id ): void {
		self::save( $lwd_id, self::build_from_dlm_post( $dlm_id ) );
	}

	/**
	 * @param list<string> $paths Raw mirror paths or URLs.
	 */
	public static function from_paths( int $lwd_id, array $paths, string $default_label = '1.0' ): void {
		$versions = [];
		foreach ( $paths as $path ) {
			$resolved = DlmFileUrlResolver::resolve_path( (string) $path );
			if ( '' !== $resolved ) {
				$versions[] = [ 'version' => $default_label, 'url' => $resolved ];
				break;
			}
		}
		self::save( $lwd_id, $versions );
	}
}
