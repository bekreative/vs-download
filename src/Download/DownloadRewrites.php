<?php
/**
 * Pretty permalinks for download URLs.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Download;

/**
 * Registers /download/{id}/ rewrite rules (e.g. https://example.com/download/11066/).
 */
final class DownloadRewrites {

	public const QUERY_ID       = 'lwd_download_id';
	public const QUERY_VERSION  = 'lwd_download_version_slug';
	public const DEFAULT_SLUG   = 'download';
	public const OPTION_FLUSHED = 'lwd_rewrite_rules_version';
	public const RULES_VERSION  = '2';

	public static function register(): void {
		add_filter( 'query_vars', [ self::class, 'add_query_vars' ] );
		add_action( 'init', [ self::class, 'add_rules' ], 20 );
		add_action( 'init', [ self::class, 'maybe_flush' ], 99 );
	}

	/**
	 * @param list<string> $vars Query vars.
	 * @return list<string>
	 */
	public static function add_query_vars( array $vars ): array {
		$vars[] = self::QUERY_ID;
		$vars[] = self::QUERY_VERSION;
		return $vars;
	}

	public static function add_rules(): void {
		if ( ! self::rewrite_ready() ) {
			return;
		}

		$slug = self::slug_pattern();

		add_rewrite_rule(
			'^' . $slug . '/([0-9]+)/?$',
			'index.php?' . self::QUERY_ID . '=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^' . $slug . '/([0-9]+)/([^/]+)/?$',
			'index.php?' . self::QUERY_ID . '=$matches[1]&' . self::QUERY_VERSION . '=$matches[2]',
			'top'
		);
	}

	public static function slug(): string {
		$slug = apply_filters( 'lwd_download_rewrite_slug', self::DEFAULT_SLUG );
		$slug = sanitize_title( (string) $slug );
		return '' !== $slug ? $slug : self::DEFAULT_SLUG;
	}

	public static function slug_pattern(): string {
		return preg_quote( self::slug(), '/' );
	}

	/**
	 * Build pretty URL: /download/11066/ or /download/11066/1.0/ for a version.
	 */
	public static function pretty_url( int $download_id, string $version = '' ): string {
		$path = self::slug() . '/' . $download_id;

		if ( '' !== $version ) {
			$path .= '/' . rawurlencode( $version );
		}

		return user_trailingslashit( home_url( '/' . $path ) );
	}

	/**
	 * Mark rewrite rules for flush on next init (safe during activation).
	 */
	public static function schedule_flush(): void {
		delete_option( self::OPTION_FLUSHED );
	}

	/**
	 * Flush once after upgrade/activation — runs on init only.
	 */
	public static function maybe_flush(): void {
		if ( ! self::can_flush_rewrites() ) {
			return;
		}

		$flushed = (string) get_option( self::OPTION_FLUSHED, '' );
		if ( self::RULES_VERSION === $flushed ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::OPTION_FLUSHED, self::RULES_VERSION, false );
	}

	/**
	 * Whether rewrite rules can be registered or flushed safely.
	 */
	public static function rewrite_ready(): bool {
		global $wp_rewrite;

		return isset( $wp_rewrite ) && $wp_rewrite instanceof \WP_Rewrite;
	}

	/**
	 * Skip rewrite flush during cron/CLI where WP_Rewrite may be unavailable.
	 */
	private static function can_flush_rewrites(): bool {
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return false;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		return self::rewrite_ready();
	}
}
