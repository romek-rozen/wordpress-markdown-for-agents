# Changelog

Wszystkie istotne zmiany w projekcie są dokumentowane w tym pliku.

## [1.0.3] — 2026-02-15

### Dodane
- Nagłówek HTTP `Link: <url>; rel="canonical"` w odpowiedziach Markdown wskazujący na oryginalną stronę HTML (RFC 5988)
- Ustawienie włączenia/wyłączenia nagłówka canonical (domyślnie włączony)
- Filtr po poście w zakładce Logi (dropdown "Wszystkie posty")
- Kliknięcie tytułu posta w "Najpopularniejsze posty" przenosi do logów przefiltrowanych na ten post

## [1.0.2] — 2026-02-15

### Dodane
- Obsługa archiwów taksonomii (kategorie, tagi, WooCommerce product_cat/product_tag, custom taxonomies)
- Frontmatter archiwów: type, taxonomy, name, description, url, post_count, page, total_pages
- Lista postów z excerptami, podkategorie (hierarchiczne), paginacja
- WooCommerce: cena/SKU w liście produktów na archiwach
- Migracja DB: kolumny `term_id`/`taxonomy` w tabeli logów
- Ustawienia: checkboxy włączonych taksonomii

### Naprawione
- `?format=md` nie działał na stronie głównej (static front page) — fallback na `$_GET['format']`
- Brak `status_header(200)` w odpowiedzi markdown
- `display: block` na pasku statystyk (bar-fill)

## [1.0.1] — 2026-02-15

### Dodane
- Screen Options z wyborem kolumn i per_page w logach
- Dropdown filtry po nazwie bota i metodzie (Accept/format_param)
- Views: AI / Wyszukiwarki / Narzędzia / Przeglądarki / Inne
- Kolumna Bot/Klient w tabeli logów

### Zmienione
- Refaktoryzacja class-admin: wydzielenie zakładek do osobnych klas (settings, logs, stats)

## [1.0.0] — 2026-02-15

### Dodane
- Konwersja HTML→Markdown z YAML frontmatter (title, author, date, categories, tags, url)
- Content negotiation: `Accept: text/markdown` + `?format=md`
- Discovery tag: `<link rel="alternate" type="text/markdown">`
- Nagłówki HTTP: Content-Type, Vary, X-Markdown-Tokens, Content-Signal, X-Robots-Tag
- Estymacja tokenów (`ceil(mb_strlen / 4)`)
- Logowanie requestów z identyfikacją botów (24+ AI bots, crawlery, narzędzia)
- Panel admina z zakładkami: statystyki, logi, ustawienia
- Porównanie HTML vs Markdown (oszczędność tokenów)
- Cache via WordPress Transients API
- Obsługa WooCommerce: add_to_cart_url, price, currency, sku, in_stock we frontmatterze
- Uniwersalne pobieranie taksonomii (get_object_taxonomies)
- Konfigurowalne listy botów (AI, wyszukiwarki, narzędzia)
- Opcja noindex (X-Robots-Tag)
- Auto-update z repozytorium Forgejo
- Docker dev environment
- Czysty uninstall (opcje, tabela, cache, post meta)
