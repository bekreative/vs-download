<?php
/**
 * Aggregated statistics for the Reports dashboard.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Admin;

use Vs\Download\PostTypes\Download;

/**
 * Query helpers for download log analytics.
 */
final class ReportsStats {

	public const RANGE_7   = 7;
	public const RANGE_30  = 30;
	public const RANGE_90  = 90;
	public const RANGE_365 = 365;

	/**
	 * @return array{days: int, start: string, end: string, prev_start: string, prev_end: string}
	 */
	public static function resolve_range( int $days ): array {
		$allowed = [ self::RANGE_7, self::RANGE_30, self::RANGE_90, self::RANGE_365 ];
		if ( ! in_array( $days, $allowed, true ) ) {
			$days = self::RANGE_30;
		}

		$end_ts   = current_time( 'timestamp' );
		$start_ts = strtotime( '-' . ( $days - 1 ) . ' days', $end_ts );
		$prev_end = strtotime( '-1 day', $start_ts );
		$prev_start = strtotime( '-' . ( $days - 1 ) . ' days', $prev_end );

		return [
			'days'       => $days,
			'start'      => gmdate( 'Y-m-d 00:00:00', $start_ts ),
			'end'        => gmdate( 'Y-m-d 23:59:59', $end_ts ),
			'prev_start' => gmdate( 'Y-m-d 00:00:00', $prev_start ),
			'prev_end'   => gmdate( 'Y-m-d 23:59:59', $prev_end ),
		];
	}

	/**
	 * @return array{
	 *   total_downloads: array{value: int, change: float},
	 *   unique_users: array{value: int, change: float},
	 *   logged_in_rate: array{value: float, change: float},
	 *   active_files: array{value: int, change: float}
	 * }
	 */
	public static function get_summary_cards( string $start, string $end, string $prev_start, string $prev_end ): array {
		$current  = self::period_metrics( $start, $end );
		$previous = self::period_metrics( $prev_start, $prev_end );

		return [
			'total_downloads' => [
				'value'  => $current['downloads'],
				'change' => self::percent_change( $current['downloads'], $previous['downloads'] ),
			],
			'unique_users' => [
				'value'  => $current['unique_users'],
				'change' => self::percent_change( $current['unique_users'], $previous['unique_users'] ),
			],
			'logged_in_rate' => [
				'value'  => $current['logged_in_rate'],
				'change' => round( $current['logged_in_rate'] - $previous['logged_in_rate'], 1 ),
			],
			'active_files' => [
				'value'  => $current['active_files'],
				'change' => self::percent_change( $current['active_files'], $previous['active_files'] ),
			],
		];
	}

