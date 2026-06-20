<?php
/**
 * Abilities API Controller for agentic operations.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Api;

use Vs\Download\PostTypes\Download;
use Vs\Download\Database\Activator;

/**
 * Class AbilitiesController
 */
final class AbilitiesController extends \WP_REST_Controller {

	/**
	 * Namespace.
	 */
	protected $namespace = 'wp-abilities/v1';

	/**
	 * Ability name.
	 */
	protected $ability = 'download';

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/abilities/' . $this->ability . '/health-check/run',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'run_health_check' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/abilities/' . $this->ability . '/get-stats/run',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'run_get_stats' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Check permission.
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Run health check.
	 */
	public function run_health_check(): \WP_REST_Response {
		global $wpdb;
		$table_name = $wpdb->prefix . 'lwd_download_logs';
		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name;

		return rest_ensure_response( [
			'status'  => 'ok',
			'version' => VS_DOWNLOAD_VERSION,
			'db_info' => [
				'table_name'   => $table_name,
				'table_exists' => $table_exists,
				'db_version'   => get_option( 'lw_download_db_version' ),
			],
			'post_types' => [
				'download' => post_type_exists( Download::POST_TYPE ),
			],
		] );
	}

	/**
	 * Get stats.
	 */
	public function run_get_stats(): \WP_REST_Response {
		global $wpdb;
		$table_name = $wpdb->prefix . 'lwd_download_logs';

		$total_downloads = (int) $wpdb->get_var( "SELECT COUNT(id) FROM $table_name" );
		$unique_users    = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM $table_name" );
		$unique_ips      = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT ip_address) FROM $table_name" );

		return rest_ensure_response( [
			'total_downloads' => $total_downloads,
			'unique_users'    => $unique_users,
			'unique_ips'      => $unique_ips,
			'last_updated'    => current_time( 'mysql' ),
		] );
	}
}
