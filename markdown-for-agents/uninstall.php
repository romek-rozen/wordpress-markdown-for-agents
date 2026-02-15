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

// Usuń tabelę logów
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mdfa_request_log" );

// Usuń post meta (klucze cache)
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_mdfa_cache_key'" );

// Usuń transienty cache
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mdfa_md_%' OR option_name LIKE '_transient_timeout_mdfa_md_%' OR option_name LIKE '_transient_mdfa_html_tokens_%' OR option_name LIKE '_transient_timeout_mdfa_html_tokens_%' OR option_name LIKE '_transient_mdfa_archive_%' OR option_name LIKE '_transient_timeout_mdfa_archive_%'"
);
