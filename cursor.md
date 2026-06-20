# LW Download — Cursor / Agent Rules

> Rules for editing `vs-download`. Follow PSR-4, strict types, and ~200 lines per class where practical.

## Identity

| Key | Value |
|-----|-------|
| Slug | `vs-download` |
| Namespace | `Vs\Download` |
| CPT | `lwd_download` |
| Log table | `{prefix}lwd_download_logs` |
| DB version option | `lw_download_db_version` |

## Structure

```
vs-download/
├── languages/ — vs-download-{en_US,hu_HU,es_ES}.mo (+ .po, .pot)
├── vs-download.php
├── src/
│   ├── Plugin.php
│   ├── CLI/CLI.php
│   ├── I18n/TextDomain.php
│   ├── Health/ — HealthCheckRunner, Checks/*, HealthReportStore
│   ├── Admin/ — ToolsPage, MigrationPage (tab), ReportsPage, SettingsPage, Metaboxes/
│   ├── Api/ — DownloadsController, DownloadSchema, AbilitiesController
│   ├── Database/ — Activator, LogRepository, LogPruner, DownloadCountSyncer
│   ├── Download/ — DownloadHandler, AccessValidator, DownloadUrl, BotDetector
│   ├── Frontend/ — Shortcode, ShortcodeRenderer
│   ├── Migration/ — DownloadMonitorImporter (facade), DlmDownloadMigrator, DlmModernDownloadImporter, DlmLegacyDownloadImporter, DlmVersionImporter, DlmLogMigrator, DlmModernLogImporter, DlmLegacyLogImporter, DlmMigrationMap, DlmDetector, DlmFileUrlResolver, DlmTermAssigner, DlmLegacyTaxonomyImporter
│   ├── Meta/, PostTypes/, Taxonomies/
└── vendor/
```

## Bootstrap (`Plugin.php`)

Always instantiate: `AccessValidator`, `LogPruner`, `DownloadHandler`, admin metaboxes/pages, REST controllers, `Shortcode`.

`I18n\TextDomain::boot()` in `Plugin` constructor; `load_plugin_textdomain` also on `plugins_loaded` priority 0. Admin locale = `get_user_locale()`.

`Activator::maybe_upgrade()` runs from `Plugin::init_hooks()` before `init`.

Activation sets `lwd_pending_health_check`; admin notice + auto-run on **Tools → Environment**.

## Download flow

1. Public URL: `/download/{id}/` via `DownloadRewrites` (legacy query URLs still work) → `DownloadHandler`
2. `lwd_pre_grant_download` → `AccessValidator`
3. `DownloadUrl::resolve_file_url()` (internal only) → `LogRepository::insert_log()` → serve file
4. DLM migration converts `dlm_download` → `lwd_download` **in place** (same post ID) so old short URLs keep working

## Migration (DLM)

- Facade: `DownloadMonitorImporter`
- Downloads: `DlmDownloadMigrator` → modern/legacy importers + `DlmVersionImporter` + `DlmMigrationMap`
- Logs: `DlmLogMigrator` → modern/legacy batch importers via `LogRepository::insert_historical_log()` (sync at end)
- Admin: Downloads → Tools → Migration | CLI: `wp vs-download import-dlm`, `wp vs-download health`

## Rules

1. Logs only in `wp_lwd_download_logs` — `_lwd_download_count` is a cached aggregate only.
2. Live downloads: `LogRepository::insert_log()`. Historical/migration: `insert_historical_log()`.
3. `AccessMetabox` + `AccessValidator` must stay wired in `Plugin.php`.
4. REST: update `DownloadSchema` when changing API fields.
5. Keep new classes focused; split before exceeding ~200 lines.
6. New user-facing strings: update `languages/*.po` and run `msgfmt`; ship `.mo` in release ZIPs.

## Planned

- Gutenberg block, IP anonymisation, `wp vs-download stats` / `check-files`, more Abilities (CLI `import-dlm` / `dlm-status` done)
