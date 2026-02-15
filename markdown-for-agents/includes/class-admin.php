<?php

class MDFA_Admin {

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_init', [ MDFA_Admin_Tab_Settings::class, 'register' ] );
		add_action( 'admin_init', [ MDFA_Admin_Tab_Logs::class, 'handle_clear' ] );
		add_action( 'admin_init', [ MDFA_Admin_Tab_Stats::class, 'handle_reset' ] );
		add_filter( 'set-screen-option', [ __CLASS__, 'save_screen_option' ], 10, 3 );
	}

	public static function save_screen_option( mixed $status, string $option, mixed $value ): mixed {
		if ( $option === 'mdfa_logs_per_page' ) {
			return (int) $value;
		}
		return $status;
	}

	public static function add_menu(): void {
		$hook = add_options_page(
			'Markdown for Agents',
			'Markdown for Agents',
			'manage_options',
			'markdown-for-agents',
			[ __CLASS__, 'render_page' ]
		);

		if ( $hook ) {
			add_action( "load-{$hook}", [ __CLASS__, 'load_page' ] );
		}
	}

	public static function load_page(): void {
		$tab = sanitize_text_field( $_GET['tab'] ?? 'stats' );

		if ( $tab === 'logs' ) {
			$screen = get_current_screen();

			add_filter( "manage_{$screen->id}_columns", function () {
				$table = new MDFA_Request_Log_Table();
				return $table->get_columns();
			} );

			add_screen_option( 'per_page', [
				'label'   => __( 'Logów na stronę', 'markdown-for-agents' ),
				'default' => 20,
				'option'  => 'mdfa_logs_per_page',
			] );
		}
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
				'logs'  => MDFA_Admin_Tab_Logs::render(),
				'stats' => MDFA_Admin_Tab_Stats::render(),
				default => MDFA_Admin_Tab_Settings::render(),
			};
			?>
		</div>
		<?php
	}
}
