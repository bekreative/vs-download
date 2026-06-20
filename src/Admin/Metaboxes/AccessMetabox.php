<?php
/**
 * Access Metabox for Downloads.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Admin\Metaboxes;

use Vs\Download\PostTypes\Download;
use Vs\Download\Download\AccessValidator;

/**
 * Access Metabox.
 */
class AccessMetabox {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'save_post', [ $this, 'save_meta_box' ] );
	}

	/**
	 * Register the meta box.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'lwd_access_metabox',
			__( 'Download Access', 'vs-download' ),
			[ $this, 'render' ],
			Download::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render the meta box.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( 'lwd_save_access', 'lwd_access_nonce' );

		$access = get_post_meta( $post->ID, '_lwd_access', true );
		if ( empty( $access ) ) {
			$access = AccessValidator::ACCESS_PUBLIC;
		}

		$saved_roles = get_post_meta( $post->ID, '_lwd_access_roles', true );
		if ( ! is_array( $saved_roles ) ) {
			$saved_roles = [];
		}

		$all_roles = wp_roles()->get_names();

		?>
		<p>
			<label>
				<input type="radio" name="_lwd_access" value="public" <?php checked( $access, 'public' ); ?> />
				<?php esc_html_e( 'Nyilvános (mindenki)', 'vs-download' ); ?>
			</label>
		</p>
		<p>
			<label>
				<input type="radio" name="_lwd_access" value="logged_in" <?php checked( $access, 'logged_in' ); ?> />
				<?php esc_html_e( 'Csak bejelentkezett felhasználók', 'vs-download' ); ?>
			</label>
		</p>
		<p>
			<label>
				<input type="radio" name="_lwd_access" value="role" <?php checked( $access, 'role' ); ?> />
				<?php esc_html_e( 'Adott szerepkörök:', 'vs-download' ); ?>
			</label>
		</p>
		<div id="lwd-role-list" style="margin-left:20px;<?php echo $access !== 'role' ? 'display:none;' : ''; ?>">
			<?php foreach ( $all_roles as $role_key => $role_name ) : ?>
				<label style="display:block;margin:3px 0;">
					<input type="checkbox" name="_lwd_access_roles[]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $saved_roles, true ) ); ?> />
					<?php echo esc_html( translate_user_role( $role_name ) ); ?>
				</label>
			<?php endforeach; ?>
		</div>
		<script>
		jQuery(function($){
			$('input[name="_lwd_access"]').on('change',function(){
				$('#lwd-role-list').toggle($(this).val()==='role');
			});
		});
		</script>
		<?php
	}

	/**
	 * Save access settings.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_meta_box( int $post_id ): void {
		if ( ! isset( $_POST['lwd_access_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lwd_access_nonce'] ) ), 'lwd_save_access' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$access = isset( $_POST['_lwd_access'] ) ? sanitize_text_field( wp_unslash( $_POST['_lwd_access'] ) ) : 'public';
		update_post_meta( $post_id, '_lwd_access', $access );

		$roles = [];
		if ( 'role' === $access && isset( $_POST['_lwd_access_roles'] ) ) {
			$roles = array_map( 'sanitize_text_field', wp_unslash( $_POST['_lwd_access_roles'] ) );
		}
		update_post_meta( $post_id, '_lwd_access_roles', $roles );
	}
}
