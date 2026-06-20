<?php
/**
 * Download URL and file resolution helpers.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Download;

use Vs\Download\PostTypes\Download;

/**
 * Public short links point at the handler; file paths stay internal meta only.
 */
final class DownloadUrl {

	/**
	 * Public short download URL (DLM-compatible query form).
	 *
	 * Example: https://example.com/?post_type=lwd_download&p=18111
	 *
	 * @param int    $download_id Download post ID.
	 * @param string $version     Optional version label.
	 */
	public static function public_url( int $download_id, string $version = '' ): string {
		$url = DownloadRewrites::pretty_url( $download_id, $version );

		/**
		 * Filter the public download URL shown to visitors.
		 *
		 * @param string $url         Full URL.
		 * @param int    $download_id Post ID.
		 * @param string $version     Version label.
		 */
		return (string) apply_filters( 'lwd_download_public_url', $url, $download_id, $version );
	}

	/**
	 * Fallback query-string URL (legacy / DLM compatibility).
	 */
	public static function query_url( int $download_id, string $version = '' ): string {
		$args = [
			'post_type' => Download::POST_TYPE,
			'p'         => $download_id,
		];

		if ( '' !== $version ) {
			$args['lwd_version'] = sanitize_text_field( $version );
		}

		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * Legacy DLM-style URL (still handled by DownloadHandler after migration).
	 *
	 * @param int $dlm_post_id Original dlm_download post ID.
	 */
	public static function legacy_dlm_url( int $dlm_post_id ): string {
		return add_query_arg(
			[
				'post_type' => 'dlm_download',
				'p'         => $dlm_post_id,
			],
			home_url( '/' )
		);
	}

	/**
	 * @deprecated Use public_url()
	 */
	public static function tracking_url( int $download_id, string $version = '' ): string {
		return self::public_url( $download_id, $version );
	}

	/**
	 * Resolve the internal file URL for serving (never expose in public links).
	 *
	 * @param int    $download_id Download post ID.
	 * @param string $version     Version label; empty uses primary entry.
	 */
	public static function resolve_file_url( int $download_id, string $version = '' ): string {
		$version = sanitize_text_field( $version );

		if ( '' !== $version ) {
			$versions = get_post_meta( $download_id, '_lwd_versions', true );
			if ( is_array( $versions ) ) {
				foreach ( $versions as $entry ) {
					if (
						is_array( $entry )
						&& isset( $entry['version'], $entry['url'] )
						&& (string) $entry['version'] === $version
						&& '' !== (string) $entry['url']
					) {
						return esc_url_raw( (string) $entry['url'] );
					}
				}
			}

			return '';
		}

		return (string) get_post_meta( $download_id, '_lwd_file_url', true );
	}

	/**
	 * Map a pretty-URL version segment back to the stored version label.
	 */
	public static function resolve_version_label( int $download_id, string $segment ): string {
		$segment = rawurldecode( $segment );
		if ( '' === $segment ) {
			return '';
		}

		$versions = get_post_meta( $download_id, '_lwd_versions', true );
		if ( ! is_array( $versions ) ) {
			return $segment;
		}

		foreach ( $versions as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['version'] ) ) {
				continue;
			}
			$label = (string) $entry['version'];
			if ( $label === $segment || sanitize_title( $label ) === $segment ) {
				return $label;
			}
		}

		return $segment;
	}
}
