<?php

class MDFA_Rewrite {

	private static bool $intercepting = false;

	public static function init(): void {
		if ( get_option( 'permalink_structure' ) === '' ) {
			return;
		}
		add_action( 'parse_request', [ __CLASS__, 'intercept_md_request' ], 0 );
	}

	/**
	 * Detect /index.md suffix in the request and re-resolve without it,
	 * then inject format=md into query vars.
	 */
	public static function intercept_md_request( \WP $wp ): void {
		// Guard against recursion.
		if ( self::$intercepting ) {
			return;
		}

		$path = trim( $wp->request ?? '', '/' );

		if ( ! str_ends_with( $path, '/index.md' ) && $path !== 'index.md' ) {
			return;
		}

		// Strip /index.md suffix.
		$clean_path = preg_replace( '#/?index\.md$#', '', $path );

		// Temporarily override REQUEST_URI for WP's parse_request.
		$original_uri = $_SERVER['REQUEST_URI'];
		$_SERVER['REQUEST_URI'] = '/' . $clean_path . ( $clean_path ? '/' : '' );

		// Re-parse the request with the clean path.
		self::$intercepting = true;
		$wp->parse_request();
		self::$intercepting = false;

		// Restore original URI.
		$_SERVER['REQUEST_URI'] = $original_uri;

		// Inject format=md.
		$wp->query_vars['format'] = 'md';
	}
}
