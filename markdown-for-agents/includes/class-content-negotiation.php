<?php

class MDFA_Content_Negotiation {

	public static function init(): void {
		add_action( 'template_redirect', [ __CLASS__, 'handle_markdown_request' ], 0 );
		add_action( 'send_headers', [ __CLASS__, 'add_vary_header' ] );
	}

	public static function add_vary_header(): void {
		if ( is_front_page() && ! is_home() ) {
			$enabled_types = (array) get_option( 'mdfa_post_types', [ 'post', 'page' ] );
			if ( in_array( 'page', $enabled_types, true ) ) {
				header( 'Vary: Accept', false );
			}
			return;
		}

		if ( is_home() || is_front_page() ) {
			header( 'Vary: Accept', false );
			return;
		}

		if ( is_singular() ) {
			$enabled_types = (array) get_option( 'mdfa_post_types', [ 'post', 'page' ] );
			if ( in_array( get_post_type(), $enabled_types, true ) ) {
				header( 'Vary: Accept', false );
			}
			return;
		}

		if ( is_tax() || is_category() || is_tag() ) {
			$obj = get_queried_object();
			if ( $obj instanceof WP_Term ) {
				$enabled_taxonomies = (array) get_option( 'mdfa_taxonomies', [ 'category', 'post_tag' ] );
				if ( in_array( $obj->taxonomy, $enabled_taxonomies, true ) ) {
					header( 'Vary: Accept', false );
				}
			}
		}
	}

	public static function handle_markdown_request(): void {
		$format = self::get_requested_format();
		if ( ! $format ) {
			return;
		}

		// Static front page — detect both native is_front_page() and the case where
		// ?format=md causes WP to lose the front page context (is_home() becomes true).
		if ( self::is_static_front_page_request() ) {
			$front_page_id = (int) get_option( 'page_on_front' );
			if ( $front_page_id ) {
				$post = get_post( $front_page_id );
				if ( $post ) {
					self::handle_singular_request( $post, $format );
				}
			}
			return;
		}

		// Blog page (latest posts listing).
		if ( is_home() ) {
			self::handle_home_request( $format );
			return;
		}

		$obj = get_queried_object();

		if ( is_singular() && $obj instanceof WP_Post ) {
			self::handle_singular_request( $obj, $format );
			return;
		}

		if ( ( is_tax() || is_category() || is_tag() ) && $obj instanceof WP_Term ) {
			self::handle_archive_request( $obj, $format );
			return;
		}
	}

	private static function handle_singular_request( WP_Post $post, string $format = 'md' ): void {
		if ( post_password_required( $post ) ) {
			return;
		}

		$enabled_types = (array) get_option( 'mdfa_post_types', [ 'post', 'page' ] );
		if ( ! in_array( $post->post_type, $enabled_types, true ) ) {
			return;
		}

		$markdown = MDFA_Converter::to_markdown( $post );
		if ( $markdown === false ) {
			return;
		}

		$tokens = MDFA_Token_Estimator::estimate( $markdown );

		MDFA_Request_Log::log( $post->ID, $tokens );

		self::send_markdown_response( $markdown, $tokens, get_permalink( $post ), $post, null, $format );
	}

	private static function handle_home_request( string $format = 'md' ): void {
		$page = (int) get_query_var( 'paged' ) ?: 1;

		$markdown = MDFA_Converter::to_markdown_home( $page );
		if ( $markdown === false ) {
			return;
		}

		$tokens = MDFA_Token_Estimator::estimate( $markdown );

		MDFA_Request_Log::log( 0, $tokens );

		$home_url = is_front_page() ? home_url( '/' ) : get_permalink( get_option( 'page_for_posts' ) );
		self::send_markdown_response( $markdown, $tokens, $home_url, null, null, $format );
	}

