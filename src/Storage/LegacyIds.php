<?php
/**
 * Stable storage identifiers (unchanged from lw-download for live data).
 *
 * @package Vs\Download\Storage
 */

declare(strict_types=1);

namespace Vs\Download\Storage;

/**
 * WordPress storage keys that must not change across lw → vs upgrade.
 */
final class LegacyIds {

	public const POST_TYPE = 'lwd_download';

	public const TAXONOMY_CATEGORY = 'lwd_category';

	public const TAXONOMY_TAG = 'lwd_tag';

	public const TABLE_LOGS = 'lwd_download_logs';

	public const OPTION_DB_VERSION = 'lw_download_db_version';

	public const OPTION_REWRITE_FLUSHED = 'lwd_rewrite_rules_version';

	public const QUERY_DOWNLOAD_ID = 'lwd_download_id';

	public const QUERY_VERSION_SLUG = 'lwd_download_version_slug';

	public const META_PREFIX = '_lwd_';

	public const FILTER_REWRITE_SLUG = 'lwd_download_rewrite_slug';
}
