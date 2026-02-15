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

		register_setting( 'mdfa_settings', 'mdfa_taxonomies', [
			'type'              => 'array',
			'sanitize_callback' => [ __CLASS__, 'sanitize_taxonomies' ],
			'default'           => [ 'category', 'post_tag' ],
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

		register_setting( 'mdfa_settings', 'mdfa_canonical', [
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		] );

		register_setting( 'mdfa_settings', 'mdfa_anonymize_ip', [
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		] );

		register_setting( 'mdfa_settings', 'mdfa_beta_updates', [
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => false,
		] );

		register_setting( 'mdfa_settings', 'mdfa_max_log_rows', [
			'type'              => 'integer',
			'sanitize_callback' => 'intval',
			'default'           => 50000,
		] );

		register_setting( 'mdfa_settings', 'mdfa_signal_ai_train', [
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		] );

		register_setting( 'mdfa_settings', 'mdfa_signal_search', [
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		] );

		register_setting( 'mdfa_settings', 'mdfa_signal_ai_input', [
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
			'mdfa_taxonomies',
			__( 'Taksonomie (archiwa)', 'markdown-for-agents' ),
			[ __CLASS__, 'render_taxonomies_field' ],
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
			'mdfa_canonical',
			__( 'Nagłówek canonical', 'markdown-for-agents' ),
			[ __CLASS__, 'render_canonical_field' ],
			'markdown-for-agents',
			'mdfa_general'
		);

		add_settings_field(
			'mdfa_anonymize_ip',
			__( 'Anonimizacja IP (GDPR)', 'markdown-for-agents' ),
			[ __CLASS__, 'render_anonymize_ip_field' ],
			'markdown-for-agents',
			'mdfa_general'
		);

		add_settings_field(
			'mdfa_beta_updates',
			__( 'Aktualizacje beta', 'markdown-for-agents' ),
			[ __CLASS__, 'render_beta_updates_field' ],
			'markdown-for-agents',
			'mdfa_general'
		);

		add_settings_field(
			'mdfa_max_log_rows',
			__( 'Limit logów', 'markdown-for-agents' ),
			[ __CLASS__, 'render_max_log_rows_field' ],
			'markdown-for-agents',
			'mdfa_general'
		);

		add_settings_section(
			'mdfa_content_signals',
			__( 'Content Signals', 'markdown-for-agents' ),
			null,
			'markdown-for-agents'
		);

		add_settings_field(
			'mdfa_signal_ai_train',
			__( 'ai-train', 'markdown-for-agents' ),
			[ __CLASS__, 'render_signal_ai_train_field' ],
			'markdown-for-agents',
			'mdfa_content_signals'
		);

		add_settings_field(
			'mdfa_signal_search',
			__( 'search', 'markdown-for-agents' ),
			[ __CLASS__, 'render_signal_search_field' ],
			'markdown-for-agents',
			'mdfa_content_signals'
		);

		add_settings_field(
			'mdfa_signal_ai_input',
			__( 'ai-input', 'markdown-for-agents' ),
			[ __CLASS__, 'render_signal_ai_input_field' ],
			'markdown-for-agents',
			'mdfa_content_signals'
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

	public static function sanitize_taxonomies( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return [ 'category', 'post_tag' ];
		}
		$public_taxonomies = get_taxonomies( [ 'public' => true ] );
		return array_values( array_intersect( $value, $public_taxonomies ) );
	}

	public static function sanitize_bot_list( mixed $value ): array {
		if ( is_array( $value ) ) {
			$value = implode( "\n", $value );
		}
		$lines = explode( "\n", (string) $value );
		$lines = array_map( 'trim', $lines );
		$lines = array_filter( $lines, fn( $l ) => $l !== '' );
		$lines = array_map( 'sanitize_text_field', $lines );
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

	public static function render_taxonomies_field(): void {
		$selected   = (array) get_option( 'mdfa_taxonomies', [ 'category', 'post_tag' ] );
		$taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );

		foreach ( $taxonomies as $taxonomy ) {
			printf(
				'<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="mdfa_taxonomies[]" value="%s" %s /> %s</label>',
				esc_attr( $taxonomy->name ),
				checked( in_array( $taxonomy->name, $selected, true ), true, false ),
				esc_html( $taxonomy->labels->name )
			);
		}
		echo '<p class="description">' . esc_html__( 'Zaznacz taksonomie, których archiwa mają być dostępne jako Markdown.', 'markdown-for-agents' ) . '</p>';
	}

	public static function render_noindex_field(): void {
		$noindex = get_option( 'mdfa_noindex', true );
		printf(
			'<input type="checkbox" name="mdfa_noindex" value="1" %s />',
			checked( $noindex, true, false )
		);
		echo '<p class="description">' . esc_html__( 'Wysyłaj nagłówek X-Robots-Tag: noindex dla odpowiedzi Markdown. Odznacz, aby wyszukiwarki mogły indeksować wersję Markdown.', 'markdown-for-agents' ) . '</p>';
	}

	public static function render_canonical_field(): void {
		$canonical = get_option( 'mdfa_canonical', true );
		printf(
			'<input type="checkbox" name="mdfa_canonical" value="1" %s />',
			checked( $canonical, true, false )
		);
		echo '<p class="description">' . esc_html__( 'Wysyłaj nagłówek HTTP Link: rel="canonical" wskazujący na oryginalną stronę HTML. Zalecane przez Google dla treści w formatach innych niż HTML (RFC 5988).', 'markdown-for-agents' ) . '</p>';
	}

	public static function render_cache_ttl_field(): void {
		$ttl = (int) get_option( 'mdfa_cache_ttl', 3600 );
		printf(
			'<input type="number" name="mdfa_cache_ttl" value="%d" min="0" step="1" class="small-text" />',
			$ttl
		);
		echo '<p class="description">' . esc_html__( '0 = bez cache. Domyślnie: 3600 (1 godzina).', 'markdown-for-agents' ) . '</p>';
	}

	public static function render_anonymize_ip_field(): void {
		$anonymize = get_option( 'mdfa_anonymize_ip', true );
		printf(
			'<input type="checkbox" name="mdfa_anonymize_ip" value="1" %s />',
			checked( $anonymize, true, false )
		);
		echo '<p class="description">' . esc_html__( 'Obcinaj ostatni oktet IPv4 / ostatnie 80 bitów IPv6 przed zapisem do logów. Zalecane ze względu na GDPR.', 'markdown-for-agents' ) . '</p>';
	}

	public static function render_beta_updates_field(): void {
		$beta = get_option( 'mdfa_beta_updates', false );
		printf(
			'<input type="checkbox" name="mdfa_beta_updates" value="1" %s />',
			checked( $beta, true, false )
		);
		echo '<p class="description">' . esc_html__( 'Włącz, aby otrzymywać aktualizacje pre-release (beta, RC). Niezalecane na produkcji.', 'markdown-for-agents' ) . '</p>';
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

	public static function render_max_log_rows_field(): void {
		$max = (int) get_option( 'mdfa_max_log_rows', 50000 );
		printf(
			'<input type="number" name="mdfa_max_log_rows" value="%d" min="0" step="1000" class="small-text" />',
			$max
		);
		echo '<p class="description">' . esc_html__( 'Maksymalna liczba wpisów w logach. Najstarsze wpisy są automatycznie usuwane. 0 = bez limitu.', 'markdown-for-agents' ) . '</p>';
	}

	public static function render_signal_ai_train_field(): void {
		$val = get_option( 'mdfa_signal_ai_train', true );
		printf(
			'<input type="checkbox" name="mdfa_signal_ai_train" value="1" %s />',
			checked( $val, true, false )
		);
		echo '<p class="description">' . esc_html__( 'Zezwalaj na trenowanie modeli AI na tej treści.', 'markdown-for-agents' ) . '</p>';
	}

	public static function render_signal_search_field(): void {
		$val = get_option( 'mdfa_signal_search', true );
		printf(
			'<input type="checkbox" name="mdfa_signal_search" value="1" %s />',
			checked( $val, true, false )
		);
		echo '<p class="description">' . esc_html__( 'Zezwalaj na użycie w wynikach wyszukiwania.', 'markdown-for-agents' ) . '</p>';
	}

	public static function render_signal_ai_input_field(): void {
		$val = get_option( 'mdfa_signal_ai_input', true );
		printf(
			'<input type="checkbox" name="mdfa_signal_ai_input" value="1" %s />',
			checked( $val, true, false )
		);
		echo '<p class="description">' . esc_html__( 'Zezwalaj na użycie jako kontekst wejściowy AI (np. RAG, podsumowania).', 'markdown-for-agents' ) . '</p>';
	}

	public static function handle_clear_cache(): void {
		if ( ! isset( $_POST['mdfa_clear_cache'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'mdfa_clear_cache' );

		global $wpdb;
		$deleted = (int) $wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mdfa_md_%' OR option_name LIKE '_transient_timeout_mdfa_md_%' OR option_name LIKE '_transient_mdfa_archive_%' OR option_name LIKE '_transient_timeout_mdfa_archive_%'"
		);
		$wpdb->query(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_mdfa_cache_key'"
		);

		$count = (int) ( $deleted / 2 ); // each transient has value + timeout row
		add_settings_error(
			'mdfa_settings',
			'cache_cleared',
			sprintf(
				__( 'Cache wyczyszczony (%d wpisów).', 'markdown-for-agents' ),
				$count
			),
			'success'
		);
	}

	public static function render(): void {
		self::handle_clear_cache();
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'mdfa_settings' );
			do_settings_sections( 'markdown-for-agents' );
			submit_button( __( 'Zapisz ustawienia', 'markdown-for-agents' ) );
			?>
		</form>

		<hr>
		<h2><?php esc_html_e( 'Cache Markdown', 'markdown-for-agents' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Wyczyść wszystkie zapisane wersje Markdown. Przydatne po zmianie konfiguracji lub aktywacji nowych wtyczek (np. WooCommerce).', 'markdown-for-agents' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'mdfa_clear_cache' ); ?>
			<p>
				<button type="submit" name="mdfa_clear_cache" value="1" class="button button-secondary">
					<?php esc_html_e( 'Wyczyść cały cache', 'markdown-for-agents' ); ?>
				</button>
			</p>
		</form>
		<?php
	}
}
