<?php
/**
 * Admin UI: import from Download Monitor.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Admin;

use Vs\Download\Migration\DlmDetector;
use Vs\Download\Migration\DownloadMonitorImporter;

/**
 * Tools → Migration tab: import from Download Monitor.
 */
final class MigrationPage {

	private const NONCE_ACTION = 'lwd_dlm_migration';
	private const AJAX_DETECT  = 'lwd_dlm_migration_detect';
	private const AJAX_DOWNLOADS = 'lwd_dlm_migration_downloads';
	private const AJAX_LOGS    = 'lwd_dlm_migration_logs';

	/**
	 * Constructor — AJAX only (menu is ToolsPage).
	 */
	public function __construct() {
		add_action( 'wp_ajax_' . self::AJAX_DETECT, [ $this, 'ajax_detect' ] );
		add_action( 'wp_ajax_' . self::AJAX_DOWNLOADS, [ $this, 'ajax_import_downloads' ] );
		add_action( 'wp_ajax_' . self::AJAX_LOGS, [ $this, 'ajax_import_logs' ] );
	}

	/**
	 * Render migration tab content (inside Tools).
	 */
	public function render_tab(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$scan = DlmDetector::scan();
		?>
			<h2><?php esc_html_e( 'Import from Download Monitor', 'vs-download' ); ?></h2>
			<p><?php esc_html_e( 'Migrate downloads, file URLs, versions, categories, tags, and download statistics from Download Monitor into LW Download.', 'vs-download' ); ?></p>

			<div id="lwd-dlm-scan" class="card" style="max-width:720px;padding:16px 20px;">
				<h2><?php esc_html_e( 'Detected data', 'vs-download' ); ?></h2>
				<ul>
					<li><?php printf( esc_html__( 'DLM downloads (CPT): %d', 'vs-download' ), (int) $scan['modern_downloads'] ); ?></li>
					<li><?php printf( esc_html__( 'DLM file versions: %d', 'vs-download' ), (int) $scan['modern_versions'] ); ?></li>
					<li><?php printf( esc_html__( 'DLM log rows (%s): %d', 'vs-download' ), esc_html( (string) $scan['modern_log_table'] ), (int) $scan['modern_log_count'] ); ?></li>
					<li><?php printf( esc_html__( 'Legacy files table: %d', 'vs-download' ), (int) $scan['legacy_files'] ); ?></li>
					<li><?php printf( esc_html__( 'Legacy log rows: %d', 'vs-download' ), (int) $scan['legacy_log_count'] ); ?></li>
					<li><?php printf( esc_html__( 'Already mapped to LW Download: %d', 'vs-download' ), (int) $scan['already_mapped'] ); ?></li>
				</ul>
				<?php if ( ! $scan['ready'] ) : ?>
					<p><strong><?php esc_html_e( 'No Download Monitor data found on this site.', 'vs-download' ); ?></strong></p>
				<?php endif; ?>
			</div>

			<?php if ( $scan['ready'] ) : ?>
			<div class="card" style="max-width:720px;padding:16px 20px;margin-top:16px;">
				<h2><?php esc_html_e( 'Import', 'vs-download' ); ?></h2>
				<p>
					<label>
						<input type="checkbox" id="lwd-dlm-skip-existing" checked />
						<?php esc_html_e( 'Skip downloads already imported (recommended)', 'vs-download' ); ?>
					</label>
				</p>
				<p>
					<label>
						<input type="checkbox" id="lwd-dlm-use-legacy-logs" />
						<?php esc_html_e( 'Import legacy log table (download_monitor_log) instead of modern download_log', 'vs-download' ); ?>
					</label>
				</p>
				<p>
					<button type="button" class="button button-primary" id="lwd-dlm-import-downloads">
						<?php esc_html_e( '1. Import downloads & files', 'vs-download' ); ?>
					</button>
					<button type="button" class="button button-secondary" id="lwd-dlm-import-logs" disabled>
						<?php esc_html_e( '2. Import download logs (statistics)', 'vs-download' ); ?>
					</button>
				</p>
				<div id="lwd-dlm-status" style="display:none;margin-top:12px;padding:10px 12px;background:#fff;border-left:4px solid #72aee6;"></div>
			</div>
			<?php endif; ?>
		<script>
		jQuery(function($){
			var nonce = <?php echo wp_json_encode( wp_create_nonce( self::NONCE_ACTION ) ); ?>;
			var $status = $('#lwd-dlm-status');
			function showStatus(msg, ok) {
				$status.show().css('border-left-color', ok ? '#46b450' : '#d63638').html(msg);
			}
			$('#lwd-dlm-import-downloads').on('click', function(){
				if (!confirm(<?php echo wp_json_encode( __( 'Import all Download Monitor downloads into LW Download?', 'vs-download' ) ); ?>)) return;
				var btn = $(this).prop('disabled', true);
				showStatus(<?php echo wp_json_encode( __( 'Importing downloads…', 'vs-download' ) ); ?>, true);
				$.post(ajaxurl, {
					action: <?php echo wp_json_encode( self::AJAX_DOWNLOADS ); ?>,
					security: nonce,
					skip_existing: $('#lwd-dlm-skip-existing').is(':checked') ? 1 : 0
				}).done(function(res){
					if (res.success) {
						showStatus(res.data.message, true);
						$('#lwd-dlm-import-logs').prop('disabled', false);
					} else {
						showStatus(res.data && res.data.message ? res.data.message : 'Error', false);
					}
				}).fail(function(){ showStatus('Request failed', false); })
				.always(function(){ btn.prop('disabled', false); });
			});
			function importLogsBatch(offset) {
				$.post(ajaxurl, {
					action: <?php echo wp_json_encode( self::AJAX_LOGS ); ?>,
					security: nonce,
					offset: offset,
					legacy: $('#lwd-dlm-use-legacy-logs').is(':checked') ? 1 : 0
				}).done(function(res){
					if (!res.success) {
						showStatus(res.data && res.data.message ? res.data.message : 'Error', false);
						$('#lwd-dlm-import-logs').prop('disabled', false);
						return;
					}
					showStatus(res.data.message, true);
					if (res.data.done) {
						$('#lwd-dlm-import-logs').prop('disabled', false);
					} else {
						importLogsBatch(res.data.offset || 0);
					}
				}).fail(function(){
					showStatus('Log import request failed', false);
					$('#lwd-dlm-import-logs').prop('disabled', false);
				});
			}
			$('#lwd-dlm-import-logs').on('click', function(){
				if (!confirm(<?php echo wp_json_encode( __( 'Import all download log entries? This may take a while on large sites.', 'vs-download' ) ); ?>)) return;
				$(this).prop('disabled', true);
				showStatus(<?php echo wp_json_encode( __( 'Importing logs…', 'vs-download' ) ); ?>, true);
				importLogsBatch(0);
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX: refresh scan stats.
	 */
	public function ajax_detect(): void {
		$this->verify_ajax();
		wp_send_json_success( DlmDetector::scan() );
	}

	/**
	 * AJAX: import downloads.
	 */
	public function ajax_import_downloads(): void {
		$this->verify_ajax();
		$skip = ! empty( $_POST['skip_existing'] );
		wp_send_json_success( DownloadMonitorImporter::import_downloads( $skip ) );
	}

	/**
	 * AJAX: import logs batch.
	 */
	public function ajax_import_logs(): void {
		$this->verify_ajax();
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$modern = empty( $_POST['legacy'] );
		wp_send_json_success( DownloadMonitorImporter::import_logs( $offset, $modern ) );
	}

	/**
	 * Verify nonce and capability.
	 */
	private function verify_ajax(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'vs-download' ) ], 403 );
		}
		check_ajax_referer( self::NONCE_ACTION, 'security' );
	}
}
