<?php
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require dirname( __DIR__ ) . '/.local/wordpress/wp-load.php';
use Atshift\Deft\Connections;
use Atshift\Deft\Mcp;
if ( ! preg_match( '/^sf_deft_prefix_[a-f0-9]+$/D', DB_NAME ) || home_url() !== 'http://127.0.0.1:8128' ) throw new RuntimeException( 'Disposable test site required.' );
$checks = 0; $key = null; $outer = null;
function remote_check( $ok, $label ) { global $checks; if ( ! $ok ) throw new RuntimeException( $label ); echo 'PASS ' . ++$checks . ': ' . $label . PHP_EOL; }
$admin = get_user_by( 'login', 'sf_local_admin' );
wp_set_current_user( $admin->ID );
$plain = register_post_type( 'ac_scope_plain', array( 'public' => true, 'supports' => array( 'title', 'editor' ), 'capability_type' => 'ac_fixture', 'map_meta_cap' => false ) );
foreach ( get_object_vars( $plain->cap ) as $cap ) $admin->add_cap( $cap );
register_taxonomy( 'ac_scope_admin', 'post', array( 'public' => true, 'capabilities' => array( 'assign_terms' => 'edit_theme_options' ) ) );
register_taxonomy( 'ac_scope_publish', 'post', array( 'public' => true, 'capabilities' => array( 'assign_terms' => 'publish_posts' ) ) );
register_taxonomy( 'ac_scope_sites', 'post', array( 'public' => true, 'capabilities' => array( 'assign_terms' => 'create_sites' ) ) );
register_taxonomy( 'ac_scope_upgrade', 'post', array( 'public' => true, 'capabilities' => array( 'assign_terms' => 'upgrade_network' ) ) );
$post = wp_insert_post( array( 'post_type' => 'post', 'post_author' => $admin->ID, 'post_title' => 'Disposable permission control', 'post_status' => 'draft' ) );
$unmapped = wp_insert_post( array( 'post_type' => $plain->name, 'post_author' => $admin->ID, 'post_title' => 'Unmapped capability control', 'post_status' => 'draft' ) );
$_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie( $admin->ID, time() + HOUR_IN_SECONDS, 'logged_in' );
$request = new WP_REST_Request( 'POST' ); $request->set_header( 'content-type', 'application/json' );
$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
$request->set_body( wp_json_encode( array( 'name' => 'Disposable scope test', 'authoring' => true ) ) );
try {
    $key = Connections::create( $request ); remote_check( is_array( $key ) && isset( $key['token'] ), 'Administrator issues an explicitly scoped key' );
    wp_set_current_user( 0 ); unset( $_COOKIE[LOGGED_IN_COOKIE] );
    $outer = new WP_REST_Request( 'POST', Connections::ROUTE ); $outer->set_header( 'authorization', 'Bearer ' . $key['token'] );
    remote_check( null === Connections::guard( null, rest_get_server(), $outer ) && Connections::authorized(), 'Scoped administrator context authenticates' );
    foreach ( array( 'manage_options', 'install_plugins', 'activate_plugins', 'update_plugins', 'delete_plugins', 'switch_themes', 'edit_theme_options', 'customize', 'edit_themes', 'edit_plugins', 'create_users', 'edit_users', 'promote_users', 'delete_users', 'manage_network', 'unfiltered_html', 'administrator', 'publish_posts' ) as $cap ) {
        remote_check( ! current_user_can( $cap ), 'Scoped administrator loses site administration: ' . $cap );
    }
    remote_check( current_user_can( 'atshde_edit_drafts' ) && current_user_can( 'upload_files' ), 'Content authoring rights remain available' );
    foreach ( array( 'create_sites', 'upgrade_network' ) as $cap ) remote_check( ! current_user_can( $cap ), 'Taxonomy mapping cannot import network administration: ' . $cap );
    foreach ( array( 'edit_user', 'create_app_password', 'list_app_passwords', 'read_app_password', 'edit_app_password', 'delete_app_passwords', 'delete_app_password' ) as $cap ) {
        remote_check( ! current_user_can( $cap, $admin->ID ), 'Self-account aliases cannot escape content scope: ' . $cap );
    }
    remote_check( current_user_can( 'edit_post', $post ), 'Owner-held native editing remains available' );
    remote_check( current_user_can( $plain->cap->edit_post, $unmapped ), 'Unmapped custom singular editing remains available' );
    $deny = static function( $allcaps, $caps, $args ) {
        if ( 'edit_post' === $args[0] ) $allcaps['edit_posts'] = false;
        return $allcaps;
    };
    if ( ! is_multisite() ) {
        add_filter( 'user_has_cap', $deny, 10, 3 );
        remote_check( ! current_user_can( 'edit_post', $post ), 'Earlier object-specific user capability denial is preserved' );
        remove_filter( 'user_has_cap', $deny );
    }
    $deny_meta = static fn( $caps, $cap ) => 'edit_post' === $cap ? array( 'do_not_allow' ) : $caps;
    add_filter( 'map_meta_cap', $deny_meta, 20, 2 );
    remote_check( ! current_user_can( 'edit_post', $post ), 'Earlier meta-capability denial is preserved, including superadmins' );
    remove_filter( 'map_meta_cap', $deny_meta );
    foreach ( array( '/wp/v2/users', '/wp/v2/settings', '/wp/v2/plugins', '/wp/v2/themes', '/batch/v1', '/structured-fields/v1/connections', '/structured-fields/v1/behaviors', Connections::ROUTE, Connections::PUBLISH_ROUTE ) as $route ) {
        $nested = new WP_REST_Request( 'POST', $route );
        $response = rest_do_request( $nested );
        remote_check( 403 === $response->get_status(), 'Nested REST request denied: ' . $route );
        Connections::finish( $response, rest_get_server(), $nested );
        remote_check( Connections::authorized() && get_current_user_id() === (int) $admin->ID, 'Nested response cannot overwrite or clear the outer scope' );
    }
    foreach ( array( 'install-plugin', 'activate-plugin', 'update-theme', 'create-user', 'update-settings', 'execute-code', 'register-component', 'set-publication' ) as $tool ) remote_check( is_wp_error( Mcp::execute( $tool, array() ) ), 'Draft connection refuses non-allowlisted/admin tool: ' . $tool );
    Connections::finish( new WP_REST_Response(), rest_get_server(), $outer ); $outer = null;
    remote_check( 0 === get_current_user_id() && ! Connections::authorized(), 'Only the outer response restores the original anonymous identity' );
    wp_set_current_user( $admin->ID );
    remote_check( current_user_can( 'manage_options' ) && current_user_can( 'edit_user', $admin->ID ), 'Ordinary administrator permissions return outside connection scope' );
    wp_set_current_user( 0 );
    foreach ( array( '/wp/v2/users', '/wp/v2/settings', '/wp/v2/plugins', '/wp/v2/themes', '/batch/v1' ) as $route ) {
        $outside = new WP_REST_Request( 'POST', $route ); $outside->set_header( 'authorization', 'Bearer ' . $key['token'] );
        remote_check( 403 === rest_do_request( $outside )->get_status() && 0 === get_current_user_id(), 'Bearer key cannot authorize core administration: ' . $route );
    }
    echo 'Remote scope checks: ' . $checks . ' passed.' . PHP_EOL;
} finally {
    if ( $outer ) Connections::finish( new WP_REST_Response(), rest_get_server(), $outer );
    if ( is_array( $key ) ) $wpdb->delete( Connections::table(), array( 'id' => $key['id'] ) );
    wp_set_current_user( $admin->ID );
    wp_delete_post( $post, true ); wp_delete_post( $unmapped, true );
    foreach ( get_object_vars( $plain->cap ) as $cap ) if ( str_contains( $cap, 'ac_fixture' ) ) $admin->remove_cap( $cap );
    unregister_post_type( $plain->name ); unregister_taxonomy( 'ac_scope_admin' ); unregister_taxonomy( 'ac_scope_publish' );
    unregister_taxonomy( 'ac_scope_sites' ); unregister_taxonomy( 'ac_scope_upgrade' );
}
