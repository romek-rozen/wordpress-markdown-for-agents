<?php

class MDFA_Discovery {

	public static function init(): void {
		add_action( 'wp_head', [ __CLASS__, 'add_discovery_tag' ] );
	}

	public static function add_discovery_tag(): void {
		if ( is_front_page() && ! is_home() ) {
			$enabled_types = (array) get_option( 'mdfa_post_types', [ 'post', 'page' ] );
			if ( in_array( 'page', $enabled_types, true ) ) {
				$md_url = self::get_md_url( home_url( '/' ) );
				printf(
					'<link rel="alternate" type="text/markdown" href="%s" title="Markdown" />' . "\n",
					esc_url( $md_url )
				);
			}
			return;
		}

		if ( is_home() ) {
			$blog_url = is_front_page() ? home_url( '/' ) : get_permalink( get_option( 'page_for_posts' ) );
			$md_url   = self::get_md_url( $blog_url ?: home_url( '/' ) );
			printf(
				'<link rel="alternate" type="text/markdown" href="%s" title="Markdown" />' . "\n",
				esc_url( $md_url )
			);
			return;
		}

		$obj = get_queried_object();

		if ( is_singular() && $obj instanceof WP_Post ) {
			$enabled_types = (array) get_option( 'mdfa_post_types', [ 'post', 'page' ] );
			if ( ! in_array( $obj->post_type, $enabled_types, true ) ) {
				return;
			}

			$md_url = self::get_md_url( get_permalink( $obj ) );

			printf(
				'<link rel="alternate" type="text/markdown" href="%s" title="Markdown" />' . "\n",
				esc_url( $md_url )
			);
			return;
		}

		if ( ( is_tax() || is_category() || is_tag() ) && $obj instanceof WP_Term ) {
			$enabled_taxonomies = (array) get_option( 'mdfa_taxonomies', [ 'category', 'post_tag' ] );
			if ( ! in_array( $obj->taxonomy, $enabled_taxonomies, true ) ) {
				return;
			}

			$term_link = get_term_link( $obj );
			if ( is_wp_error( $term_link ) ) {
				return;
			}

			$md_url = self::get_md_url( $term_link );

			printf(
				'<link rel="alternate" type="text/markdown" href="%s" title="Markdown" />' . "\n",
				esc_url( $md_url )
			);
		}
	}

	private static function get_md_url( string $canonical_url ): string {
		if ( get_option( 'permalink_structure' ) === '' ) {
			return add_query_arg( 'format', 'md', $canonical_url );
		}
		return rtrim( $canonical_url, '/' ) . '/index.md';
	}
}
