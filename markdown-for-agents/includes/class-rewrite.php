<?php

class MDFA_Rewrite {

	public static function init(): void {
		if ( get_option( 'permalink_structure' ) === '' ) {
			return;
		}
		add_filter( 'rewrite_rules_array', [ __CLASS__, 'add_md_rules' ] );
	}

	/**
	 * For every existing rewrite rule, create a /index.md variant
	 * that adds format=md to the query string.
	 */
	public static function add_md_rules( array $rules ): array {
		$md_rules = [];

		// Handle root /index.md — WP has no ^$ rule, root is handled specially.
		$md_rules['^index\\.md$'] = 'index.php?format=md';

		foreach ( $rules as $regex => $query ) {
			if ( ! str_ends_with( $regex, '$' ) || $regex === '^$' ) {
				continue;
			}

			// Strip trailing /? or /?$ patterns and rebuild with /index.md
			$base = substr( $regex, 0, -1 ); // remove $
			$base = preg_replace( '#/?\??$#', '', $base ); // remove trailing /? or ?

			$md_key = $base . '/index\\.md$';
			$md_val = rtrim( $query, '&' ) . '&format=md';

			$md_rules[ $md_key ] = $md_val;
		}

		return array_merge( $md_rules, $rules );
	}
}
