<?php
/**
 * Auto-updater from GitHub repository.
 */

defined( 'ABSPATH' ) || exit;

class MDFA_Updater {

	private const REPO_URL  = 'https://github.com/romek-rozen/wordpress-markdown-for-agents';
	private const API_URL   = 'https://api.github.com/repos/romek-rozen/wordpress-markdown-for-agents';
	private const CACHE_KEY = 'mdfa_update_check';
	private const CACHE_TTL = 43200; // 12 hours

	private static string $plugin_file;
	private static string $plugin_basename;

	public static function init( string $plugin_file ): void {
		self::$plugin_file     = $plugin_file;
		self::$plugin_basename = plugin_basename( $plugin_file );

		add_filter( 'site_transient_update_plugins', [ __CLASS__, 'check_update' ] );
		add_filter( 'plugins_api', [ __CLASS__, 'plugin_info' ], 20, 3 );
		add_filter( 'upgrader_source_selection', [ __CLASS__, 'fix_source_dir' ], 10, 4 );
		add_action( 'upgrader_process_complete', [ __CLASS__, 'clear_cache' ], 10, 2 );
	}

	/**
	 * Fetch latest release info from GitHub API (cached).
	 */
	private static function get_remote_release(): ?object {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached ?: null;
		}

		$response = wp_remote_get( self::API_URL . '/releases/latest', [
			'timeout' => 10,
			'headers' => [
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
			],
		] );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( self::CACHE_KEY, '', self::CACHE_TTL );
			return null;
		}

		$release = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! $release || empty( $release->tag_name ) ) {
			set_transient( self::CACHE_KEY, '', self::CACHE_TTL );
			return null;
		}

		set_transient( self::CACHE_KEY, $release, self::CACHE_TTL );
		return $release;
	}

	/**
	 * Parse version from tag name (strip leading 'v').
	 */
	private static function parse_version( string $tag ): string {
		return ltrim( $tag, 'vV' );
	}

	/**
	 * Get download URL for a release tag.
	 */
	private static function get_download_url( object $release ): string {
		// Prefer attached ZIP asset if available.
		if ( ! empty( $release->assets ) ) {
			foreach ( $release->assets as $asset ) {
				if ( str_ends_with( $asset->browser_download_url, '.zip' ) ) {
					return $asset->browser_download_url;
				}
			}
		}
		// Fallback to GitHub archive.
		return self::REPO_URL . '/archive/refs/tags/' . $release->tag_name . '.zip';
	}

	/**
	 * Push update notification to WordPress.
	 */
	public static function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = self::get_remote_release();
		if ( ! $release ) {
			return $transient;
		}

		$remote_version = self::parse_version( $release->tag_name );

		if ( version_compare( MDFA_VERSION, $remote_version, '<' ) ) {
			$item              = new stdClass();
			$item->slug        = 'markdown-for-agents';
			$item->plugin      = self::$plugin_basename;
			$item->new_version = $remote_version;
			$item->url         = self::REPO_URL;
			$item->package     = self::get_download_url( $release );
			$item->icons       = [];
			$item->banners     = [];
			$item->tested      = '';
			$item->requires    = '6.0';
			$item->requires_php = '8.0';

			$transient->response[ self::$plugin_basename ] = $item;
		}

		return $transient;
	}

	/**
	 * Provide plugin info for the "View details" modal.
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( ! isset( $args->slug ) || 'markdown-for-agents' !== $args->slug ) {
			return $result;
		}

		$release = self::get_remote_release();
		if ( ! $release ) {
			return $result;
		}

		$remote_version = self::parse_version( $release->tag_name );

		$info                = new stdClass();
		$info->name          = 'Markdown for Agents';
		$info->slug          = 'markdown-for-agents';
		$info->version       = $remote_version;
		$info->author        = '<a href="https://rozenberger.com/">Romek</a>';
		$info->homepage      = self::REPO_URL;
		$info->download_link = self::get_download_url( $release );
		$info->requires      = '6.0';
		$info->requires_php  = '8.0';
		$info->tested        = '';
		$info->sections      = [
			'description' => 'Serves AI agents with Markdown instead of HTML, reducing token usage ~80%. Implements Cloudflare\'s Markdown for Agents specification.',
			'changelog'   => nl2br( esc_html( $release->body ?? '' ) ),
		];
		$info->banners       = [];
		$info->last_updated  = $release->published_at ?? '';

		return $info;
	}

	/**
	 * Fix extracted folder name after download.
	 *
	 * GitHub archive ZIP extracts to 'wordpress-markdown-for-agents-{tag}/' which contains
	 * the plugin in 'markdown-for-agents/' subdirectory. We need to point WordPress
	 * to the correct subdirectory, or rename if using a release asset ZIP.
	 */
	public static function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra ) {
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== self::$plugin_basename ) {
			return $source;
		}

		global $wp_filesystem;

		// Check if this is a GitHub archive (contains the repo name as root folder).
		$plugin_subdir = trailingslashit( $source ) . 'markdown-for-agents/';
		if ( $wp_filesystem->is_dir( $plugin_subdir ) ) {
			// Archive structure: wordpress-markdown-for-agents-{tag}/markdown-for-agents/
			// Move the plugin subdirectory up.
			$new_source = trailingslashit( $remote_source ) . 'markdown-for-agents/';
			if ( $wp_filesystem->move( $plugin_subdir, $new_source, true ) ) {
				// Clean up the original extracted folder.
				$wp_filesystem->delete( $source, true );
				return $new_source;
			}
		}

		// If source doesn't end with 'markdown-for-agents/', rename it.
		$desired = trailingslashit( $remote_source ) . 'markdown-for-agents/';
		if ( trailingslashit( $source ) !== $desired ) {
			if ( $wp_filesystem->move( $source, $desired, true ) ) {
				return $desired;
			}
		}

		return $source;
	}

	/**
	 * Clear update cache after plugin update.
	 */
	public static function clear_cache( $upgrader, $options ): void {
		if ( 'update' === ( $options['action'] ?? '' )
			&& 'plugin' === ( $options['type'] ?? '' )
			&& ! empty( $options['plugins'] )
			&& in_array( self::$plugin_basename, $options['plugins'], true )
		) {
			delete_transient( self::CACHE_KEY );
		}
	}
}
