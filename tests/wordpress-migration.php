<?php
// wp eval-file, disposable database only. No production paths or data.
use Atshift\ComposerMigration\Migration;
use Atshift\Deft\{Store, Snapshot, PrivateBlobs, Assets, Publications, Connections, Mcp, Catalog};
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() || ! preg_match( '/^ac_migration_[a-f0-9]{10}$/D', DB_NAME ) ) throw new RuntimeException( 'Disposable migration database required.' );
global $wpdb;
$phase = getenv( 'AC_MIGRATION_PHASE' );
function acm_check( $value, $label ) { if ( ! $value ) throw new RuntimeException( $label ); echo 'PASS: ' . $label . "\n"; }
function acm_good( $value ) { if ( is_wp_error( $value ) ) throw new RuntimeException( $value->get_error_message() ); return $value; }
function acm_denied( $fn, $label ) { try { $fn(); } catch ( Throwable $error ) { acm_check( true, $label ); return; } throw new RuntimeException( 'Expected rejection: ' . $label ); }
$admin = get_user_by( 'login', 'migration_admin' )->ID; wp_set_current_user( $admin );
if ( 'seed' === $phase ) {
    acm_check( '0.7.4' === Atshift\Deft\VERSION, 'Seed uses actual published 0.7.4 runtime' );
    $author = acm_good( wp_insert_user( array( 'user_login' => 'migration_author', 'user_pass' => wp_generate_password( 40 ), 'role' => 'atshde_author' ) ) );
    $source = json_decode( file_get_contents( dirname( Atshift\Deft\PLUGIN_FILE ) . '/sample.json' ) );
    $source->schemaVersion = 2;
    $request = new WP_REST_Request( 'POST' ); $request->set_header( 'content-type', 'application/json' );
    $current_component = Catalog::read( $source->components[0]->id );
    $request->set_body( Snapshot::json( array( 'component' => $source->components[0], 'expectedVersion' => $current_component ? (int) $current_component['version'] : 0 ) ) );
    acm_good( Catalog::register( $request ) );
    foreach ( array( 'migration-draft' => $admin, 'migration-owner' => $author, 'migration-published' => $admin ) as $id => $owner ) {
        wp_set_current_user( $owner ); $sample = clone $source; $sample->article = clone $source->article;
        $sample->article->id = $id; $sample->article->title = 'Migration <test> & preserved ' . $id;
        acm_good( Store::save( Snapshot::validate( Snapshot::json( $sample ) ), 0 ) );
        if ( 'migration-draft' === $id ) {
            $sample->article->revision++; $sample->article->title .= ' version 2';
            acm_good( Store::save( Snapshot::validate( Snapshot::json( $sample ) ), 1 ) );
        }
    }
    wp_set_current_user( $admin );
    $bytes = "%PDF-1.4\n" . str_repeat( 'fixture-only-media-', 5000 ) . "\n%%EOF";
    $asset = wp_generate_uuid4(); $hash = hash( 'sha256', $bytes );
    PrivateBlobs::put( 'asset', $asset, $bytes, array( 'bytes' => strlen( $bytes ), 'sha256' => $hash ) );
    acm_check( 1 === $wpdb->insert( Assets::table(), array( 'asset_id' => $asset, 'article_id' => 'migration-draft', 'purpose' => 'reference', 'sha256' => $hash, 'filename' => 'fixture.pdf', 'mime' => 'application/pdf', 'bytes' => strlen( $bytes ), 'created_by' => $admin, 'created_at' => current_time( 'mysql', true ) ) ), 'Seed multi-chunk private reference media' );
    $media = json_decode( file_get_contents( dirname( Atshift\Deft\PLUGIN_FILE ) . '/media-sample.json' ) );
    $media->article->id = 'migration-published'; $media->article->revision = 2;
    foreach ( array( 'image' => array( 'photo.png', 'image/png', file_get_contents( get_theme_root() . '/twentytwentyfive/screenshot.png' ) ), 'document' => array( 'fixture.pdf', 'application/pdf', $bytes ) ) as $field => $file ) {
        $media_id = wp_generate_uuid4(); $media_hash = hash( 'sha256', $file[2] );
        PrivateBlobs::put( 'asset', $media_id, $file[2], array( 'bytes' => strlen( $file[2] ), 'sha256' => $media_hash ) );
        acm_check( 1 === $wpdb->insert( Assets::table(), array( 'asset_id' => $media_id, 'article_id' => 'migration-published', 'purpose' => 'publication', 'sha256' => $media_hash, 'filename' => $file[0], 'mime' => $file[1], 'bytes' => strlen( $file[2] ), 'created_by' => $admin, 'created_at' => current_time( 'mysql', true ) ) ), 'Seed publication ' . $field );
        $media->article->blocks[0]->values->$field = (object) array( 'value' => '', 'assetRef' => (object) array( 'kind' => 'staged', 'id' => $media_id, 'sha256' => $media_hash ), 'alt' => 'Migration test ' . $field );
    }
    acm_good( Store::save( Snapshot::validate( Snapshot::json( $media ) ), 1 ) );
    $id = wp_generate_uuid4(); $secret = bin2hex( random_bytes( 32 ) );
    acm_check( 1 === $wpdb->insert( Connections::table(), array( 'id' => $id, 'owner_id' => $admin, 'name' => 'Disposable migration connection', 'secret_hash' => hash( 'sha256', $secret ), 'created' => time(), 'expires' => time() + HOUR_IN_SECONDS, 'publishing' => 1, 'authoring' => 1 ) ), 'Seed connection' );
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $request = new WP_REST_Request( 'POST', Connections::PUBLISH_ROUTE ); $request->set_body( '{}' ); $request->set_header( 'authorization', 'Bearer sfc_' . $id . '_' . $secret );
    acm_good( Connections::guard( null, rest_get_server(), $request ) );
    $publication = acm_good( Mcp::execute( 'set-publication', array( 'articleId' => 'migration-published', 'expectedVersion' => 2, 'expectedPublicationVersion' => 0, 'requestId' => wp_generate_uuid4(), 'timing' => 'now', 'publishAt' => '', 'timeZone' => '', 'postType' => 'post' ) ) );
    acm_check( 'published' === $publication['state'], 'Old runtime created real published post' );
    Connections::finish( new WP_REST_Response(), rest_get_server(), $request ); wp_set_current_user( $admin );
    $term = acm_good( wp_insert_term( 'Migration category', 'category' ) ); wp_set_post_terms( $publication['postId'], array( $term['term_id'] ), 'category' );
    // Representative inactive OAuth/transfer records are independent of actual credential secrets.
    $wpdb->insert( $wpdb->prefix . 'atshde_oauth_tokens', array( 'id' => str_repeat( 'b', 80 ), 'kind' => 'access', 'connection_id' => $id, 'client_id' => wp_generate_uuid4(), 'owner_id' => $admin, 'resource' => home_url(), 'scopes' => 'sf:drafts', 'expires' => time() + 300, 'created' => time() ) );
    $wpdb->insert( $wpdb->prefix . 'atshde_oauth_flows', array( 'id' => str_repeat( 'c', 64 ), 'owner_id' => $admin, 'session_hash' => str_repeat( 'd', 64 ), 'payload' => '{}', 'expires' => time() + 300 ) );
    $wpdb->insert( $wpdb->prefix . 'atshde_transfers', array( 'transfer_id' => wp_generate_uuid4(), 'article_id' => 'migration-draft', 'version' => 2, 'digest' => str_repeat( 'a', 64 ), 'token_hash' => str_repeat( 'e', 64 ), 'filename' => 'pending.pdf', 'mime' => 'application/pdf', 'bytes' => 1, 'sha256' => str_repeat( 'f', 64 ), 'purpose' => 'reference', 'created_by' => $admin, 'expires' => time() + 300, 'connection_id' => $id ) );
    update_option( 'ac_migration_fixture', array( 'asset' => $asset, 'owner' => $author, 'post' => $publication['postId'], 'url' => get_permalink( $publication['postId'] ), 'connection' => $id, 'secret' => $secret ) );
    acm_check( ! Migration::inspect()['supported'], 'Refuse cutover while old plugin is active' );
} elseif ( 'guards' === $phase ) {
    $initial = Migration::inspect(); acm_check( $initial['supported'], 'Supported baseline: ' . implode( ', ', $initial['errors'] ) );
    acm_denied( static fn() => Migration::prepare( str_repeat( '0', 64 ), 'test-backup', true ), 'Reject stale fingerprint' );
    acm_denied( static fn() => Migration::prepare( $initial['fingerprint'], 'test-backup', false ), 'Reject missing confirmation' );
    acm_denied( static fn() => Migration::prepare( $initial['fingerprint'], '', true ), 'Reject missing backup reference' );
    acm_denied( static fn() => Migration::activate(), 'Reject activation before prepare' );
    acm_check( false === get_option( Migration::RECORD ), 'Rejected operations do not create migration state' );
    $fail = static function( $sql ) {
        if ( str_starts_with( $sql, 'UPDATE ' ) && str_contains( $sql, 'atshde_oauth_tokens' ) ) throw new RuntimeException( 'Injected token write failure' );
        return $sql;
    };
    add_filter( 'query', $fail );
    try { acm_denied( static fn() => Migration::prepare( $initial['fingerprint'], 'test-backup', true ), 'Preparation write failure stops before activation' ); }
    finally { remove_filter( 'query', $fail ); }
    acm_check( ! get_option( Migration::RECORD ) && $initial['fingerprint'] === Migration::inspect()['fingerprint'], 'Failed preparation rolls back credential changes and checkpoint' );
    wp_set_current_user( get_user_by( 'login', 'migration_author' )->ID );
    acm_denied( static fn() => Migration::inspect(), 'Author cannot inspect or migrate other users data' );
    wp_set_current_user( 0 ); acm_denied( static fn() => Migration::inspect(), 'Anonymous cannot inspect' ); wp_set_current_user( $admin );
    $prefix_state = get_option( 'atshde_prefix_migration' ); update_option( 'atshde_prefix_migration', 'pending' );
    acm_check( ! Migration::inspect()['supported'], 'Incomplete historical prefix migration blocks cutover' );
    if ( false === $prefix_state ) delete_option( 'atshde_prefix_migration' ); else update_option( 'atshde_prefix_migration', $prefix_state );
    wp_schedule_single_event( time() + 300, 'atshde_publish_article', array( 'fixture-job', 1 ) );
    acm_check( ! Migration::inspect()['supported'], 'Pending publication cron blocks migration' );
    wp_clear_scheduled_hook( 'atshde_publish_article', array( 'fixture-job', 1 ) );
    $table = $wpdb->prefix . 'atshde_publications';
    $wpdb->update( $table, array( 'state' => 'scheduled' ), array( 'article_id' => 'migration-published' ) );
    acm_check( ! Migration::inspect()['supported'], 'Scheduled record blocks migration even without cron' );
    $wpdb->update( $table, array( 'state' => 'published' ), array( 'article_id' => 'migration-published' ) );
    $legacy = $wpdb->prefix . 'sf_snapshots'; $wpdb->query( "CREATE TABLE $legacy (id int PRIMARY KEY) ENGINE=InnoDB" );
    acm_check( ! Migration::inspect()['supported'], 'Legacy table conflict blocks migration' ); $wpdb->query( "DROP TABLE $legacy" );
    $table = $wpdb->prefix . 'atshde_blob_chunks'; $chunk = $wpdb->get_row( "SELECT * FROM $table ORDER BY piece LIMIT 1", ARRAY_A );
    $where = array_intersect_key( $chunk, array_fill_keys( array( 'kind', 'identity', 'piece' ), true ) );
    $wpdb->update( $table, array( 'payload' => 'corrupt' ), $where );
    acm_check( ! Migration::inspect()['supported'], 'Corrupt chunks block migration' );
    $wpdb->update( $table, array( 'payload' => $chunk['payload'] ), $where );
    $wpdb->delete( $table, $where ); acm_check( ! Migration::inspect()['supported'], 'Missing media or filesystem-only media blocks migration' ); $wpdb->insert( $table, $chunk );
    $path = WP_PLUGIN_DIR . '/' . Migration::NEW_PLUGIN; $bytes = file_get_contents( $path );
    file_put_contents( $path, $bytes . "\n// changed\n" ); acm_check( ! Migration::inspect()['supported'], 'Changed target package rejected' ); file_put_contents( $path, $bytes );
    $table = $wpdb->prefix . 'atshde_snapshots'; $wpdb->query( "UPDATE $table SET css=CONCAT(css,' ') WHERE article_id='migration-draft' AND version=2" );
    acm_denied( static fn() => Migration::prepare( $initial['fingerprint'], 'backup', true ), 'Changed content invalidates inspection fingerprint' );
    $wpdb->query( "UPDATE $table SET css=LEFT(css,CHAR_LENGTH(css)-1) WHERE article_id='migration-draft' AND version=2" );
    acm_check( Migration::inspect()['fingerprint'] === $initial['fingerprint'], 'Guard tests restore exact baseline' );
} elseif ( 'prepared' === $phase ) {
    $record = get_option( Migration::RECORD ); acm_check( 'prepared' === $record['stage'], 'Prepared checkpoint persisted' );
    acm_denied( static fn() => Migration::prepare( '', 'test', true ), 'Do not repeat preparation' );
    foreach ( array( 'connections' => 'expires<>0 OR authoring<>0 OR publishing<>0', 'oauth_tokens' => 'revoked<>1', 'oauth_flows' => 'expires<>0', 'transfers' => 'revoked<>1' ) as $name => $where ) {
        acm_check( '0' === $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'atshde_' . $name . ' WHERE ' . $where ), 'Invalidate ' . $name );
    }
    acm_check( ! is_plugin_active( Migration::OLD_PLUGIN ) && ! is_plugin_active( Migration::NEW_PLUGIN ), 'Preparation does not activate or publish' );
    $table = $wpdb->prefix . 'atshde_snapshots'; $wpdb->query( "UPDATE $table SET css=CONCAT(css,' ') WHERE article_id='migration-draft' AND version=2" );
    acm_denied( static fn() => Migration::activate(), 'Changed prepared data stops target activation' );
    acm_check( ! is_plugin_active( Migration::NEW_PLUGIN ), 'Failed activation precondition keeps target inactive' );
    $wpdb->query( "UPDATE $table SET css=LEFT(css,CHAR_LENGTH(css)-1) WHERE article_id='migration-draft' AND version=2" );
} elseif ( 'behavior' === $phase ) {
    $fixture = get_option( 'ac_migration_fixture' );
    acm_check( '0.7.6' === Atshift\Deft\VERSION, 'New runtime loaded in fresh process' );
    acm_check( get_permalink( $fixture['post'] ) === $fixture['url'] && 'publish' === get_post_status( $fixture['post'] ), 'Published URL/status retained' );
    acm_check( 1 === count( wp_get_post_terms( $fixture['post'], 'category' ) ), 'Category link retained' );
    acm_check( 2 === (int) Store::read( 'migration-draft' )['version'] && Store::read( 'migration-draft', 1 ), 'Immutable revisions retained' );
    $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE asset_id=%s', Assets::table(), $fixture['asset'] ), ARRAY_A );
    acm_check( strlen( PrivateBlobs::get( 'asset', $fixture['asset'], $row ) ) === (int) $row['bytes'], 'New runtime reads preserved binary media' );
    acm_check( count( Catalog::listing( new WP_REST_Request( 'GET' ) )['items'] ) > 0, 'Shared component catalog available' );
    wp_set_current_user( $fixture['owner'] );
    acm_check( ! Atshift\Deft\Access::owns_article( 'migration-draft' ) && Atshift\Deft\Access::owns_article( 'migration-owner' ), 'Owner isolation retained' );
    $sample = json_decode( Store::read( 'migration-owner' )['payload'] ); $sample->article->revision++; $sample->article->title = 'After migration save';
    acm_good( Store::save( Snapshot::validate( Snapshot::json( $sample ) ), 1 ) );
    acm_check( 2 === (int) Store::read( 'migration-owner' )['version'], 'Owner can save a new revision' );
    wp_set_current_user( $admin );
    acm_denied( static fn() => Migration::verify(), 'Late edit changes receipt; helper will not falsely report equality' );
    $request = new WP_REST_Request( 'POST', Connections::PUBLISH_ROUTE ); $request->set_body( '{}' ); $request->set_header( 'authorization', 'Bearer sfc_' . $fixture['connection'] . '_' . $fixture['secret'] ); $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    acm_check( is_wp_error( Connections::guard( null, rest_get_server(), $request ) ), 'Old connection cannot authorize publication' );
} else throw new RuntimeException( 'Unknown phase' );
