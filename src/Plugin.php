<?php
namespace BookShare;

use BookShare\Controllers\BookController;
use BookShare\Controllers\LibraryController;
use BookShare\Controllers\RentalController;
use BookShare\API\RestAPI;

/**
 * Main Plugin Singleton Class
 */
final class Plugin {

    private static ?Plugin $instance = null;

    public BookController   $books;
    public LibraryController $library;
    public RentalController  $rental;
    public RestAPI           $api;

    private function __construct() {
        $this->init_hooks();
    }

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init_hooks(): void {
        register_activation_hook( BOOKSHARE_PATH . 'bookshare.php', [ Installer::class, 'activate' ] );
        register_deactivation_hook( BOOKSHARE_PATH . 'bookshare.php', [ Installer::class, 'deactivate' ] );

        add_action( 'init', [ $this, 'boot' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function boot(): void {
        $this->books   = new BookController();
        $this->library = new LibraryController();
        $this->rental  = new RentalController();
        $this->api     = new RestAPI();

        // Register shortcodes
        add_shortcode( 'bookshare_catalog',  [ $this->books,   'shortcode_catalog'  ] );
        add_shortcode( 'bookshare_library',  [ $this->library, 'shortcode_library'  ] );
        add_shortcode( 'bookshare_search',   [ $this->books,   'shortcode_search'   ] );
    }

    public function enqueue_assets(): void {
        wp_enqueue_style(
            'bookshare-style',
            BOOKSHARE_URL . 'assets/css/bookshare.css',
            [],
            BOOKSHARE_VERSION
        );

        wp_enqueue_script(
            'bookshare-app',
            BOOKSHARE_URL . 'assets/js/bookshare.js',
            [ 'jquery' ],
            BOOKSHARE_VERSION,
            true
        );

        wp_localize_script( 'bookshare-app', 'BookShare', [
            'rest_url'   => esc_url_raw( rest_url( 'bookshare/v1/' ) ),
            'nonce'      => wp_create_nonce( 'wp_rest' ),
            'user_id'    => get_current_user_id(),
            'is_logged'  => is_user_logged_in(),
        ] );
    }
}