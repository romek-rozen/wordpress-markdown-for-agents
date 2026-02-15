<?php
/**
 * Plugin Name: Markdown for Agents
 * Description: Serves AI agents with Markdown instead of HTML, reducing token usage ~80%. Implements Cloudflare's Markdown for Agents specification.
 * Version: 1.0.3
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Romek
 * Author URI: https://rozenberger.com/
 * Plugin URI: https://github.com/romek-rozen/wordpress-markdown-for-agents
 * License: GPL v2 or later
 * Text Domain: markdown-for-agents
 * Update URI: https://repo.nimblio.work/roman/wordpress-markdown-for-agents
 */

defined( 'ABSPATH' ) || exit;

define( 'MDFA_VERSION', '1.0.3' );
define( 'MDFA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once MDFA_PLUGIN_DIR . 'vendor/autoload.php';
require_once MDFA_PLUGIN_DIR . 'includes/class-token-estimator.php';
require_once MDFA_PLUGIN_DIR . 'includes/class-converter.php';
require_once MDFA_PLUGIN_DIR . 'includes/class-content-negotiation.php';
require_once MDFA_PLUGIN_DIR . 'includes/class-discovery.php';
require_once MDFA_PLUGIN_DIR . 'includes/class-request-log.php';
require_once MDFA_PLUGIN_DIR . 'includes/class-request-log-table.php';
require_once MDFA_PLUGIN_DIR . 'includes/class-stats-tracker.php';
require_once MDFA_PLUGIN_DIR . 'includes/class-admin-tab-settings.php';
require_once MDFA_PLUGIN_DIR . 'includes/class-admin-tab-logs.php';
require_once MDFA_PLUGIN_DIR . 'includes/class-admin-tab-stats.php';
require_once MDFA_PLUGIN_DIR . 'includes/class-admin.php';
require_once MDFA_PLUGIN_DIR . 'includes/class-updater.php';

register_activation_hook( __FILE__, function () {
	if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
		wp_die( 'Markdown for Agents requires PHP 8.0 or higher.' );
	}
	add_option( 'mdfa_enabled', true );
	add_option( 'mdfa_post_types', [ 'post', 'page' ] );
	add_option( 'mdfa_taxonomies', [ 'category', 'post_tag' ] );
	add_option( 'mdfa_cache_ttl', 3600 );
	add_option( 'mdfa_canonical', true );
	add_option( 'mdfa_db_version', 3 );
	MDFA_Request_Log::create_table();
} );

add_filter( 'query_vars', fn( array $vars ): array => [ ...$vars, 'format' ] );

add_action( 'save_post', [ 'MDFA_Converter', 'invalidate_cache' ] );

add_action( 'plugins_loaded', function () {
	// Migrate from old mfa_ prefix to mdfa_.
	if ( get_option( 'mfa_enabled' ) !== false ) {
		global $wpdb;

		// Migrate options.
		foreach ( [ 'enabled', 'post_types', 'cache_ttl' ] as $key ) {
			$old_value = get_option( "mfa_{$key}" );
			if ( $old_value !== false ) {
				update_option( "mdfa_{$key}", $old_value );
				delete_option( "mfa_{$key}" );
			}
		}

		// Rename DB table.
		$old_table = $wpdb->prefix . 'mfa_request_log';
		$new_table = $wpdb->prefix . 'mdfa_request_log';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_table ) ) === $old_table ) {
			$wpdb->query( "RENAME TABLE `{$old_table}` TO `{$new_table}`" );
		}

		// Rename post meta.
		$wpdb->query(
			"UPDATE {$wpdb->postmeta} SET meta_key = '_mdfa_cache_key' WHERE meta_key = '_mfa_cache_key'"
		);

		// Rename transients.
		$wpdb->query(
			"UPDATE {$wpdb->options} SET option_name = REPLACE(option_name, '_transient_mfa_md_', '_transient_mdfa_md_') WHERE option_name LIKE '_transient_mfa_md_%'"
		);
		$wpdb->query(
			"UPDATE {$wpdb->options} SET option_name = REPLACE(option_name, '_transient_timeout_mfa_md_', '_transient_timeout_mdfa_md_') WHERE option_name LIKE '_transient_timeout_mfa_md_%'"
		);
	}

	MDFA_Request_Log::maybe_migrate();
	MDFA_Updater::init( __FILE__ );
	MDFA_Admin::init();

	if ( ! get_option( 'mdfa_enabled', true ) ) {
		return;
	}
	MDFA_Discovery::init();
	MDFA_Content_Negotiation::init();
	MDFA_Stats_Tracker::init();
} );
