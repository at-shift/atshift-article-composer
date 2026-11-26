<?php
/**
 * Plugin Name: atshift Article Composer
 * Description: Delegate execution. Focus on thinking. Create structured articles with your preferred AI.
 * Version: 1.0.2
 * Author: at-shift
 * Author URI: https://plugins.at-shift.net/
 * Requires at least: 6.9
 * Requires PHP: 8.2
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: atshift-article-composer
 * Domain Path: /languages
 */

namespace Atshift\Deft;

defined( 'ABSPATH' ) || exit;

if ( ! ( require __DIR__ . '/includes/channel-guard.php' )( __FILE__ ) ) return;
require_once __DIR__ . '/includes/i18n.php';
\Atshift\Deft\register_translations( __FILE__ );
require_once __DIR__ . '/includes/runtime.php';
if ( ! runtime_guard( PHP_VERSION_ID ) ) return;

const VERSION = '1.0.2';
const PLUGIN_FILE = __FILE__;
require_once __DIR__ . '/includes/bootstrap.php';
