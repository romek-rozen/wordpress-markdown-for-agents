<?php

use League\HTMLToMarkdown\HtmlConverter;

class MDFA_Converter {

	private static ?HtmlConverter $converter = null;

	private static function get_converter(): HtmlConverter {
		if ( self::$converter === null ) {
			self::$converter = new HtmlConverter( [
				'strip_tags'   => true,
				'hard_break'   => true,
				'remove_nodes' => 'script style',
			] );
		}
		return self::$converter;
	}

	public static function to_markdown( int|WP_Post $post ): string|false {
		$post = get_post( $post );
		if ( ! $post ) {
			return false;
		}

		$ttl = (int) get_option( 'mdfa_cache_ttl', 3600 );

		if ( $ttl > 0 ) {
			$cache_key = self::get_cache_key( $post );
			$cached    = get_transient( $cache_key );
			if ( $cached !== false ) {
				return $cached;
			}
		}

		$markdown = self::generate_markdown( $post );

		if ( $ttl > 0 ) {
			set_transient( $cache_key, $markdown, $ttl );
			update_post_meta( $post->ID, '_mdfa_cache_key', $cache_key );
		}

		return $markdown;
	}

	private static function get_cache_key( WP_Post $post ): string {
		return 'mdfa_md_' . $post->ID . '_' . md5( $post->post_modified );
	}

	private static function generate_markdown( WP_Post $post ): string {
		$html = apply_filters( 'the_content', $post->post_content );

		$markdown_body = self::get_converter()->convert( $html );

		$frontmatter = self::generate_frontmatter( $post );

		return $frontmatter . "\n\n" . $markdown_body;
	}

