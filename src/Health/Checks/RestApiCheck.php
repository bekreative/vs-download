<?php
/**
 * REST API availability check.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Health\Checks;

use Vs\Download\Health\HealthCheckInterface;
use Vs\Download\Health\HealthCheckResult;

/**
 * Verifies REST routes are registered.
 */
final class RestApiCheck implements HealthCheckInterface {

	public function run(): HealthCheckResult {
		$routes = rest_get_server()->get_routes();
		$ns     = 'lwd/v1/downloads';

		foreach ( array_keys( $routes ) as $route ) {
			if ( str_contains( (string) $route, $ns ) ) {
				return new HealthCheckResult(
					'rest_api',
					HealthCheckResult::STATUS_PASS,
					__( 'REST API', 'vs-download' ),
					sprintf(
						/* translators: %s: REST route namespace */
						__( 'Downloads REST route is registered (%s).', 'vs-download' ),
						$ns
					)
				);
			}
		}

		return new HealthCheckResult(
			'rest_api',
			HealthCheckResult::STATUS_WARN,
			__( 'REST API', 'vs-download' ),
			__( 'Downloads REST route was not found (rest_api_init may not have run yet).', 'vs-download' ),
			__( 'Save permalinks or reload admin; ensure no security plugin blocks /wp-json/.', 'vs-download' )
		);
	}
}
