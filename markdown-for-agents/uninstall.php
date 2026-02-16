<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Usuń opcje
delete_option( 'mdfa_enabled' );
delete_option( 'mdfa_post_types' );
delete_option( 'mdfa_taxonomies' );
delete_option( 'mdfa_cache_ttl' );
delete_option( 'mdfa_db_version' );
delete_option( 'mdfa_stats' );
delete_option( 'mdfa_beta_updates' );
delete_option( 'mdfa_noindex' );
delete_option( 'mdfa_canonical' );
delete_option( 'mdfa_anonymize_ip' );
delete_option( 'mdfa_ai_bots' );
delete_option( 'mdfa_search_crawlers' );
delete_option( 'mdfa_tool_crawlers' );
delete_option( 'mdfa_max_log_rows' );
delete_option( 'mdfa_signal_ai_train' );
delete_option( 'mdfa_signal_search' );
delete_option( 'mdfa_signal_ai_input' );
delete_transient( 'mdfa_update_check' );
delete_transient( 'mdfa_update_check_beta' );

// Usuń tabelę logów
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mdfa_request_log" );

// Usuń post meta (klucze cache + HTML tokens + content signals)
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_mdfa_cache_key', '_mdfa_html_tokens', '_mdfa_signal_ai_train', '_mdfa_signal_search', '_mdfa_signal_ai_input')" );

// Usuń term meta (content signals)
$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE meta_key IN ('_mdfa_signal_ai_train', '_mdfa_signal_search', '_mdfa_signal_ai_input')" );

// Usuń transienty cache
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mdfa_md_%' OR option_name LIKE '_transient_timeout_mdfa_md_%' OR option_name LIKE '_transient_mdfa_html_tokens_%' OR option_name LIKE '_transient_timeout_mdfa_html_tokens_%' OR option_name LIKE '_transient_mdfa_archive_%' OR option_name LIKE '_transient_timeout_mdfa_archive_%' OR option_name LIKE '_transient_mdfa_home_%' OR option_name LIKE '_transient_timeout_mdfa_home_%'"
);
