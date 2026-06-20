<?php
/**
 * Shortcode functionality.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Frontend;

use Vs\Download\Download\AccessValidator;
use Vs\Download\Download\DownloadUrl;
use Vs\Download\PostTypes\Download;
use Vs\Download\Taxonomies\DownloadCategory;
use Vs\Download\Taxonomies\DownloadTag;

/**
 * Shortcode handler class.
 */
class Shortcode {

	public function __construct() {
		add_shortcode( 'lw_download', [ $this, 'render_download_shortcode' ] );
		add_shortcode( 'lw_downloads', [ $this, 'render_downloads_list' ] );
		add_action( 'wp_head', [ $this, 'inline_styles' ] );
	}

	public function inline_styles(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS.
		echo ShortcodeRenderer::inline_css();
	}

	/**
	 * @param array|string $atts Shortcode attributes.
	 */
	public function render_download_shortcode( $atts ): string {
		$attributes = shortcode_atts(
			[
				'id'       => 0,
				'version'  => '',
				'template' => 'box',
			],
			$atts,
			'lw_download'
		);

		$download_id = (int) $attributes['id'];
		if ( ! $download_id ) {
			return '';
		}

		$post = get_post( $download_id );
		if ( ! $post || Download::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return '';
		}

		if ( ! AccessValidator::user_can_download( $download_id ) ) {
			return '';
		}

		$data = $this->build_data( $post, sanitize_text_field( (string) $attributes['version'] ) );
		if ( '' === $data['url'] ) {
			return '';
		}

		return ShortcodeRenderer::render( (string) $attributes['template'], $data );
	}

	/**
	 * @param array|string $atts Shortcode attributes.
	 */
	public function render_downloads_list( $atts ): string {
		$attributes = shortcode_atts(
			[
				'category' => '',
				'tag'      => '',
				'limit'    => 10,
				'template' => 'box',
				'orderby'  => 'date',
			],
			$atts,
			'lw_downloads'
		);

		$query  = new \WP_Query( $this->list_query_args( $attributes ) );
		$output = '';

		foreach ( $query->posts as $post ) {
			if ( ! AccessValidator::user_can_download( $post->ID ) ) {
				continue;
			}

			$data = $this->build_data( $post );
			if ( '' === $data['url'] ) {
				continue;
			}

			$output .= ShortcodeRenderer::render( (string) $attributes['template'], $data );
		}

		wp_reset_postdata();
		return $output;
	}

	/**
	 * @param array<string, mixed> $attributes Shortcode attributes.
	 * @return array<string, mixed>
	 */
	private function list_query_args( array $attributes ): array {
		$args = [
			'post_type'      => Download::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => (int) $attributes['limit'],
			'orderby'        => 'downloads' === $attributes['orderby'] ? 'meta_value_num' : $attributes['orderby'],
		];

		if ( 'downloads' === $attributes['orderby'] ) {
			$args['meta_key'] = '_lwd_download_count';
			$args['order']    = 'DESC';
		}

		if ( ! empty( $attributes['category'] ) ) {
			$args['tax_query'] = [
				[
					'taxonomy' => DownloadCategory::TAXONOMY,
					'field'    => 'slug',
					'terms'    => array_map( 'trim', explode( ',', (string) $attributes['category'] ) ),
				],
			];
		}

		if ( ! empty( $attributes['tag'] ) ) {
			$tax = [
				'taxonomy' => DownloadTag::TAXONOMY,
				'field'    => 'slug',
				'terms'    => array_map( 'trim', explode( ',', (string) $attributes['tag'] ) ),
			];
			if ( isset( $args['tax_query'] ) ) {
				$args['tax_query']['relation'] = 'AND';
				$args['tax_query'][]           = $tax;
			} else {
				$args['tax_query'] = [ $tax ];
			}
		}

		return $args;
	}

	/**
	 * @return array{title:string,url:string,version:string,count:int}
	 */
	private function build_data( \WP_Post $post, string $version = '' ): array {
		$version = sanitize_text_field( $version );

		if ( '' === $version ) {
			$version = (string) get_post_meta( $post->ID, '_lwd_version', true );
		}

		$file_url = DownloadUrl::resolve_file_url( $post->ID, $version );

		return [
			'title'   => get_the_title( $post ),
			'url'     => '' !== $file_url ? DownloadUrl::tracking_url( $post->ID, $version ) : '',
			'version' => $version,
			'count'   => (int) get_post_meta( $post->ID, '_lwd_download_count', true ),
		];
	}
}
