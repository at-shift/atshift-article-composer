<?php
define( 'ABSPATH', __DIR__ . '/' );
$active = array(); $network = array(); $multisite = false; $allowed = true; $hooks = array(); $activation = null;
function plugin_basename( $file ) { return $file; }
function get_option( $key, $default ) { global $active; return $active; }
function get_site_option( $key, $default ) { global $network; return $network; }
function is_multisite() { global $multisite; return $multisite; }
function current_user_can( $cap ) { global $allowed; return $allowed; }
function __( $text, $domain ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function add_action( $hook, $fn ) { global $hooks; $hooks[$hook] = $fn; }
function register_activation_hook( $file, $fn ) { global $activation; $activation = $fn; }
function wp_die( $text, $title, $args ) { throw new RuntimeException( $text, $args['response'] ); }
$guard = require __DIR__ . '/../wordpress/atshift-article-composer/includes/channel-guard.php';
$checks = 0;
function channel_check( $ok ) { global $checks; if ( ! $ok ) throw new RuntimeException( 'Channel guard check failed: ' . ( $checks + 1 ) ); $checks++; }
$github = 'atshift-deft/atshift-deft.php';
$org = 'atshift-article-composer/atshift-article-composer.php';
foreach ( array( $github => $org, $org => $github ) as $current => $other ) {
    $active = array( $current );
    channel_check( $guard( $current ) );
    $active[] = $other;
    channel_check( ! $guard( $current ) );
    try { $activation(); channel_check( false ); } catch ( RuntimeException $e ) { channel_check( 409 === $e->getCode() ); }
    ob_start(); $hooks['admin_notices'](); channel_check( str_contains( ob_get_clean(), 'Only one distribution' ) );
    $allowed = false; ob_start(); $hooks['admin_notices'](); channel_check( '' === ob_get_clean() ); $allowed = true;
    $active = array(); $multisite = true; $network = array( $other => 1 );
    channel_check( ! $guard( $current ) );
    $multisite = false; $network = array();
}
define( 'Atshift\\Deft\\PLUGIN_FILE', '/old/runtime.php' );
channel_check( ! $guard( $org ) );
channel_check( ! $guard( $github ) );
echo "Distribution activation guard: $checks passed.\n";
