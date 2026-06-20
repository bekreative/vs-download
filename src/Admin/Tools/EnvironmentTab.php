<?php
/**
 * Tools → Environment tab.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Admin\Tools;

use Vs\Download\Health\HealthCheckRunner;
use Vs\Download\Health\HealthReportStore;

/**
 * Renders and runs host environment checks.
 */
final class EnvironmentTab {

	public const NONCE_HEALTH = 'lwd_health_check';
	public const AJAX_HEALTH  = 'lwd_run_health';

	public function __construct() {
		add_action( 'wp_ajax_' . self::AJAX_HEALTH, [ $this, 'ajax_run_health' ] );
	}

	public function render(): void {
		$report   = HealthReportStore::get_report();
		$last_run = (int) get_option( HealthReportStore::OPTION_LAST_RUN, 0 );
		$auto_run = HealthReportStore::is_pending() || empty( $report );
		?>
		<p><?php esc_html_e( 'Verify PHP, database, cron, outbound HTTP, and conflicts before using downloads or migration on this host.', 'vs-download' ); ?></p>
		<p>
			<button type="button" class="button button-primary" id="lwd-run-health">
				<?php esc_html_e( 'Run environment checks', 'vs-download' ); ?>
			</button>
			<?php if ( $last_run > 0 ) : ?>
				<span class="description" style="margin-left:8px;">
					<?php
					printf(
						/* translators: %s: localized date/time */
						esc_html__( 'Last run: %s', 'vs-download' ),
						esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_run ) )
					);
					?>
				</span>
			<?php endif; ?>
		</p>
		<div id="lwd-health-results">
			<?php $this->render_table( $report ); ?>
		</div>
		<script>
		jQuery(function($){
			var nonce = <?php echo wp_json_encode( wp_create_nonce( self::NONCE_HEALTH ) ); ?>;
			var autoRun = <?php echo wp_json_encode( $auto_run ); ?>;
			function runChecks() {
				$('#lwd-run-health').prop('disabled', true);
				$('#lwd-health-results').html('<p><?php echo esc_js( __( 'Running checks…', 'vs-download' ) ); ?></p>');
				$.post(ajaxurl, {
					action: <?php echo wp_json_encode( self::AJAX_HEALTH ); ?>,
					security: nonce
				}).done(function(res){
					if (res.success && res.data.html) {
						$('#lwd-health-results').html(res.data.html);
					} else {
						$('#lwd-health-results').html('<p class="notice notice-error"><?php echo esc_js( __( 'Health check failed.', 'vs-download' ) ); ?></p>');
					}
				}).fail(function(){
					$('#lwd-health-results').html('<p class="notice notice-error"><?php echo esc_js( __( 'Request failed.', 'vs-download' ) ); ?></p>');
				}).always(function(){
					$('#lwd-run-health').prop('disabled', false);
				});
			}
			$('#lwd-run-health').on('click', runChecks);
			if (autoRun) { runChecks(); }
		});
		</script>
		<?php
	}

	/**
	 * @param list<array<string, string>> $report
	 */
	public function render_table( array $report ): void {
		if ( empty( $report ) ) {
			echo '<p class="description">' . esc_html__( 'No results yet. Click "Run environment checks".', 'vs-download' ) . '</p>';
			return;
		}

		$counts = HealthReportStore::count_statuses( $report );
		printf(
			'<p><strong>%s</strong> %d &nbsp;|&nbsp; <strong>%s</strong> %d &nbsp;|&nbsp; <strong>%s</strong> %d</p>',
			esc_html__( 'Pass:', 'vs-download' ),
			(int) $counts['pass'],
			esc_html__( 'Warnings:', 'vs-download' ),
			(int) $counts['warn'],
			esc_html__( 'Failed:', 'vs-download' ),
			(int) $counts['fail']
		);

		echo '<table class="widefat striped" style="max-width:900px;"><thead><tr>';
		echo '<th>' . esc_html__( 'Check', 'vs-download' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'vs-download' ) . '</th>';
		echo '<th>' . esc_html__( 'Details', 'vs-download' ) . '</th>';
		echo '<th>' . esc_html__( 'Recommendation', 'vs-download' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $report as $row ) {
			$status = (string) ( $row['status'] ?? '' );
			$badge  = match ( $status ) {
				'pass' => '<span style="color:#00a32a;">✓ ' . esc_html__( 'OK', 'vs-download' ) . '</span>',
				'warn' => '<span style="color:#dba617;">⚠ ' . esc_html__( 'Warning', 'vs-download' ) . '</span>',
				'fail' => '<span style="color:#d63638;">✗ ' . esc_html__( 'Failed', 'vs-download' ) . '</span>',
				default => esc_html( $status ),
			};
			echo '<tr>';
			echo '<td><strong>' . esc_html( (string) ( $row['label'] ?? '' ) ) . '</strong></td>';
			echo '<td>' . $badge . '</td>';
			echo '<td>' . esc_html( (string) ( $row['message'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $row['recommendation'] ?? '' ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	public function ajax_run_health(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'vs-download' ) ], 403 );
		}
		check_ajax_referer( self::NONCE_HEALTH, 'security' );

		$report = ( new HealthCheckRunner() )->run_and_store();

		ob_start();
		$this->render_table( $report );
		wp_send_json_success( [ 'html' => (string) ob_get_clean(), 'report' => $report ] );
	}
}
