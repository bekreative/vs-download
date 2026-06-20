<?php
/**
 * Main Plugin class.
 *
 * @package Vs\Download
 */

declare(strict_types=1);

namespace Vs\Download;

use Vs\Download\Admin\Metaboxes\AccessMetabox;
use Vs\Download\Admin\Metaboxes\FileMetabox;
use Vs\Download\Admin\ReportsPage;
use Vs\Download\Admin\SettingsPage;
use Vs\Download\Admin\ToolsPage;
use Vs\Download\I18n\TextDomain;
use Vs\Download\Api\AbilitiesController;
use Vs\Download\Api\DownloadsController;
use Vs\Download\Database\Activator;
use Vs\Download\Database\LogPruner;
use Vs\Download\Download\AccessValidator;
use Vs\Download\Download\DownloadHandler;
use Vs\Download\Download\DownloadRewrites;
use Vs\Download\Frontend\Shortcode;
use Vs\Download\Meta\DownloadMeta;
use Vs\Download\PostTypes\Download;
use Vs\Download\Taxonomies\DownloadCategory;
use Vs\Download\Taxonomies\DownloadTag;

/**
 * Main plugin class.
 */
final class Plugin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		TextDomain::boot();
		$this->init_hooks();
		$this->init_components();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		DownloadRewrites::register();
		Activator::maybe_upgrade();
		add_action( 'init', [ $this, 'register_post_types' ] );
		add_action( 'init', [ $this, 'register_meta' ] );
	}

	/**
	 * Initialize plugin components.
	 *
	 * @return void
	 */
	private function init_components(): void {
		new AccessValidator();
		new LogPruner();

		if ( is_admin() ) {
			new FileMetabox();
			new AccessMetabox();
			new ReportsPage();
			new SettingsPage();
			new ToolsPage();
		}

		new DownloadHandler();

		$downloads_controller = new DownloadsController();
		$downloads_controller->init();

		$abilities_controller = new AbilitiesController();
		add_action( 'rest_api_init', [ $abilities_controller, 'register_routes' ] );

		new Shortcode();
	}

	/**
	 * Register custom post types.
	 *
	 * @return void
	 */
	public function register_post_types(): void {
		Download::register();
		DownloadCategory::register();
		DownloadTag::register();
	}

	/**
	 * Register meta fields.
	 *
	 * @return void
	 */
	public function register_meta(): void {
		DownloadMeta::register();
	}
}
