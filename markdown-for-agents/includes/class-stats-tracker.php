<?php

class MDFA_Stats_Tracker {

	public static function init(): void {
		add_action( 'template_redirect', [ __CLASS__, 'track_html_request' ], 99 );
		add_action( 'template_redirect', [ __CLASS__, 'track_html_archive_request' ], 99 );
	}

	public static function track_html_request(): void {
		if ( ! is_singular() ) {
			return;
		}

		$enabled_types = (array) get_option( 'mdfa_post_types', [ 'post', 'page' ] );
		if ( ! in_array( get_post_type(), $enabled_types, true ) ) {
			return;
		}

		if ( get_query_var( 'format' ) === 'md' ) {
			return;
		}

		$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
		if ( stripos( $accept, 'text/markdown' ) !== false ) {
			return;
		}

		$post = get_queried_object();
		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}

		$cache_key = 'mdfa_html_tokens_' . $post->ID . '_' . md5( $post->post_modified );
		$tokens    = get_transient( $cache_key );

		if ( $tokens === false ) {
			$html   = apply_filters( 'the_content', $post->post_content );
			$tokens = MDFA_Token_Estimator::estimate( $html );
			$ttl    = (int) get_option( 'mdfa_cache_ttl', 3600 );
			set_transient( $cache_key, $tokens, $ttl ?: 3600 );
		}

		$stats = get_option( 'mdfa_stats', [
			'html_requests'         => 0,
			'html_tokens_estimated' => 0,
			'started_at'            => current_time( 'mysql' ),
		] );

		$stats['html_requests']++;
		$stats['html_tokens_estimated'] += (int) $tokens;

		if ( empty( $stats['started_at'] ) ) {
			$stats['started_at'] = current_time( 'mysql' );
		}

		update_option( 'mdfa_stats', $stats, false );
	}

	public static function track_html_archive_request(): void {
		if ( ! ( is_tax() || is_category() || is_tag() ) ) {
			return;
		}

		if ( get_query_var( 'format' ) === 'md' ) {
			return;
		}

		$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
		if ( stripos( $accept, 'text/markdown' ) !== false ) {
			return;
		}

		$term = get_queried_object();
		if ( ! ( $term instanceof WP_Term ) ) {
			return;
		}

		$enabled_taxonomies = (array) get_option( 'mdfa_taxonomies', [ 'category', 'post_tag' ] );
		if ( ! in_array( $term->taxonomy, $enabled_taxonomies, true ) ) {
			return;
		}

		$stats = get_option( 'mdfa_stats', [
			'html_requests'         => 0,
			'html_tokens_estimated' => 0,
			'started_at'            => current_time( 'mysql' ),
		] );

		$stats['html_archive_requests'] = ( $stats['html_archive_requests'] ?? 0 ) + 1;

		if ( empty( $stats['started_at'] ) ) {
			$stats['started_at'] = current_time( 'mysql' );
		}

		update_option( 'mdfa_stats', $stats, false );
	}

	public static function get_stats(): array {
		$html_stats = get_option( 'mdfa_stats', [
			'html_requests'         => 0,
			'html_tokens_estimated' => 0,
			'started_at'            => '',
		] );

		$md_count       = MDFA_Request_Log::count_all();
		$md_token_stats = MDFA_Request_Log::get_token_stats();

		return [
			'html_requests'         => (int) ( $html_stats['html_requests'] ?? 0 ),
			'html_tokens_estimated' => (int) ( $html_stats['html_tokens_estimated'] ?? 0 ),
			'md_requests'           => $md_count,
			'md_tokens'             => $md_token_stats['total_tokens'],
			'started_at'            => $html_stats['started_at'] ?? '',
		];
	}

	public static function reset_stats(): void {
		delete_option( 'mdfa_stats' );
	}
}
