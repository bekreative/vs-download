<?php
/**
 * Persist health check reports.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Health;

/**
 * Options for health reports and pending first-run flag.
 */
final class HealthReportStore {

	public const OPTION_REPORT          = 'lwd_health_report';
	public const OPTION_PENDING         = 'lwd_pending_health_check';
	public const OPTION_LAST_RUN        = 'lwd_health_last_run';

	/**
	 * @return list<array<string, string>>
	 */
	public static function get_report(): array {
		$stored = get_option( self::OPTION_REPORT, [] );
		return is_array( $stored ) ? $stored : [];
	}

	/**
	 * @param list<array<string, string>> $results Check rows.
	 */
	public static function save_report( array $results ): void {
		update_option( self::OPTION_REPORT, $results, false );
		update_option( self::OPTION_LAST_RUN, time(), false );
	}

	public static function is_pending(): bool {
		return (bool) get_option( self::OPTION_PENDING, false );
	}

	public static function clear_pending(): void {
		delete_option( self::OPTION_PENDING );
	}

	public static function mark_pending(): void {
		update_option( self::OPTION_PENDING, '1', false );
	}

	/**
	 * @param list<HealthCheckResult> $results
	 * @return list<array<string, string>>
	 */
	public static function serialize( array $results ): array {
		return array_map(
			static fn( HealthCheckResult $r ): array => $r->to_array(),
			$results
		);
	}

	/**
	 * @return array{pass:int,warn:int,fail:int}
	 */
	public static function count_statuses( array $report ): array {
		$counts = [ 'pass' => 0, 'warn' => 0, 'fail' => 0 ];
		foreach ( $report as $row ) {
			$status = (string) ( $row['status'] ?? '' );
			if ( isset( $counts[ $status ] ) ) {
				++$counts[ $status ];
			}
		}
		return $counts;
	}
}
