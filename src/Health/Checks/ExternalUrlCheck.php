<?php
/**
 * Outbound HTTP check for remote file URLs.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Health\Checks;

use Vs\Download\Health\HealthCheckInterface;
use Vs\Download\Health\HealthCheckResult;

/**
 * Tests wp_remote_head for external download URLs.
 */
final class ExternalUrlCheck implements HealthCheckInterface {

	public function run(): HealthCheckResult {
		$test_url = 'https://www.wordpress.org/';
		$response = wp_remote_head(
			$test_url,
			[
				'timeout'   => 10,
				'sslverify' => true,
			]
		);

		if ( is_wp_error( $response ) ) {
			return new HealthCheckResult(
				'external_http',
				HealthCheckResult::STATUS_WARN,
				__( 'Outbound HTTP', 'vs-download' ),
				sprintf(
					/* translators: %s: error message */
					__( 'Could not reach a test URL: %s', 'vs-download' ),
					$response->get_error_message()
				),
				__( 'Remote file URLs in downloads may fail. Check firewall, SSL, or allow_url_fopen / cURL on the host.', 'vs-download' )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 400 ) {
			return new HealthCheckResult(
				'external_http',
				HealthCheckResult::STATUS_PASS,
				__( 'Outbound HTTP', 'vs-download' ),
				__( 'The server can reach external URLs (remote download files should work).', 'vs-download' )
			);
		}

		return new HealthCheckResult(
			'external_http',
			HealthCheckResult::STATUS_WARN,
			__( 'Outbound HTTP', 'vs-download' ),
			sprintf(
				/* translators: %d: HTTP status code */
				__( 'Test request returned HTTP %d.', 'vs-download' ),
				$code
			),
			__( 'Verify hosting allows outbound HTTPS for remote file downloads.', 'vs-download' )
		);
	}
}
