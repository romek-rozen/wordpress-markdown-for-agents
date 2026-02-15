<?php

class MDFA_Content_Negotiation {

	public static function init(): void {
		add_action( 'template_redirect', [ __CLASS__, 'handle_markdown_request' ], 0 );
		add_action( 'send_headers', [ __CLASS__, 'add_vary_header' ] );
	}

	public static function add_vary_header(): void {
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
		if ( ! self::is_markdown_requested() ) {
			return;
		}

		$obj = get_queried_object();

		if ( is_singular() && $obj instanceof WP_Post ) {
			self::handle_singular_request( $obj );
			return;
		}

		if ( ( is_tax() || is_category() || is_tag() ) && $obj instanceof WP_Term ) {
			self::handle_archive_request( $obj );
			return;
		}
	}

	private static function handle_singular_request( WP_Post $post ): void {
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

		self::send_markdown_response( $markdown, $tokens, get_permalink( $post ) );
	}

	private static function handle_archive_request( WP_Term $term ): void {
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

		self::send_markdown_response( $markdown, $tokens, get_term_link( $term ) );
	}

	private static function send_markdown_response( string $markdown, int $tokens, string $canonical_url = '' ): void {
		status_header( 200 );
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'Vary: Accept' );
		header( 'X-Markdown-Tokens: ' . $tokens );
		$signals = [];
		if ( get_option( 'mdfa_signal_ai_train', true ) ) {
			$signals[] = 'ai-train=yes';
		}
		if ( get_option( 'mdfa_signal_search', true ) ) {
			$signals[] = 'search=yes';
		}
		if ( get_option( 'mdfa_signal_ai_input', true ) ) {
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

		echo $markdown;
		exit;
	}

	private static function is_markdown_requested(): bool {
		if ( get_query_var( 'format' ) === 'md' ) {
			return true;
		}

		if ( isset( $_GET['format'] ) && $_GET['format'] === 'md' ) {
			return true;
		}

		$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
		if ( stripos( $accept, 'text/markdown' ) !== false ) {
			return true;
		}

		return false;
	}
}
