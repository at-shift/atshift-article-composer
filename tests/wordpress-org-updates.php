<?php
// Fresh WP-CLI process after the simulated GitHub-to-directory update.
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() || ! preg_match( '/^sf_deft_prefix_[a-f0-9]+$/D', DB_NAME ) ) {
    throw new RuntimeException( 'Use a disposable local review database.' );
}
$file = 'atshift-article-composer/atshift-article-composer.php';
$version = getenv( 'SF_HANDOFF_VERSION' );
$next = getenv( 'SF_DIRECTORY_NEXT_VERSION' );
$checks = 0;
$check = static function( $ok, $message ) use ( &$checks ) {
    if ( ! $ok ) throw new RuntimeException( $message );
    ++$checks;
};
$header = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );
$check( $version === $header['Version'], 'Handoff version survives a new request.' );
$check( '' === $header['UpdateURI'], 'Directory updates are no longer excluded by Update URI.' );
$check( ! class_exists( Atshift\Deft\GitHubUpdater::class ), 'GitHub updater is no longer loaded.' );
$check( is_plugin_active( $file ), 'Plugin remains active after handoff.' );
$check( ! has_filter( 'update_plugins_github.com' ), 'No GitHub update hook remains.' );
$requests = array();
$http = static function( $reply, $args, $url ) use ( &$requests, $file, $next ) {
    $requests[] = $url;
    if ( str_starts_with( $url, 'https://api.wordpress.org/plugins/update-check/' ) ) {
        $plugins = json_decode( $args['body']['plugins'], true );
        if ( ! isset( $plugins['plugins'][$file] ) ) throw new RuntimeException( 'Canonical plugin identity missing from core update request.' );
        return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => wp_json_encode( array(
            'plugins' => array( $file => array( 'slug' => 'atshift-article-composer', 'plugin' => $file,
                'new_version' => $next, 'package' => 'https://downloads.wordpress.org/plugin/atshift-article-composer.' . $next . '.zip',
                'url' => 'https://wordpress.org/plugins/atshift-article-composer/' ) ),
            'no_update' => array(), 'translations' => array(),
        ) ) );
    }
    return new WP_Error( 'fixture_external_http', 'External HTTP blocked by test.' );
};
add_filter( 'pre_http_request', $http, 10, 3 );
try {
    delete_site_transient( 'update_plugins' );
    wp_update_plugins();
    $update = get_site_transient( 'update_plugins' )->response[$file] ?? null;
    $check( $update && $next === $update->new_version, 'WordPress core now offers the directory update.' );
    $check( $update && str_starts_with( $update->package, 'https://downloads.wordpress.org/plugin/atshift-article-composer.' ), 'Package comes from the official directory.' );
    $check( count( $requests ) === 1 && ! str_contains( $requests[0], 'github' ), 'Only the standard WordPress update API is contacted.' );
    echo wp_json_encode( array( 'directory_handoff_checks' => $checks, 'installed' => $version, 'offered' => $next ) ), PHP_EOL;
} finally {
    remove_filter( 'pre_http_request', $http, 10 );
    delete_site_transient( 'update_plugins' );
}
