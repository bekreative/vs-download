<?php
/**
 * Assign taxonomy terms by name during DLM migration.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Migration;

/**
 * Creates missing terms and assigns them to a download post.
 */
final class DlmTermAssigner {

	/**
	 * @param list<string> $term_names Term labels.
	 */
	public static function assign( int $post_id, array $term_names, string $taxonomy ): void {
		$term_ids = [];

		foreach ( $term_names as $name ) {
			$existing = term_exists( $name, $taxonomy );
			if ( is_array( $existing ) && isset( $existing['term_id'] ) ) {
				$term_ids[] = (int) $existing['term_id'];
			} elseif ( is_numeric( $existing ) ) {
				$term_ids[] = (int) $existing;
			} else {
				$created = wp_insert_term( $name, $taxonomy );
				if ( ! is_wp_error( $created ) && isset( $created['term_id'] ) ) {
					$term_ids[] = (int) $created['term_id'];
				}
			}
		}

		if ( $term_ids ) {
			wp_set_post_terms( $post_id, $term_ids, $taxonomy );
		}
	}
}
