<?php
namespace Atshift\Deft;

defined( 'ABSPATH' ) || exit;

// Run before any named declarations, even when an older package loads first.
return static function( $file ) {
    $installed = plugin_basename( $file );
    $identities = array( 'atshift-deft/atshift-deft.php', 'atshift-article-composer/atshift-article-composer.php' );
    $active = (array) get_option( 'active_plugins', array() );
    if ( is_multisite() ) $active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
    $conflict = array_intersect( array_diff( $identities, array( $installed ) ), $active );
    if ( ! $conflict && ! defined( __NAMESPACE__ . '\\PLUGIN_FILE' ) ) return true;
    $message = static function() {
        return __( 'Deactivate the other atshift Article Composer / atshift DeFt package before activating this one. Keep the saved data; do not delete the old plugin. Only one distribution can be active at a time.', 'atshift-article-composer' );
    };
    $notice = static function() use ( $message ) {
        if ( ! current_user_can( 'activate_plugins' ) && ! current_user_can( 'manage_network_plugins' ) ) return;
        echo '<div class="notice notice-error" role="alert"><p>' . esc_html( $message() ) . '</p></div>';
    };
    add_action( 'admin_notices', $notice );
    add_action( 'network_admin_notices', $notice );
    register_activation_hook( $file, static function() use ( $message ) {
        wp_die( esc_html( $message() ), 'atshift Article Composer', array( 'response' => 409, 'back_link' => true ) );
    } );
    return false;
};
