<?php
namespace Atshift\Deft;

defined( 'ABSPATH' ) || exit;

final class Schema {
    private static $failed = false;

    public static function create( $table, $sql ) {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $suppressed = $wpdb->suppress_errors( true );
        try {
            dbDelta( $sql );
            // dbDelta reports attempted changes, not success. Reconcile the actual schema.
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
            $pending = $exists ? dbDelta( $sql, false ) : array( 'missing' );
            $engine = $exists ? $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) ) : null;
            $ok = $exists && ! $pending && 'InnoDB' === $engine && '' === $wpdb->last_error;
        } finally {
            $wpdb->suppress_errors( $suppressed );
        }
        if ( ! $ok ) {
            self::$failed = true;
            add_action( 'admin_notices', array( self::class, 'notice' ) );
            add_action( 'network_admin_notices', array( self::class, 'notice' ) );
        }
        return $ok;
    }

    public static function notice() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Article Composer could not prepare its database tables. Check database permissions and InnoDB support, then reload to retry. Setup has not been marked complete.', 'atshift-article-composer' ) . '</p></div>';
    }

    public static function activate() {
        foreach ( array( Store::class, Drafts::class, PrivateBlobs::class, Assets::class, Transfer::class, Review::class, Access::class, Catalog::class, Connections::class, OAuthStore::class, Publications::class ) as $class ) $class::install();
        if ( self::$failed ) wp_die( esc_html__( 'Article Composer database setup failed. Check database permissions and InnoDB support, then activate again.', 'atshift-article-composer' ), esc_html__( 'Activation failed', 'atshift-article-composer' ), array( 'response' => 500, 'back_link' => true ) );
    }
}
