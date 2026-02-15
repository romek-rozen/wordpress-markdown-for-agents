<?php

class MDFA_Content_Negotiation {

	public static function init(): void {
		add_action( 'template_redirect', [ __CLASS__, 'handle_markdown_request' ], 5 );
		add_action( 'send_headers', [ __CLASS__, 'add_vary_header' ] );
	}

	public static function add_vary_header(): void {
		if ( ! is_singular() ) {
			return;
		}
		$enabled_types = (array) get_option( 'mdfa_post_types', [ 'post', 'page' ] );
		if ( ! in_array( get_post_type(), $enabled_types, true ) ) {
			return;
		}
		header( 'Vary: Accept', false );
	}

	public static function handle_markdown_request(): void {
		if ( ! is_singular() ) {
			return;
		}

		if ( ! self::is_markdown_requested() ) {
			return;
		}

		$post = get_queried_object();
		if ( ! ( $post instanceof WP_Post ) ) {
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

		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'Vary: Accept' );
		header( 'X-Markdown-Tokens: ' . $tokens );
		header( 'Content-Signal: ai-train=yes, search=yes, ai-input=yes' );
		if ( get_option( 'mdfa_noindex', true ) ) {
			header( 'X-Robots-Tag: noindex' );
		}

		echo $markdown;
		exit;
	}

	private static function is_markdown_requested(): bool {
		if ( get_query_var( 'format' ) === 'md' ) {
			return true;
		}

		$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
		if ( stripos( $accept, 'text/markdown' ) !== false ) {
			return true;
		}

		return false;
	}
}