	/**
	 * @return list<array{label: string, count: int}>
	 */
	public static function get_chart_data( string $start, string $end, string $granularity ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'lwd_download_logs';
		$group = 'week' === $granularity ? '%x-W%v' : '%Y-%m';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(downloaded_at, %s) AS period_key,
				        MIN(DATE(downloaded_at)) AS period_start,
				        COUNT(id) AS total
				 FROM `{$table}`
				 WHERE downloaded_at BETWEEN %s AND %s
				 GROUP BY period_key
				 ORDER BY period_start ASC",
				$group,
				$start,
				$end
			),
			ARRAY_A
		);

		$out = [];
		foreach ( $rows as $row ) {
			$label = 'week' === $granularity
				? self::week_label( (string) ( $row['period_start'] ?? '' ) )
				: self::month_label( (string) ( $row['period_key'] ?? '' ) );

			$out[] = [
				'label' => $label,
				'count' => (int) ( $row['total'] ?? 0 ),
			];
		}

		return $out;
	}

	/**
	 * @return list<array{download_id: int, title: string, count: int}>
	 */
	public static function get_top_downloads( string $start, string $end, int $limit = 5 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'lwd_download_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT download_id, COUNT(id) AS total
				 FROM `{$table}`
				 WHERE downloaded_at BETWEEN %s AND %s
				 GROUP BY download_id
				 ORDER BY total DESC
				 LIMIT %d",
				$start,
				$end,
				$limit
			)
		);

		$out = [];
		foreach ( $rows as $row ) {
			$id = (int) $row->download_id;
			$out[] = [
				'download_id' => $id,
				'title'       => get_the_title( $id ) ?: __( '(deleted)', 'vs-download' ),
				'count'       => (int) $row->total,
			];
		}

		return $out;
	}

	/**
	 * @return list<array{user_id: int, name: string, detail: string, count: int}>
	 */
	public static function get_top_users( string $start, string $end, int $limit = 5 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'lwd_download_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$logged_in = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, COUNT(id) AS total
				 FROM `{$table}`
				 WHERE downloaded_at BETWEEN %s AND %s AND user_id > 0
				 GROUP BY user_id
				 ORDER BY total DESC
				 LIMIT %d",
				$start,
				$end,
				$limit
			)
		);

		$out = [];
		foreach ( $logged_in as $row ) {
			$user_id = (int) $row->user_id;
			$user    = get_userdata( $user_id );
			$out[]   = [
				'user_id' => $user_id,
				'name'    => $user ? $user->display_name : sprintf( __( 'User #%d', 'vs-download' ), $user_id ),
				'detail'  => $user ? implode( ', ', $user->roles ) : '',
				'count'   => (int) $row->total,
			];
		}

		if ( count( $out ) >= $limit ) {
			return $out;
		}

		$remaining = $limit - count( $out );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$guests = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ip_address, COUNT(id) AS total
				 FROM `{$table}`
				 WHERE downloaded_at BETWEEN %s AND %s AND user_id = 0 AND ip_address != ''
				 GROUP BY ip_address
				 ORDER BY total DESC
				 LIMIT %d",
				$start,
				$end,
				$remaining
			)
		);

		foreach ( $guests as $row ) {
			$out[] = [
				'user_id' => 0,
				'name'    => (string) $row->ip_address,
				'detail'  => __( 'Guest', 'vs-download' ),
				'count'   => (int) $row->total,
			];
		}

		return $out;
	}

	/**
	 * @return int Published download posts count.
	 */
	public static function get_published_download_count(): int {
		$counts = wp_count_posts( Download::POST_TYPE );

		return (int) ( $counts->publish ?? 0 );
	}

	/**
	 * @return array{downloads: int, unique_users: int, logged_in_rate: float, active_files: int}
	 */
	private static function period_metrics( string $start, string $end ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'lwd_download_logs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$downloads = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM `{$table}` WHERE downloaded_at BETWEEN %s AND %s",
				$start,
				$end
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$logged_in = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM `{$table}` WHERE downloaded_at BETWEEN %s AND %s AND user_id > 0",
				$start,
				$end
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$unique_logged_in = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM `{$table}` WHERE downloaded_at BETWEEN %s AND %s AND user_id > 0",
				$start,
				$end
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$unique_guest_ips = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT ip_address) FROM `{$table}` WHERE downloaded_at BETWEEN %s AND %s AND user_id = 0 AND ip_address != ''",
				$start,
				$end
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$active_files = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT download_id) FROM `{$table}` WHERE downloaded_at BETWEEN %s AND %s",
				$start,
				$end
			)
		);

		$logged_in_rate = $downloads > 0 ? round( ( $logged_in / $downloads ) * 100, 1 ) : 0.0;

		return [
			'downloads'      => $downloads,
			'unique_users'   => $unique_logged_in + $unique_guest_ips,
			'logged_in_rate' => $logged_in_rate,
			'active_files'   => $active_files,
		];
	}

	private static function percent_change( int|float $current, int|float $previous ): float {
		if ( $previous <= 0 ) {
			return $current > 0 ? 100.0 : 0.0;
		}

		return round( ( ( $current - $previous ) / $previous ) * 100, 1 );
	}

	private static function week_label( string $date ): string {
		if ( $date === '' ) {
			return '';
		}

		$ts = strtotime( $date );

		return $ts ? 'W' . gmdate( 'W', $ts ) : $date;
	}

	private static function month_label( string $period_key ): string {
		$ts = strtotime( $period_key . '-01' );

		return $ts ? gmdate( 'M Y', $ts ) : $period_key;
	}
}
