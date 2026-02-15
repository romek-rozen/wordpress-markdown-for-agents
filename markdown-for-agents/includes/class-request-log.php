<?php

class MDFA_Request_Log {

	private static string $table_name_suffix = 'mdfa_request_log';

	private static array $bot_patterns = [
		'GPTBot'              => '/GPTBot/i',
		'OAI-SearchBot'       => '/OAI-SearchBot/i',
		'ChatGPT-User'        => '/ChatGPT-User/i',
		'ClaudeBot'           => '/ClaudeBot/i',
		'Claude-User'         => '/Claude-User/i',
		'Claude-SearchBot'    => '/Claude-SearchBot/i',
		'Googlebot'           => '/Googlebot/i',
		'Google-Extended'     => '/Google-Extended/i',
		'GoogleOther'         => '/GoogleOther/i',
		'Bingbot'             => '/bingbot/i',
		'CCBot'               => '/CCBot/i',
		'Bytespider'          => '/Bytespider/i',
		'PetalBot'            => '/PetalBot/i',
		'Applebot'            => '/Applebot/i',
		'facebookexternalhit' => '/facebookexternalhit/i',
		'Twitterbot'          => '/Twitterbot/i',
		'LinkedInBot'         => '/LinkedInBot/i',
		'Slurp'               => '/Slurp/i',
		'YandexBot'           => '/YandexBot/i',
		'DuckDuckBot'         => '/DuckDuckBot/i',
		'Amazonbot'           => '/Amazonbot/i',
		'PerplexityBot'       => '/PerplexityBot/i',
		'Perplexity-User'     => '/Perplexity-User/i',
	];

	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::$table_name_suffix;
	}

	public static function create_table(): void {
		global $wpdb;

		$table   = self::get_table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			request_method varchar(20) NOT NULL DEFAULT 'accept_header',
			user_agent varchar(512) NOT NULL DEFAULT '',
			ip_address varchar(45) NOT NULL DEFAULT '',
			tokens int unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function identify_bot( string $user_agent ): array {
		foreach ( self::$bot_patterns as $name => $pattern ) {
			if ( preg_match( $pattern, $user_agent ) ) {
				return [
					'type' => 'bot',
					'name' => $name,
				];
			}
		}

		if ( preg_match( '/(bot|crawler|spider|scraper)/i', $user_agent ) ) {
			return [
				'type' => 'bot',
				'name' => __( 'Inny bot', 'markdown-for-agents' ),
			];
		}

		if ( preg_match( '/(Mozilla|Chrome|Safari|Firefox|Edge|Opera)/i', $user_agent ) ) {
			return [
				'type' => 'browser',
				'name' => __( 'Przeglądarka', 'markdown-for-agents' ),
			];
		}

		return [
			'type' => 'unknown',
			'name' => __( 'Nieznany', 'markdown-for-agents' ),
		];
	}

	public static function get_filtered( array $args = [] ): object {
		global $wpdb;

		$defaults = [
			'offset'     => 0,
			'limit'      => 20,
			'order_by'   => 'created_at',
			'order'      => 'DESC',
			'bot_filter' => '',
			'search'     => '',
		];

		$args  = wp_parse_args( $args, $defaults );
		$table = self::get_table_name();
		$where = [ '1=1' ];
		$values = [];

		if ( ! empty( $args['search'] ) ) {
			$where[] = 'p.post_title LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		$where_sql = implode( ' AND ', $where );

		$order_by = in_array( $args['order_by'], [ 'created_at', 'tokens' ], true )
			? 'l.' . $args['order_by']
			: 'l.created_at';
		$order = $args['order'] === 'ASC' ? 'ASC' : 'DESC';

		$count_sql = "SELECT COUNT(*) FROM {$table} l LEFT JOIN {$wpdb->posts} p ON l.post_id = p.ID WHERE {$where_sql}";
		$items_sql = "SELECT l.*, p.post_title FROM {$table} l LEFT JOIN {$wpdb->posts} p ON l.post_id = p.ID WHERE {$where_sql} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d";

		$items_values = array_merge( $values, [ $args['limit'], $args['offset'] ] );

		$total = empty( $values )
			? (int) $wpdb->get_var( $count_sql )
			: (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) );

		$items = empty( $items_values ) || ( count( $items_values ) === 2 && empty( $values ) )
			? $wpdb->get_results( $wpdb->prepare( $items_sql, $args['limit'], $args['offset'] ) )
			: $wpdb->get_results( $wpdb->prepare( $items_sql, $items_values ) );

		if ( ! empty( $args['bot_filter'] ) ) {
			$items = array_values( array_filter( $items, function ( $item ) use ( $args ) {
				$bot = self::identify_bot( $item->user_agent );
				return $bot['type'] === $args['bot_filter'];
			} ) );
		}

		return (object) [
			'items' => $items,
			'total' => $total,
		];
	}

	public static function get_bot_stats(): array {
		global $wpdb;

		$table = self::get_table_name();
		$rows  = $wpdb->get_results( "SELECT user_agent, COUNT(*) as cnt FROM {$table} GROUP BY user_agent" );

		$stats = [];
		foreach ( $rows as $row ) {
			$bot  = self::identify_bot( $row->user_agent );
			$name = $bot['name'];
			$stats[ $name ] = ( $stats[ $name ] ?? 0 ) + (int) $row->cnt;
		}

		arsort( $stats );
		return $stats;
	}

	public static function get_token_stats(): array {
		global $wpdb;

		$table = self::get_table_name();
		$row   = $wpdb->get_row( "SELECT SUM(tokens) as total_tokens, AVG(tokens) as avg_tokens, MAX(tokens) as max_tokens FROM {$table}" );

		return [
			'total_tokens' => (int) ( $row->total_tokens ?? 0 ),
			'avg_tokens'   => (int) round( (float) ( $row->avg_tokens ?? 0 ) ),
			'max_tokens'   => (int) ( $row->max_tokens ?? 0 ),
		];
	}

	public static function get_top_posts( int $limit = 10 ): array {
		global $wpdb;

		$table = self::get_table_name();

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT l.post_id, p.post_title, COUNT(*) as request_count, SUM(l.tokens) as total_tokens
			FROM {$table} l
			LEFT JOIN {$wpdb->posts} p ON l.post_id = p.ID
			GROUP BY l.post_id, p.post_title
			ORDER BY request_count DESC
			LIMIT %d",
			$limit
		) );
	}

	public static function get_recent( int $limit = 50 ): array {
		global $wpdb;

		$table = self::get_table_name();

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT l.*, p.post_title
			FROM {$table} l
			LEFT JOIN {$wpdb->posts} p ON l.post_id = p.ID
			ORDER BY l.created_at DESC
			LIMIT %d",
			$limit
		) );
	}

	public static function clear_all(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE " . self::get_table_name() );
	}

	public static function count_all(): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::get_table_name() );
	}

	public static function log( int $post_id, int $tokens ): void {
		global $wpdb;

		$method = get_query_var( 'format' ) === 'md' ? 'format_param' : 'accept_header';

		$wpdb->insert(
			self::get_table_name(),
			[
				'post_id'        => $post_id,
				'request_method' => $method,
				'user_agent'     => mb_substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512 ),
				'ip_address'     => $_SERVER['REMOTE_ADDR'] ?? '',
				'tokens'         => $tokens,
			],
			[ '%d', '%s', '%s', '%s', '%d' ]
		);
	}
}
