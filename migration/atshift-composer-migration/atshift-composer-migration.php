<?php
/**
 * Plugin Name: atshift Article Composer Migration Helper
 * Description: One-time DeFt 0.7.4 to Article Composer 0.7.6 migration checks and controlled activation.
 * Version: 0.1.0
 * Requires at least: 6.9
 * Requires PHP: 8.2
 * Author: at-shift
 * License: GPLv2 or later
 * Text Domain: atshift-composer-migration
 */
namespace Atshift\ComposerMigration;
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-migration.php';

add_action( 'admin_menu', static function() {
    if ( is_multisite() ) return;
    add_management_page( 'Article Composer migration', 'Article Composer migration', 'manage_options', 'atshift-composer-migration', __NAMESPACE__ . '\\page' );
} );

function page() {
    try { Migration::authorize(); } catch ( \Throwable $error ) { wp_die( esc_html( $error->getMessage() ), '', array( 'response' => 403 ) ); }
    $record = get_option( Migration::RECORD );
    $after = is_array( $record ) && in_array( $record['stage'], array( 'activating', 'activated', 'verified' ), true );
    $report = Migration::inspect( $after );
    echo '<div class="wrap"><h1>Article Composer migration</h1><p>DeFt 0.7.4 (GitHub) &rarr; Article Composer 0.7.6 (GitHub)</p>';
    echo '<p>Use this temporary helper on a restored copy first. It does not back up your site, upload a target ZIP, or make public-page access decisions for you.</p>';
    echo '<ol><li>Back up the complete database, plugin files, uploads and any old private files outside WordPress. Test restoration on a separate copy.</li>';
    echo '<li>Install the original Article Composer 0.7.6 GitHub ZIP without activating it. Keep MCP Adapter active.</li>';
    echo '<li>Arrange site maintenance and stop editing, AI sessions and external cron. Deactivate DeFt, but do not delete it. Reload this page.</li>';
    echo '<li>Inspect, prepare, activate, then verify here. Check previews and real public pages before removing maintenance.</li></ol>';
    echo '<p><a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">Installed plugins</a> &middot; <a href="' . esc_url( admin_url( 'tools.php?page=atshift-composer-migration' ) ) . '">Inspect again</a></p>';
    foreach ( $report['warnings'] as $message ) echo '<p>' . esc_html( $message ) . '</p>';
    foreach ( $report['errors'] as $message ) echo '<div class="notice notice-error inline"><p>' . esc_html( $message ) . '</p></div>';
    if ( $record ) echo '<h2>State: ' . esc_html( $record['stage'] ) . '</h2>';
    if ( isset( $report['inventory'] ) ) {
        echo '<h2>Inventory</h2><table class="widefat striped"><thead><tr><th>Data</th><th>Rows</th></tr></thead><tbody>';
        foreach ( $report['inventory']['tables'] as $table => $data ) echo '<tr><td>' . esc_html( $table ) . '</td><td>' . esc_html( $data['rows'] ) . '</td></tr>';
        echo '</tbody></table><p>Posts: ' . esc_html( $report['inventory']['postCount'] ) . ' / Owners: ' . esc_html( $report['inventory']['ownerCount'] ) . '</p>';
    }
    form_start( 'report' ); submit_button( 'Download diagnostic report', 'secondary' ); echo '</form>';
    if ( ! $record && $report['supported'] ) {
        form_start( 'prepare' );
        echo '<input type="hidden" name="fingerprint" value="' . esc_attr( $report['fingerprint'] ) . '">';
        echo '<p><label for="backup">Tested backup reference (not a password or access URL)</label><br><input id="backup" name="backup" class="regular-text" required minlength="3" maxlength="160"></p>';
        echo '<p><label><input type="checkbox" name="confirmed" value="yes" required> I verified backup restoration, stopped all writers, and approve invalidating old AI connections and transfer keys. This is an isolated rehearsal, or a production run after a successful rehearsal.</label></p>';
        submit_button( 'Prepare migration' ); echo '</form>';
    } elseif ( $record && 'prepared' === $record['stage'] && $report['supported'] ) {
        echo '<p>Data is retained and old connection authority has been removed. Activate the verified target next.</p>';
        form_start( 'activate' ); submit_button( 'Activate Article Composer' ); echo '</form>';
    } elseif ( $after ) {
        form_start( 'verify' ); submit_button( 'Verify saved data' ); echo '</form>';
        echo '<p>Verification compares retained data, not visual or membership behavior. Check each owner\'s drafts, preview, public links and media, then reconnect AI. Do not publish a test without permission.</p>';
        echo '<p>Deactivate this helper before testing public managed media: while active it is intentionally an unverified plugin for the new access policy. Keep maintenance enabled until those checks pass. Keep the old package inactive and the backup. This helper has no uninstall data deletion.</p>';
    }
    echo '</div>';
}

