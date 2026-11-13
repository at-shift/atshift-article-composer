<?php
// Failure injection is limited to fresh tables in the disposable review database.
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() ||
    ! preg_match( '/^sf_deft_prefix_[a-f0-9]+$/D', DB_NAME ) ) throw new RuntimeException( 'Disposable review site required.' );
use Atshift\Deft\{Schema, Drafts, Connections, Publications, Store};
global $wpdb, $checks;
$checks = 0;
function review_check( $ok, $label ) { global $checks; if ( ! $ok ) throw new RuntimeException( $label ); echo 'PASS ' . ++$checks . ': ' . $label . PHP_EOL; }
foreach ( array(
    array( Store::class, 'atshde_store_schema_version', 1, 'rendered' ),
    array( Drafts::class, 'atshde_drafts_schema_version', 1, 'changed_at' ),
    array( Connections::class, 'atshde_connections_schema_version', 4, 'name' ),
    array( Publications::class, 'atshde_publications_schema_version', 3, 'state' ),
) as list( $class, $option, $version, $column ) ) {
    $table = $class::table();
    review_check( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ), 'Failure fixture starts empty: ' . $class );
    $wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $table ) );
    delete_option( $option );
    $block = static function( $query ) use ( $table ) {
        return preg_match( '/^(?:CREATE TABLE|ALTER TABLE)\s+\x60?' . preg_quote( $table, '/' ) . '(?:\x60|\s)/i', $query ) ? 'SELECT 1' : $query;
    };
    add_filter( 'query', $block );
    ob_start(); $class::install(); $output = ob_get_clean();
    review_check( '' === $output && false === get_option( $option ), 'Failed CREATE produces no output or completion marker: ' . $class );
    remove_filter( 'query', $block );
    $class::install();
    review_check( $version === (int) get_option( $option ), 'Retry completes the actual schema: ' . $class );
    update_option( $option, 0 );
    $wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP COLUMN %i', $table, $column ) );
    add_filter( 'query', $block );
    $class::install();
    review_check( 0 === (int) get_option( $option ), 'Failed ALTER does not advance schema marker: ' . $class );
    remove_filter( 'query', $block );
    $class::install();
    review_check( $version === (int) get_option( $option ), 'ALTER retries successfully: ' . $class );
    ob_start(); $class::install(); $output = ob_get_clean();
    review_check( '' === $output, 'Repeated installation is silent: ' . $class );
}
review_check( Store::install(), 'Snapshot table reconciles successfully' );
wp_set_current_user( 0 ); ob_start(); Schema::notice(); review_check( '' === ob_get_clean(), 'Schema notice hides from anonymous visitors' );
wp_set_current_user( get_user_by( 'login', 'sf_local_admin' )->ID );
ob_start(); Schema::notice(); review_check( str_contains( ob_get_clean(), 'database tables' ), 'Schema notice explains retry to administrator' );
echo 'Schema review checks: ' . $checks . ' passed.' . PHP_EOL;