	private static function handle_archive_request( WP_Term $term, string $format = 'md' ): void {
		$enabled_taxonomies = (array) get_option( 'mdfa_taxonomies', [ 'category', 'post_tag' ] );
		if ( ! in_array( $term->taxonomy, $enabled_taxonomies, true ) ) {
			return;
		}

		$page = (int) get_query_var( 'paged' ) ?: 1;

		$markdown = MDFA_Converter::to_markdown_archive( $term, $page );
		if ( $markdown === false ) {
			return;
		}

		$tokens = MDFA_Token_Estimator::estimate( $markdown );

		MDFA_Request_Log::log( 0, $tokens, $term->term_id, $term->taxonomy );

		self::send_markdown_response( $markdown, $tokens, get_term_link( $term ), null, $term, $format );
	}

	private static function send_markdown_response( string $markdown, int $tokens, string $canonical_url = '', ?WP_Post $post = null, ?WP_Term $term = null, string $format = 'md' ): void {
		$content_type = $format === 'txt' ? 'text/plain' : 'text/markdown';
		status_header( 200 );
		header( 'Content-Type: ' . $content_type . '; charset=utf-8' );
		header( 'Vary: Accept' );
		header( 'X-Markdown-Tokens: ' . $tokens );
		$resolved = MDFA_Content_Signals::get_signals( $post, $term );
		$signals  = [];
		if ( $resolved['ai_train'] ) {
			$signals[] = 'ai-train=yes';
		}
		if ( $resolved['search'] ) {
			$signals[] = 'search=yes';
		}
		if ( $resolved['ai_input'] ) {
			$signals[] = 'ai-input=yes';
		}
		if ( ! empty( $signals ) ) {
			header( 'Content-Signal: ' . implode( ', ', $signals ) );
		}
		if ( get_option( 'mdfa_noindex', true ) ) {
			header( 'X-Robots-Tag: noindex' );
		}
		if ( $canonical_url && get_option( 'mdfa_canonical', true ) ) {
			header( 'Link: <' . esc_url( $canonical_url ) . '>; rel="canonical"' );
		}
		if ( $canonical_url ) {
			$alt_format = $format === 'txt' ? 'md' : 'txt';
			$alt_type   = $format === 'txt' ? 'text/markdown' : 'text/plain';
			$alt_url    = MDFA_Discovery::get_alternate_url( $canonical_url, $alt_format );
			header( 'Link: <' . esc_url( $alt_url ) . '>; rel="alternate"; type="' . $alt_type . '"', false );
		}

		echo $markdown;
		exit;
	}

	private static function is_static_front_page_request(): bool {
		// Native detection works with Accept header.
		if ( is_front_page() && ! is_home() ) {
			return true;
		}

		// ?format=md on root URL causes WP to lose front page context.
		// Detect: show_on_front=page, page_on_front set, is_home() true,
		// and no explicit page_id/pagename in the request (i.e. root URL).
		if ( is_home()
			&& get_option( 'show_on_front' ) === 'page'
			&& get_option( 'page_on_front' )
			&& ! isset( $_GET['page_id'] )
			&& ! isset( $_GET['pagename'] )
			&& ! get_query_var( 'page_id' )
			&& ! get_query_var( 'pagename' )
		) {
			// Verify the request path is the site root (or /index.md).
			$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
			$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
			$clean_path = preg_replace( '#/?index\.(?:md|txt)$#', '', rtrim( $path, '/' ) );
			if ( $clean_path === rtrim( $home_path, '/' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Detect requested alternate format.
	 *
	 * @return string|null 'md', 'txt', or null if not requested.
	 */
	private static function get_requested_format(): ?string {
		$format_var = get_query_var( 'format' );
		if ( ! $format_var && isset( $_GET['format'] ) ) {
			$format_var = $_GET['format'];
		}

		if ( $format_var === 'md' ) {
			return 'md';
		}
		if ( $format_var === 'txt' ) {
			return 'txt';
		}

		$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
		if ( stripos( $accept, 'text/markdown' ) !== false ) {
			return 'md';
		}

		return null;
	}
}
