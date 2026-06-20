<?php
/**
 * Register Download Meta.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Meta;

use Vs\Download\Download\AccessValidator;
use Vs\Download\PostTypes\Download;

/**
 * Class DownloadMeta
 */
class DownloadMeta {

	/**
	 * Register meta fields.
	 *
	 * @return void
	 */
	public static function register(): void {
		$edit_posts = static function (): bool {
			return current_user_can( 'edit_posts' );
		};

		register_post_meta(
			Download::POST_TYPE,
			'_lwd_file_url',
			[
				'type'              => 'string',
				'description'       => 'URL of the downloadable file.',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => $edit_posts,
			]
		);

		register_post_meta(
			Download::POST_TYPE,
			'_lwd_version',
			[
				'type'              => 'string',
				'description'       => 'Version string of the primary file.',
				'single'            => true,
				'show_in_rest'      => true,
				'auth_callback'     => $edit_posts,
			]
		);

		register_post_meta(
			Download::POST_TYPE,
			'_lwd_download_count',
			[
				'type'              => 'integer',
				'description'       => 'Cached total download count.',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => 0,
				'auth_callback'     => $edit_posts,
			]
		);

		register_post_meta(
			Download::POST_TYPE,
			'_lwd_versions',
			[
				'type'              => 'array',
				'description'       => 'Array of file versions [{version, url}].',
				'single'            => true,
				'show_in_rest'      => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'version' => [ 'type' => 'string' ],
								'url'     => [ 'type' => 'string', 'format' => 'uri' ],
							],
						],
					],
				],
				'auth_callback'     => $edit_posts,
			]
		);

		register_post_meta(
			Download::POST_TYPE,
			'_lwd_access',
			[
				'type'              => 'string',
				'description'       => 'Access mode: public, logged_in, or role.',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => AccessValidator::ACCESS_PUBLIC,
				'auth_callback'     => $edit_posts,
			]
		);

		register_post_meta(
			Download::POST_TYPE,
			'_lwd_access_roles',
			[
				'type'              => 'array',
				'description'       => 'Allowed roles when access mode is role.',
				'single'            => true,
				'show_in_rest'      => [
					'schema' => [
						'type'  => 'array',
						'items' => [ 'type' => 'string' ],
					],
				],
				'auth_callback'     => $edit_posts,
			]
		);
	}
}
