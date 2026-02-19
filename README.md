# WordPress Markdown for Agents

![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-21759b?logo=wordpress)
![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-777bb4?logo=php&logoColor=white)
![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue)
![Latest Release](https://img.shields.io/github/v/release/romek-rozen/wordpress-markdown-for-agents)

**Serve AI agents with Markdown instead of HTML, reducing token usage ~80%.**
Implements the [Markdown for Agents](https://blog.cloudflare.com/markdown-for-agents/) specification by Cloudflare with [Content Signals](https://contentsignals.org/) headers.

---

## Example output

```yaml
---
title: "How to Train Your Dragon"
author: "Jane Smith"
date: "2026-01-15"
categories:
  - Tutorials
tags:
  - AI
  - WordPress
url: "https://example.com/how-to-train-your-dragon/"
---

This is the **full post content** converted from Gutenberg blocks
to clean Markdown. Lists, headings, images, code blocks — all preserved.

- Bullet points work
- [Links work](https://example.com)
- `inline code` works
```

```
HTTP/1.1 200 OK
Content-Type: text/markdown; charset=utf-8
Vary: Accept
X-Markdown-Tokens: 342
Content-Signal: ai-train=yes, search=yes, ai-input=yes
X-Robots-Tag: noindex
Link: <https://example.com/post/>; rel="canonical"
```

## Features

### Core

- **Content negotiation** — responds with Markdown when `Accept: text/markdown` header is present
- **Static endpoint** — `?format=md` URL parameter for any post/page
- **Plain text fallback** — `?format=txt` / `/slug/index.txt` serves identical Markdown with `Content-Type: text/plain` for clients that don't support `text/markdown`
- **Pretty URLs** — `/slug/index.md` and `/slug/index.txt` (with pretty permalinks enabled)
- **Discovery tags** — `<link rel="alternate" type="text/markdown">` and `<link rel="alternate" type="text/plain">` in `<head>`
- **YAML frontmatter** — title, author, date, categories, tags, URL
- **HTTP headers** — `Content-Type`, `Vary`, `X-Markdown-Tokens`, `Content-Signal` per spec
- **Token estimation** — approximate token count in response header
- **Caching** — Transients-based, invalidated on post save

### Admin

- **Request logging** — tracks all markdown requests with bot identification, filtering, sorting, pagination
- **Bot identification** — recognizes 24+ AI bots (GPTBot, ClaudeBot, Googlebot, PerplexityBot, etc.)
- **Statistics dashboard** — HTML vs Markdown comparison, bot distribution chart, top posts, token stats
- **Settings UI** — tabbed interface: settings, request logs, statistics
- **Auto-update** — automatic updates from releases (no ZIP uploading needed)
- **Clean uninstall** — removes all data (table, options, cache) when plugin is deleted

### Integrations

- **Taxonomy archives** — category, tag and custom taxonomy pages served as Markdown (post list with frontmatter, pagination, subcategories)
- **WooCommerce** — products get `add_to_cart_url`, `price`, `currency`, `sku`, `in_stock` in frontmatter; product category archives include price/SKU per item

## Quick start

**Install:**

1. Download `markdown-for-agents.zip` from the [latest release](https://github.com/romek-rozen/wordpress-markdown-for-agents/releases/latest)
2. WordPress admin → Plugins → Add New → Upload Plugin → Activate

**Test:**

```bash
# Via URL parameter
curl 'https://your-site.com/hello-world/?format=md'

# Via Accept header (how AI agents do it)
curl -H 'Accept: text/markdown' 'https://your-site.com/hello-world/'

# Pretty URL
curl 'https://your-site.com/hello-world/index.md'

# Plain text (same content, Content-Type: text/plain)
curl 'https://your-site.com/hello-world/index.txt'
```

Configure which post types and taxonomies are enabled in **Settings → Markdown for Agents**.

## How it works

1. AI agent requests a page with `Accept: text/markdown` header (or `?format=md`)
2. Plugin intercepts at `template_redirect` (priority 5)
3. Post content is rendered through WordPress (`apply_filters('the_content')`)
4. HTML is converted to Markdown via [league/html-to-markdown](https://github.com/thephpleague/html-to-markdown)
5. YAML frontmatter with post metadata is prepended
6. Response is served with proper headers and cached via WordPress Transients

## Configuration

| Option | Default | Description |
|--------|---------|-------------|
| Enable plugin | On | Master switch |
| Post types | Posts, Pages | Which post types serve Markdown |
| Taxonomies | Categories, Tags | Which taxonomy archives serve Markdown |
| Cache TTL | 3600s | How long converted Markdown is cached |
| Canonical Link header | On | Send `Link: rel="canonical"` pointing to HTML page |
| Beta updates | Off | Opt-in to receive pre-release (beta/RC) updates |

## WooCommerce

Enable the `product` post type in settings. Product frontmatter includes add-to-cart URL, price, currency, SKU and stock status. Taxonomy terms (`product_cat`, `product_tag`) are automatically mapped to `categories` and `tags`.

## Development

```bash
docker compose up -d
# WordPress at http://localhost:8080 (admin/admin)
```

See `docker-compose.yml` for the full dev environment setup.

## Planned features

- Page builder compatibility

## Links

- [Cloudflare: Markdown for Agents](https://blog.cloudflare.com/markdown-for-agents/) — announcement blog post
- [Cloudflare: Spec reference](https://developers.cloudflare.com/fundamentals/reference/markdown-for-agents/) — technical specification
- [Content Signals](https://contentsignals.org/) — HTTP header standard for AI content permissions

## License

GPL v2 or later
