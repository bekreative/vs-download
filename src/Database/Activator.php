<?php
/**
 * Database Activator class.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Database;

use Vs\Download\Download\DownloadRewrites;

/**
 * Activator handles creating custom database tables.
 */
class Activator {

	public const DB_VERSION_OPTION = 'lw_download_db_version';

	/**
	 * Current schema version. Bump when table SQL changes.
	 */
	public const DB_VERSION = '1.0.0';

	/**
	 * Run upon plugin activation.
	 */
	public static function activate(): void {
		self::create_tables();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		DownloadRewrites::schedule_flush();
	}

	/**
	 * Run on init when stored version is behind (e.g. after plugin update).
	 */
	public static function maybe_upgrade(): void {
		$installed = (string) get_option( self::DB_VERSION_OPTION, '' );

		if ( version_compare( $installed, self::DB_VERSION, '>=' ) ) {
			return;
		}

		self::create_tables();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Create custom tables via dbDelta.
	 */
	private static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'lwd_download_logs';

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			download_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			ip_address varchar(45) NOT NULL DEFAULT '',
			downloaded_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY download_id (download_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
