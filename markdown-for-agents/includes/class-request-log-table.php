<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class MDFA_Request_Log_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( [
			'singular' => 'log',
			'plural'   => 'logs',
			'ajax'     => false,
		] );
	}

	public function get_columns(): array {
		return [
			'created_at'     => __( 'Data', 'markdown-for-agents' ),
			'post_title'     => __( 'Post', 'markdown-for-agents' ),
			'bot_name'       => __( 'Bot / Klient', 'markdown-for-agents' ),
			'user_agent'     => __( 'User-Agent', 'markdown-for-agents' ),
			'request_method' => __( 'Metoda', 'markdown-for-agents' ),
			'tokens'         => __( 'Tokeny', 'markdown-for-agents' ),
			'ip_address'     => __( 'IP', 'markdown-for-agents' ),
		];
	}


	public function get_sortable_columns(): array {
		return [
			'created_at' => [ 'created_at', true ],
			'tokens'     => [ 'tokens', false ],
		];
	}

	protected function get_views(): array {
		$current  = sanitize_text_field( $_GET['bot_filter'] ?? '' );
		$base_url = admin_url( 'options-general.php?page=markdown-for-agents&tab=logs' );

		$filters = [
			''               => __( 'Wszystkie', 'markdown-for-agents' ),
			'ai_bot'         => __( 'AI', 'markdown-for-agents' ),
			'search_crawler' => __( 'Wyszukiwarki', 'markdown-for-agents' ),
			'tool_crawler'   => __( 'Narzędzia', 'markdown-for-agents' ),
			'browser'        => __( 'Przeglądarki', 'markdown-for-agents' ),
			'unknown'        => __( 'Inne', 'markdown-for-agents' ),
		];

		$views = [];
		foreach ( $filters as $key => $label ) {
			$url   = $key === '' ? $base_url : add_query_arg( 'bot_filter', $key, $base_url );
			$class = $current === $key ? 'current' : '';
			$views[ $key ?: 'all' ] = sprintf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( $url ),
				$class,
				esc_html( $label )
			);
		}

		return $views;
	}

	protected function extra_tablenav( $which ): void {
		if ( $which !== 'top' ) {
			return;
		}

		$bot_name_filter = sanitize_text_field( $_GET['bot_name_filter'] ?? '' );
		$method_filter   = sanitize_text_field( $_GET['method_filter'] ?? '' );
		$bot_names       = MDFA_Request_Log::get_distinct_bot_names();

		?>
		<div class="alignleft actions">
			<select name="bot_name_filter">
				<option value=""><?php esc_html_e( 'Wszystkie boty', 'markdown-for-agents' ); ?></option>
				<?php foreach ( $bot_names as $name ) : ?>
					<option value="<?php echo esc_attr( $name ); ?>" <?php selected( $bot_name_filter, $name ); ?>>
						<?php echo esc_html( $name ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select name="method_filter">
				<option value=""><?php esc_html_e( 'Wszystkie metody', 'markdown-for-agents' ); ?></option>
				<option value="accept_header" <?php selected( $method_filter, 'accept_header' ); ?>>Accept header</option>
				<option value="format_param" <?php selected( $method_filter, 'format_param' ); ?>>?format=md</option>
			</select>

			<?php submit_button( __( 'Filtruj', 'markdown-for-agents' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	public function prepare_items(): void {
		$screen   = get_current_screen();
		$per_page = $screen
			? (int) get_user_meta( get_current_user_id(), 'mdfa_logs_per_page', true ) ?: 20
			: 20;

		$current_page = $this->get_pagenum();

		$args = [
			'offset'          => ( $current_page - 1 ) * $per_page,
			'limit'           => $per_page,
			'order_by'        => sanitize_text_field( $_GET['orderby'] ?? 'created_at' ),
			'order'           => sanitize_text_field( $_GET['order'] ?? 'DESC' ),
			'bot_filter'      => sanitize_text_field( $_GET['bot_filter'] ?? '' ),
			'bot_name_filter' => sanitize_text_field( $_GET['bot_name_filter'] ?? '' ),
			'method_filter'   => sanitize_text_field( $_GET['method_filter'] ?? '' ),
			'search'          => sanitize_text_field( $_GET['s'] ?? '' ),
		];

		$result = MDFA_Request_Log::get_filtered( $args );

		$this->items = $result->items;

		$this->set_pagination_args( [
			'total_items' => $result->total,
			'per_page'    => $per_page,
			'total_pages' => ceil( $result->total / $per_page ),
		] );
	}

	public function column_default( $item, $column_name ): string {
		return esc_html( $item->$column_name ?? '' );
	}

	public function column_created_at( $item ): string {
		return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item->created_at ) ) );
	}

	public function column_post_title( $item ): string {
		if ( $item->post_title ) {
			return sprintf(
				'<a href="%s">%s</a>',
				esc_url( get_edit_post_link( $item->post_id ) ),
				esc_html( $item->post_title )
			);
		}
		return '#' . esc_html( $item->post_id );
	}

	public function column_bot_name( $item ): string {
		$bot = MDFA_Request_Log::identify_bot( $item->user_agent );
		return esc_html( $bot['name'] );
	}

	public function column_user_agent( $item ): string {
		return sprintf(
			'<code style="word-break: break-all; font-size: 12px;">%s</code>',
			esc_html( $item->user_agent )
		);
	}

	public function column_request_method( $item ): string {
		return $item->request_method === 'format_param'
			? '<code>?format=md</code>'
			: '<code>Accept</code>';
	}

	public function column_tokens( $item ): string {
		return esc_html( number_format_i18n( $item->tokens ) );
	}

	public function column_ip_address( $item ): string {
		return esc_html( $item->ip_address );
	}

	public function no_items(): void {
		esc_html_e( 'Brak zapytań.', 'markdown-for-agents' );
	}
}
