# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WordPress plugin implementing the **Markdown for Agents** specification (Cloudflare, Feb 2026). Serves AI agents (GPTBot, ClaudeBot, Gemini etc.) with Markdown instead of HTML, reducing token usage ~80%.

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
        └── class-admin.php              # wp-admin settings (tabs: settings, logs, stats)
```

## Dependencies

Composer with `league/html-to-markdown ^5.1`. Run `composer install --no-dev` inside `markdown-for-agents/`.

## Architecture

Four-layer content discovery system:

1. **Vary header** (`send_headers`) — `Vary: Accept` on HTML responses for enabled post types (enables HEAD-based discovery)
2. **Discovery tag** (`wp_head`) — `<link rel="alternate" type="text/markdown">` on singular views
3. **Static endpoint** (`template_redirect`) — `?format=md` URL parameter
4. **Content negotiation** (`template_redirect`, priority 5) — transparent `Accept: text/markdown` handling

**Conversion pipeline:** `post_content` → `apply_filters('the_content')` (render Gutenberg blocks) → `league/html-to-markdown` → prepend YAML frontmatter → serve with proper headers.

**WooCommerce support:** Converter uses universal taxonomy retrieval (`get_object_taxonomies()`) for any post type. For `product` post type, frontmatter includes `add_to_cart_url`, `price`, `currency`, `sku`, `in_stock` via WooCommerce API (guarded by `function_exists('wc_get_product')`).

**HTTP headers:** `Content-Type: text/markdown; charset=utf-8`, `Vary: Accept`, `X-Markdown-Tokens: <count>`, `Content-Signal: ai-train=yes, search=yes, ai-input=yes`, `X-Robots-Tag: noindex`.

**Caching:** WordPress Transients API, key `mdfa_md_{post_id}_{modified_hash}`, invalidated on `save_post` via post meta `_mdfa_cache_key`.

**Request logging:** Custom table `wp_mdfa_request_log` — logs every markdown request with post_id, request_method (accept_header/format_param), user_agent, ip_address, tokens, timestamp.

## Classes

| Class | File | Responsibility |
|-------|------|---------------|
| MDFA_Converter | `includes/class-converter.php` | HTML→Markdown + frontmatter + cache + WooCommerce product data |
| MDFA_Content_Negotiation | `includes/class-content-negotiation.php` | Accept header + ?format=md routing |
| MDFA_Discovery | `includes/class-discovery.php` | `<link rel="alternate">` tag |
| MDFA_Token_Estimator | `includes/class-token-estimator.php` | Token count: `ceil(mb_strlen / 4)` |
| MDFA_Request_Log | `includes/class-request-log.php` | Request logging + bot identification + stats queries |
| MDFA_Request_Log_Table | `includes/class-request-log-table.php` | WP_List_Table with filtering, sorting, pagination |
| MDFA_Stats_Tracker | `includes/class-stats-tracker.php` | HTML request counter + token estimation |
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
```

## Sprint Status

- **Sprint 1 (MVP)** — DONE: converter, content negotiation, discovery tag, HTTP headers, token estimator, request logging, Docker dev env
- **Sprint 2** — DONE: wp-admin settings page (post types, cache TTL, enable/disable), log viewer, uninstall cleanup, prefix rename MFA→MDFA with auto-migration, i18n
- **Sprint 2.5** — DONE: tabbed admin (settings/logs/stats), WP_List_Table with bot identification + filtering + pagination, HTML vs Markdown stats comparison, bot distribution chart, top posts, token stats, X-Robots-Tag: noindex
- **Sprint 2.6** — DONE: WooCommerce compatibility (universal taxonomy retrieval, product frontmatter with add_to_cart_url/price/currency/sku/in_stock)
- **Sprint 3** — TODO: taxonomy archive support (categories, tags, custom taxonomies — see `taxonomy_plan.md`), rewrite rules (`/slug/index.md`), page builder compatibility
