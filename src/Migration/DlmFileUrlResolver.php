<?php
/**
 * Resolve Download Monitor file paths to public URLs.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Migration;

/**
 * Converts DLM mirror paths (relative, absolute, or full URL) to downloadable URLs.
 */
final class DlmFileUrlResolver {

	/**
	 * Normalize a list of mirror values to a single URL.
	 *
	 * @param mixed $mirrors Meta value from _files (array, JSON string, or scalar).
	 * @return string
	 */
	public static function first_url( mixed $mirrors ): string {
		$list = self::normalize_mirrors( $mirrors );
		if ( empty( $list ) ) {
			return '';
		}

		return self::resolve_path( (string) $list[0] );
	}

	/**
	 * @param mixed $mirrors Raw mirrors meta.
	 * @return list<string>
	 */
	public static function normalize_mirrors( mixed $mirrors ): array {
		if ( is_string( $mirrors ) ) {
			$decoded = json_decode( $mirrors, true );
			$mirrors = is_array( $decoded ) ? $decoded : array_filter( array_map( 'trim', explode( "\n", $mirrors ) ) );
		}

		if ( ! is_array( $mirrors ) ) {
			return $mirrors ? [ (string) $mirrors ] : [];
		}

		$out = [];
		foreach ( $mirrors as $mirror ) {
			if ( is_string( $mirror ) && '' !== trim( $mirror ) ) {
				$out[] = trim( $mirror );
			} elseif ( is_array( $mirror ) && isset( $mirror['file'] ) ) {
				$out[] = trim( (string) $mirror['file'] );
			}
		}

		return $out;
	}

	/**
	 * Turn a DLM path or URL into a full URL suitable for _lwd_file_url.
	 *
	 * @param string $path Mirror path or URL.
	 * @return string
	 */
	public static function resolve_path( string $path ): string {
		$path = trim( $path );
		if ( '' === $path ) {
			return '';
		}

		if ( preg_match( '#^https?://#i', $path ) ) {
			return esc_url_raw( $path );
		}

		if ( str_starts_with( $path, '//' ) ) {
			return esc_url_raw( 'https:' . $path );
		}

		$upload = wp_get_upload_dir();

		if ( str_starts_with( $path, '/wp-content/' ) || str_starts_with( $path, 'wp-content/' ) ) {
			$relative = ltrim( $path, '/' );
			return esc_url_raw( trailingslashit( $upload['baseurl'] ) . str_replace( 'wp-content/uploads/', '', $relative ) );
		}

		if ( str_starts_with( $path, '/' ) ) {
			return esc_url_raw( home_url( $path ) );
		}

		if ( str_contains( $path, 'dlm_uploads/' ) ) {
			return esc_url_raw( trailingslashit( $upload['baseurl'] ) . ltrim( $path, '/' ) );
		}

		return esc_url_raw( trailingslashit( $upload['baseurl'] ) . ltrim( $path, '/' ) );
	}
}
