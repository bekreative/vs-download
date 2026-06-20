<?php
/**
 * Download Handler.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Download;

use Vs\Download\Database\LogRepository;
use Vs\Download\PostTypes\Download;

/**
 * Serves files via short public URLs; never exposes direct file paths in links.
 */
class DownloadHandler {

	public function __construct() {
		add_action( 'template_redirect', [ $this, 'maybe_serve_download' ], 0 );
	}

	/**
	 * @return void
	 */
	public function maybe_serve_download(): void {
		$request = DownloadRequest::from_globals()
			?? DownloadRequest::from_queried_post();

		if ( null === $request ) {
			return;
		}

		$this->serve_download( $request['download_id'], $request['version'] );
	}

	/**
	 * @param int    $download_id Post ID.
	 * @param string $version     Optional version label.
	 */
	private function serve_download( int $download_id, string $version = '' ): void {
		$post = get_post( $download_id );

		if ( ! $post || Download::POST_TYPE !== $post->post_type ) {
			wp_die( esc_html__( 'Download not found.', 'vs-download' ), '', [ 'response' => 404 ] );
		}

		if ( 'publish' !== $post->post_status ) {
			wp_die( esc_html__( 'Download not found.', 'vs-download' ), '', [ 'response' => 404 ] );
		}

		$user_id = get_current_user_id();
		$allow   = apply_filters( 'lwd_pre_grant_download', true, $download_id, $user_id );

		if ( ! $allow ) {
			wp_die( esc_html__( 'You do not have permission to download this file.', 'vs-download' ), '', [ 'response' => 403 ] );
		}

		$file_url = DownloadUrl::resolve_file_url( $download_id, $version );

		if ( '' === $file_url ) {
			wp_die( esc_html__( 'File URL not configured.', 'vs-download' ), '', [ 'response' => 404 ] );
		}

		$logger = new LogRepository();
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
		$logger->insert_log( $download_id, $user_id, $ip );

		/**
		 * Fires after a download is logged and before the file is served.
		 *
		 * @param int    $download_id Download post ID.
		 * @param int    $user_id     User ID (0 for guests).
		 * @param string $file_url    Internal file URL (not shown to visitors).
		 * @param string $version     Requested version label, if any.
		 */
		do_action( 'lwd_before_serve_download', $download_id, $user_id, $file_url, $version );

		$this->serve_file( $file_url );
	}

	/**
	 * Stream local uploads or redirect to remote URL.
	 *
	 * @param string $file_url Internal resolved file URL.
	 */
	private function serve_file( string $file_url ): void {
		$upload_dir = wp_get_upload_dir();

		if ( str_starts_with( $file_url, $upload_dir['baseurl'] ) ) {
			$local_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $file_url );

			if ( file_exists( $local_path ) ) {
				$mime_type = wp_check_filetype( $local_path )['type'];
				$mime_type = $mime_type ? $mime_type : 'application/octet-stream';

				header( 'Content-Description: File Transfer' );
				header( 'Content-Type: ' . $mime_type );
				header( 'Content-Disposition: attachment; filename="' . basename( $local_path ) . '"' );
				header( 'Expires: 0' );
				header( 'Cache-Control: must-revalidate' );
				header( 'Pragma: public' );
				header( 'Content-Length: ' . (string) filesize( $local_path ) );

				while ( ob_get_level() ) {
					ob_end_clean();
				}
				flush();
				readfile( $local_path );
				exit;
			}
		}

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- external file URLs are expected.
		wp_redirect( $file_url );
		exit;
	}
}
