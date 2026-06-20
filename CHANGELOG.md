# Changelog

All notable changes to **LW Download** are documented here.

Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
 versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] — 2026-06-20

### Added

- **Very Simple** rebrand from LW Download (`vs-download`, `Vs\Download\`, `verysimple/vs-core` hub/updater).
- `LegacyIds` storage contract — same CPT, meta, options, and log table as lw-download (no DB migration).

### Fixed

- Bootstrap loads `vendor/autoload.php` before `AutoloadGuard` so WP-CLI activation works.

### Added (continued from 1.0.x track)

- **Tools → Import / Export** — JSON backup and site-to-site migration for downloads (meta, taxonomies, access rules; optional download logs).
- **Reports dashboard** — summary cards, date-range and download filters, Chart.js charts, ranked lists (top downloads, referrers, countries, user agents).
- `ReportsStats` query layer and `admin-reports.css` for the admin reports UI.
- `DataService` and `ImportExportTab` for export download and AJAX import with URL search/replace.
- New translatable strings in `languages/vs-download.pot`, `hu_HU`, and `es_ES`.

### Fixed

- **wp-cron / WP-CLI:** `DownloadRewrites::can_flush_rewrites()` skips `flush_rewrite_rules()` when `DOING_CRON` or `WP_CLI` is defined, preventing `add_rule() on null` fatals during background jobs.
- Rewrite rules version bumped to `RULES_VERSION = '2'` so existing installs flush once on next web request.

## [1.0.0] — 2026-06-04

First public release.

### Added

- Custom post type `lwd_download` with categories, tags, and download meta (file URL, versions, access).
- Pretty permalinks: `/download/{id}/` and `/download/{id}/{version}/` via `DownloadRewrites`.
- Secure download handler with access validation (logged-in, roles, members) and bot detection.
- Download log table `{prefix}lwd_download_logs` with daily log pruning cron.
- **Downloads → Tools → Environment** — host compatibility checks (PHP, DB, cron, REST, DLM conflicts, memory).
- **Downloads → Tools → Migration** — import from Download Monitor (modern CPT + legacy tables, logs, taxonomies).
- **Downloads → Reports** and **Settings** admin pages; file and access metaboxes.
- REST API: `DownloadsController`, `DownloadSchema`, WordPress Abilities (`health-check`, `get-stats`).
- WP-CLI: `wp vs-download health`, `dlm-status`, `import-dlm`.
- Frontend shortcode for listing and linking downloads.
- i18n: English, Hungarian (`hu_HU`), Spanish (`es_ES`); POT and compile script.
- Composer PSR-4 autoload under `Vs\Download`.

[Unreleased]: https://github.com/bekreative/vs-download/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/bekreative/vs-download/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/bekreative/vs-download/releases/tag/v1.0.0
