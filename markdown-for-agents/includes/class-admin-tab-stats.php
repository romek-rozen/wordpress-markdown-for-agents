<?php

class MDFA_Admin_Tab_Stats {

	public static function handle_reset(): void {
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

	public static function render(): void {
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
			.mdfa-bar-fill { display: block; height: 100%; background: #2271b1; border-radius: 3px; min-width: 2px; }
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
