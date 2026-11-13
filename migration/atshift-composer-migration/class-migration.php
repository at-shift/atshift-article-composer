<?php
namespace Atshift\ComposerMigration;

defined( 'ABSPATH' ) || exit;

/** One-time, deliberately narrow migration. No runtime compatibility layer. */
final class Migration {
    const OLD_PLUGIN = 'atshift-deft/atshift-deft.php';
    const NEW_PLUGIN = 'atshift-article-composer/atshift-article-composer.php';
    const RECORD = 'atshift_composer_migration_074_076';
    const TABLES = array( 'snapshots', 'draft_states', 'assets', 'blob_chunks', 'transfers', 'reviews', 'catalog', 'components', 'component_usage', 'connections', 'oauth_clients', 'oauth_tokens', 'oauth_flows', 'publications' );
    const AUTH = array( 'connections', 'oauth_tokens', 'oauth_flows', 'transfers' );

    public static function authorize() {
        if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'activate_plugins' ) ||
            is_multisite() || wp_is_file_mod_allowed( 'atshift_composer_migration' ) === false ) {
            throw new \RuntimeException( 'A single-site administrator with plugin management permission is required.' );
        }
    }

    private static function table( $suffix ) { global $wpdb; return $wpdb->prefix . 'atshde_' . $suffix; }

    private static function query( $sql ) {
        global $wpdb;
        $result = $wpdb->query( $sql );
        if ( false === $result ) throw new \RuntimeException( 'Database operation failed. Stop and inspect the private database log.' );
        return $result;
    }

    private static function rows( $sql ) {
        global $wpdb;
        $rows = $wpdb->get_results( $sql, ARRAY_A );
        if ( ! is_array( $rows ) || $wpdb->last_error ) throw new \RuntimeException( 'Database inspection failed.' );
        return $rows;
    }

    private static function digest( $value ) { return hash( 'sha256', serialize( $value ) ); }

    private static function package( $key ) {
        $manifest = json_decode( file_get_contents( __DIR__ . '/packages.json' ), true, 512, JSON_THROW_ON_ERROR );
        $entry = $manifest[$key];
        $base = WP_PLUGIN_DIR . '/' . $entry['slug'];
        if ( ! is_dir( $base ) || is_link( $base ) ) throw new \RuntimeException( 'Install both supported ZIPs in their original folders. Symlinks are not supported.' );
        $actual = array();
        $iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $base, \FilesystemIterator::SKIP_DOTS ) );
        foreach ( $iterator as $file ) {
            if ( $file->isLink() || ! $file->isFile() ) throw new \RuntimeException( 'Unsupported plugin filesystem entry.' );
            $name = str_replace( DIRECTORY_SEPARATOR, '/', substr( $file->getPathname(), strlen( $base ) + 1 ) );
            if ( '.DS_Store' === basename( $name ) ) continue;
            $actual[$name] = hash_file( 'sha256', $file->getPathname() );
        }
        ksort( $actual ); $expected = $entry['files']; ksort( $expected );
        if ( $actual !== $expected ) throw new \RuntimeException( 'Package files differ from the supported GitHub releases (DeFt 0.7.4 and Article Composer 0.7.6). Do not bypass this check.' );
        return $entry['zipSha256'];
    }

    private static function table_digest( $table ) {
        global $wpdb;
        $keys = self::rows( $wpdb->prepare( 'SHOW KEYS FROM %i WHERE Key_name=%s', $table, 'PRIMARY' ) );
        usort( $keys, static fn( $a, $b ) => (int) $a['Seq_in_index'] <=> (int) $b['Seq_in_index'] );
        if ( ! $keys ) throw new \RuntimeException( 'A table has no primary key.' );
        $columns = array_column( $keys, 'Column_name' );
        foreach ( $columns as $column ) if ( ! preg_match( '/^[a-zA-Z0-9_]+$/D', $column ) ) throw new \RuntimeException( 'Unsupported schema.' );
        $order = implode( ',', array_map( static fn( $name ) => '`' . $name . '`', $columns ) );
        $hash = hash_init( 'sha256' ); $count = 0;
        do {
            // Bounded pages also handle binary chunks without JSON/charset conversion.
            $rows = self::rows( $wpdb->prepare( "SELECT * FROM %i ORDER BY $order LIMIT 100 OFFSET %d", $table, $count ) );
            foreach ( $rows as $row ) hash_update( $hash, serialize( $row ) );
            $count += count( $rows );
            if ( $count > 100000 ) throw new \RuntimeException( 'Dataset exceeds this helper limit; use a reviewed migration plan.' );
        } while ( 100 === count( $rows ) );
        return array( 'rows' => $count, 'sha256' => hash_final( $hash ) );
    }

    private static function blob( $kind, $identity, $meta ) {
        global $wpdb;
        $size = (int) ( $meta['bytes'] ?? 0 );
        if ( $size < 1 || $size > 10 * 1024 * 1024 || ! preg_match( '/^[a-f0-9]{64}$/D', $meta['sha256'] ?? '' ) ) throw new \RuntimeException( 'Invalid media metadata.' );
        $rows = self::rows( $wpdb->prepare( 'SELECT piece,payload FROM %i WHERE kind=%s AND identity=%s ORDER BY piece LIMIT 162', self::table( 'blob_chunks' ), $kind, hash( 'sha256', $identity ) ) );
        if ( count( $rows ) !== (int) ceil( $size / 65536 ) ) throw new \RuntimeException( 'Private media is missing from database storage. Legacy filesystem files require a separate import; the helper will not discard them.' );
        $hash = hash_init( 'sha256' ); $total = 0;
        foreach ( $rows as $i => $row ) {
            $length = strlen( $row['payload'] );
            if ( (int) $row['piece'] !== $i || $length !== min( 65536, $size - $total ) ) throw new \RuntimeException( 'Private media chunks are incomplete.' );
            hash_update( $hash, $row['payload'] ); $total += $length;
        }
        if ( ! hash_equals( $meta['sha256'], hash_final( $hash ) ) ) throw new \RuntimeException( 'Private media checksum mismatch.' );
    }

    private static function content() {
        global $wpdb;
        $tables = array();
        foreach ( self::TABLES as $suffix ) $tables[$suffix] = self::table_digest( self::table( $suffix ) );
        $snapshots = self::rows( $wpdb->prepare( 'SELECT article_id,post_id,created_by FROM %i ORDER BY article_id,version', self::table( 'snapshots' ) ) );
        $jobs = self::rows( $wpdb->prepare( 'SELECT * FROM %i ORDER BY article_id', self::table( 'publications' ) ) );
        $post_ids = $owners = array();
        foreach ( $snapshots as $row ) {
            $post = get_post( (int) $row['post_id'] );
            if ( ! $post || 'atshde_article' !== $post->post_type || 'draft' !== $post->post_status || (int) $post->post_author !== (int) $row['created_by'] ) throw new \RuntimeException( 'A draft post or owner is inconsistent.' );
            $post_ids[(int) $row['post_id']] = true; $owners[(int) $row['created_by']] = true;
        }
        foreach ( $jobs as $job ) {
            if ( ! in_array( $job['state'], array( 'published', 'cancelled', 'failed' ), true ) ) throw new \RuntimeException( 'Resolve pending or processing reservations in DeFt before migrating. No reservation is published or cancelled automatically.' );
            if ( $job['post_id'] ) {
                $post = get_post( (int) $job['post_id'] );
                if ( ! $post || ! post_type_exists( $post->post_type ) || in_array( $post->post_status, array( 'future', 'atshde_reserved', 'atshde_reserve_hold' ), true ) ) throw new \RuntimeException( 'A destination post needs manual review.' );
                if ( (int) $post->post_author !== (int) $job['owner_id'] || $post->post_type !== $job['post_type'] ) throw new \RuntimeException( 'A publication destination or owner is inconsistent.' );
                $post_ids[(int) $job['post_id']] = true;
            }
            $owners[(int) $job['owner_id']] = true;
            $files = json_decode( $job['files'], true, 512, JSON_THROW_ON_ERROR );
            if ( ! is_array( $files ) ) throw new \RuntimeException( 'Invalid publication media manifest.' );
            foreach ( $files as $meta ) self::blob( 'publication', $job['publication_id'] . ':' . $meta['sha256'], $meta );
        }
        $assets = self::rows( $wpdb->prepare( 'SELECT * FROM %i ORDER BY asset_id', self::table( 'assets' ) ) );
        foreach ( $assets as $asset ) { self::blob( 'asset', $asset['asset_id'], $asset ); $owners[(int) $asset['created_by']] = true; }
        $posts = array(); ksort( $post_ids );
        foreach ( array_keys( $post_ids ) as $id ) {
            $post = get_post( $id, ARRAY_A );
            $meta = self::rows( $wpdb->prepare( 'SELECT meta_key,meta_value FROM %i WHERE post_id=%d ORDER BY meta_id', $wpdb->postmeta, $id ) );
            $terms = self::rows( $wpdb->prepare( 'SELECT term_taxonomy_id,term_order FROM %i WHERE object_id=%d ORDER BY term_taxonomy_id', $wpdb->term_relationships, $id ) );
            $posts[$id] = array( $post, $meta, $terms, 'atshde_article' === $post['post_type'] ? '' : get_permalink( $id ) );
        }
        foreach ( array_keys( $owners ) as $owner ) if ( ! $owner || ! get_userdata( $owner ) ) throw new \RuntimeException( 'A saved owner no longer exists. Resolve ownership before migrating.' );
        $roles = (array) get_option( $wpdb->prefix . 'user_roles', array() );
        foreach ( $roles as &$role ) unset( $role['name'] ); unset( $role );
        $user_caps = self::rows( $wpdb->prepare( 'SELECT user_id,meta_key,meta_value FROM %i WHERE meta_key=%s ORDER BY user_id,umeta_id', $wpdb->usermeta, $wpdb->prefix . 'capabilities' ) );
        $protected = array_diff_key( $tables, array_fill_keys( self::AUTH, true ) );
        return array( 'tables' => $tables, 'postCount' => count( $posts ), 'ownerCount' => count( $owners ), 'mediaCount' => count( $assets ),
            'contentHash' => self::digest( array( $protected, $posts, $roles, $user_caps, get_option( 'permalink_structure' ), home_url() ) ) );
    }

    public static function inspect( $after = false ) {
        self::authorize(); global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $report = array( 'supported' => false, 'source' => '0.7.4', 'target' => '0.7.6', 'errors' => array(), 'warnings' => array(
            'Backups are not created by this helper. Verify a complete database/files backup and restoration on a separate copy.',
            'Stop AI sessions, editing, external cron and other writers for the cutover. Keep the public site in maintenance during the switch.',
            'Existing AI keys, OAuth tokens, pending login flows and transfer tokens are invalidated. Reconnect each account afterward.',
            'New access protections can withhold structure JSON and managed images/PDFs for unverified membership/theme combinations. Check real pages before leaving maintenance.',
        ) );
        try {
            if ( version_compare( get_bloginfo( 'version' ), '6.9', '<' ) || PHP_VERSION_ID < 80200 ) throw new \RuntimeException( 'WordPress 6.9+ and PHP 8.2+ are required.' );
            $report['packages'] = array( self::package( 'old' ), self::package( 'new' ) );
            if ( is_plugin_active( self::OLD_PLUGIN ) || ( ! $after && is_plugin_active( self::NEW_PLUGIN ) ) ) throw new \RuntimeException( 'Deactivate DeFt and leave Article Composer inactive, then inspect again in a fresh request. Do not delete either plugin.' );
            if ( ! is_plugin_active( 'mcp-adapter/mcp-adapter.php' ) || ! defined( 'WP_MCP_VERSION' ) || version_compare( WP_MCP_VERSION, '0.7.1', '<' ) ) throw new \RuntimeException( 'Activate the official MCP Adapter 0.7.1 or newer first.' );
            if ( 'pending' === get_option( 'atshde_prefix_migration' ) ) throw new \RuntimeException( 'The old prefix migration is incomplete. Resolve it before this cutover.' );
            foreach ( self::TABLES as $suffix ) {
                $legacy = $wpdb->prefix . 'sf_' . $suffix;
                if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $legacy ) ) ) ) throw new \RuntimeException( 'Legacy sf_ tables or a naming conflict were found. This helper does not rename or merge them.' );
                $table = self::table( $suffix );
                if ( 'InnoDB' !== $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) ) ) throw new \RuntimeException( 'A required atshde_ table is missing or not InnoDB.' );
            }
            if ( 'InnoDB' !== $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $wpdb->options ) ) ) throw new \RuntimeException( 'The options table must support transactions.' );
            foreach ( (array) _get_cron_array() as $hooks ) if ( isset( $hooks['atshde_publish_article'] ) || isset( $hooks['sf_publish_article'] ) ) throw new \RuntimeException( 'A publication cron event remains. Resolve it in the old plugin; do not delete it blindly.' );
            $report['inventory'] = self::content();
            $report['fingerprint'] = self::digest( array( $report['packages'], $report['inventory'], (array) get_option( 'active_plugins' ), DB_NAME, $wpdb->prefix ) );
            $report['supported'] = true;
        } catch ( \Throwable $error ) { $report['errors'][] = $error->getMessage(); }
        return $report;
    }

    private static function locked( $callback ) {
        self::authorize(); global $wpdb;
        $lock = 'atshift_migrate_' . substr( hash( 'sha256', DB_NAME . ':' . $wpdb->prefix ), 0, 40 );
        if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,0)', $lock ) ) ) throw new \RuntimeException( 'Another migration is running.' );
        try { return $callback(); }
        finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
    }

    public static function prepare( $fingerprint, $backup, $confirmed ) {
        return self::locked( static function() use ( $fingerprint, $backup, $confirmed ) {
            global $wpdb;
            if ( true !== $confirmed || ! is_string( $backup ) || strlen( trim( $backup ) ) < 3 || strlen( $backup ) > 160 || sanitize_text_field( $backup ) !== $backup ) throw new \RuntimeException( 'Confirm the tested backup, rehearsal, stopped writers and AI reconnection. Supply a backup reference, never a credential.' );
            if ( get_option( self::RECORD ) ) throw new \RuntimeException( 'A migration record already exists. Continue verification; do not repeat preparation.' );
            if ( defined( 'Atshift\\Deft\\PLUGIN_FILE' ) ) throw new \RuntimeException( 'Start a fresh request with both packages inactive.' );
            $report = self::inspect();
            if ( ! $report['supported'] || ! is_string( $fingerprint ) || ! hash_equals( $report['fingerprint'], $fingerprint ) ) throw new \RuntimeException( 'Preflight changed or is blocked. Inspect again before making changes.' );
            self::query( 'START TRANSACTION' );
            try {
                self::query( $wpdb->prepare( 'UPDATE %i SET expires=0,authoring=0,publishing=0', self::table( 'connections' ) ) );
                self::query( $wpdb->prepare( 'UPDATE %i SET revoked=1', self::table( 'oauth_tokens' ) ) );
                self::query( $wpdb->prepare( 'UPDATE %i SET expires=0', self::table( 'oauth_flows' ) ) );
                self::query( $wpdb->prepare( 'UPDATE %i SET revoked=1', self::table( 'transfers' ) ) );
                $inventory = self::content();
                if ( ! hash_equals( $report['inventory']['contentHash'], $inventory['contentHash'] ) ) throw new \RuntimeException( 'Content changed during preparation. No cutover was performed.' );
                $record = array( 'stage' => 'prepared', 'created' => gmdate( 'c' ), 'administrator' => get_current_user_id(), 'backupReference' => $backup,
                    'contentHash' => $inventory['contentHash'], 'inventory' => $inventory, 'packages' => $report['packages'] );
                if ( ! add_option( self::RECORD, $record, '', false ) ) throw new \RuntimeException( 'Could not save the migration record.' );
                self::query( 'COMMIT' );
                return $record;
            } catch ( \Throwable $error ) { $wpdb->query( 'ROLLBACK' ); wp_cache_delete( self::RECORD, 'options' ); throw $error; }
        } );
    }

    public static function activate() {
        return self::locked( static function() {
            $record = get_option( self::RECORD );
            if ( ! is_array( $record ) || 'prepared' !== $record['stage'] ) throw new \RuntimeException( 'Complete preparation first; an interrupted activation requires inspection, not blind retry.' );
            if ( defined( 'Atshift\\Deft\\PLUGIN_FILE' ) ) throw new \RuntimeException( 'Activate in a fresh request with both packages inactive.' );
            $report = self::inspect();
            if ( ! $report['supported'] || $record['inventory'] !== $report['inventory'] || $record['packages'] !== $report['packages'] ) throw new \RuntimeException( 'The prepared dataset or packages changed. Stop and investigate.' );
            $record['stage'] = 'activating'; update_option( self::RECORD, $record, false );
            $result = activate_plugin( self::NEW_PLUGIN );
            if ( is_wp_error( $result ) ) throw new \RuntimeException( 'Target activation failed. Keep maintenance enabled and inspect the plugin error.' );
            $record['stage'] = 'activated'; update_option( self::RECORD, $record, false );
            return $record;
        } );
    }

    public static function verify() {
        return self::locked( static function() {
            $record = get_option( self::RECORD );
            if ( ! is_array( $record ) || ! in_array( $record['stage'], array( 'activating', 'activated', 'verified' ), true ) ) throw new \RuntimeException( 'No activated migration to verify.' );
            if ( ! defined( 'Atshift\\Deft\\VERSION' ) || '0.7.6' !== \Atshift\Deft\VERSION || ! defined( 'Atshift\\Deft\\PLUGIN_FILE' ) || plugin_basename( \Atshift\Deft\PLUGIN_FILE ) !== self::NEW_PLUGIN ) throw new \RuntimeException( 'Verify in a fresh request with Article Composer 0.7.6 active.' );
            $report = self::inspect( true );
            if ( ! $report['supported'] || ! is_plugin_active( self::NEW_PLUGIN ) || $record['inventory'] !== $report['inventory'] || $record['packages'] !== $report['packages'] ) throw new \RuntimeException( 'Verification differs from the prepared snapshot. Keep maintenance enabled and use the backup recovery procedure.' );
            $record['stage'] = 'verified'; $record['verified'] = gmdate( 'c' ); update_option( self::RECORD, $record, false );
            return array( 'stage' => 'verified', 'inventory' => $report['inventory'], 'manualChecksRequired' => array( 'Draft save/preview per owner', 'Public URLs and managed media access', 'Membership restrictions', 'Reconnect AI and test without publishing' ) );
        } );
    }
}
