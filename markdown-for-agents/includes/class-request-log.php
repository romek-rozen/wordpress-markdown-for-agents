<?php

class MDFA_Request_Log {

	private static string $table_name_suffix = 'mdfa_request_log';

	public const DEFAULT_AI_BOTS = [
		'GPTBot',
		'OAI-SearchBot',
		'ChatGPT-User',
		'ClaudeBot',
		'Claude-User',
		'Claude-SearchBot',
		'PerplexityBot',
		'Perplexity-User',
		'Google-Extended',
		'CCBot',
		'Bytespider',
	];

	public const DEFAULT_SEARCH_CRAWLERS = [
		'Googlebot',
		'GoogleOther',
		'bingbot',
		'Applebot',
		'YandexBot',
		'DuckDuckBot',
		'Slurp',
	];

	public const DEFAULT_TOOL_CRAWLERS = [
		'AhrefsBot',
		'SemrushBot',
		'PetalBot',
		'Amazonbot',
		'facebookexternalhit',
		'Twitterbot',
		'LinkedInBot',
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
			term_id bigint(20) unsigned DEFAULT NULL,
			taxonomy varchar(32) DEFAULT NULL,
			request_method varchar(20) NOT NULL DEFAULT 'accept_header',
			user_agent varchar(512) NOT NULL DEFAULT '',
			ip_address varchar(45) NOT NULL DEFAULT '',
			tokens int unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY created_at (created_at),
			KEY idx_taxonomy_term (taxonomy, term_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function identify_bot( string $user_agent ): array {
		$ai_bots         = (array) get_option( 'mdfa_ai_bots', self::DEFAULT_AI_BOTS );
		$search_crawlers = (array) get_option( 'mdfa_search_crawlers', self::DEFAULT_SEARCH_CRAWLERS );
		$tool_crawlers   = (array) get_option( 'mdfa_tool_crawlers', self::DEFAULT_TOOL_CRAWLERS );

		$ua_lower = mb_strtolower( $user_agent );

		foreach ( $ai_bots as $name ) {
			if ( $name !== '' && stripos( $ua_lower, mb_strtolower( $name ) ) !== false ) {
				return [
					'type' => 'ai_bot',
					'name' => $name,
				];
			}
		}

		foreach ( $search_crawlers as $name ) {
			if ( $name !== '' && stripos( $ua_lower, mb_strtolower( $name ) ) !== false ) {
				return [
					'type' => 'search_crawler',
					'name' => $name,
				];
			}
		}

		foreach ( $tool_crawlers as $name ) {
			if ( $name !== '' && stripos( $ua_lower, mb_strtolower( $name ) ) !== false ) {
				return [
					'type' => 'tool_crawler',
					'name' => $name,
				];
			}
		}

		if ( preg_match( '/(bot|crawler|spider|scraper)/i', $user_agent ) ) {
			return [
				'type' => 'crawler',
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
			'post_id'    => 0,
		];

		$args  = wp_parse_args( $args, $defaults );
		$table = self::get_table_name();
		$where = [ '1=1' ];
		$values = [];

		if ( ! empty( $args['search'] ) ) {
			$where[] = 'p.post_title LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		if ( ! empty( $args['post_id'] ) ) {
			$where[] = 'l.post_id = %d';
			$values[] = (int) $args['post_id'];
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

		if ( ! empty( $args['bot_filter'] ) || ! empty( $args['bot_name_filter'] ) || ! empty( $args['method_filter'] ) ) {
			$items = array_values( array_filter( $items, function ( $item ) use ( $args ) {
				$bot = self::identify_bot( $item->user_agent );

				if ( ! empty( $args['bot_filter'] ) && $bot['type'] !== $args['bot_filter'] ) {
					return false;
				}
				if ( ! empty( $args['bot_name_filter'] ) && $bot['name'] !== $args['bot_name_filter'] ) {
					return false;
				}
				if ( ! empty( $args['method_filter'] ) && $item->request_method !== $args['method_filter'] ) {
					return false;
				}

				return true;
			} ) );
		}

		return (object) [
			'items' => $items,
			'total' => $total,
		];
	}

	public static function get_distinct_posts(): array {
		global $wpdb;

		$table = self::get_table_name();

		return $wpdb->get_results(
			"SELECT l.post_id, p.post_title, COUNT(*) as request_count
			FROM {$table} l
			LEFT JOIN {$wpdb->posts} p ON l.post_id = p.ID
			WHERE l.post_id > 0
			GROUP BY l.post_id, p.post_title
			ORDER BY request_count DESC"
		);
	}

	public static function get_distinct_bot_names(): array {
		global $wpdb;

		$table = self::get_table_name();
		$rows  = $wpdb->get_col( "SELECT DISTINCT user_agent FROM {$table}" );

		$names = [];
		foreach ( $rows as $ua ) {
			$bot = self::identify_bot( $ua );
			$names[ $bot['name'] ] = true;
		}

		ksort( $names );
		return array_keys( $names );
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

	public static function log( int $post_id, int $tokens, ?int $term_id = null, ?string $taxonomy = null ): void {
		global $wpdb;

		$method = get_query_var( 'format' ) === 'md' ? 'format_param' : 'accept_header';

		$data = [
			'post_id'        => $post_id,
			'request_method' => $method,
			'user_agent'     => mb_substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512 ),
			'ip_address'     => $_SERVER['REMOTE_ADDR'] ?? '',
			'tokens'         => $tokens,
		];
		$format = [ '%d', '%s', '%s', '%s', '%d' ];

		if ( $term_id !== null ) {
			$data['term_id']  = $term_id;
			$data['taxonomy'] = $taxonomy;
			$format[]         = '%d';
			$format[]         = '%s';
		}

		$wpdb->insert( self::get_table_name(), $data, $format );
	}

	public static function maybe_migrate(): void {
		$db_version = (int) get_option( 'mdfa_db_version', 1 );
		if ( $db_version >= 2 ) {
			return;
		}

		global $wpdb;
		$table = self::get_table_name();

		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
		if ( ! in_array( 'term_id', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN term_id bigint(20) unsigned DEFAULT NULL AFTER post_id" );
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN taxonomy varchar(32) DEFAULT NULL AFTER term_id" );
			$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_taxonomy_term (taxonomy, term_id)" );
		}

		update_option( 'mdfa_db_version', 2 );
	}
}
