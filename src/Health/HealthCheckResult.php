<?php
/**
 * Single health check outcome.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Health;

/**
 * Value object for one environment check.
 */
final class HealthCheckResult {

	public const STATUS_PASS = 'pass';
	public const STATUS_WARN = 'warn';
	public const STATUS_FAIL = 'fail';

	/**
	 * @param string               $id             Machine-readable check id.
	 * @param string               $status         pass|warn|fail.
	 * @param string               $label          Short title (translated).
	 * @param string               $message        What was found.
	 * @param string               $recommendation Suggested fix (may be empty).
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $status,
		public readonly string $label,
		public readonly string $message,
		public readonly string $recommendation = '',
	) {
	}

	/**
	 * @return array<string, string>
	 */
	public function to_array(): array {
		return [
			'id'               => $this->id,
			'status'           => $this->status,
			'label'            => $this->label,
			'message'          => $this->message,
			'recommendation'   => $this->recommendation,
		];
	}
}
