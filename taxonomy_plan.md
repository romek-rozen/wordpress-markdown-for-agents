# Taxonomy Archive Support — Plan

## Cel

Rozszerzenie pluginu o serwowanie markdowna na stronach archiwów taksonomii: kategorie, tagi i custom taxonomy.

## Obecny stan

Plugin obsługuje tylko `is_singular()` — pojedyncze posty/strony. Trzy blokady:

| Plik | Blokada | Powód |
|------|---------|-------|
| `class-content-negotiation.php:10` | `is_singular()` early return | Blokuje archiwa |
| `class-discovery.php:10` | `is_singular()` early return | Blokuje archiwa |
| `class-converter.php` | Przyjmuje `WP_Post` | Nie obsługuje `WP_Term` |

## Architektura rozszerzenia

### 1. Nowa opcja: `mdfa_taxonomies`

```php
// class-admin.php — register_settings()
register_setting( 'mdfa_settings', 'mdfa_taxonomies', [
    'type'              => 'array',
    'sanitize_callback' => [ __CLASS__, 'sanitize_taxonomies' ],
    'default'           => [ 'category', 'post_tag' ],
] );
```

UI: checkboxy analogiczne do `mdfa_post_types`, pobierane z `get_taxonomies(['public' => true], 'objects')`.

### 2. Detekcja archiwum taksonomii

```php
// class-content-negotiation.php
$obj = get_queried_object();

if ( is_singular() && $obj instanceof WP_Post ) {
    // istniejąca logika — single post
} elseif ( is_tax() || is_category() || is_tag() ) {
    if ( $obj instanceof WP_Term ) {
        // nowa logika — taxonomy archive
    }
}
```

**Ważne:** `is_tax()` obejmuje custom taxonomy, ale NIE obejmuje wbudowanych `category` i `post_tag`. Dlatego trzeba sprawdzać `is_tax() || is_category() || is_tag()`.

### 3. Nowa metoda konwertera: `to_markdown_archive()`

```php
// class-converter.php
public static function to_markdown_archive( WP_Term $term, int $page = 1 ): string|false {
    $frontmatter = self::generate_archive_frontmatter( $term, $page );
    $posts_list  = self::generate_posts_list( $term, $page );
    return $frontmatter . "\n\n" . $posts_list;
}
```

#### Frontmatter archiwum

```yaml
---
type: "archive"
taxonomy: "category"
name: "Technologia"
description: "Artykuły o technologii"
url: "https://example.com/category/technologia/"
post_count: 42
page: 1
total_pages: 5
---
```

#### Lista postów

```markdown
## Artykuły

- [Tytuł posta 1](https://example.com/post-1/) — 2025-01-15
  > Opis/excerpt posta 1

- [Tytuł posta 2](https://example.com/post-2/) — 2025-01-10
  > Opis/excerpt posta 2
```

### 4. Paginacja

WordPress domyślnie używa `posts_per_page` z ustawień. Na archiwum markdown:
- Parametr `?format=md&paged=2`
- Lub `Accept: text/markdown` z `?paged=2`
- Frontmatter zawiera `page` i `total_pages`
- Na dole markdowna: linki do następnej/poprzedniej strony

### 5. Cache

Klucz transient dla archiwum:
```
mdfa_archive_{taxonomy}_{term_id}_{page}_{latest_post_modified}
```

`latest_post_modified` = data modyfikacji najnowszego posta w taksonomii — zapewnia invalidację gdy dodano/edytowano post.

Invalidacja w `save_post`: oprócz cache posta, usunąć cache archiwów taksonomii przypisanych do tego posta.

### 6. Request Log

Obecna tabela `wp_mdfa_request_log` ma kolumnę `post_id`. Dla archiwów:
- Opcja A: Nowa kolumna `term_id` + `taxonomy` (wymaga migracja DB)
- Opcja B: Użyć `post_id = 0` i dodać `term_id` w `user_agent` field (hack, nie polecane)
- **Rekomendacja: Opcja A** — dodać `term_id INT UNSIGNED DEFAULT NULL` i `taxonomy VARCHAR(32) DEFAULT NULL`

### 7. Discovery tag

```php
// class-discovery.php — add_discovery_tag()
if ( (is_tax() || is_category() || is_tag()) && $obj instanceof WP_Term ) {
    $enabled_taxonomies = (array) get_option( 'mdfa_taxonomies', ['category', 'post_tag'] );
    if ( in_array( $obj->taxonomy, $enabled_taxonomies, true ) ) {
        $md_url = add_query_arg( 'format', 'md', get_term_link( $obj ) );
        // output <link rel="alternate">
    }
}
```

## Niuanse

### Custom Taxonomy

- `get_taxonomies(['public' => true])` zwraca też custom taxonomy zarejestrowane przez inne pluginy/motywy
- Trzeba sprawdzać `is_taxonomy_viewable()` (WP 5.1+) zamiast tylko `public => true`
- Custom taxonomy mogą mieć niestandardowe rewrite rules — `get_term_link()` to obsługuje
- Niektóre custom taxonomy nie mają archiwum (`has_archive` equivalent) — `$taxonomy->publicly_queryable` musi być `true`

### Hierarchiczne taksonomie (np. kategorie)

- Kategorie mogą mieć parent/child — czy pokazywać podkategorie w archiwum?
- Rekomendacja: pokazać jako sekcję w markdown, np. "## Podkategorie" z linkami

### Puste taksonomie

- Term bez postów — zwrócić markdown z frontmatter i info "Brak artykułów" zamiast 404

### WooCommerce i inne pluginy

- WooCommerce rejestruje `product_cat`, `product_tag` — pojawią się w UI jeśli są public
- To jest OK — użytkownik sam wybiera które taksonomie włączyć

### Wydajność

- Archiwum z wieloma postami: NIE konwertować pełnej treści każdego posta, tylko tytuł + excerpt + link
- To jest znacznie lżejsze niż pełna konwersja HTML→Markdown

## Pliki do modyfikacji

| Plik | Zmiany |
|------|--------|
| `class-content-negotiation.php` | Dodać branch dla archiwów taksonomii |
| `class-discovery.php` | Dodać discovery tag dla archiwów |
| `class-converter.php` | Nowa metoda `to_markdown_archive()` |
| `class-admin.php` | Nowa opcja `mdfa_taxonomies` + UI |
| `class-request-log.php` | Nowe kolumny `term_id`, `taxonomy` |
| `markdown-for-agents.php` | Activation hook: `add_option('mdfa_taxonomies')` |
| `uninstall.php` | `delete_option('mdfa_taxonomies')` |

## Kolejność implementacji

1. Opcja `mdfa_taxonomies` w admin + UI checkboxy
2. Discovery tag dla archiwów
3. `to_markdown_archive()` w converterze (frontmatter + lista postów)
4. Content negotiation — branch archiwum
5. Cache z invalidacją
6. Migracja DB dla request log (term_id, taxonomy)
7. Paginacja
