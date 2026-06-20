<?php
/**
 * Detect common search-engine and crawler user agents.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Download;

use Vs\Download\Admin\SettingsPage;

/**
 * Bot detection for optional log exclusion.
 */
final class BotDetector {

	/**
	 * Known bot/crawler substrings (lowercase).
	 *
	 * @var list<string>
	 */
	private const PATTERNS = [
		'googlebot',
		'bingbot',
		'slurp',
		'duckduckbot',
		'baiduspider',
		'yandexbot',
		'facebot',
		'ia_archiver',
		'semrushbot',
		'ahrefsbot',
		'mj12bot',
		'dotbot',
		'petalbot',
		'applebot',
		'facebookexternalhit',
		'linkedinbot',
		'twitterbot',
		'rogerbot',
		'embedly',
		'quora link preview',
		'showyoubot',
		'outbrain',
		'pinterest',
		'developers.google.com/+/web/snippet',
		'wget',
		'curl/',
		'python-requests',
		'go-http-client',
		'headlesschrome',
	];

	/**
	 * Whether the current request looks like a bot and should be excluded from logging.
	 */
	public static function should_skip_logging(): bool {
		if ( ! (bool) get_option( SettingsPage::OPT_EXCLUDE_BOTS, true ) ) {
			return false;
		}

		$ua = isset( $_SERVER['HTTP_USER_AGENT'] )
			? strtolower( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) )
			: '';

		if ( '' === $ua ) {
			return false;
		}

		foreach ( self::PATTERNS as $pattern ) {
			if ( str_contains( $ua, $pattern ) ) {
				return true;
			}
		}

		return false;
	}
}
