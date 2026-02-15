# Markdown for Agents

WordPress plugin implementing the [Markdown for Agents](https://blog.cloudflare.com/markdown-for-agents/) specification (Cloudflare, Feb 2026). Serves AI agents with Markdown instead of HTML, reducing token usage ~80%.

## Features

- **Content negotiation** — responds with Markdown when `Accept: text/markdown` header is present
- **Static endpoint** — `?format=md` URL parameter for any post/page
- **Discovery tag** — `<link rel="alternate" type="text/markdown">` in `<head>`
- **YAML frontmatter** — title, author, date, categories, tags, URL
- **WooCommerce support** — products get `add_to_cart_url`, `price`, `currency`, `sku`, `in_stock` in frontmatter
- **HTTP headers** — `Content-Type`, `Vary`, `X-Markdown-Tokens`, `Content-Signal` per spec
- **Token estimation** — approximate token count in response header
- **Request logging** — tracks all markdown requests with bot identification, filtering, sorting, pagination
- **Bot identification** — recognizes 24+ AI bots (GPTBot, ClaudeBot, Googlebot, PerplexityBot, etc.)
- **Statistics dashboard** — HTML vs Markdown comparison, bot distribution chart, top posts, token stats
- **Admin settings** — tabbed interface: settings, request logs, statistics
- **Auto-update** — automatic updates from Forgejo repository (no ZIP uploading needed)
- **Clean uninstall** — removes all data (table, options, cache) when plugin is deleted

## Requirements

- WordPress 6.0+
- PHP 8.0+

## Installation

1. Download `markdown-for-agents.zip`
2. WordPress admin → Plugins → Add New → Upload Plugin
3. Activate

Or manually: copy `markdown-for-agents/` to `wp-content/plugins/` and run `composer install --no-dev` inside it.

## Usage

Once activated, any post or page is available as Markdown:

```bash
# Via URL parameter
curl 'https://example.com/my-post/?format=md'

# Via Accept header
curl -H 'Accept: text/markdown' 'https://example.com/my-post/'
```

Configure which post types are enabled in **Settings → Markdown for Agents**.

### WooCommerce

Enable the `product` post type in settings. Product frontmatter includes add-to-cart URL, price, currency, SKU and stock status. Taxonomy terms (`product_cat`, `product_tag`) are automatically mapped to `categories` and `tags`.

## Settings

| Option | Default | Description |
|--------|---------|-------------|
| Enable plugin | On | Master switch |
| Post types | Posts, Pages | Which post types serve Markdown |
| Cache TTL | 3600s | How long converted Markdown is cached |

## How it works

1. AI agent requests a page with `Accept: text/markdown` header (or `?format=md`)
2. Plugin intercepts at `template_redirect` (priority 5)
3. Post content is rendered through WordPress (`apply_filters('the_content')`)
4. HTML is converted to Markdown via [league/html-to-markdown](https://github.com/thephpleague/html-to-markdown)
5. YAML frontmatter with post metadata is prepended
6. Response is served with proper headers and cached via WordPress Transients

## Releasing a new version

1. Update `MDFA_VERSION` in `markdown-for-agents/markdown-for-agents.php`
2. Update `Version:` in the plugin header to match
3. Commit and push to master
4. Create a Release in Forgejo with tag `v{version}` (e.g. `v1.1.0`)
5. WordPress installations will detect the update within 12 hours (or on next plugin check)

## Planned features

- Taxonomy archive support — serve category, tag and custom taxonomy pages as Markdown (post list with frontmatter)
- Pretty URLs via rewrite rules (`/slug/index.md`)
- Page builder compatibility

## Specification

Implements the [Cloudflare Markdown for Agents](https://developers.cloudflare.com/fundamentals/reference/markdown-for-agents/) spec with [Content Signals](https://contentsignals.org/) headers.

## License

GPL v2 or later
