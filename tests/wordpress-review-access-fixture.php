<?php
// These three test-only MU files register post types/taxonomies, never restrictions.
if ( 'local' !== wp_get_environment_type() || ! preg_match( '/^sf_deft_prefix_[a-f0-9]+$/D', DB_NAME ) ) return;
add_filter( 'atshift_article_composer_access_integrations', static function( $integrations ) {
    foreach ( array( 'post-types-fixture.php', 'taxonomies-fixture.php', 'review-access-fixture.php' ) as $file ) $integrations['mu:' . $file] = '__return_true';
    return $integrations;
} );
