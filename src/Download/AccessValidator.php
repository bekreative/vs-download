<?php
/**
 * Access Validator with meta-driven permission logic.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Download;

/**
 * Validates access to downloads based on per-download meta settings.
 */
class AccessValidator {

	/**
	 * Access modes.
	 */
	public const ACCESS_PUBLIC    = 'public';
	public const ACCESS_LOGGED_IN = 'logged_in';
	public const ACCESS_ROLE      = 'role';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'lwd_pre_grant_download', [ $this, 'check_access' ], 10, 3 );
	}

	/**
	 * Whether the given user may download this item (runs registered filters).
	 *
	 * @param int      $download_id Download post ID.
	 * @param int|null $user_id     User ID; defaults to current user.
	 * @return bool
	 */
	public static function user_can_download( int $download_id, ?int $user_id = null ): bool {
		$user_id = null !== $user_id ? $user_id : get_current_user_id();

		return (bool) apply_filters( 'lwd_pre_grant_download', true, $download_id, (int) $user_id );
	}

	/**
	 * Check access based on the download's _lwd_access meta.
	 *
	 * @param bool $allow       Current allowance state.
	 * @param int  $download_id Post ID being downloaded.
	 * @param int  $user_id     User ID attempting download.
	 * @return bool
	 */
	public function check_access( bool $allow, int $download_id, int $user_id ): bool {
		if ( ! $allow ) {
			return false;
		}

		return self::evaluate_meta_access( $download_id, $user_id );
	}

	/**
	 * Core access rules from post meta (no filter chain).
	 *
	 * @param int $download_id Download post ID.
	 * @param int $user_id     User ID attempting download.
	 * @return bool
	 */
	public static function evaluate_meta_access( int $download_id, int $user_id ): bool {
		$access_mode = get_post_meta( $download_id, '_lwd_access', true );

		if ( empty( $access_mode ) || self::ACCESS_PUBLIC === $access_mode ) {
			return true;
		}

		if ( self::ACCESS_LOGGED_IN === $access_mode ) {
			return $user_id > 0;
		}

		if ( self::ACCESS_ROLE === $access_mode ) {
			$roles = get_post_meta( $download_id, '_lwd_access_roles', true );
			if ( empty( $roles ) || ! is_array( $roles ) ) {
				return true;
			}

			if ( 0 === $user_id ) {
				return false;
			}

			$user = get_userdata( $user_id );
			if ( ! $user ) {
				return false;
			}

			return ! empty( array_intersect( $user->roles, $roles ) );
		}

		return true;
	}
}
