<?php
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() ||
    ! preg_match( '/^sf_deft_members_[a-f0-9]+$/D', DB_NAME ) ) throw new RuntimeException( 'Use the membership runner.' );
use Atshift\Deft\{PublicationAccess, PublicStructure};
global $checks;
$checks = 0;
function acj_check( $ok, $label ) { global $checks; if ( ! $ok ) throw new RuntimeException( $label ); ++$checks; }
$fixture = get_option( 'ac_members_fixture' ); $id = $fixture['post'];
wp_set_current_user( 0 );
acj_check( 404 === rest_do_request( new WP_REST_Request( 'GET', '/structured-fields/v1/public-structure/' . $id ) )->get_status(), 'No independent raw reader remains.' );
$content = '<div class="sf-article"><section class="sf-built-columns"><h2>Visible</h2><p>Text &amp; more</p><details><summary>Question</summary><p>Answer</p></details><img src="/image.png" alt="Image"><a href="javascript:alert(1)">Unsafe link</a><p hidden>HIDDEN_SENTINEL</p><script>EXECUTABLE_SENTINEL</script></section></div>';
$data = PublicStructure::from_content( $id, $content );
acj_check( is_array( $data ), 'Current viewer can project provided visible content.' );
acj_check( null === PublicStructure::from_content( $id, '<div class="sf-article"></div>' ), 'Empty content has no metadata projection.' );
$json = wp_json_encode( $data );
foreach ( array( 'HIDDEN_SENTINEL', 'EXECUTABLE_SENTINEL', 'javascript:', 'PRIVATE_IMAGE_SENTINEL', 'payload', 'componentRevision' ) as $secret ) acj_check( ! str_contains( $json, $secret ), 'No saved body or hidden field: ' . $secret );
acj_check( str_contains( $json, 'columns' ) && str_contains( $json, 'Answer' ), 'Layout hints and expandable text remain.' );
foreach ( array( '<div>Outside</div>', '<div class="sf-article"></div><div class="sf-article"></div>',
    '<div class="sf-article">' . str_repeat( '<div>', 66 ) . 'deep' . str_repeat( '</div>', 66 ) . '</div>',
    '<div class="sf-article">' . str_repeat( '<br>', 10001 ) . '</div>', str_repeat( 'a', 4 * 1024 * 1024 + 1 ) ) as $invalid ) acj_check( null === PublicStructure::project( $invalid ), 'Ambiguous/oversized structure fails closed.' );
$title = static fn() => 'Displayed &lt;/script&gt;&lt;script&gt;bad&lt;/script&gt;';
add_filter( 'the_title', $title );
$query = new WP_Query( array( 'p' => $id ) ); $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = $query; $query->the_post();
$html = PublicStructure::append( $content );
acj_check( 2 === substr_count( $html, '<script type=' ) && 3 === substr_count( $html, '</script>' ), 'Only two encoded JSON scripts are appended to provided HTML.' );
acj_check( ! str_contains( $html, '<script>bad' ), 'Displayed title cannot break out of JSON.' );
remove_filter( 'the_title', $title );
add_filter( 'atshift_deft_json_ld_enabled', '__return_false' );
acj_check( ! str_contains( PublicStructure::append( $content ), 'application/ld+json' ), 'JSON-LD opt-out does not require suppressing structure.' );
remove_filter( 'atshift_deft_json_ld_enabled', '__return_false' );
add_filter( 'atshift_deft_public_structure_enabled', '__return_false' );
acj_check( $content === PublicStructure::append( $content ), 'Structure veto preserves ordinary content.' );
acj_check( PublicationAccess::allows( $id ), 'Structure veto does not change media authorization.' );
remove_filter( 'atshift_deft_public_structure_enabled', '__return_false' );
acj_check( '<p>Restricted</p>' === PublicStructure::append( '<p>Restricted</p>' ), 'Restriction message never causes raw-content fallback.' );
$query->is_feed = true; acj_check( $content === PublicStructure::append( $content ), 'No feed projection.' ); $query->is_feed = false;
$query->in_the_loop = false; acj_check( $content === PublicStructure::append( $content ), 'No secondary rendering projection.' );
$unknown = static function( $integrations ) { $integrations['theme:twentytwentyfive'] = static fn() => null; return $integrations; };
add_filter( 'atshift_article_composer_access_integrations', $unknown ); acj_check( 'unknown' === PublicationAccess::decision( $id ), 'Unknown result cannot authorize.' );
remove_filter( 'atshift_article_composer_access_integrations', $unknown );
$recursive = static function( $integrations ) { $integrations['theme:twentytwentyfive'] = static fn( $post ) => PublicationAccess::allows( $post ); return $integrations; };
add_filter( 'atshift_article_composer_access_integrations', $recursive ); acj_check( ! PublicationAccess::allows( $id ), 'Recursive provider fails closed.' );
remove_filter( 'atshift_article_composer_access_integrations', $recursive );
echo "Structure checks: $checks\n";
