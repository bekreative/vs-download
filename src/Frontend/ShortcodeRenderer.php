<?php
/**
 * HTML templates for download shortcodes.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download\Frontend;

/**
 * Renders box, button, and link shortcode markup.
 */
final class ShortcodeRenderer {

	/**
	 * @param array{title:string,url:string,version:string,count:int} $d Download data.
	 */
	public static function render( string $template, array $d ): string {
		return match ( $template ) {
			'button' => self::button( $d ),
			'link'   => self::link( $d ),
			default  => self::box( $d ),
		};
	}

	public static function inline_css(): string {
		return '<style>
			.lwd-box{background:#f8f9fa;border:1px solid #dee2e6;border-radius:8px;padding:16px 20px;margin:12px 0;display:flex;align-items:center;gap:14px;transition:box-shadow .2s,border-color .2s}
			.lwd-box:hover{border-color:#0073aa;box-shadow:0 2px 8px rgba(0,115,170,.12)}
			.lwd-box .lwd-icon{font-size:28px;flex-shrink:0}
			.lwd-box .lwd-info{flex:1;min-width:0}
			.lwd-box .lwd-title{font-weight:600;font-size:15px;color:#1d2327;text-decoration:none;display:block}
			.lwd-box .lwd-title:hover{color:#0073aa}
			.lwd-box .lwd-meta{font-size:12px;color:#646970;margin-top:3px}
			.lwd-btn{display:inline-flex;align-items:center;gap:6px;background:#0073aa;color:#fff!important;padding:10px 22px;border-radius:6px;font-weight:600;font-size:14px;text-decoration:none!important;transition:background .2s}
			.lwd-btn:hover{background:#005a87}
			.lwd-btn svg{width:16px;height:16px;fill:currentColor}
			.lwd-link{display:inline-flex;align-items:center;gap:4px;color:#0073aa;font-weight:500;text-decoration:none}
			.lwd-link:hover{text-decoration:underline}
		</style>';
	}

	/**
	 * @param array{title:string,url:string,version:string,count:int} $d Download data.
	 */
	private static function box( array $d ): string {
		$meta  = '';
		$parts = [];
		if ( $d['version'] ) {
			$parts[] = 'v' . esc_html( $d['version'] );
		}
		if ( $d['count'] > 0 ) {
			$parts[] = esc_html( (string) $d['count'] ) . ' ' . esc_html__( 'letöltés', 'vs-download' );
		}
		if ( $parts ) {
			$meta = '<div class="lwd-meta">' . implode( ' &middot; ', $parts ) . '</div>';
		}

		return '<div class="lwd-box">'
			. '<span class="lwd-icon">📥</span>'
			. '<div class="lwd-info">'
			. '<a href="' . esc_url( $d['url'] ) . '" class="lwd-title">' . esc_html( $d['title'] ) . '</a>'
			. $meta
			. '</div>'
			. '</div>';
	}

	/**
	 * @param array{title:string,url:string,version:string,count:int} $d Download data.
	 */
	private static function button( array $d ): string {
		$label = esc_html( $d['title'] );
		if ( $d['version'] ) {
			$label .= ' <small>(v' . esc_html( $d['version'] ) . ')</small>';
		}

		return '<a href="' . esc_url( $d['url'] ) . '" class="lwd-btn">'
			. self::icon_svg()
			. $label
			. '</a> ';
	}

	/**
	 * @param array{title:string,url:string,version:string,count:int} $d Download data.
	 */
	private static function link( array $d ): string {
		$suffix = '';
		if ( $d['version'] ) {
			$suffix .= ' (v' . esc_html( $d['version'] ) . ')';
		}
		if ( $d['count'] > 0 ) {
			$suffix .= ' — ' . esc_html( (string) $d['count'] ) . ' ' . esc_html__( 'letöltés', 'vs-download' );
		}

		return '<a href="' . esc_url( $d['url'] ) . '" class="lwd-link">'
			. self::icon_svg()
			. esc_html( $d['title'] ) . $suffix
			. '</a><br>';
	}

	private static function icon_svg(): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>';
	}
}
