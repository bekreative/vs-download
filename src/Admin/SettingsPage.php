<?php
/**
 * Settings Page for LW Download.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Admin;

/**
 * Registers and renders the Downloads > Settings admin page.
 */
final class SettingsPage {

	/**
	 * Option keys.
	 */
	public const OPT_RETENTION = 'lwd_log_retention_days';
	public const OPT_EXCLUDE_BOTS = 'lwd_exclude_bots';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Add settings submenu page.
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'edit.php?post_type=lwd_download',
			__( 'Settings', 'vs-download' ),
			__( 'Settings', 'vs-download' ),
			'manage_options',
			'lwd_settings',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Register settings with the Settings API.
	 */
	public function register_settings(): void {
		register_setting(
			'lwd_settings_group',
			self::OPT_RETENTION,
			[
				'type'              => 'integer',
				'default'           => 90,
				'sanitize_callback' => 'absint',
			]
		);

		register_setting(
			'lwd_settings_group',
			self::OPT_EXCLUDE_BOTS,
			[
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			]
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_page(): void {
		$retention    = (int) get_option( self::OPT_RETENTION, 90 );
		$exclude_bots = (bool) get_option( self::OPT_EXCLUDE_BOTS, true );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'LW Download Settings', 'vs-download' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'lwd_settings_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="lwd_log_retention_days">
								<?php esc_html_e( 'Log Retention', 'vs-download' ); ?>
							</label>
						</th>
						<td>
							<select id="lwd_log_retention_days" name="<?php echo esc_attr( self::OPT_RETENTION ); ?>">
								<?php
								$options = [
									30  => __( '30 days', 'vs-download' ),
									60  => __( '60 days', 'vs-download' ),
									90  => __( '90 days (default)', 'vs-download' ),
									180 => __( '180 days', 'vs-download' ),
									365 => __( '1 year', 'vs-download' ),
									0   => __( 'Forever (no pruning)', 'vs-download' ),
								];
								foreach ( $options as $val => $label ) {
									printf(
										'<option value="%d"%s>%s</option>',
										(int) $val,
										selected( $retention, $val, false ),
										esc_html( $label )
									);
								}
								?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Download logs older than this will be automatically removed by the daily cleanup task.', 'vs-download' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Exclude Bots', 'vs-download' ); ?>
						</th>
						<td>
							<label>
								<input
									type="checkbox"
									id="lwd_exclude_bots"
									name="<?php echo esc_attr( self::OPT_EXCLUDE_BOTS ); ?>"
									value="1"
									<?php checked( $exclude_bots ); ?>
								/>
								<?php esc_html_e( 'Do not log downloads from known search engine bots and crawlers.', 'vs-download' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
