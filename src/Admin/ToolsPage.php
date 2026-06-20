<?php
/**
 * Downloads → Tools (environment checks + migration).
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Admin;

use Vs\Download\Admin\Tools\EnvironmentTab;
use Vs\Download\Admin\Tools\ImportExportTab;
use Vs\Download\Health\HealthReportStore;

/**
 * Admin Tools hub with Environment, Migration, and Import/Export tabs.
 */
final class ToolsPage {

	public const PAGE_SLUG       = 'lwd_tools';
	public const TAB_ENV         = 'environment';
	public const TAB_MIGRATION   = 'migration';
	public const TAB_IMPORT_EXPORT = 'import-export';

	private EnvironmentTab $environment;
	private MigrationPage $migration;
	private ImportExportTab $import_export;

	public function __construct() {
		$this->environment    = new EnvironmentTab();
		$this->migration      = new MigrationPage();
		$this->import_export  = new ImportExportTab();
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_notices', [ $this, 'maybe_pending_notice' ] );
	}

	public function register_menu(): void {
		add_submenu_page(
			'edit.php?post_type=lwd_download',
			__( 'Tools', 'vs-download' ),
			__( 'Tools', 'vs-download' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function maybe_pending_notice(): void {
		if ( ! HealthReportStore::is_pending() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'lwd_download_page_' . self::PAGE_SLUG === $screen->id ) {
			return;
		}

		$url = admin_url( 'edit.php?post_type=lwd_download&page=' . self::PAGE_SLUG . '&tab=' . self::TAB_ENV );
		printf(
			'<div class="notice notice-warning is-dismissible"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
			esc_html__( 'LW Download:', 'vs-download' ),
			esc_html__( 'Please run environment checks to verify this host is compatible.', 'vs-download' ),
			esc_url( $url ),
			esc_html__( 'Open Tools → Environment', 'vs-download' )
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : self::TAB_ENV;
		if ( HealthReportStore::is_pending() && ! isset( $_GET['tab'] ) ) {
			$tab = self::TAB_ENV;
		}
		if ( ! in_array( $tab, [ self::TAB_ENV, self::TAB_MIGRATION, self::TAB_IMPORT_EXPORT ], true ) ) {
			$tab = self::TAB_ENV;
		}

		$base = admin_url( 'edit.php?post_type=lwd_download&page=' . self::PAGE_SLUG );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'LW Download Tools', 'vs-download' ); ?></h1>
			<nav class="nav-tab-wrapper wp-clearfix" style="margin-bottom:16px;">
				<a href="<?php echo esc_url( $base . '&tab=' . self::TAB_ENV ); ?>" class="nav-tab <?php echo self::TAB_ENV === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Environment', 'vs-download' ); ?>
				</a>
				<a href="<?php echo esc_url( $base . '&tab=' . self::TAB_MIGRATION ); ?>" class="nav-tab <?php echo self::TAB_MIGRATION === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Migration', 'vs-download' ); ?>
				</a>
				<a href="<?php echo esc_url( $base . '&tab=' . self::TAB_IMPORT_EXPORT ); ?>" class="nav-tab <?php echo self::TAB_IMPORT_EXPORT === $tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Import / Export', 'vs-download' ); ?>
				</a>
			</nav>
			<?php
			if ( self::TAB_MIGRATION === $tab ) {
				$this->migration->render_tab();
			} elseif ( self::TAB_IMPORT_EXPORT === $tab ) {
				$this->import_export->render();
			} else {
				$this->environment->render();
			}
			?>
		</div>
		<?php
	}
}
