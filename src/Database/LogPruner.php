<?php
/**
 * Log Pruner — daily cron-based cleanup of old download logs.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Database;

use Vs\Download\Admin\SettingsPage;

/**
 * Schedules and executes periodic log pruning.
 */
final class LogPruner {

	/**
	 * Cron hook name.
	 */
	public const CRON_HOOK = 'lwd_prune_logs';

	/**
	 * Constructor — registers hooks.
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, [ $this, 'prune' ] );
	}

	/**
	 * Schedule the cron event on plugin activation.
	 * Called from Database\Activator::activate().
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule the cron event on plugin deactivation.
	 * Called from lw_download_deactivate().
	 */
	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Execute pruning: delete rows older than the configured retention window.
	 */
	public function prune(): void {
		$days = (int) get_option( SettingsPage::OPT_RETENTION, 90 );

		// 0 means "keep forever" — skip pruning.
		if ( $days <= 0 ) {
			return;
		}

		global $wpdb;

		$table_name = $wpdb->prefix . 'lwd_download_logs';
		$cutoff     = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE downloaded_at < %s",
				$cutoff
			)
		);
	}
}