	private static function generate_frontmatter( WP_Post $post ): string {
		$author  = get_userdata( $post->post_author );
		$excerpt = $post->post_excerpt ?: wp_trim_words( $post->post_content, 55, '' );

		$taxonomies     = get_object_taxonomies( $post->post_type, 'objects' );
		$category_names = [];
		$tag_names      = [];

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! $taxonomy->public ) {
				continue;
			}
			$terms = wp_get_post_terms( $post->ID, $taxonomy->name, [ 'fields' => 'names' ] );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			if ( $taxonomy->hierarchical ) {
				$category_names = array_merge( $category_names, $terms );
			} else {
				$tag_names = array_merge( $tag_names, $terms );
			}
		}

		$lines   = [];
		$lines[] = '---';
		$lines[] = 'title: "' . self::escape_yaml( $post->post_title ) . '"';
		$lines[] = 'description: "' . self::escape_yaml( $excerpt ) . '"';
		$lines[] = 'date: ' . get_the_date( 'Y-m-d', $post );
		$lines[] = 'author: "' . self::escape_yaml( $author->display_name ?? '' ) . '"';
		$lines[] = 'url: "' . get_permalink( $post ) . '"';

		if ( $post->post_type === 'product' && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post->ID );
			if ( $product ) {
				$lines[] = 'add_to_cart_url: "' . self::escape_yaml( $product->add_to_cart_url() ) . '"';
				$lines[] = 'price: "' . self::escape_yaml( $product->get_price() ) . '"';
				$lines[] = 'currency: "' . self::escape_yaml( get_woocommerce_currency() ) . '"';
				if ( $product->get_sku() ) {
					$lines[] = 'sku: "' . self::escape_yaml( $product->get_sku() ) . '"';
				}
				$lines[] = 'in_stock: ' . ( $product->is_in_stock() ? 'true' : 'false' );
			}
		}

		if ( ! empty( $category_names ) ) {
			$lines[] = 'categories:';
			foreach ( $category_names as $cat ) {
				$lines[] = '  - "' . self::escape_yaml( $cat ) . '"';
			}
		}

		if ( ! empty( $tag_names ) ) {
			$lines[] = 'tags:';
			foreach ( $tag_names as $tag ) {
				$lines[] = '  - "' . self::escape_yaml( $tag ) . '"';
			}
		}

		$lines[] = '---';

		return implode( "\n", $lines );
	}

	private static function escape_yaml( string $value ): string {
		return str_replace( '"', '\\"', $value );
	}

	public static function invalidate_cache( int $post_id ): void {
		$old_key = get_post_meta( $post_id, '_mdfa_cache_key', true );
		if ( $old_key ) {
			delete_transient( $old_key );
			delete_post_meta( $post_id, '_mdfa_cache_key' );
		}

		self::invalidate_archive_cache( $post_id );
	}

	public static function to_markdown_archive( \WP_Term $term, int $page = 1 ): string|false {
		$ttl = (int) get_option( 'mdfa_cache_ttl', 3600 );

		if ( $ttl > 0 ) {
			$cache_key = self::get_archive_cache_key( $term, $page );
			$cached    = get_transient( $cache_key );
			if ( $cached !== false ) {
				return $cached;
			}
		}

		$posts_per_page = (int) get_option( 'posts_per_page', 10 );
		$query          = new \WP_Query( [
			'tax_query'      => [
				[
					'taxonomy' => $term->taxonomy,
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				],
			],
			'paged'          => max( 1, $page ),
			'posts_per_page' => $posts_per_page,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'post_status'    => 'publish',
		] );

		$total_pages = (int) $query->max_num_pages;
		$post_count  = (int) $query->found_posts;

		$frontmatter = self::generate_archive_frontmatter( $term, $page, $total_pages, $post_count );
		$posts_list  = self::generate_posts_list( $term, $query );

		$taxonomy_obj = get_taxonomy( $term->taxonomy );
		$subcategories = '';
		if ( $taxonomy_obj && $taxonomy_obj->hierarchical ) {
			$subcategories = self::generate_subcategories( $term );
		}

		$parts = [ $frontmatter ];
		if ( $subcategories !== '' ) {
			$parts[] = $subcategories;
		}
		$parts[] = $posts_list;

		if ( $total_pages > 1 ) {
			$parts[] = self::generate_pagination_links( $term, $page, $total_pages );
		}

		$markdown = implode( "\n\n", $parts );

		if ( $ttl > 0 ) {
			set_transient( $cache_key, $markdown, $ttl );
		}

		return $markdown;
	}

	private static function generate_archive_frontmatter( \WP_Term $term, int $page, int $total_pages, int $post_count ): string {
		$term_link = get_term_link( $term );
		$url       = is_wp_error( $term_link ) ? '' : $term_link;

		$lines   = [];
		$lines[] = '---';
		$lines[] = 'type: "archive"';
		$lines[] = 'taxonomy: "' . self::escape_yaml( $term->taxonomy ) . '"';
		$lines[] = 'name: "' . self::escape_yaml( $term->name ) . '"';
		if ( $term->description ) {
			$lines[] = 'description: "' . self::escape_yaml( $term->description ) . '"';
		}
		$lines[] = 'url: "' . $url . '"';
		$lines[] = 'post_count: ' . $post_count;
		$lines[] = 'page: ' . $page;
		$lines[] = 'total_pages: ' . $total_pages;
		$lines[] = '---';

		return implode( "\n", $lines );
	}

	private static function generate_posts_list( \WP_Term $term, \WP_Query $query ): string {
		if ( ! $query->have_posts() ) {
			return __( 'Brak wpisów w tym archiwum.', 'markdown-for-agents' );
		}

		$is_product_taxonomy = in_array( $term->taxonomy, [ 'product_cat', 'product_tag' ], true )
			&& function_exists( 'wc_get_product' );

		$lines   = [];
		$lines[] = '## ' . self::escape_yaml( $term->name );
		$lines[] = '';

		while ( $query->have_posts() ) {
			$query->the_post();
			$post = get_post();

			$title = get_the_title( $post );
			$url   = get_permalink( $post );
			$date  = get_the_date( 'Y-m-d', $post );

			if ( $is_product_taxonomy ) {
				$product = wc_get_product( $post->ID );
				if ( $product ) {
					$price_info = $product->get_price() . ' ' . get_woocommerce_currency();
					$sku_info   = $product->get_sku() ? ' | SKU: ' . $product->get_sku() : '';
					$lines[]    = "- [{$title}]({$url}) — {$price_info}{$sku_info}";
				} else {
					$lines[] = "- [{$title}]({$url}) — {$date}";
				}
			} else {
				$lines[] = "- [{$title}]({$url}) — {$date}";
			}

			$excerpt = get_the_excerpt( $post );
			if ( $excerpt ) {
				$lines[] = "  > {$excerpt}";
			}

			$lines[] = '';
		}

		wp_reset_postdata();

		return implode( "\n", $lines );
	}

	private static function generate_subcategories( \WP_Term $term ): string {
		$children = get_terms( [
			'taxonomy'   => $term->taxonomy,
			'parent'     => $term->term_id,
			'hide_empty' => false,
		] );

		if ( is_wp_error( $children ) || empty( $children ) ) {
			return '';
		}

		$lines   = [];
		$lines[] = '## ' . __( 'Podkategorie', 'markdown-for-agents' );
		$lines[] = '';

		foreach ( $children as $child ) {
			$link = get_term_link( $child );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			$lines[] = "- [{$child->name}]({$link})";
		}

		return implode( "\n", $lines );
	}

	private static function generate_pagination_links( \WP_Term $term, int $page, int $total_pages ): string {
		$term_link = get_term_link( $term );
		if ( is_wp_error( $term_link ) ) {
			return '';
		}

		$lines   = [];
		$lines[] = '---';

		if ( $page > 1 ) {
			$prev_url = add_query_arg( [ 'format' => 'md', 'paged' => $page - 1 ], $term_link );
			$lines[]  = __( 'Poprzednia strona', 'markdown-for-agents' ) . ": [{$prev_url}]({$prev_url})";
		}
		if ( $page < $total_pages ) {
			$next_url = add_query_arg( [ 'format' => 'md', 'paged' => $page + 1 ], $term_link );
			$lines[]  = __( 'Następna strona', 'markdown-for-agents' ) . ": [{$next_url}]({$next_url})";
		}

		return implode( "  \n", $lines );
	}

	private static function get_archive_cache_key( \WP_Term $term, int $page ): string {
		$taxonomy_safe = sanitize_key( $term->taxonomy );
		$term_id       = (int) $term->term_id;
		$modified_key  = "mdfa_archive_modified_{$term_id}_{$taxonomy_safe}";

		$latest_modified = get_transient( $modified_key );
		if ( $latest_modified === false ) {
			global $wpdb;
			$latest_modified = (string) $wpdb->get_var( $wpdb->prepare(
				"SELECT MAX(p.post_modified) FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
				WHERE tr.term_taxonomy_id = %d AND p.post_status = 'publish'",
				$term->term_taxonomy_id
			) );
			set_transient( $modified_key, $latest_modified, 300 );
		}

		return "mdfa_archive_{$taxonomy_safe}_{$term_id}_{$page}_" . md5( $latest_modified );
	}

	private static function invalidate_archive_cache( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		global $wpdb;

		$taxonomies = get_object_taxonomies( $post->post_type );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_post_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			$taxonomy_safe = sanitize_key( $taxonomy );
			foreach ( $terms as $term_id ) {
				$term_id_int = (int) $term_id;
				$pattern     = $wpdb->esc_like( "mdfa_archive_{$taxonomy_safe}_{$term_id_int}_" ) . '%';
				$wpdb->query( $wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					'_transient_' . $pattern,
					'_transient_timeout_' . $pattern
				) );
				delete_transient( "mdfa_archive_modified_{$term_id_int}_{$taxonomy_safe}" );
			}
		}
	}
}
