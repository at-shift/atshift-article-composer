<?php
namespace Atshift\Deft;

defined( 'ABSPATH' ) || exit;

function boot() {
    require_once __DIR__ . '/plugin.php';
}
if ( did_action( 'plugins_loaded' ) ) boot();
else add_action( 'plugins_loaded', __NAMESPACE__ . '\\boot', 0 );
