<?php

class MDFA_Admin_Tab_Settings {

	public static function register( string $admin_class ): void {
		register_setting( 'mdfa_settings', 'mdfa_enabled', [
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		] );

		register_setting( 'mdfa_settings', 'mdfa_post_types', [
			'type'              => 'array',
			'sanitize_callback' => [ __CLASS__, 'sanitize_post_types' ],
			'default'           => [ 'post', 'page' ],
		] );

		register_setting( 'mdfa_settings', 'mdfa_cache_ttl', [
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 3600,
		] );

		register_setting( 'mdfa_settings', 'mdfa_noindex', [
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		] );

		register_setting( 'mdfa_settings', 'mdfa_ai_bots', [
			'type'              => 'array',
			'sanitize_callback' => [ __CLASS__, 'sanitize_bot_list' ],
			'default'           => MDFA_Request_Log::DEFAULT_AI_BOTS,
		] );

		register_setting( 'mdfa_settings', 'mdfa_search_crawlers', [
			'type'              => 'array',
			'sanitize_callback' => [ __CLASS__, 'sanitize_bot_list' ],
			'default'           => MDFA_Request_Log::DEFAULT_SEARCH_CRAWLERS,
		] );

		register_setting( 'mdfa_settings', 'mdfa_tool_crawlers', [
			'type'              => 'array',
			'sanitize_callback' => [ __CLASS__, 'sanitize_bot_list' ],
			'default'           => MDFA_Request_Log::DEFAULT_TOOL_CRAWLERS,
		] );

		add_settings_section(
			'mdfa_general',
			__( 'Ustawienia ogólne', 'markdown-for-agents' ),
			null,
			'markdown-for-agents'
		);

		add_settings_field(
			'mdfa_enabled',
			__( 'Włącz wtyczkę', 'markdown-for-agents' ),
			[ __CLASS__, 'render_enabled_field' ],
			'markdown-for-agents',
			'mdfa_general'
		);

		add_settings_field(
			'mdfa_post_types',
			__( 'Typy postów', 'markdown-for-agents' ),
			[ __CLASS__, 'render_post_types_field' ],
			'markdown-for-agents',
			'mdfa_general'
		);

		add_settings_field(
			'mdfa_cache_ttl',
			__( 'Cache TTL (sekundy)', 'markdown-for-agents' ),
			[ __CLASS__, 'render_cache_ttl_field' ],
			'markdown-for-agents',
			'mdfa_general'
		);

		add_settings_field(
			'mdfa_noindex',
			__( 'Indeksowanie Markdown', 'markdown-for-agents' ),
			[ __CLASS__, 'render_noindex_field' ],
			'markdown-for-agents',
			'mdfa_general'
		);

		add_settings_field(
			'mdfa_ai_bots',
			__( 'Boty AI', 'markdown-for-agents' ),
			[ __CLASS__, 'render_ai_bots_field' ],
			'markdown-for-agents',
			'mdfa_general'
		);

		add_settings_field(
			'mdfa_search_crawlers',
			__( 'Crawlery wyszukiwarek', 'markdown-for-agents' ),
			[ __CLASS__, 'render_search_crawlers_field' ],
			'markdown-for-agents',
			'mdfa_general'
		);

		add_settings_field(
			'mdfa_tool_crawlers',
			__( 'Crawlery narzędzi', 'markdown-for-agents' ),
			[ __CLASS__, 'render_tool_crawlers_field' ],
			'markdown-for-agents',
			'mdfa_general'
		);
	}

