# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WordPress plugin implementing the **Markdown for Agents** specification (Cloudflare, Feb 2026). Serves AI agents (GPTBot, ClaudeBot, Gemini etc.) with Markdown instead of HTML, reducing token usage ~80%.

- **Working version:** 1.0.6-rc2 (in development)
- **Language:** PHP 8.0+
- **Platform:** WordPress 6.0+ (requires Gutenberg block content)
- **Spec references:** [Cloudflare blog](https://blog.cloudflare.com/markdown-for-agents/), [Cloudflare docs](https://developers.cloudflare.com/fundamentals/reference/markdown-for-agents/), [Content Signals](https://contentsignals.org/)
- **Planning document:** `poc.md` — full architecture spec in Polish

## File Structure

```
wp-markdown-for-agents/                  # repo root
├── CLAUDE.md                            # this file
├── poc.md                               # architecture spec (Polish)
├── docker-compose.yml                   # dev environment
└── markdown-for-agents/                 # plugin directory (ZIP this for WP install)
    ├── markdown-for-agents.php          # main plugin file
    ├── uninstall.php                    # cleanup on plugin deletion
    ├── composer.json
    ├── vendor/                          # composer install --no-dev
    └── includes/
        ├── class-converter.php          # HTML→Markdown + YAML frontmatter
        ├── class-content-negotiation.php # Accept header + ?format=md routing
        ├── class-discovery.php          # <link rel="alternate"> tag
        ├── class-token-estimator.php    # token count estimation
        ├── class-request-log.php        # request logging (custom DB table) + bot identification
        ├── class-request-log-table.php  # WP_List_Table for logs (filtering, sorting, pagination)
        ├── class-stats-tracker.php      # HTML request counter + token estimation
        ├── class-updater.php           # auto-update from Forgejo repository
        ├── class-rewrite.php           # /slug/index.md rewrite rules
        ├── class-admin.php              # wp-admin dispatcher (tabs, menu, Screen Options)
        ├── class-admin-tab-settings.php # settings tab (register_settings, field renderers)
        ├── class-admin-tab-logs.php     # logs tab (render + clear handler)
        └── class-admin-tab-stats.php    # stats tab (render + reset handler)
```

## Dependencies

Composer with `league/html-to-markdown ^5.1`. Run `composer install --no-dev` inside `markdown-for-agents/`.

## Architecture

Four-layer content discovery system:

1. **Vary header** (`send_headers`) — `Vary: Accept` on HTML responses for enabled post types (enables HEAD-based discovery)
2. **Discovery tag** (`wp_head`) — `<link rel="alternate" type="text/markdown">` on singular views and taxonomy archives
3. **Static endpoint** (`template_redirect`) — `?format=md` URL parameter (posts and taxonomy archives)
4. **Content negotiation** (`template_redirect`, priority 5) — transparent `Accept: text/markdown` handling (posts and taxonomy archives). Uses `$_GET['format']` fallback for front page where `get_query_var()` doesn't work

**Conversion pipeline (single posts):** `post_content` → `apply_filters('the_content')` (render Gutenberg blocks) → `league/html-to-markdown` → prepend YAML frontmatter → serve with proper headers.

**Taxonomy archives:** `WP_Term` → YAML frontmatter (type, taxonomy, name, description, url, post_count, page, total_pages) → post list (title + link + date/price + excerpt) → subcategories section (hierarchical only) → pagination links. WooCommerce product taxonomies include price/SKU per item. Cache key: `mdfa_archive_{taxonomy}_{term_id}_{page}_{modified_hash}`.

**WooCommerce support:** Converter uses universal taxonomy retrieval (`get_object_taxonomies()`) for any post type. For `product` post type, frontmatter includes `add_to_cart_url`, `price`, `currency`, `sku`, `in_stock` via WooCommerce API (guarded by `function_exists('wc_get_product')`).

**HTTP headers:** `Content-Type: text/markdown; charset=utf-8`, `Vary: Accept`, `X-Markdown-Tokens: <count>`, `Content-Signal: ai-train=yes, search=yes, ai-input=yes`, `X-Robots-Tag: noindex`, `Link: <url>; rel="canonical"` (RFC 5988, configurable).

**Caching:** WordPress Transients API, key `mdfa_md_{post_id}_{modified_hash}`, invalidated on `save_post` via post meta `_mdfa_cache_key`.

**Auto-update:** `MDFA_Updater` checks GitHub releases API every 1h via WordPress Transients. Uses `site_transient_update_plugins` + `plugins_api` filters. `upgrader_source_selection` fixes folder structure from archive ZIP. Supports opt-in beta/pre-release updates (`mdfa_beta_updates` option) — when enabled, queries `/releases` (all) instead of `/releases/latest` (stable only), with separate cache key.

**Request logging:** Custom table `wp_mdfa_request_log` — logs every markdown request with post_id, term_id, taxonomy, request_method (accept_header/format_param), user_agent, bot_name, bot_type, ip_address, tokens, timestamp. Bot identification happens at insert time (not at query time). DB schema versioned via `mdfa_db_version` option (current: 3) with automatic migration on `plugins_loaded`.

## Classes

| Class | File | Responsibility |
|-------|------|---------------|
| MDFA_Converter | `includes/class-converter.php` | HTML→Markdown + frontmatter + cache + WooCommerce product data + taxonomy archives |
| MDFA_Content_Negotiation | `includes/class-content-negotiation.php` | Accept header + ?format=md routing (posts + archives) |
| MDFA_Discovery | `includes/class-discovery.php` | `<link rel="alternate">` tag (posts + archives) |
| MDFA_Token_Estimator | `includes/class-token-estimator.php` | Token count: `ceil(mb_strlen / 4)` |
| MDFA_Request_Log | `includes/class-request-log.php` | Request logging + bot identification (at insert) + stats queries (SQL-based) |
| MDFA_Request_Log_Table | `includes/class-request-log-table.php` | WP_List_Table with filtering, sorting, pagination |
| MDFA_Stats_Tracker | `includes/class-stats-tracker.php` | HTML request counter + token estimation (via post meta, not per-request rendering) |
| MDFA_Updater | `includes/class-updater.php` | Auto-update from Forgejo releases API |
| MDFA_Rewrite | `includes/class-rewrite.php` | `/slug/index.md` rewrite rules (auto-generated from WP rules) |
| MDFA_Admin | `includes/class-admin.php` | Tabbed admin (settings, logs, stats) |

## Performance Note

Use WordPress API (`get_queried_object()`, `apply_filters('the_content')`), never direct DB queries. The post object is already in memory at `template_redirect`. The Transients cache ensures HTML→Markdown conversion runs only once per post edit, not per request.

## Development

```bash
# Start dev environment
docker compose up -d

# First time: install WP + activate plugin
docker compose exec wordpress bash -c "curl -sO https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x wp-cli.phar && mv wp-cli.phar /usr/local/bin/wp"
docker compose exec wordpress wp core install --url=http://localhost:8080 --title="Test" --admin_user=admin --admin_password=admin --admin_email=a@b.com --skip-email --allow-root
docker compose exec wordpress wp plugin activate markdown-for-agents --allow-root

# Stop
docker compose down          # keep data
docker compose down -v       # wipe data
```

## Testing

```bash
# Discovery tag
curl -s 'http://localhost:8080/?p=1' | grep 'rel="alternate".*text/markdown'

# Static endpoint
curl -s 'http://localhost:8080/?p=1&format=md'

# Content negotiation
curl -s -H 'Accept: text/markdown' 'http://localhost:8080/?p=1'

# HTTP headers
curl -sI -H 'Accept: text/markdown' 'http://localhost:8080/?p=1'

# Check request log
docker compose exec db mysql -uroot -proot wordpress -e "SELECT * FROM wp_mdfa_request_log"

# Taxonomy archive — discovery tag
curl -s 'http://localhost:8080/category/uncategorized/' | grep 'rel="alternate".*text/markdown'

# Taxonomy archive — static endpoint
curl -s 'http://localhost:8080/category/uncategorized/?format=md'

# Taxonomy archive — content negotiation
curl -s -H 'Accept: text/markdown' 'http://localhost:8080/category/uncategorized/'
```

## Changelog

After every feature/fix, update `CHANGELOG.md` under the current working version. Keep entries concise (one line per change). When releasing a new version, also bump `MDFA_VERSION` in `markdown-for-agents.php` and update `Working version` above.

## Release Process

1. Upewnij się, że `MDFA_VERSION` w `markdown-for-agents.php` i `CHANGELOG.md` są aktualne
2. Commit + push wszystkich zmian
3. Utwórz tag: `git tag vX.Y.Z && git push origin vX.Y.Z`
4. Utwórz release na Forgejo via API:
   ```bash
   curl -s -X POST 'https://repo.nimblio.work/api/v1/repos/roman/wordpress-markdown-for-agents/releases' \
     -H 'Content-Type: application/json' \
     -H "Authorization: token $FORGEJO_TOKEN" \
     -d '{"tag_name":"vX.Y.Z","name":"vX.Y.Z","body":"opis zmian","draft":false,"prerelease":false}'
   ```
5. **Zbuduj ZIP tylko z folderu `markdown-for-agents/`** (archiwum repo zawiera pliki dev — CLAUDE.md, poc.md, docker-compose.yml):
   ```bash
   zip -r /tmp/markdown-for-agents-X.Y.Z.zip markdown-for-agents/ -x "*/.DS_Store"
   ```
6. Uploaduj ZIP jako asset do release (updater preferuje attached asset nad archiwum repo):
   ```bash
   curl -s -X POST "https://repo.nimblio.work/api/v1/repos/roman/wordpress-markdown-for-agents/releases/{RELEASE_ID}/assets" \
     -H "Authorization: token $FORGEJO_TOKEN" \
     -F "attachment=@/tmp/markdown-for-agents-X.Y.Z.zip"
   ```
7. Token Forgejo jest w `.env` jako `FORGEJO-TOKEN`


## Sprint Status

- **Sprint 1 (MVP)** — DONE: converter, content negotiation, discovery tag, HTTP headers, token estimator, request logging, Docker dev env
- **Sprint 2** — DONE: wp-admin settings page (post types, cache TTL, enable/disable), log viewer, uninstall cleanup, prefix rename MFA→MDFA with auto-migration, i18n
- **Sprint 2.5** — DONE: tabbed admin (settings/logs/stats), WP_List_Table with bot identification + filtering + pagination, HTML vs Markdown stats comparison, bot distribution chart, top posts, token stats, X-Robots-Tag: noindex
- **Sprint 2.6** — DONE: WooCommerce compatibility (universal taxonomy retrieval, product frontmatter with add_to_cart_url/price/currency/sku/in_stock)
- **Sprint 2.7** — DONE: Auto-update from Forgejo repository (releases API, folder fix for archive ZIP)
- **Sprint 3** — DONE: Taxonomy archive support (categories, tags, WooCommerce product_cat/product_tag, custom taxonomies). Archive frontmatter + post list + subcategories + pagination. DB migration for term_id/taxonomy in request log. Settings UI for enabled taxonomies.
- **Sprint 3.1** — DONE: HTTP `Link: rel="canonical"` header (RFC 5988) pointing to original HTML page, configurable in settings (default: on)
- **Sprint 3.2** — DONE: Pre-release opt-in (beta updates checkbox, separate cache, visual "(beta)" label, update check TTL 12h→1h)
- **Sprint 4** — DONE (partial): rewrite rules (`/slug/index.md`) via `rewrite_rules_array` filter. TODO: page builder compatibility

