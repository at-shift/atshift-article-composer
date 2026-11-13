<?php
require_once dirname( __DIR__ ) . '/.local/wordpress/wp-includes/pomo/mo.php';
function __( $text, $domain = 'default' ) {
    global $locale;
    if ( 'atshift-article-composer' !== $domain || 'ja' !== $locale ) return $text;
    static $catalog;
    if ( ! $catalog ) {
        $catalog = new MO();
        if ( ! $catalog->import_from_file( dirname( __DIR__ ) . '/wordpress/atshift-article-composer/languages/atshift-article-composer-ja.mo' ) ) throw new RuntimeException( 'Japanese catalog unavailable.' );
    }
    return $catalog->translate( $text );
}
