<?php
/**
 * Plugin Name:     BookCircle Community
 * Plugin URI:      https://bookcircle.community
 * Description:     Community book sharing plugin. Admin manages Books, Authors & Publishers via CPT. Readers build personal libraries and request to rent from each other.
 * Version:         2.0.0
 * Author:          Your Name
 * License:         GPL-2.0+
 * Text Domain:     bookshare
 * Requires PHP:    7.4
 * Requires at least: 5.8
 */

defined( 'ABSPATH' ) || exit;

define( 'BS_VERSION',  '2.0.0' );
define( 'BS_PATH',     plugin_dir_path( __FILE__ ) );
define( 'BS_URL',      plugin_dir_url( __FILE__ ) );
define( 'BS_BASENAME', plugin_basename( __FILE__ ) );

// ── PSR-4 Autoloader (no Composer required) ───────────────────────────────
spl_autoload_register( function ( string $class ) {
    $prefix = 'BookShare\\';
    if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) return;
    $relative = str_replace( '\\', DIRECTORY_SEPARATOR, substr( $class, strlen( $prefix ) ) );
    $file = BS_PATH . 'src/' . $relative . '.php';
    if ( file_exists( $file ) ) require_once $file;
} );

// Also support Composer autoloader if available
if ( file_exists( BS_PATH . 'vendor/autoload.php' ) ) {
    require_once BS_PATH . 'vendor/autoload.php';
}

// ── Activation / Deactivation hooks (must be outside plugins_loaded) ─────
register_activation_hook(   __FILE__, [ 'BookShare\\Installer', 'activate'   ] );
register_deactivation_hook( __FILE__, [ 'BookShare\\Installer', 'deactivate' ] );

// ── Boot plugin ───────────────────────────────────────────────────────────
function bookshare(): \BookShare\Plugin {
    return \BookShare\Plugin::instance();
}

add_action( 'plugins_loaded', 'bookshare' );