function form_start( $operation ) {
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="atshift_composer_migrate"><input type="hidden" name="operation" value="' . esc_attr( $operation ) . '">';
    wp_nonce_field( 'atshift_composer_migrate' );
}

add_action( 'admin_post_atshift_composer_migrate', static function() {
    try {
        Migration::authorize();
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) throw new \RuntimeException( 'POST is required.' );
        check_admin_referer( 'atshift_composer_migrate' );
        $operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
        if ( 'report' === $operation ) {
            $record = get_option( Migration::RECORD );
            $report = Migration::inspect( is_array( $record ) && in_array( $record['stage'], array( 'activating', 'activated', 'verified' ), true ) );
            nocache_headers(); header( 'Content-Type: application/json; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="article-composer-migration-report.json"' );
            echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); exit;
        }
        if ( 'prepare' === $operation ) {
            $fingerprint = isset( $_POST['fingerprint'] ) && is_string( $_POST['fingerprint'] ) ? wp_unslash( $_POST['fingerprint'] ) : '';
            $backup = isset( $_POST['backup'] ) && is_string( $_POST['backup'] ) ? wp_unslash( $_POST['backup'] ) : '';
            Migration::prepare( $fingerprint, $backup, 'yes' === ( $_POST['confirmed'] ?? null ) );
        } elseif ( 'activate' === $operation ) Migration::activate();
        elseif ( 'verify' === $operation ) Migration::verify();
        else throw new \RuntimeException( 'Unknown operation.' );
        wp_safe_redirect( admin_url( 'tools.php?page=atshift-composer-migration' ) ); exit;
    } catch ( \Throwable $error ) { wp_die( esc_html( $error->getMessage() ), 'Migration stopped', array( 'response' => 409, 'back_link' => true ) ); }
} );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    \WP_CLI::add_command( 'atshift-composer-migration', static function( $args, $options ) {
        try {
            $operation = $args[0] ?? 'inspect';
            if ( 'inspect' === $operation ) $result = Migration::inspect();
            elseif ( 'prepare' === $operation ) $result = Migration::prepare( $options['fingerprint'] ?? '', $options['backup-reference'] ?? '', true === ( $options['confirm-preconditions'] ?? null ) );
            elseif ( 'activate' === $operation ) $result = Migration::activate();
            elseif ( 'verify' === $operation ) $result = Migration::verify();
            else throw new \RuntimeException( 'Use inspect, prepare, activate or verify.' );
            // The CLI receipt excludes backup references and administrator identifiers.
            if ( isset( $result['backupReference'] ) ) $result = array_intersect_key( $result, array_fill_keys( array( 'stage', 'contentHash', 'inventory' ), true ) );
            \WP_CLI::line( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
            if ( isset( $result['supported'] ) && ! $result['supported'] ) \WP_CLI::halt( 2 );
        } catch ( \Throwable $error ) { \WP_CLI::error( $error->getMessage() ); }
    } );
}
