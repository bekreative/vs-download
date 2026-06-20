<?php
/**
 * Resolve download requests from query strings and singular routes.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Download;

use Vs\Download\Migration\DlmDetector;
use Vs\Download\PostTypes\Download;

/**
 * Maps HTTP requests to a download post ID and optional version.
 */
final class DownloadRequest {

	/**
	 * @return array{download_id:int,version:string}|null
	 */
	public static function from_query_vars(): ?array {
		$download_id = (int) get_query_var( DownloadRewrites::QUERY_ID );
		if ( $download_id <= 0 ) {
			return null;
		}

		$version_slug = (string) get_query_var( DownloadRewrites::QUERY_VERSION );
		$version      = '' !== $version_slug
			? DownloadUrl::resolve_version_label( $download_id, $version_slug )
			: '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$get = array_merge( $_GET, [ 'lwd_version' => $version ] );

		return self::pack( $download_id, $get );
	}

	public static function from_globals(): ?array {
		$from_rewrite = self::from_query_vars();
		if ( null !== $from_rewrite ) {
			return $from_rewrite;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public download endpoint.
		$get = $_GET;

		if ( isset( $get['lwd_download'] ) ) {
			return self::pack( absint( $get['lwd_download'] ), $get );
		}

		if ( isset( $get['download-id'] ) ) {
			return self::pack( absint( $get['download-id'] ), $get );
		}

		$post_type = isset( $get['post_type'] ) ? sanitize_key( (string) $get['post_type'] ) : '';
		$p         = isset( $get['p'] ) ? absint( $get['p'] ) : 0;

		if ( $p > 0 && in_array( $post_type, [ Download::POST_TYPE, DlmDetector::CPT_DOWNLOAD ], true ) ) {
			return self::pack( $p, $get );
		}

		return null;
	}

	/**
	 * @return array{download_id:int,version:string}|null
	 */
	public static function from_queried_post(): ?array {
		if ( ! is_singular( Download::POST_TYPE ) ) {
			return null;
		}

		$post_id = (int) get_queried_object_id();
		if ( $post_id <= 0 ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return self::pack( $post_id, $_GET );
	}

	/**
	 * @param array<string, mixed> $get Request query params.
	 * @return array{download_id:int,version:string}|null
	 */
	private static function pack( int $download_id, array $get ): ?array {
		if ( $download_id <= 0 ) {
			return null;
		}

		$version = isset( $get['lwd_version'] ) ? sanitize_text_field( wp_unslash( (string) $get['lwd_version'] ) ) : '';

		return [
			'download_id' => $download_id,
			'version'     => $version,
		];
	}
}
