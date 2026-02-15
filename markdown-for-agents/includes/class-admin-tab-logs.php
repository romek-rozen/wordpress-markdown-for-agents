<?php

class MDFA_Admin_Tab_Logs {

	public static function handle_clear(): void {
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

	public static function render(): void {
		$table = new MDFA_Request_Log_Table();
		$table->prepare_items();

		?>
		<form method="get" style="margin-top: 20px;">
			<input type="hidden" name="page" value="markdown-for-agents" />
			<input type="hidden" name="tab" value="logs" />
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
}
