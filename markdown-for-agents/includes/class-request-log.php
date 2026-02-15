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
			bot_name varchar(100) NOT NULL DEFAULT '',
			bot_type varchar(20) NOT NULL DEFAULT '',
			ip_address varchar(45) NOT NULL DEFAULT '',
			tokens int unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY created_at (created_at),
			KEY idx_taxonomy_term (taxonomy, term_id),
			KEY idx_bot_name (bot_name),
			KEY idx_bot_type (bot_type),
			KEY idx_request_method (request_method)
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

	private static function get_client_ip(): string {
		$ip_raw = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
		$ip     = filter_var( $ip_raw, FILTER_VALIDATE_IP ) ? $ip_raw : '';

		if ( $ip && get_option( 'mdfa_anonymize_ip', true ) ) {
			$ip = self::anonymize_ip( $ip );
		}

		return $ip;
	}

	public static function anonymize_ip( string $ip ): string {
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return preg_replace( '/\.\d+$/', '.0', $ip );
		}

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$packed = inet_pton( $ip );
			// Zero last 80 bits (10 bytes)
			for ( $i = 6; $i < 16; $i++ ) {
				$packed[ $i ] = "\0";
			}
			return inet_ntop( $packed );
		}

		return $ip;
	}

	public static function get_filtered( array $args = [] ): object {
		global $wpdb;

		$defaults = [
			'offset'          => 0,
			'limit'           => 20,
			'order_by'        => 'created_at',
			'order'           => 'DESC',
			'bot_filter'      => '',
			'bot_name_filter' => '',
			'method_filter'   => '',
			'search'          => '',
			'post_id'         => 0,
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

		if ( ! empty( $args['bot_filter'] ) ) {
			$where[] = 'l.bot_type = %s';
			$values[] = $args['bot_filter'];
		}

		if ( ! empty( $args['bot_name_filter'] ) ) {
			$where[] = 'l.bot_name = %s';
			$values[] = $args['bot_name_filter'];
		}

		if ( ! empty( $args['method_filter'] ) ) {
			$where[] = 'l.request_method = %s';
			$values[] = $args['method_filter'];
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

		return $wpdb->get_col( "SELECT DISTINCT bot_name FROM {$table} WHERE bot_name != '' ORDER BY bot_name ASC" );
	}

	public static function get_bot_stats(): array {
		global $wpdb;

		$table = self::get_table_name();
		$rows  = $wpdb->get_results( "SELECT bot_name, COUNT(*) as cnt FROM {$table} WHERE bot_name != '' GROUP BY bot_name ORDER BY cnt DESC" );

		$stats = [];
		foreach ( $rows as $row ) {
			$stats[ $row->bot_name ] = (int) $row->cnt;
		}

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

		$method     = get_query_var( 'format' ) === 'md' ? 'format_param' : 'accept_header';
		$user_agent = sanitize_text_field( mb_substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512 ) );
		$bot        = self::identify_bot( $user_agent );

		$data = [
			'post_id'        => $post_id,
			'request_method' => $method,
			'user_agent'     => $user_agent,
			'bot_name'       => $bot['name'],
			'bot_type'       => $bot['type'],
			'ip_address'     => self::get_client_ip(),
			'tokens'         => $tokens,
		];
		$format = [ '%d', '%s', '%s', '%s', '%s', '%s', '%d' ];

		if ( $term_id !== null ) {
			$data['term_id']  = $term_id;
			$data['taxonomy'] = $taxonomy;
			$format[]         = '%d';
			$format[]         = '%s';
		}

		$wpdb->insert( self::get_table_name(), $data, $format );

		self::maybe_trim_logs();
	}

	private static function maybe_trim_logs(): void {
		static $counter = null;

		if ( $counter === null ) {
			$counter = wp_rand( 0, 99 );
		} else {
			$counter++;
		}

		if ( $counter % 100 !== 0 ) {
			return;
		}

		$max_rows = (int) get_option( 'mdfa_max_log_rows', 50000 );
		if ( $max_rows <= 0 ) {
			return;
		}

		global $wpdb;
		$table = self::get_table_name();
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		if ( $count > $max_rows ) {
			$delete_count = $count - $max_rows;
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$table} ORDER BY created_at ASC LIMIT %d",
				$delete_count
			) );
		}
	}

	public static function maybe_migrate(): void {
		$db_version = (int) get_option( 'mdfa_db_version', 1 );
		if ( $db_version >= 3 ) {
			return;
		}

		global $wpdb;
		$table = self::get_table_name();

		// Check if table exists first.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );

		// Migration v1 → v2: add term_id, taxonomy columns.
		if ( ! in_array( 'term_id', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN term_id bigint(20) unsigned DEFAULT NULL AFTER post_id" );
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN taxonomy varchar(32) DEFAULT NULL AFTER term_id" );
			$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_taxonomy_term (taxonomy, term_id)" );
		}

		// Migration v2 → v3: add bot_name, bot_type columns + indexes.
		if ( ! in_array( 'bot_name', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN bot_name varchar(100) NOT NULL DEFAULT '' AFTER user_agent" );
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN bot_type varchar(20) NOT NULL DEFAULT '' AFTER bot_name" );
			$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_bot_name (bot_name)" );
			$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_bot_type (bot_type)" );
			$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_request_method (request_method)" );

			// Backfill bot_name/bot_type for existing rows.
			$rows = $wpdb->get_results( "SELECT id, user_agent FROM {$table} WHERE bot_name = ''" );
			foreach ( $rows as $row ) {
				$bot = self::identify_bot( $row->user_agent );
				$wpdb->update(
					$table,
					[ 'bot_name' => $bot['name'], 'bot_type' => $bot['type'] ],
					[ 'id' => $row->id ],
					[ '%s', '%s' ],
					[ '%d' ]
				);
			}
		}

		update_option( 'mdfa_db_version', 3 );
	}
}
