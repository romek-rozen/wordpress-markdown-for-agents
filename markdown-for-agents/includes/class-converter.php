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

		$cache_key = self::get_cache_key( $post );

		$cached = get_transient( $cache_key );
		if ( $cached !== false ) {
			return $cached;
		}

		$markdown = self::generate_markdown( $post );

		$ttl = (int) get_option( 'mdfa_cache_ttl', 3600 );
		set_transient( $cache_key, $markdown, $ttl );
		update_post_meta( $post->ID, '_mdfa_cache_key', $cache_key );

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

		$categories    = get_the_category( $post->ID );
		$category_names = array_map( fn( $cat ) => $cat->name, $categories );

		$tags      = get_the_tags( $post->ID );
		$tag_names = $tags ? array_map( fn( $tag ) => $tag->name, $tags ) : [];

		$lines   = [];
		$lines[] = '---';
		$lines[] = 'title: "' . self::escape_yaml( $post->post_title ) . '"';
		$lines[] = 'description: "' . self::escape_yaml( $excerpt ) . '"';
		$lines[] = 'date: ' . get_the_date( 'Y-m-d', $post );
		$lines[] = 'author: "' . self::escape_yaml( $author->display_name ?? '' ) . '"';
		$lines[] = 'url: "' . get_permalink( $post ) . '"';

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
	}
}
