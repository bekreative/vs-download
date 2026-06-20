<?php
/**
 * File Metabox for Downloads.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Admin\Metaboxes;

use Vs\Download\Download\DownloadUrl;
use Vs\Download\PostTypes\Download;

/**
 * File Metabox.
 */
class FileMetabox {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'save_post', [ $this, 'save_meta_box' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Add the meta box.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'lwd_file_metabox',
			__( 'Downloadable File', 'vs-download' ),
			[ $this, 'render_meta_box' ],
			Download::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Enqueue WP media scripts.
	 *
	 * @param string $hook Admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		global $post;
		
		if ( ! $post || Download::POST_TYPE !== $post->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_add_inline_script( 'media-editor', FileMetaboxScripts::inline_js() );
	}

	/**
	 * Render the meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'lwd_save_file_meta', 'lwd_file_meta_nonce' );

		$versions = get_post_meta( $post->ID, '_lwd_versions', true );
		if ( ! is_array( $versions ) ) {
			$versions = [];
			// Backward compatibility:
			$old_url = get_post_meta( $post->ID, '_lwd_file_url', true );
			$old_ver = get_post_meta( $post->ID, '_lwd_version', true );
			if ( ! empty( $old_url ) ) {
				$versions[] = [ 'version' => $old_ver, 'url' => $old_url ];
			}
		}

        // Add an empty row if no versions
        if ( empty( $versions ) ) {
            $versions[] = [ 'version' => '', 'url' => '' ];
        }

		$public_url = DownloadUrl::public_url( (int) $post->ID );
		$legacy_url = (string) get_post_meta( $post->ID, '_lwd_legacy_dlm_url', true );
		?>
		<p style="margin-bottom:14px;">
			<strong><?php esc_html_e( 'Public download link', 'vs-download' ); ?></strong><br/>
			<input type="text" class="large-text" readonly="readonly" value="<?php echo esc_attr( $public_url ); ?>" onclick="this.select();" />
			<span class="description"><?php esc_html_e( 'Short tracked URL for visitors — not the direct file path.', 'vs-download' ); ?></span>
			<?php if ( '' !== $legacy_url ) : ?>
				<br/><span class="description"><?php esc_html_e( 'Legacy DLM URL (still works):', 'vs-download' ); ?>
				<code><?php echo esc_html( $legacy_url ); ?></code></span>
			<?php endif; ?>
		</p>
		<table id="lwd-versions-table" style="width: 100%; text-align: left; margin-bottom: 10px;">
			<thead>
				<tr>
					<th style="width: 20%;"><?php esc_html_e( 'Version', 'vs-download' ); ?></th>
					<th style="width: 70%;"><?php esc_html_e( 'Internal file URL', 'vs-download' ); ?></th>
					<th style="width: 10%;"></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $versions as $v ) : ?>
					<tr>
						<td><input type="text" name="_lwd_versions[]" value="<?php echo esc_attr( $v['version'] ); ?>" placeholder="Version" /></td>
						<td>
							<input type="text" name="_lwd_urls[]" class="lwd-file-url" value="<?php echo esc_attr( $v['url'] ); ?>" style="width:70%;" />
							<button class="button lwd-upload-button"><?php esc_html_e( 'Upload', 'vs-download' ); ?></button>
						</td>
						<td><button class="button lwd-remove-version"><?php esc_html_e( 'Remove', 'vs-download' ); ?></button></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<button id="lwd-add-version" class="button"><?php esc_html_e( '+ Add Version', 'vs-download' ); ?></button>

		<hr style="margin: 20px 0;">
		<p>
			<strong><?php esc_html_e( 'Shortcode:', 'vs-download' ); ?></strong><br/>
			<code>[lw_download id="<?php echo esc_html( (string) $post->ID ); ?>" template="box"]</code>
			<?php if ( count( $versions ) > 1 ) : ?>
				<br/><span class="description"><?php esc_html_e( 'Specific version:', 'vs-download' ); ?>
				<code>version="<?php echo esc_html( (string) ( $versions[0]['version'] ?? '' ) ); ?>"</code></span>
			<?php endif; ?>
		</p>
		<?php
        $count = (int) get_post_meta( $post->ID, '_lwd_download_count', true );
		if ( $count > 0 ) {
			echo '<p><strong>' . esc_html__( 'Total Downloads:', 'vs-download' ) . '</strong> ' . esc_html( (string)$count ) . '</p>';
		}
	}

	/**
	 * Save the meta box data.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_meta_box( int $post_id ): void {
		if ( ! isset( $_POST['lwd_file_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lwd_file_meta_nonce'] ) ), 'lwd_save_file_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

        $saved_versions = [];
		if ( isset( $_POST['_lwd_versions'] ) && isset( $_POST['_lwd_urls'] ) ) {
            $versions = array_map( 'sanitize_text_field', wp_unslash( $_POST['_lwd_versions'] ) );
            $urls     = array_map( 'sanitize_url', wp_unslash( $_POST['_lwd_urls'] ) );

            foreach ( $versions as $index => $version ) {
                if ( ! empty( $urls[ $index ] ) ) {
                    $saved_versions[] = [
                        'version' => $version,
                        'url'     => $urls[ $index ],
                    ];
                }
            }
		}

        update_post_meta( $post_id, '_lwd_versions', $saved_versions );

        // Maintain the most recent (first) version as the active _lwd_file_url for backward compatibility
        if ( ! empty( $saved_versions ) ) {
            update_post_meta( $post_id, '_lwd_file_url', $saved_versions[0]['url'] );
            update_post_meta( $post_id, '_lwd_version', $saved_versions[0]['version'] );
        } else {
            delete_post_meta( $post_id, '_lwd_file_url' );
            delete_post_meta( $post_id, '_lwd_version' );
        }
	}
}
