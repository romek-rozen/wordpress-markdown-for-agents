# Markdown for Agents — WordPress Plugin

## Code Conventions

- PHP 8.0+ features OK (union types, named args, arrow functions)
- Class prefix: `MDFA_` (e.g. `MDFA_Converter`, `MDFA_Request_Log`)
- WordPress Coding Standards: tabs, spaces around parentheses in control structures
- Never direct DB queries — use WordPress API (`get_post()`, `get_transient()`, `get_option()`, etc.)
- Exception: `MDFA_Request_Log` uses `$wpdb->insert()` for custom table — this is the WordPress way for custom tables
- WooCommerce integration guarded by `function_exists()` checks — plugin works with or without WooCommerce

## WordPress Hooks

| Hook | Priority | Class | Purpose |
|------|----------|-------|---------|
| `plugins_loaded` | 10 | main file | Initialize Admin + Discovery + Content_Negotiation |
| `admin_menu` | 10 | MDFA_Admin | Register settings page under Settings menu |
| `admin_init` | 10 | MDFA_Admin | Register settings + handle clear logs |
| `wp_head` | 10 | MDFA_Discovery | Output `<link rel="alternate">` |
| `send_headers` | 10 | MDFA_Content_Negotiation | Add `Vary: Accept` to HTML responses |
| `template_redirect` | 5 | MDFA_Content_Negotiation | Intercept markdown requests |
| `template_redirect` | 99 | MDFA_Stats_Tracker | Count HTML requests + estimate tokens |
| `save_post` | 10 | MDFA_Converter | Invalidate transient cache |
| `query_vars` | 10 | main file | Register `format` query var |

## Options (wp_options)

| Option | Default | Purpose |
|--------|---------|---------|
| `mdfa_enabled` | `true` | Master switch |
| `mdfa_post_types` | `['post', 'page']` | Enabled post types |
| `mdfa_cache_ttl` | `3600` | Cache TTL in seconds |
| `mdfa_taxonomies` | `['category', 'post_tag']` | Enabled taxonomies for archive Markdown |
| `mdfa_db_version` | `2` | DB schema version for migrations |
| `mdfa_stats` | `[]` | HTML request counters (html_requests, html_tokens_estimated, html_archive_requests, started_at) |

## Custom DB Table

`wp_mdfa_request_log` — created on plugin activation via `dbDelta()`. Columns: id, post_id, term_id, taxonomy, request_method, user_agent, ip_address, tokens, created_at. Schema versioned via `mdfa_db_version` with auto-migration on `plugins_loaded`.

## Uninstall

`uninstall.php` cleans up: deletes options (incl. `mdfa_taxonomies`, `mdfa_db_version`, `mdfa_stats`), drops `wp_mdfa_request_log` table, removes `_mdfa_cache_key` post meta, removes `mdfa_md_*`, `mdfa_html_tokens_*` and `mdfa_archive_*` transients.