	public static function sanitize_post_types( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return [ 'post', 'page' ];
		}
		$public_types = get_post_types( [ 'public' => true ] );
		return array_values( array_intersect( $value, $public_types ) );
	}

	public static function sanitize_bot_list( mixed $value ): array {
		if ( is_array( $value ) ) {
			$value = implode( "\n", $value );
		}
		$lines = explode( "\n", (string) $value );
		$lines = array_map( 'trim', $lines );
		$lines = array_filter( $lines, fn( $l ) => $l !== '' );
		return array_values( array_unique( $lines ) );
	}

	public static function render_enabled_field(): void {
		$enabled = get_option( 'mdfa_enabled', true );
		printf(
			'<input type="checkbox" name="mdfa_enabled" value="1" %s />',
			checked( $enabled, true, false )
		);
	}

	public static function render_post_types_field(): void {
		$selected = (array) get_option( 'mdfa_post_types', [ 'post', 'page' ] );
		$types    = get_post_types( [ 'public' => true ], 'objects' );

		foreach ( $types as $type ) {
			printf(
				'<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="mdfa_post_types[]" value="%s" %s /> %s</label>',
				esc_attr( $type->name ),
				checked( in_array( $type->name, $selected, true ), true, false ),
				esc_html( $type->labels->name )
			);
		}
		echo '<p class="description">' . esc_html__( 'Zaznacz typy postów, dla których ma być dostępny endpoint markdown.', 'markdown-for-agents' ) . '</p>';
	}

	public static function render_noindex_field(): void {
		$noindex = get_option( 'mdfa_noindex', true );
		printf(
			'<input type="checkbox" name="mdfa_noindex" value="1" %s />',
			checked( $noindex, true, false )
		);
		echo '<p class="description">' . esc_html__( 'Wysyłaj nagłówek X-Robots-Tag: noindex dla odpowiedzi Markdown. Odznacz, aby wyszukiwarki mogły indeksować wersję Markdown.', 'markdown-for-agents' ) . '</p>';
	}

	public static function render_cache_ttl_field(): void {
		$ttl = (int) get_option( 'mdfa_cache_ttl', 3600 );
		printf(
			'<input type="number" name="mdfa_cache_ttl" value="%d" min="0" step="1" class="small-text" />',
			$ttl
		);
		echo '<p class="description">' . esc_html__( '0 = bez cache. Domyślnie: 3600 (1 godzina).', 'markdown-for-agents' ) . '</p>';
	}

	public static function render_ai_bots_field(): void {
		$bots = (array) get_option( 'mdfa_ai_bots', MDFA_Request_Log::DEFAULT_AI_BOTS );
		printf(
			'<textarea name="mdfa_ai_bots" rows="8" cols="40" class="large-text code">%s</textarea>',
			esc_textarea( implode( "\n", $bots ) )
		);
		echo '<p class="description">' . esc_html__( 'Jedna nazwa bota na linię. Dopasowanie po fragmencie User-Agent (bez rozróżniania wielkości liter).', 'markdown-for-agents' ) . '</p>';
	}

	public static function render_search_crawlers_field(): void {
		$crawlers = (array) get_option( 'mdfa_search_crawlers', MDFA_Request_Log::DEFAULT_SEARCH_CRAWLERS );
		printf(
			'<textarea name="mdfa_search_crawlers" rows="6" cols="40" class="large-text code">%s</textarea>',
			esc_textarea( implode( "\n", $crawlers ) )
		);
		echo '<p class="description">' . esc_html__( 'Crawlery wyszukiwarek (Google, Bing, etc.). Jedna nazwa na linię.', 'markdown-for-agents' ) . '</p>';
	}

	public static function render_tool_crawlers_field(): void {
		$crawlers = (array) get_option( 'mdfa_tool_crawlers', MDFA_Request_Log::DEFAULT_TOOL_CRAWLERS );
		printf(
			'<textarea name="mdfa_tool_crawlers" rows="6" cols="40" class="large-text code">%s</textarea>',
			esc_textarea( implode( "\n", $crawlers ) )
		);
		echo '<p class="description">' . esc_html__( 'Crawlery narzędzi zewnętrznych (Ahrefs, Semrush, social media, etc.). Jedna nazwa na linię.', 'markdown-for-agents' ) . '</p>';
	}

	public static function render(): void {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'mdfa_settings' );
			do_settings_sections( 'markdown-for-agents' );
			submit_button( __( 'Zapisz ustawienia', 'markdown-for-agents' ) );
			?>
		</form>
		<?php
	}
}
