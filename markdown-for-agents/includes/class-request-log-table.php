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
		$current    = $_GET['bot_filter'] ?? '';
		$base_url   = admin_url( 'options-general.php?page=markdown-for-agents&tab=logs' );
		$total      = MDFA_Request_Log::count_all();

		$views = [
			'all' => sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
				esc_url( $base_url ),
				empty( $current ) ? 'current' : '',
				__( 'Wszystkie', 'markdown-for-agents' ),
				number_format_i18n( $total )
			),
			'ai_bot' => sprintf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( add_query_arg( 'bot_filter', 'ai_bot', $base_url ) ),
				$current === 'ai_bot' ? 'current' : '',
				__( 'Boty AI', 'markdown-for-agents' )
			),
			'search_crawler' => sprintf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( add_query_arg( 'bot_filter', 'search_crawler', $base_url ) ),
				$current === 'search_crawler' ? 'current' : '',
				__( 'Wyszukiwarki', 'markdown-for-agents' )
			),
			'tool_crawler' => sprintf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( add_query_arg( 'bot_filter', 'tool_crawler', $base_url ) ),
				$current === 'tool_crawler' ? 'current' : '',
				__( 'Narzędzia', 'markdown-for-agents' )
			),
			'browser' => sprintf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( add_query_arg( 'bot_filter', 'browser', $base_url ) ),
				$current === 'browser' ? 'current' : '',
				__( 'Przeglądarki', 'markdown-for-agents' )
			),
			'unknown' => sprintf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( add_query_arg( 'bot_filter', 'unknown', $base_url ) ),
				$current === 'unknown' ? 'current' : '',
				__( 'Nieznane', 'markdown-for-agents' )
			),
		];

		return $views;
	}

	public function prepare_items(): void {
		$per_page = 20;
		$current_page = $this->get_pagenum();

		$args = [
			'offset'     => ( $current_page - 1 ) * $per_page,
			'limit'      => $per_page,
			'order_by'   => sanitize_text_field( $_GET['orderby'] ?? 'created_at' ),
			'order'      => sanitize_text_field( $_GET['order'] ?? 'DESC' ),
			'bot_filter' => sanitize_text_field( $_GET['bot_filter'] ?? '' ),
			'search'     => sanitize_text_field( $_GET['s'] ?? '' ),
		];

		$result = MDFA_Request_Log::get_filtered( $args );

		$this->items = $result->items;

		$this->_column_headers = [
			$this->get_columns(),
			[],
			$this->get_sortable_columns(),
		];

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
		return sprintf(
			'<span title="%s">%s</span>',
			esc_attr( $item->user_agent ),
			esc_html( $bot['name'] )
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
