<?php
/**
 * Plugin Name:       VS Download
 * Plugin URI:        https://github.com/bekreative/vs-download
 * Description:       Lightweight, no-bloat Download Manager for WordPress.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            WPSuli
 * Author URI:        https://wpsuli.hu
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vs-download
 * Domain Path:       /languages
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download;

use Vs\Core\Admin\HubMenu;
use Vs\Core\Bootstrap\AutoloadGuard;
use Vs\Core\I18n\TextDomain as CoreTextDomain;
use Vs\Core\Updater\GitHubReleaseUpdater;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VS_DOWNLOAD_VERSION', '1.1.0' );
define( 'VS_DOWNLOAD_FILE', __FILE__ );
define( 'VS_DOWNLOAD_PATH', plugin_dir_path( __FILE__ ) );
define( 'VS_DOWNLOAD_URL', plugin_dir_url( __FILE__ ) );

$vs_download_autoload = VS_DOWNLOAD_PATH . 'vendor/autoload.php';
if ( is_readable( $vs_download_autoload ) ) {
	require_once $vs_download_autoload;
}

AutoloadGuard::require_vendor( VS_DOWNLOAD_PATH, Plugin::class, 'VS Download' );

if ( ! class_exists( Plugin::class ) ) {
	return;
}

CoreTextDomain::load( 'vs-download', VS_DOWNLOAD_PATH );
I18n\TextDomain::boot();
HubMenu::boot();
GitHubReleaseUpdater::register( 'vs-download', VS_DOWNLOAD_FILE, VS_DOWNLOAD_VERSION );

/**
 * Main plugin instance.
 */
function vs_download(): Plugin {
	static $instance = null;

	if ( null === $instance ) {
		$instance = new Plugin();
	}

	return $instance;
}

/**
 * Activation hook.
 */
function vs_download_activate(): void {
	if ( class_exists( Database\Activator::class ) ) {
		Database\Activator::activate();
	}

	Database\LogPruner::schedule();

	if ( class_exists( Download\DownloadRewrites::class ) ) {
		Download\DownloadRewrites::schedule_flush();
	}

	if ( class_exists( Health\HealthReportStore::class ) ) {
		Health\HealthReportStore::mark_pending();
	}
}

/**
 * Deactivation hook.
 */
function vs_download_deactivate(): void {
	Database\LogPruner::unschedule();
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\\vs_download_activate' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\vs_download_deactivate' );

add_action(
	'plugins_loaded',
	static function (): void {
		vs_download();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			CLI\CLI::register();
		}
	},
	1
);

add_action(
	'admin_notices',
	static function (): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( is_plugin_active( 'lw-download/lw-download.php' ) ) {
			echo '<div class="notice notice-warning"><p>';
			esc_html_e( 'VS Download: deactivate and remove the legacy lw-download plugin to avoid conflicts.', 'vs-download' );
			echo '</p></div>';
		}
	}
);
