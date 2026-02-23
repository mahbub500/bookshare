<?php
/**
 * Plugin Name:     BookShare Community
 * Plugin URI:      https://bookshare.community
 * Description:     A community book listing, library, and rental plugin. Members can list books, create personal libraries, and request to rent from others.
 * Version:         1.0.0
 * Author:          Mahbub
 * License:         GPL-2.0+
 * Text Domain:     bookshare
 */

defined( 'ABSPATH' ) || exit;

define( 'BOOKSHARE_VERSION', '1.0.0' );
define( 'BOOKSHARE_PATH', plugin_dir_path( __FILE__ ) );
define( 'BOOKSHARE_URL', plugin_dir_url( __FILE__ ) );

require_once BOOKSHARE_PATH . 'vendor/autoload.php';

use BookShare\Plugin;

/**
 * Main plugin instance.
 */
function bookshare(): Plugin {
    return Plugin::instance();
}

bookshare();