<?php

class MDFA_Admin {

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_clear_logs' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_reset_stats' ] );
	}

	public static function add_menu(): void {
		add_options_page(
			'Markdown for Agents',
			'Markdown for Agents',
			'manage_options',
			'markdown-for-agents',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function register_settings(): void {
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
	}

	public static function sanitize_post_types( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return [ 'post', 'page' ];
		}
		$public_types = get_post_types( [ 'public' => true ] );
		return array_values( array_intersect( $value, $public_types ) );
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

	public static function handle_clear_logs(): void {
		if ( ! isset( $_POST['mdfa_clear_logs'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'mdfa_clear_logs' );

		MDFA_Request_Log::clear_all();

		add_settings_error( 'mdfa_settings', 'logs_cleared', __( 'Logi zostały wyczyszczone.', 'markdown-for-agents' ), 'success' );
	}

	public static function handle_reset_stats(): void {
		if ( ! isset( $_POST['mdfa_reset_stats'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'mdfa_reset_stats' );

		MDFA_Stats_Tracker::reset_stats();

		add_settings_error( 'mdfa_settings', 'stats_reset', __( 'Statystyki zostały zresetowane.', 'markdown-for-agents' ), 'success' );
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = sanitize_text_field( $_GET['tab'] ?? 'stats' );

		?>
		<div class="wrap">
			<h1>Markdown for Agents</h1>

			<?php settings_errors( 'mdfa_settings' ); ?>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=markdown-for-agents&tab=stats' ) ); ?>"
				   class="nav-tab <?php echo $active_tab === 'stats' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Statystyki', 'markdown-for-agents' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=markdown-for-agents&tab=logs' ) ); ?>"
				   class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Logi zapytań', 'markdown-for-agents' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=markdown-for-agents&tab=settings' ) ); ?>"
				   class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Ustawienia', 'markdown-for-agents' ); ?>
				</a>
			</h2>

			<?php
			match ( $active_tab ) {
				'logs'  => self::render_logs_tab(),
				'stats' => self::render_stats_tab(),
				default => self::render_settings_tab(),
			};
			?>
		</div>
		<?php
	}

	private static function render_settings_tab(): void {
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

	private static function render_logs_tab(): void {
		$table = new MDFA_Request_Log_Table();
		$table->prepare_items();

		?>
		<form method="get" style="margin-top: 20px;">
			<input type="hidden" name="page" value="markdown-for-agents" />
			<input type="hidden" name="tab" value="logs" />
			<?php if ( ! empty( $_GET['bot_filter'] ) ) : ?>
				<input type="hidden" name="bot_filter" value="<?php echo esc_attr( $_GET['bot_filter'] ); ?>" />
			<?php endif; ?>
			<?php $table->search_box( __( 'Szukaj posta', 'markdown-for-agents' ), 'post_search' ); ?>
			<?php $table->views(); ?>
			<?php $table->display(); ?>
		</form>

		<?php if ( MDFA_Request_Log::count_all() > 0 ) : ?>
			<form method="post" style="margin-top: 20px;">
				<?php wp_nonce_field( 'mdfa_clear_logs' ); ?>
				<input type="submit" name="mdfa_clear_logs" class="button"
				       value="<?php esc_attr_e( 'Wyczyść logi', 'markdown-for-agents' ); ?>"
				       onclick="return confirm('<?php echo esc_js( __( 'Na pewno wyczyścić wszystkie logi?', 'markdown-for-agents' ) ); ?>');" />
			</form>
		<?php endif; ?>
		<?php
	}

	private static function render_stats_tab(): void {
		$stats       = MDFA_Stats_Tracker::get_stats();
		$bot_stats   = MDFA_Request_Log::get_bot_stats();
		$token_stats = MDFA_Request_Log::get_token_stats();
		$top_posts   = MDFA_Request_Log::get_top_posts( 10 );

		$html_avg = $stats['html_requests'] > 0
			? round( $stats['html_tokens_estimated'] / $stats['html_requests'] )
			: 0;
		$md_avg = $stats['md_requests'] > 0
			? round( $stats['md_tokens'] / $stats['md_requests'] )
			: 0;

		$token_savings = ( $html_avg > 0 && $md_avg > 0 )
			? round( ( 1 - $md_avg / $html_avg ) * 100, 1 )
			: 0;

		?>
		<style>
			.mdfa-stats { margin-top: 20px; }
			.mdfa-stats .postbox { margin-bottom: 20px; }
			.mdfa-stats .inside { padding: 12px; }
			.mdfa-bar { display: flex; align-items: center; margin-bottom: 8px; }
			.mdfa-bar-label { width: 180px; font-size: 13px; }
			.mdfa-bar-track { flex: 1; height: 24px; background: #f0f0f1; border-radius: 3px; overflow: hidden; margin: 0 10px; }
			.mdfa-bar-fill { height: 100%; background: #2271b1; border-radius: 3px; min-width: 2px; }
			.mdfa-bar-value { width: 50px; text-align: right; font-weight: 600; font-size: 13px; }
			.mdfa-savings { color: #00a32a; font-weight: 700; }
		</style>

		<div class="mdfa-stats">

			<div class="postbox">
				<div class="postbox-header">
					<h2 class="hndle"><?php esc_html_e( 'HTML vs Markdown', 'markdown-for-agents' ); ?></h2>
				</div>
				<div class="inside">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Metryka', 'markdown-for-agents' ); ?></th>
								<th><?php esc_html_e( 'HTML', 'markdown-for-agents' ); ?></th>
								<th><?php esc_html_e( 'Markdown', 'markdown-for-agents' ); ?></th>
								<th><?php esc_html_e( 'Oszczędność', 'markdown-for-agents' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><strong><?php esc_html_e( 'Zapytania', 'markdown-for-agents' ); ?></strong></td>
								<td><?php echo esc_html( number_format_i18n( $stats['html_requests'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $stats['md_requests'] ) ); ?></td>
								<td>&mdash;</td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Tokeny (suma)', 'markdown-for-agents' ); ?></strong></td>
								<td><?php echo esc_html( number_format_i18n( $stats['html_tokens_estimated'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $stats['md_tokens'] ) ); ?></td>
								<td>
									<?php if ( $token_savings != 0 ) : ?>
										<span style="color: <?php echo $token_savings > 0 ? '#00a32a' : '#d63638'; ?>; font-weight: 700;"><?php echo esc_html( $token_savings ); ?>%</span>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Śr. tokenów/zapytanie', 'markdown-for-agents' ); ?></strong></td>
								<td><?php echo esc_html( number_format_i18n( $html_avg ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $md_avg ) ); ?></td>
								<td>
									<?php if ( $html_avg > 0 && $md_avg > 0 ) :
									$avg_savings = round( ( 1 - $md_avg / $html_avg ) * 100, 1 );
								?>
										<span style="color: <?php echo $avg_savings >= 0 ? '#00a32a' : '#d63638'; ?>; font-weight: 700;"><?php echo esc_html( $avg_savings ); ?>%</span>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</td>
							</tr>
						</tbody>
					</table>
					<?php if ( ! empty( $stats['started_at'] ) ) : ?>
						<p class="description" style="margin-top: 10px;">
							<?php
							printf(
								/* translators: %s: start date */
								esc_html__( 'Statystyki zbierane od: %s', 'markdown-for-agents' ),
								esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $stats['started_at'] ) ) )
							);
							?>
						</p>
					<?php endif; ?>

					<form method="post" style="margin-top: 10px;">
						<?php wp_nonce_field( 'mdfa_reset_stats' ); ?>
						<input type="submit" name="mdfa_reset_stats" class="button"
						       value="<?php esc_attr_e( 'Resetuj statystyki', 'markdown-for-agents' ); ?>"
						       onclick="return confirm('<?php echo esc_js( __( 'Na pewno zresetować statystyki?', 'markdown-for-agents' ) ); ?>');" />
					</form>
				</div>
			</div>

			<?php if ( ! empty( $bot_stats ) ) : ?>
			<div class="postbox">
				<div class="postbox-header">
					<h2 class="hndle"><?php esc_html_e( 'Zapytania MD wg klienta', 'markdown-for-agents' ); ?></h2>
				</div>
				<div class="inside">
					<?php
					$max_count = max( $bot_stats );
					foreach ( $bot_stats as $name => $count ) :
						$pct = $max_count > 0 ? round( $count / $max_count * 100 ) : 0;
					?>
						<div class="mdfa-bar">
							<span class="mdfa-bar-label"><?php echo esc_html( $name ); ?></span>
							<span class="mdfa-bar-track"><span class="mdfa-bar-fill" style="width: <?php echo esc_attr( $pct ); ?>%;"></span></span>
							<span class="mdfa-bar-value"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $top_posts ) ) : ?>
			<div class="postbox">
				<div class="postbox-header">
					<h2 class="hndle"><?php esc_html_e( 'Najpopularniejsze posty (MD)', 'markdown-for-agents' ); ?></h2>
				</div>
				<div class="inside">
					<table class="widefat striped">
						<thead>
							<tr>
								<th>#</th>
								<th><?php esc_html_e( 'Post', 'markdown-for-agents' ); ?></th>
								<th><?php esc_html_e( 'Zapytania', 'markdown-for-agents' ); ?></th>
								<th><?php esc_html_e( 'Tokeny', 'markdown-for-agents' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $top_posts as $i => $post ) : ?>
								<tr>
									<td><?php echo esc_html( $i + 1 ); ?></td>
									<td>
										<?php if ( $post->post_title ) : ?>
											<a href="<?php echo esc_url( get_edit_post_link( $post->post_id ) ); ?>">
												<?php echo esc_html( $post->post_title ); ?>
											</a>
										<?php else : ?>
											#<?php echo esc_html( $post->post_id ); ?>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( number_format_i18n( $post->request_count ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $post->total_tokens ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
			<?php endif; ?>

			<div class="postbox">
				<div class="postbox-header">
					<h2 class="hndle"><?php esc_html_e( 'Statystyki tokenów (MD)', 'markdown-for-agents' ); ?></h2>
				</div>
				<div class="inside">
					<ul>
						<li>
							<strong><?php esc_html_e( 'Suma:', 'markdown-for-agents' ); ?></strong>
							<?php echo esc_html( number_format_i18n( $token_stats['total_tokens'] ) ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Średnia:', 'markdown-for-agents' ); ?></strong>
							<?php echo esc_html( number_format_i18n( $token_stats['avg_tokens'] ) ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Maks.:', 'markdown-for-agents' ); ?></strong>
							<?php echo esc_html( number_format_i18n( $token_stats['max_tokens'] ) ); ?>
						</li>
					</ul>
				</div>
			</div>

		</div>
		<?php
	}
}
