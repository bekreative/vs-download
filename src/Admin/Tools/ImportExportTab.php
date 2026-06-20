<?php
/**
 * Tools → Import / Export tab.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Admin\Tools;

use Vs\Download\Admin\DataService;

/**
 * JSON export/import UI for moving downloads between sites.
 */
final class ImportExportTab {

	private const NONCE_ACTION = 'lwd_import_export';
	private const AJAX_EXPORT  = 'lwd_export_json';
	private const AJAX_IMPORT  = 'lwd_import_json';

	public function __construct() {
		add_action( 'wp_ajax_' . self::AJAX_EXPORT, [ $this, 'ajax_export' ] );
		add_action( 'wp_ajax_' . self::AJAX_IMPORT, [ $this, 'ajax_import' ] );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$nonce = wp_create_nonce( self::NONCE_ACTION );
		?>
		<div class="lwd-tools-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;max-width:960px;">
			<div class="card" style="padding:16px 20px;">
				<h2><?php esc_html_e( 'Export (JSON)', 'vs-download' ); ?></h2>
				<p><?php esc_html_e( 'Download all LW Download entries as a JSON file for backup or migration to another site.', 'vs-download' ); ?></p>
				<p>
					<label>
						<input type="checkbox" id="lwd-export-include-logs" />
						<?php esc_html_e( 'Include download logs (statistics)', 'vs-download' ); ?>
					</label>
				</p>
				<button type="button" class="button button-secondary" id="lwd-export-json" style="width:100%;">
					<?php esc_html_e( 'Download export file', 'vs-download' ); ?>
				</button>
			</div>

			<div class="card" style="padding:16px 20px;">
				<h2><?php esc_html_e( 'Import (JSON)', 'vs-download' ); ?></h2>
				<p><?php esc_html_e( 'Upload a JSON export from another WordPress site.', 'vs-download' ); ?></p>
				<p>
					<label>
						<input type="checkbox" id="lwd-import-skip-existing" checked />
						<?php esc_html_e( 'Skip downloads with an existing slug (recommended)', 'vs-download' ); ?>
					</label>
				</p>
				<label style="display:block;margin-bottom:5px;font-weight:600;">
					<?php esc_html_e( 'URL search & replace (optional)', 'vs-download' ); ?>
				</label>
				<input type="text" id="lwd-import-find" placeholder="old-site.com" style="width:100%;margin-bottom:5px;" />
				<input type="text" id="lwd-import-replace" placeholder="new-site.com" style="width:100%;margin-bottom:10px;" />
				<button type="button" class="button button-primary" id="lwd-import-json" style="width:100%;">
					<?php esc_html_e( 'Upload and import JSON', 'vs-download' ); ?>
				</button>
				<input type="file" id="lwd-import-file" accept=".json,application/json" style="display:none;" />
			</div>
		</div>

		<div id="lwd-import-export-status" style="display:none;max-width:960px;margin-top:16px;padding:10px 12px;background:#fff;border-left:4px solid #72aee6;"></div>

		<script>
		jQuery(function($){
			var nonce = <?php echo wp_json_encode( $nonce ); ?>;
			var $status = $('#lwd-import-export-status');

			function showStatus(msg, ok) {
				$status.show().css('border-left-color', ok ? '#46b450' : '#d63638').text(msg);
			}

			$('#lwd-export-json').on('click', function(e){
				e.preventDefault();
				var btn = $(this).prop('disabled', true);
				$.post(ajaxurl, {
					action: <?php echo wp_json_encode( self::AJAX_EXPORT ); ?>,
					security: nonce,
					include_logs: $('#lwd-export-include-logs').is(':checked') ? 1 : 0
				}).done(function(res){
					if (!res.success) {
						showStatus(res.data && res.data.message ? res.data.message : 'Export failed', false);
						return;
					}
					var blob = new Blob([JSON.stringify(res.data, null, 2)], {type: 'application/json'});
					var url = URL.createObjectURL(blob);
					var a = document.createElement('a');
					a.href = url;
					a.download = 'vs-download-export.json';
					a.click();
					URL.revokeObjectURL(url);
					showStatus(<?php echo wp_json_encode( __( 'Export file downloaded.', 'vs-download' ) ); ?>, true);
				}).fail(function(){
					showStatus('Export request failed', false);
				}).always(function(){
					btn.prop('disabled', false);
				});
			});

			$('#lwd-import-json').on('click', function(e){
				e.preventDefault();
				$('#lwd-import-file').click();
			});

			$('#lwd-import-file').on('change', function(e){
				var file = e.target.files[0];
				if (!file) return;

				var reader = new FileReader();
				reader.onload = function(ev){
					try {
						var payload = JSON.parse(ev.target.result);
					} catch (err) {
						showStatus(<?php echo wp_json_encode( __( 'Invalid JSON file.', 'vs-download' ) ); ?>, false);
						return;
					}

					showStatus(<?php echo wp_json_encode( __( 'Importing…', 'vs-download' ) ); ?>, true);

					$.post(ajaxurl, {
						action: <?php echo wp_json_encode( self::AJAX_IMPORT ); ?>,
						security: nonce,
						payload: JSON.stringify(payload),
						find: $('#lwd-import-find').val(),
						replace: $('#lwd-import-replace').val(),
						skip_existing: $('#lwd-import-skip-existing').is(':checked') ? 1 : 0
					}).done(function(res){
						if (res.success) {
							showStatus(res.data.message, true);
						} else {
							showStatus(res.data && res.data.message ? res.data.message : 'Import failed', false);
						}
					}).fail(function(){
						showStatus('Import request failed', false);
					});
				};
				reader.readAsText(file);
				$(this).val('');
			});
		});
		</script>
		<?php
	}

	public function ajax_export(): void {
		$this->verify_ajax();
		$include_logs = ! empty( $_POST['include_logs'] );
		wp_send_json_success( DataService::export_to_json( $include_logs ) );
	}

	public function ajax_import(): void {
		$this->verify_ajax();

		$raw = isset( $_POST['payload'] ) ? wp_unslash( (string) $_POST['payload'] ) : '';
		$payload = json_decode( $raw, true );
		if ( ! is_array( $payload ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid import payload.', 'vs-download' ) ] );
		}

		$find          = sanitize_text_field( wp_unslash( (string) ( $_POST['find'] ?? '' ) ) );
		$replace       = sanitize_text_field( wp_unslash( (string) ( $_POST['replace'] ?? '' ) ) );
		$skip_existing = ! empty( $_POST['skip_existing'] );

		$result = DataService::import_from_json( $payload, $find, $replace, $skip_existing );
		wp_send_json_success( $result );
	}

	private function verify_ajax(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'vs-download' ) ], 403 );
		}
		check_ajax_referer( self::NONCE_ACTION, 'security' );
	}
}
