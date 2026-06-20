# LW Download

Lightweight WordPress download manager (PHP 8.1+, no bloat).

## Install

1. Copy to `wp-content/plugins/vs-download`.
2. Run `composer install` in the plugin directory (or use a release ZIP with `vendor/` included).
3. Activate **LW Download** in WordPress admin.
4. Open **Downloads → Tools → Environment** — checks run automatically on first visit (or run manually anytime).

## Languages

Bundled translations (admin UI follows the user's **admin language**):

- English (`en_US`)
- Hungarian (`hu_HU`)
- Spanish (`es_ES`)

Set your profile language under **Users → Profile** in WordPress.

## Tools

**Downloads → Tools** includes:

- **Environment** — host compatibility checks (PHP, DB, cron, HTTP, DLM conflicts) with recommendations
- **Migration** — import from Download Monitor (see below)

### WP-CLI

```bash
wp vs-download health
wp vs-download dlm-status
wp vs-download import-dlm
```

## Import from Download Monitor

**Downloads → Tools → Migration** migrates:

- Download posts (`dlm_download`) → `lwd_download`
- File versions (`dlm_download_version` + `_files` URLs)
- Categories & tags (`dlm_download_category` / `dlm_download_tag`)
- Members-only → logged-in access
- Log table (`wp_download_log`) → `wp_lwd_download_logs` + download counts
- Legacy tables (`download_monitor_files`, `download_monitor_log`) when no modern CPT exists

Run **downloads first**, then **logs** (batched). Re-running with “skip existing” avoids duplicate posts.

```bash
wp vs-download import-dlm --downloads-only
wp vs-download import-dlm --logs-only
wp vs-download import-dlm --legacy-logs --yes
```

## Features

- CPT `lwd_download` with categories and tags
- Multiple file versions per download (`?lwd_version=` / shortcode `version=""`)
- Access: public, logged-in, or role-based (admin metabox)
- Custom log table + Reports + Settings (retention, bot exclusion)
- Daily log pruning cron
- REST: `GET /wp-json/lwd/v1/downloads`
- Abilities API: health-check, get-stats (admin)
- Shortcodes: `[lw_download]`, `[lw_downloads]`

## Shortcodes

```
[lw_download id="123" template="box|button|link" version="1.0"]
[lw_downloads category="docs" limit="10" orderby="downloads"]
```

Items hidden when the current user lacks access.

## Download URL (public pretty link)

Default format (tracked, not the direct file path):

```
https://example.com/download/11066/
https://example.com/download/11066/1-0/   # optional version segment
```

**Legacy URLs still work** after migration:

```
/?post_type=lwd_download&p=18111
/?post_type=dlm_download&p=18111
?download-id=18111
```

Custom slug: `add_filter( 'lwd_download_rewrite_slug', fn () => 'letoltes' );` then flush permalinks.

The internal file URL stays in post meta only (admin metabox).

## REST

```
GET /wp-json/lwd/v1/downloads?accessible_only=1
```

## Hooks

| Hook | Type |
|------|------|
| `lwd_pre_grant_download` | filter `(bool, int $download_id, int $user_id)` |
| `lwd_before_serve_download` | action after log, before file serve |
| `lwd_after_download_logged` | action after log insert |

## Development

```bash
composer install
composer run i18n:compile   # rebuild languages/*.mo from *.po
```

Do not ship `composer.phar` or `composer-setup.php` in release zips. **Do** ship `languages/*.mo` (and `vendor/`) in release zips.
