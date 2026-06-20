<?php
/**
 * Plugin text domain loading.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\I18n;

/**
 * Loads translations; admin UI follows the user's admin language.
 */
final class TextDomain {

	public const DOMAIN = 'vs-download';

	/**
	 * Register hooks and load translations.
	 */
	public static function boot(): void {
		add_filter( 'plugin_locale', [ self::class, 'filter_locale' ], 10, 2 );
		self::load();
	}

	/**
	 * Load MO files from languages/.
	 */
	public static function load(): void {
		load_plugin_textdomain(
			self::DOMAIN,
			false,
			dirname( plugin_basename( VS_DOWNLOAD_FILE ) ) . '/languages'
		);
	}

	/**
	 * @param string $locale Current locale.
	 * @param string $domain Text domain.
	 */
	public static function filter_locale( string $locale, string $domain ): string {
		if ( self::DOMAIN !== $domain ) {
			return $locale;
		}

		if ( is_admin() && function_exists( 'get_user_locale' ) ) {
			return get_user_locale();
		}

		return function_exists( 'determine_locale' ) ? determine_locale() : $locale;
	}
}
