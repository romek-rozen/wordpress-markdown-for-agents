<?php

class MDFA_Discovery {

	public static function init(): void {
		add_action( 'wp_head', [ __CLASS__, 'add_discovery_tag' ] );
	}

	public static function add_discovery_tag(): void {
		if ( ! is_singular() ) {
			return;
		}

		$enabled_types = (array) get_option( 'mdfa_post_types', [ 'post', 'page' ] );
		if ( ! in_array( get_post_type(), $enabled_types, true ) ) {
			return;
		}

		$md_url = add_query_arg( 'format', 'md', get_permalink() );

		printf(
			'<link rel="alternate" type="text/markdown" href="%s" title="Markdown" />' . "\n",
			esc_url( $md_url )
		);
	}
}
