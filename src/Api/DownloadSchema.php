<?php
/**
 * REST schema for download resources.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Api;

/**
 * JSON schema definitions for DownloadsController.
 */
final class DownloadSchema {

	/**
	 * @return array<string, mixed>
	 */
	public static function item(): array {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'download',
			'type'       => 'object',
			'properties' => [
				'id'             => [
					'description' => 'Unique identifier for the download.',
					'type'        => 'integer',
				],
				'title'          => [
					'description' => 'The title of the download.',
					'type'        => 'string',
				],
				'url'            => [
					'description' => 'Tracked download URL for the primary version (null if access denied).',
					'type'        => [ 'string', 'null' ],
					'format'      => 'uri',
				],
				'version'        => [
					'description' => 'Primary file version label.',
					'type'        => 'string',
				],
				'versions'       => [
					'description' => 'All file versions with tracked URLs when permitted.',
					'type'        => 'array',
				],
				'access'         => [
					'description' => 'Access mode: public, logged_in, or role.',
					'type'        => 'string',
				],
				'can_download'   => [
					'description' => 'Whether the current user may download this item.',
					'type'        => 'boolean',
				],
				'download_count' => [
					'description' => 'Historical total count.',
					'type'        => 'integer',
				],
				'categories'     => [
					'description' => 'Category slugs.',
					'type'        => 'array',
				],
				'tags'           => [
					'description' => 'Tag slugs.',
					'type'        => 'array',
				],
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function collection_params(): array {
		return [
			'page'            => [
				'description'       => 'Current page of the collection.',
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
			],
			'per_page'        => [
				'description'       => 'Maximum number of items to be returned in result set.',
				'type'              => 'integer',
				'default'           => 10,
				'sanitize_callback' => 'absint',
			],
			'accessible_only' => [
				'description' => 'When true, omit downloads the current user cannot access.',
				'type'        => 'boolean',
				'default'     => false,
			],
		];
	}
}
