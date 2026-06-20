<?php
/**
 * Composer autoloader check.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Health\Checks;

use Vs\Download\Health\HealthCheckInterface;
use Vs\Download\Health\HealthCheckResult;

/**
 * Ensures vendor/autoload.php exists.
 */
final class AutoloaderCheck implements HealthCheckInterface {

	public function run(): HealthCheckResult {
		$path = VS_DOWNLOAD_PATH . 'vendor/autoload.php';
		$ok   = file_exists( $path );

		return new HealthCheckResult(
			'autoloader',
			$ok ? HealthCheckResult::STATUS_PASS : HealthCheckResult::STATUS_FAIL,
			__( 'Composer autoloader', 'vs-download' ),
			$ok
				? __( 'vendor/autoload.php is present.', 'vs-download' )
				: __( 'vendor/autoload.php is missing.', 'vs-download' ),
			$ok ? '' : __( 'Run "composer install" in the plugin directory, or deploy a release ZIP that includes vendor/.', 'vs-download' )
		);
	}
}
