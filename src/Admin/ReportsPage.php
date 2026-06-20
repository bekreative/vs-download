<?php
/**
 * Reports dashboard in Admin UI.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Admin;

/**
 * Dashboard-style download analytics.
 */
class ReportsPage {

	private const PAGE_SLUG = 'lwd_reports';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Add menu page under Downloads CPT.
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'edit.php?post_type=lwd_download',
			__( 'Reports', 'vs-download' ),
			__( 'Reports', 'vs-download' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'lwd_download_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'lwd-admin-reports',
			VS_DOWNLOAD_URL . 'assets/css/admin-reports.css',
			[],
			VS_DOWNLOAD_VERSION
		);
	}

	/**
	 * Render the reports dashboard.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$days = isset( $_GET['range'] ) ? absint( $_GET['range'] ) : ReportsStats::RANGE_30;
		$granularity = isset( $_GET['granularity'] ) && 'month' === sanitize_key( (string) $_GET['granularity'] )
			? 'month'
			: 'week';

		$period = ReportsStats::resolve_range( $days );
		$cards  = ReportsStats::get_summary_cards(
			$period['start'],
			$period['end'],
			$period['prev_start'],
			$period['prev_end']
		);
		$chart       = ReportsStats::get_chart_data( $period['start'], $period['end'], $granularity );
		$top_files   = ReportsStats::get_top_downloads( $period['start'], $period['end'], 5 );
		$top_users   = ReportsStats::get_top_users( $period['start'], $period['end'], 5 );
		$max_chart   = 0;

		foreach ( $chart as $point ) {
			$max_chart = max( $max_chart, $point['count'] );
		}

		$base_url = admin_url( 'edit.php?post_type=lwd_download&page=' . self::PAGE_SLUG );
		?>
		<div class="wrap lwd-reports-wrap">
			<div class="lwd-reports-header">
				<h1><?php esc_html_e( 'Dashboard Overview', 'vs-download' ); ?></h1>
				<form method="get" class="lwd-reports-filters">
					<input type="hidden" name="post_type" value="lwd_download" />
					<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
					<input type="hidden" name="granularity" value="<?php echo esc_attr( $granularity ); ?>" />
					<select name="range" onchange="this.form.submit()">
						<option value="<?php echo esc_attr( (string) ReportsStats::RANGE_7 ); ?>" <?php selected( $days, ReportsStats::RANGE_7 ); ?>>
							<?php esc_html_e( 'Last 7 days', 'vs-download' ); ?>
						</option>
						<option value="<?php echo esc_attr( (string) ReportsStats::RANGE_30 ); ?>" <?php selected( $days, ReportsStats::RANGE_30 ); ?>>
							<?php esc_html_e( 'Last 30 days', 'vs-download' ); ?>
						</option>
						<option value="<?php echo esc_attr( (string) ReportsStats::RANGE_90 ); ?>" <?php selected( $days, ReportsStats::RANGE_90 ); ?>>
							<?php esc_html_e( 'Last 90 days', 'vs-download' ); ?>
						</option>
						<option value="<?php echo esc_attr( (string) ReportsStats::RANGE_365 ); ?>" <?php selected( $days, ReportsStats::RANGE_365 ); ?>>
							<?php esc_html_e( 'Last 365 days', 'vs-download' ); ?>
						</option>
					</select>
				</form>
			</div>

			<div class="lwd-summary-grid">
				<?php $this->render_summary_card(
					__( 'Total Downloads', 'vs-download' ),
					(string) number_format_i18n( $cards['total_downloads']['value'] ),
					$cards['total_downloads']['change'],
					'blue'
				); ?>
				<?php $this->render_summary_card(
					__( 'Unique Users', 'vs-download' ),
					(string) number_format_i18n( $cards['unique_users']['value'] ),
					$cards['unique_users']['change'],
					'green'
				); ?>
				<?php $this->render_summary_card(
					__( 'Logged-in Rate', 'vs-download' ),
					$cards['logged_in_rate']['value'] . '%',
					$cards['logged_in_rate']['change'],
					'orange',
					true
				); ?>
				<?php $this->render_summary_card(
					__( 'Active Files', 'vs-download' ),
					(string) number_format_i18n( $cards['active_files']['value'] ),
					$cards['active_files']['change'],
					'purple'
				); ?>
			</div>

			<div class="lwd-panel">
				<div class="lwd-panel-header">
					<h2><?php esc_html_e( 'Downloads Over Time', 'vs-download' ); ?></h2>
					<div class="lwd-chart-toggle">
						<a href="<?php echo esc_url( add_query_arg( [ 'granularity' => 'week', 'range' => $days ], $base_url ) ); ?>" class="<?php echo 'week' === $granularity ? 'is-active' : ''; ?>">
							<?php esc_html_e( 'Weekly', 'vs-download' ); ?>
						</a>
						<a href="<?php echo esc_url( add_query_arg( [ 'granularity' => 'month', 'range' => $days ], $base_url ) ); ?>" class="<?php echo 'month' === $granularity ? 'is-active' : ''; ?>">
							<?php esc_html_e( 'Monthly', 'vs-download' ); ?>
						</a>
					</div>
				</div>
				<div class="lwd-chart">
					<?php if ( empty( $chart ) ) : ?>
						<div class="lwd-chart-empty"><?php esc_html_e( 'No download activity in this period.', 'vs-download' ); ?></div>
					<?php else : ?>
						<?php foreach ( $chart as $point ) : ?>
							<?php
							$height = $max_chart > 0 ? max( 4, (int) round( ( $point['count'] / $max_chart ) * 180 ) ) : 4;
							?>
							<div class="lwd-chart-bar-wrap" title="<?php echo esc_attr( (string) $point['count'] ); ?>">
								<div class="lwd-chart-bar" style="height:<?php echo esc_attr( (string) $height ); ?>px;"></div>
								<span class="lwd-chart-label"><?php echo esc_html( $point['label'] ); ?></span>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

			<div class="lwd-lists-grid">
				<div class="lwd-panel lwd-list-panel">
					<div class="lwd-panel-header">
						<h2><?php esc_html_e( 'Top Downloads', 'vs-download' ); ?></h2>
						<span class="lwd-badge lwd-badge--blue"><?php esc_html_e( 'This period', 'vs-download' ); ?></span>
					</div>
					<?php $this->render_ranked_list( $top_files, 'download' ); ?>
				</div>

				<div class="lwd-panel lwd-list-panel">
					<div class="lwd-panel-header">
						<h2><?php esc_html_e( 'Most Active Users', 'vs-download' ); ?></h2>
						<span class="lwd-badge lwd-badge--green"><?php esc_html_e( 'Top 5', 'vs-download' ); ?></span>
					</div>
					<?php $this->render_ranked_list( $top_users, 'user' ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * @param float $change Percentage or point change.
	 */
	private function render_summary_card( string $label, string $value, float $change, string $tone, bool $points = false ): void {
		$change_class = 'flat';
		$prefix       = '';

		if ( $change > 0 ) {
			$change_class = 'up';
			$prefix       = '+';
		} elseif ( $change < 0 ) {
			$change_class = 'down';
		}

		$suffix = $points ? 'pp' : '%';
		?>
		<div class="lwd-summary-card lwd-summary-card--<?php echo esc_attr( $tone ); ?>">
			<h3><?php echo esc_html( $label ); ?></h3>
			<div class="lwd-value"><?php echo esc_html( $value ); ?></div>
			<div class="lwd-change lwd-change--<?php echo esc_attr( $change_class ); ?>">
				<?php echo esc_html( $prefix . number_format_i18n( $change, 1 ) . $suffix ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * @param list<array<string, mixed>> $items Ranked rows.
	 */
	private function render_ranked_list( array $items, string $type ): void {
		if ( empty( $items ) ) {
			echo '<p>' . esc_html__( 'No data found.', 'vs-download' ) . '</p>';
			return;
		}

		echo '<ol class="lwd-ranked-list">';
		$rank = 1;
		foreach ( $items as $item ) {
			if ( 'download' === $type ) {
				$title = (string) ( $item['title'] ?? '' );
				$count = (int) ( $item['count'] ?? 0 );
				$detail = '';
				$link = ! empty( $item['download_id'] ) ? get_edit_post_link( (int) $item['download_id'] ) : '';
			} else {
				$title  = (string) ( $item['name'] ?? '' );
				$detail = (string) ( $item['detail'] ?? '' );
				$count  = (int) ( $item['count'] ?? 0 );
				$link   = ! empty( $item['user_id'] ) ? get_edit_user_link( (int) $item['user_id'] ) : '';
			}

			$count_class = 'user' === $type ? ' lwd-ranked-count--green' : '';
			echo '<li>';
			echo '<span class="lwd-rank">' . esc_html( (string) $rank ) . '</span>';
			echo '<div class="lwd-ranked-main">';
			if ( $link ) {
				echo '<strong><a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a></strong>';
			} else {
				echo '<strong>' . esc_html( $title ) . '</strong>';
			}
			if ( $detail !== '' ) {
				echo '<span>' . esc_html( $detail ) . '</span>';
			}
			echo '</div>';
			echo '<span class="lwd-ranked-count' . esc_attr( $count_class ) . '">' . esc_html( (string) number_format_i18n( $count ) ) . '</span>';
			echo '</li>';
			++$rank;
		}
		echo '</ol>';
	}
}
