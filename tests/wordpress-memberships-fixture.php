<?php
// wp eval-file: destructive fixtures are restricted to a freshly created test database.
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() ||
    ! preg_match( '/^sf_deft_members_[a-f0-9]+$/D', DB_NAME ) ) throw new RuntimeException( 'Use a disposable membership database.' );
use Atshift\Deft\{Assets, PrivateBlobs, Publications, PublicationAccess, PublicationMedia, PublicStructure, Snapshot, Store};
function acm_good( $value ) {
    if ( is_wp_error( $value ) ) throw new RuntimeException( $value->get_error_code() . ': ' . $value->get_error_message() );
    return $value;
}
$phase = getenv( 'AC_MEMBERS_PHASE' );
$fixture = get_option( 'ac_members_fixture' );
if ( 'seed' === $phase ) {
    global $wpdb;
    $owner = get_user_by( 'login', 'membership_admin' )->ID;
    wp_set_current_user( $owner ); $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $snapshot = json_decode( file_get_contents( dirname( Atshift\Deft\PLUGIN_FILE ) . '/media-sample.json' ) );
    $snapshot->article->id = 'membership-' . wp_generate_uuid4();
    $snapshot->article->title = 'Membership publication';
    acm_good( Store::save( Snapshot::validate( Snapshot::json( $snapshot ) ), 0 ) );
    foreach ( array( 'image', 'document' ) as $field ) {
        $bytes = 'image' === $field ? file_get_contents( get_template_directory() . '/screenshot.png' ) : "%PDF-1.4\n%%EOF\n";
        $asset = wp_generate_uuid4(); $hash = hash( 'sha256', $bytes );
        $mime = 'image' === $field ? 'image/png' : 'application/pdf';
        $meta = array( 'sha256' => $hash, 'bytes' => strlen( $bytes ) );
        PrivateBlobs::put( 'asset', $asset, $bytes, $meta );
        $wpdb->insert( Assets::table(), $meta + array( 'asset_id' => $asset, 'article_id' => $snapshot->article->id,
            'purpose' => 'publication', 'filename' => 'image' === $field ? 'test.png' : 'test.pdf', 'mime' => $mime,
            'created_by' => $owner, 'created_at' => current_time( 'mysql', true ) ) );
        $value = $snapshot->article->blocks[0]->values->$field;
        $value->assetRef = (object) array( 'kind' => 'staged', 'id' => $asset, 'sha256' => $hash );
        $value->alt = 'PRIVATE_' . strtoupper( $field ) . '_SENTINEL';
    }
    $snapshot->article->revision = 2;
    $saved = acm_good( Store::save( Snapshot::validate( Snapshot::json( $snapshot ) ), 1 ) );
    $token = WP_Session_Tokens::get_instance( $owner )->create( time() + HOUR_IN_SECONDS );
    $_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $owner, time() + HOUR_IN_SECONDS, 'logged_in', $token );
    $request = new WP_REST_Request( 'POST' ); $request->set_header( 'content-type', 'application/json' );
    $request->set_header( 'x-wp-nonce', wp_create_nonce( 'wp_rest' ) );
    $request->set_body( wp_json_encode( array( 'articleId' => $saved['articleId'], 'expectedVersion' => 2,
        'expectedPublicationVersion' => 0, 'requestId' => wp_generate_uuid4(), 'timing' => 'now', 'publishAt' => '', 'timeZone' => '', 'postType' => 'post' ) ) );
    $published = acm_good( Publications::set( $request ) );
    if ( 'published' !== $published['state'] ) throw new RuntimeException( wp_json_encode( $published ) );
    $post = get_post( $published['postId'] ); $job = Publications::read( $saved['articleId'] );
    $fixture = array( 'post' => $post->ID, 'body' => $post->post_content, 'owner' => $owner,
        'publication' => $job['publication_id'], 'files' => json_decode( $job['files'], true ), 'users' => array() );
    foreach ( array( 'reader' => 'subscriber', 'other' => 'contributor' ) as $login => $role ) {
        $fixture['users'][ $login ] = acm_good( wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password( 40 ), 'role' => $role ) ) );
    }
    $fixture['users']['admin'] = $owner;
    update_option( 'ac_members_fixture', $fixture, false );
} elseif ( 'cookies' === $phase ) {
    $cookies = array();
    foreach ( $fixture['users'] as $name => $user ) {
        $expires = time() + HOUR_IN_SECONDS;
        $token = WP_Session_Tokens::get_instance( $user )->create( $expires );
        $cookies[ $name ] = LOGGED_IN_COOKIE . '=' . rawurlencode( wp_generate_auth_cookie( $user, $expires, 'logged_in', $token ) ) . '; ' . AUTH_COOKIE . '=' . rawurlencode( wp_generate_auth_cookie( $user, $expires, 'auth', $token ) );
    }
    echo wp_json_encode( $cookies ); return;
} elseif ( 'probe' === $phase ) {
    wp_set_current_user( (int) getenv( 'AC_MEMBERS_USER' ) );
    $post = get_post( $fixture['post'] );
    $result = array( 'decision' => PublicationAccess::decision( $post ), 'unknown' => PublicationAccess::environment()['unknown'],
        'structure' => null !== PublicStructure::from_content( $post->ID, $post->post_content ), 'media' => array() );
    foreach ( $fixture['files'] as $key => $meta ) {
        try { list( $found, $bytes ) = PublicationMedia::bytes( $fixture['publication'], $key ); $result['media'][] = hash( 'sha256', $bytes ) === $meta['sha256']; }
        catch ( Throwable $error ) { $result['media'][] = false; }
    }
    echo wp_json_encode( $result ); return;
} elseif ( 'configure' === $phase ) {
    wp_set_current_user( $fixture['owner'] );
    $id = $fixture['post']; $mode = getenv( 'AC_MEMBERS_MODE' );
    foreach ( array( '_atshme_members_only', '_atshme_audience', '_atshme_publication_lock', '_members_access_role', 'um_content_restriction' ) as $key ) delete_post_meta( $id, $key );
    wp_set_post_terms( $id, array(), 'category' );
    wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $fixture['body'], 'post_password' => '', 'post_status' => 'publish' ) ) );
    foreach ( $fixture['users'] as $name => $user ) {
        delete_user_meta( $user, '_atshme_state' );
        if ( 'admin' !== $name ) ( new WP_User( $user ) )->set_role( 'reader' === $name ? 'subscriber' : 'contributor' );
    }
    if ( function_exists( 'members_get_setting' ) ) {
        $settings = get_option( 'members_settings', array() ); $settings['private_blog'] = 0; $settings['content_permissions'] = 1;
        update_option( 'members_settings', $settings );
    }
    if ( function_exists( 'UM' ) ) {
        UM()->options()->update( 'accessible', 0 );
        UM()->options()->update( 'restricted_access_post_metabox', array( 'post' => 1 ) );
        UM()->options()->update( 'restricted_access_taxonomy_metabox', array( 'category' => 1 ) );
        UM()->options()->update( 'access_exclude_uris', array() );
    }
    if ( in_array( $mode, array( 'atshift', 'combined' ), true ) ) {
        update_post_meta( $id, '_atshme_members_only', '1' );
        foreach ( array( 'reader' => 'active', 'other' => 'suspended' ) as $name => $state ) {
            ( new WP_User( $fixture['users'][ $name ] ) )->set_role( 'atshme_member' );
            update_user_meta( $fixture['users'][ $name ], '_atshme_state', $state );
        }
    }
    if ( 'audience' === $mode ) update_post_meta( $id, '_atshme_audience', 'unavailable-group' );
    if ( in_array( $mode, array( 'members', 'combined' ), true ) ) members_set_post_roles( $id, array( 'subscriber' ) );
    if ( 'members-private' === $mode ) {
        $settings['private_blog'] = 1; update_option( 'members_settings', $settings );
    }
    $restriction = array( '_um_custom_access_settings' => 1, '_um_accessible' => '2', '_um_access_roles' => array( 'subscriber' => '1' ), '_um_noaccess_action' => '0', '_um_access_hide_from_queries' => '0' );
    if ( 'um' === $mode ) update_post_meta( $id, 'um_content_restriction', $restriction );
    if ( 'um-category' === $mode ) {
        $term = acm_good( wp_insert_term( 'Restricted-' . wp_generate_uuid4(), 'category' ) )['term_id'];
        wp_set_post_terms( $id, array( $term ), 'category' ); update_term_meta( $term, 'um_content_restriction', $restriction );
    }
    if ( 'um-private' === $mode ) UM()->options()->update( 'accessible', 2 );
    if ( 'partial' === $mode ) wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => '[members_access role="subscriber"]' . $fixture['body'] . '[/members_access]' ) ) );
    if ( 'block' === $mode ) wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => '<!-- wp:group -->' . $fixture['body'] . '<!-- /wp:group -->' ) ) );
    $block_spaces = array( 'block-spaces' => '  ', 'block-tab' => "\t", 'block-newline' => "\n" );
    if ( isset( $block_spaces[ $mode ] ) ) {
        $content = '<!--' . $block_spaces[ $mode ] . 'wp:group -->' . $fixture['body'] . '<!-- /wp:group -->';
        if ( 'core/group' !== parse_blocks( $content )[0]['blockName'] ) throw new RuntimeException( 'The rendering parser must recognize the fixture.' );
        wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $content ) ) );
    }
    if ( 'password' === $mode ) wp_update_post( array( 'ID' => $id, 'post_password' => 'secret' ) );
    if ( 'withdrawn' === $mode ) wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
    if ( 'removed-media' === $mode ) wp_update_post( array( 'ID' => $id, 'post_content' => '<div class="sf-article"><p>Media withdrawn.</p></div>' ) );
} else throw new RuntimeException( 'Unknown fixture phase.' );
echo wp_json_encode( $fixture );
