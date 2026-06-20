<?php
/**
 * Tag Taxonomy.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Taxonomies;

use Vs\Download\PostTypes\Download;

/**
 * Tag Taxonomy class.
 */
class DownloadTag {

	/**
	 * Taxonomy slug.
	 */
	public const TAXONOMY = 'lwd_tag';

	/**
	 * Register the taxonomy.
	 *
	 * @return void
	 */
	public static function register(): void {
		$labels = [
			'name'                       => _x( 'Tags', 'Taxonomy General Name', 'vs-download' ),
			'singular_name'              => _x( 'Tag', 'Taxonomy Singular Name', 'vs-download' ),
			'menu_name'                  => __( 'Tags', 'vs-download' ),
			'all_items'                  => __( 'All Tags', 'vs-download' ),
			'parent_item'                => null,
			'parent_item_colon'          => null,
			'new_item_name'              => __( 'New Tag Name', 'vs-download' ),
			'add_new_item'               => __( 'Add New Tag', 'vs-download' ),
			'edit_item'                  => __( 'Edit Tag', 'vs-download' ),
			'update_item'                => __( 'Update Tag', 'vs-download' ),
			'view_item'                  => __( 'View Tag', 'vs-download' ),
			'separate_items_with_commas' => __( 'Separate tags with commas', 'vs-download' ),
			'add_or_remove_items'        => __( 'Add or remove tags', 'vs-download' ),
			'choose_from_most_used'      => __( 'Choose from the most used', 'vs-download' ),
			'popular_items'              => __( 'Popular Tags', 'vs-download' ),
			'search_items'               => __( 'Search Tags', 'vs-download' ),
			'not_found'                  => __( 'Not Found', 'vs-download' ),
			'no_terms'                   => __( 'No tags', 'vs-download' ),
			'items_list'                 => __( 'Tags list', 'vs-download' ),
			'items_list_navigation'      => __( 'Tags list navigation', 'vs-download' ),
		];

		$args = [
			'labels'             => $labels,
			'hierarchical'       => false,
			'public'             => true,
			'show_ui'            => true,
			'show_admin_column'  => true,
			'show_in_nav_menus'  => true,
			'show_tagcloud'      => true,
			'show_in_rest'       => true,
			'rewrite'            => [ 'slug' => 'lwd-tag' ],
		];

		register_taxonomy( self::TAXONOMY, [ Download::POST_TYPE ], $args );
	}
}
