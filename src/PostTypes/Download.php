<?php
/**
 * Download Custom Post Type.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\PostTypes;

use Vs\Download\Storage\LegacyIds;

/**
 * Download CPT class.
 */
class Download {

	/**
	 * Post type slug (stable storage ID).
	 */
	public const POST_TYPE = LegacyIds::POST_TYPE;

	/**
	 * Register the post type.
	 *
	 * @return void
	 */
	public static function register(): void {
		$labels = [
			'name'                  => _x( 'Downloads', 'Post type general name', 'vs-download' ),
			'singular_name'         => _x( 'Download', 'Post type singular name', 'vs-download' ),
			'menu_name'             => _x( 'Downloads', 'Admin Menu text', 'vs-download' ),
			'name_admin_bar'        => _x( 'Download', 'Add New on Toolbar', 'vs-download' ),
			'add_new'               => __( 'Add New', 'vs-download' ),
			'add_new_item'          => __( 'Add New Download', 'vs-download' ),
			'new_item'              => __( 'New Download', 'vs-download' ),
			'edit_item'             => __( 'Edit Download', 'vs-download' ),
			'view_item'             => __( 'View Download', 'vs-download' ),
			'all_items'             => __( 'All Downloads', 'vs-download' ),
			'search_items'          => __( 'Search Downloads', 'vs-download' ),
			'parent_item_colon'     => __( 'Parent Downloads:', 'vs-download' ),
			'not_found'             => __( 'No downloads found.', 'vs-download' ),
			'not_found_in_trash'    => __( 'No downloads found in Trash.', 'vs-download' ),
		];

		$args = [
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => [ 'slug' => 'download-item' ],
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'menu_icon'          => 'dashicons-download',
			'supports'           => [ 'title', 'editor', 'author', 'thumbnail' ],
			'show_in_rest'       => true,
		];

		register_post_type( self::POST_TYPE, $args );
	}
}
