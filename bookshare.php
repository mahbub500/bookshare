<?php
/**
 * Plugin Name: BookCircle – Community Book Sharing
 * Plugin URI:  https://bookcircle.community
 * Description: Community book listing, personal libraries, and peer-to-peer book rental requests.
 * Version:     1.0.0
 * Author:      BookCircle
 * License:     GPL-2.0+
 * Text Domain: bookshare
 */

defined( 'ABSPATH' ) || exit;

define( 'BS_VERSION',  '1.0.0' );
define( 'BS_FILE',     __FILE__ );
define( 'BS_DIR',      plugin_dir_path( __FILE__ ) );
define( 'BS_URL',      plugin_dir_url( __FILE__ ) );

require_once BS_DIR . 'vendor/autoload.php';

register_activation_hook(   __FILE__, [ 'BookShare\\Installer', 'run' ] );
register_deactivation_hook( __FILE__, [ 'BookShare\\Installer', 'deactivate' ] );

add_action( 'plugins_loaded', function () {
    BookShare\Plugin::instance();
} );
