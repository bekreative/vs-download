<?php
/**
 * REST API for Downloads.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Api;

use Vs\Download\Download\AccessValidator;
use Vs\Download\Download\DownloadUrl;
use Vs\Download\PostTypes\Download;
use Vs\Download\Taxonomies\DownloadCategory;
use Vs\Download\Taxonomies\DownloadTag;

/**
 * REST Controller for downloads.
 */
class DownloadsController extends \WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace;

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'lwd/v1';
		$this->rest_base = 'downloads';
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => '__return_true',
					'args'                => $this->get_collection_params(),
				],
				'schema' => [ $this, 'get_item_schema' ],
			]
		);
	}

	/**
	 * Get a collection of downloads.
	 *
	 * @param \WP_REST_Request $request Full data about the request.
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function get_items( $request ) {
		$args = [
			'post_type'      => Download::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $request['per_page'] ?? 10,
			'paged'          => $request['page'] ?? 1,
		];

		$query = new \WP_Query( $args );
		$data  = [];

		foreach ( $query->posts as $post ) {
			$item = $this->prepare_item_for_response( $post, $request );
			if ( ! empty( $request['accessible_only'] ) && empty( $item['can_download'] ) ) {
				continue;
			}
			$data[] = $item;
		}

		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) count( $data ) );
		$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );

		return $response;
	}

	/**
	 * Prepare item for response.
	 *
	 * @param \WP_Post         $post    Post object.
	 * @param \WP_REST_Request $request Request object.
	 * @return array<string, mixed>
	 */
	public function prepare_item_for_response( $post, $request ): array {
		$user_id      = get_current_user_id();
		$can_download = AccessValidator::user_can_download( $post->ID, $user_id );
		$version      = (string) get_post_meta( $post->ID, '_lwd_version', true );
		$versions     = get_post_meta( $post->ID, '_lwd_versions', true );
		$access       = get_post_meta( $post->ID, '_lwd_access', true );

		if ( ! is_array( $versions ) ) {
			$versions = [];
		}

		if ( empty( $access ) ) {
			$access = AccessValidator::ACCESS_PUBLIC;
		}

		$public_versions = [];
		foreach ( $versions as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['url'] ) ) {
				continue;
			}
			$ver_label = isset( $entry['version'] ) ? (string) $entry['version'] : '';
			$public_versions[] = [
				'version' => $ver_label,
				'url'     => $can_download ? DownloadUrl::tracking_url( $post->ID, $ver_label ) : null,
			];
		}

		return [
			'id'             => $post->ID,
			'title'          => get_the_title( $post ),
			'url'            => $can_download ? DownloadUrl::tracking_url( $post->ID, $version ) : null,
			'version'        => $version,
			'versions'       => $public_versions,
			'access'         => (string) $access,
			'can_download'   => $can_download,
			'download_count' => (int) get_post_meta( $post->ID, '_lwd_download_count', true ),
			'categories'     => wp_get_post_terms( $post->ID, DownloadCategory::TAXONOMY, [ 'fields' => 'slugs' ] ),
			'tags'           => wp_get_post_terms( $post->ID, DownloadTag::TAXONOMY, [ 'fields' => 'slugs' ] ),
		];
	}

	/**
	 * Custom Collection Params.
	 *
	 * @return array<string, mixed>
	 */
	public function get_collection_params(): array {
		return DownloadSchema::collection_params();
	}

	/**
	 * Get items schema.
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema(): array {
		return DownloadSchema::item();
	}
}
