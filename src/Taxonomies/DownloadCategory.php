<?php
/**
 * Category Taxonomy.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Taxonomies;

use Vs\Download\PostTypes\Download;

/**
 * Category Taxonomy class.
 */
class DownloadCategory {

	/**
	 * Taxonomy slug.
	 */
	public const TAXONOMY = 'lwd_category';

	/**
	 * Register the taxonomy.
	 *
	 * @return void
	 */
	public static function register(): void {
		$labels = [
			'name'                       => _x( 'Categories', 'Taxonomy General Name', 'vs-download' ),
			'singular_name'              => _x( 'Category', 'Taxonomy Singular Name', 'vs-download' ),
			'menu_name'                  => __( 'Categories', 'vs-download' ),
			'all_items'                  => __( 'All Categories', 'vs-download' ),
			'parent_item'                => __( 'Parent Category', 'vs-download' ),
			'parent_item_colon'          => __( 'Parent Category:', 'vs-download' ),
			'new_item_name'              => __( 'New Category Name', 'vs-download' ),
			'add_new_item'               => __( 'Add New Category', 'vs-download' ),
			'edit_item'                  => __( 'Edit Category', 'vs-download' ),
			'update_item'                => __( 'Update Category', 'vs-download' ),
			'view_item'                  => __( 'View Category', 'vs-download' ),
			'separate_items_with_commas' => __( 'Separate categories with commas', 'vs-download' ),
			'add_or_remove_items'        => __( 'Add or remove categories', 'vs-download' ),
			'choose_from_most_used'      => __( 'Choose from the most used', 'vs-download' ),
			'popular_items'              => __( 'Popular Categories', 'vs-download' ),
			'search_items'               => __( 'Search Categories', 'vs-download' ),
			'not_found'                  => __( 'Not Found', 'vs-download' ),
			'no_terms'                   => __( 'No categories', 'vs-download' ),
			'items_list'                 => __( 'Categories list', 'vs-download' ),
			'items_list_navigation'      => __( 'Categories list navigation', 'vs-download' ),
		];

		$args = [
			'labels'             => $labels,
			'hierarchical'       => true,
			'public'             => true,
			'show_ui'            => true,
			'show_admin_column'  => true,
			'show_in_nav_menus'  => true,
			'show_tagcloud'      => true,
			'show_in_rest'       => true,
			'rewrite'            => [ 'slug' => 'lwd-category' ],
		];

		register_taxonomy( self::TAXONOMY, [ Download::POST_TYPE ], $args );
	}
}
