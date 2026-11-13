<?php
namespace Atshift\Deft;

defined( 'ABSPATH' ) || exit;

/** One current-viewer policy for additional article outputs, never an editing permission. */
final class PublicationAccess {
    private static $evaluating = false;
    private static $um_viewer = null;
    private static function integrations() {
        $integrations = array(
            'plugin:' . plugin_basename( PLUGIN_FILE ) => '__return_true',
            'plugin:mcp-adapter/mcp-adapter.php' => '__return_true',
            'plugin:atshift-members/atshift-members.php' => array( self::class, 'atshift_members' ),
            'plugin:members/members.php' => array( self::class, 'members' ),
            'plugin:ultimate-member/ultimate-member.php' => array( self::class, 'ultimate_member' ),
            'theme:twentytwentyfive' => '__return_true',
        );
        // Trusted PHP integrations must evaluate every request; null means not verified.
        return apply_filters( 'atshift_article_composer_access_integrations', $integrations );
    }

    public static function environment() {
        $components = array();
        $plugins = array_merge( (array) get_option( 'active_plugins', array() ), array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
        foreach ( array_unique( $plugins ) as $plugin ) $components[] = 'plugin:' . $plugin;
        foreach ( wp_get_mu_plugins() as $plugin ) $components[] = 'mu:' . basename( $plugin );
        if ( ! function_exists( 'get_dropins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
        foreach ( array_keys( get_dropins() ) as $dropin ) $components[] = 'dropin:' . $dropin;
        foreach ( array_unique( array( get_template(), get_stylesheet() ) ) as $theme ) $components[] = 'theme:' . $theme;
        $integrations = self::integrations(); $callbacks = $unknown = array();
        foreach ( $components as $component ) {
            $callback = is_array( $integrations ) ? ( $integrations[ $component ] ?? null ) : null;
            if ( ! is_callable( $callback ) ) $unknown[] = $component;
            else $callbacks[ $component ] = $callback;
        }
        return array( 'callbacks' => $callbacks, 'unknown' => $unknown );
    }

    public static function decision( $post ) {
        if ( self::$evaluating ) return 'unknown';
        self::$evaluating = true;
        try { return self::evaluate( $post ); }
        finally { self::$evaluating = false; }
    }

    private static function evaluate( $post ) {
        $post = get_post( $post );
        if ( ! $post || 'publish' !== $post->post_status || $post->post_password || ! is_post_publicly_viewable( $post ) ) return 'deny';
        // Block/shortcode fragments may have a narrower audience than their parent article.
        // Composer publications are static HTML; hand-edited dynamic content needs its own adapter.
        if ( strlen( $post->post_content ) > 4 * 1024 * 1024 || str_contains( $post->post_content, '[' ) ) return 'unknown';
        // Use the rendering parser, not has_blocks()'s literal-string shortcut.
        foreach ( parse_blocks( $post->post_content ) as $block ) {
            if ( ! empty( $block['blockName'] ) ) return 'unknown';
        }
        $environment = self::environment();
        if ( $environment['unknown'] ) return 'unknown';
        foreach ( $environment['callbacks'] as $callback ) {
            try { $allowed = call_user_func( $callback, $post->ID, get_current_user_id() ); }
            catch ( \Throwable $error ) { return 'unknown'; }
            if ( false === $allowed ) return 'deny';
            if ( true !== $allowed ) return 'unknown';
        }
        return 'allow';
    }

    public static function allows( $post ) {
        return 'allow' === self::decision( $post );
    }

    public static function atshift_members( $id ) {
        if ( ! is_callable( array( '\\Atshift\\Membership\\Content', 'denied' ) ) ) return null;
        return ! \Atshift\Membership\Content::denied( $id );
    }

    public static function members( $id ) {
        if ( ! function_exists( 'members_can_current_user_view_post' ) || ! function_exists( 'members_is_private_blog' ) ) return null;
        if ( members_is_private_blog() && ( ! is_user_logged_in() ||
            ( is_multisite() && ! is_user_member_of_blog() && ! is_super_admin() ) ) ) return false;
        return members_can_current_user_view_post( $id );
    }

    public static function ultimate_member( $id ) {
        if ( ! function_exists( 'UM' ) ) return null;
        // UM's inner privacy cache is post-only and cannot be flushed through its API.
        $viewer = get_current_blog_id() . ':' . get_current_user_id();
        if ( null !== self::$um_viewer && self::$um_viewer !== $viewer ) return null;
        if ( did_action( 'set_current_user' ) > 1 || did_action( 'switch_blog' ) ) return null;
        self::$um_viewer = $viewer;
        $access = UM()->access();
        if ( ! is_callable( array( $access, 'is_restricted' ) ) || ! is_callable( array( $access, 'get_post_privacy_settings' ) ) ) return null;
        // The upstream helpers use the current viewer. Never switch to an author/admin here.
        if ( $access->is_restricted( $id, false, true ) ) return false;
        if ( current_user_can( 'administrator' ) ) return true;
        $restriction = $access->get_post_privacy_settings( $id );
        if ( $restriction ) return true;
        if ( 2 !== (int) UM()->options()->get( 'accessible' ) ) return true;
        if ( is_user_logged_in() && ( ! is_multisite() || UM()->roles()->get_priority_user_role( get_current_user_id() ) ) ) return true;
        if ( (int) get_option( 'page_on_front' ) === $id && UM()->options()->get( 'home_page_accessible' ) ) return true;
        $exceptions = (array) UM()->options()->get( 'access_exclude_uris' );
        $exceptions[] = UM()->options()->get( 'access_redirect' );
        $url = untrailingslashit( get_permalink( $id ) );
        foreach ( $exceptions as $exception ) {
            if ( is_string( $exception ) && $url === untrailingslashit( trim( $exception ) ) ) return true;
        }
        return false;
    }

    public static function headers() {
        if ( ! is_singular() || ! get_post_meta( get_queried_object_id(), '_atshde_publication', true ) ) return;
        self::private_headers();
    }

    public static function private_headers() {
        if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
        if ( ! headers_sent() ) {
            nocache_headers();
            header( 'Cache-Control: private, no-store, max-age=0' );
            header( 'Vary: Cookie', false );
        }
    }

    public static function notice() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $screen = get_current_screen();
        if ( ! $screen || ! str_contains( $screen->id, 'structured-fields' ) ) return;
        $unknown = self::environment()['unknown'];
        if ( $unknown ) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'Article structure JSON and Composer-managed image/PDF delivery are paused because access rules have not been verified for the following components. Normal article HTML is unchanged; protected files are not made public.', 'atshift-article-composer' ) . '</p><p>' . esc_html( implode( ', ', $unknown ) ) . '</p></div>';
            return;
        }
        $ids = get_posts( array( 'post_type' => array_keys( get_post_types( array( 'public' => true ) ) ), 'post_status' => 'publish',
            'meta_key' => '_atshde_publication', 'posts_per_page' => 50, 'fields' => 'ids' ) );
        $held = array();
        foreach ( $ids as $id ) {
            if ( 'unknown' === self::decision( $id ) ) $held[] = get_the_title( $id ) . ' (#' . $id . ')';
        }
        if ( $held ) echo '<div class="notice notice-warning"><p>' . esc_html__( 'Additional article output is paused for these recent articles: dynamic block/shortcode content, oversized content, or an unavailable access decision needs a verified integration. Normal article HTML is unchanged.', 'atshift-article-composer' ) . '</p><p>' . esc_html( implode( ', ', $held ) ) . '</p></div>';
    }
}
