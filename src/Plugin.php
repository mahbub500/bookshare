<?php
namespace BookShare;

defined( 'ABSPATH' ) || exit;

use BookShare\Front\Helper;

final class Plugin {

    private static ?Plugin $instance = null;

    public static function instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    private function includes(): void {
        // Models, Controllers, API are autoloaded via PSR-4
    }

    private function init_hooks(): void {
        add_action( 'init',            [ $this, 'register_shortcodes' ] );
        add_action( 'rest_api_init',   [ API\RestAPI::class, 'register_routes' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_menu',      [ $this, 'register_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        add_action( 'rest_api_init', [ Controllers\ImportController::class, 'register_routes' ] );

        // Custom Post Types for Authors & Publishers        
        Cpt\BookCpt::register();
        Cpt\AuthorCpt::register();
        Cpt\PublisherCpt::register();

        if ( ! is_admin() ) {
            Helper::register();
        }
    }

    public function register_shortcodes(): void {
        // Single all-in-one shortcode
        add_shortcode( 'bookcircle',        [ Controllers\FrontController::class, 'dashboard' ] );
        // Legacy individual shortcodes still work
        add_shortcode( 'bookshare_catalog', [ Controllers\BookController::class,    'catalog_shortcode' ] );
        add_shortcode( 'bookshare_library', [ Controllers\LibraryController::class, 'library_shortcode' ] );
        add_shortcode( 'bookshare_search',  [ Controllers\BookController::class,    'search_shortcode' ] );
    }

    public function enqueue_assets(): void {

        if ( $hook !== 'bookshare_page_bookshare-import' ) {
            wp_enqueue_style(
                'bs-importer',
                BS_URL . 'assets/css/importer.css',
                [],
                BS_VERSION
            );

            wp_enqueue_script(
                'bs-importer',
                BS_URL . 'assets/js/importer.js',
                [ 'jquery' ],
                BS_VERSION,
                true  // load in footer
            );

            wp_localize_script( 'bs-importer', 'BSImporter', [
                'endpoint' => rest_url( 'bookshare/v1/import/rokomari' ),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'edit_url' => admin_url( 'post.php' ),
            ] );


        }

        global $post;
        $has_shortcode = is_a( $post, 'WP_Post' ) && (
            has_shortcode( $post->post_content, 'bookcircle' ) ||
            has_shortcode( $post->post_content, 'bookshare_catalog' ) ||
            has_shortcode( $post->post_content, 'bookshare_library' ) ||
            has_shortcode( $post->post_content, 'bookshare_search' )
        );

        if ( ! $has_shortcode ) return;

        wp_enqueue_style(
            'bookshare-css',
            BS_URL . 'assets/css/bookshare.css',
            [],
            BS_VERSION
        );

        wp_enqueue_script(
            'bookshare-js',
            BS_URL . 'assets/js/bookshare.js',
            ['jquery'],
            BS_VERSION,
            true
        );

        wp_localize_script( 'bookshare-js', 'BSConfig', [
            'root'    => esc_url_raw( rest_url( 'bookshare/v1/' ) ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'user_id' => get_current_user_id(),
            'logged_in' => is_user_logged_in(),
            'login_url' => wp_login_url( get_permalink() ),
        ] );
    }

    public function enqueue_admin_assets( string $hook ): void {

        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }        

        /*
         * Load CSS for Author & Publisher CPT
         */
        if ( in_array( $screen->post_type, [ 'bs_author', 'bs_publisher' ], true ) ) {

            wp_enqueue_style(
                'bookshare-authors-publisher-css',
                BS_URL . 'assets/css/author.css',
                [],
                BS_VERSION
            );
        }

        /*
         * Load assets for BookShare admin pages
         */
        if ( strpos( $hook, 'bookshare' ) !== false ) {

            wp_enqueue_style(
                'bookshare-admin-css',
                BS_URL . 'assets/css/bookshare-admin.css',
                [],
                BS_VERSION
            );

            wp_enqueue_script(
                'bookshare-admin-js',
                BS_URL . 'assets/js/bookshare-admin.js',
                [ 'jquery' ],
                BS_VERSION,
                true
            );

            wp_localize_script(
                'bookshare-admin-js',
                'BSAdmin',
                [
                    'root'     => esc_url_raw( rest_url( 'bookshare/v1/' ) ),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                ]
            );
        }
    }
    public function register_admin_menu(): void {
        add_menu_page(
            __( 'BookCircle', 'bookshare' ),
            __( 'BookCircle', 'bookshare' ),
            'manage_options',
            'bookshare',
            [ Controllers\AdminController::class, 'main_page' ],
            'dashicons-book-alt',
            25
        );
        // add_submenu_page( 'bookshare', __( 'Books',     'bookshare' ), __( 'Books',     'bookshare' ), 'manage_options', 'bookshare',              [ Controllers\AdminController::class, 'main_page' ] );
        // add_submenu_page( 'bookshare', __( 'Authors',   'bookshare' ), __( 'Authors',   'bookshare' ), 'manage_options', 'bookshare-authors',      [ Controllers\AdminController::class, 'authors_page' ] );
        // add_submenu_page( 'bookshare', __( 'Publishers','bookshare' ), __( 'Publishers','bookshare' ), 'manage_options', 'bookshare-publishers',   [ Controllers\AdminController::class, 'publishers_page' ] );
        add_submenu_page( 'bookshare', __( 'Rentals',   'bookshare' ), __( 'Rentals',   'bookshare' ), 'manage_options', 'bookshare-rentals',      [ Controllers\AdminController::class, 'rentals_page' ] );
        add_submenu_page( 'bookshare', __( 'Members',   'bookshare' ), __( 'Members',   'bookshare' ), 'manage_options', 'bookshare-members',      [ Controllers\AdminController::class, 'members_page' ] );
        add_submenu_page( 'bookshare', __( 'Analytics',  'bookshare' ), __( 'Analytics',  'Analytics' ), 'manage_options', 'bookshare-analytics',     [ Controllers\AdminController::class, 'analytics_page' ] );
        add_submenu_page(
            'bookshare',
            __( 'Import Books', 'bookshare' ),
            __( '📥 Import Books', 'bookshare' ),
            'manage_options',
            'bookshare-import',
            [ Controllers\ImportController::class, 'import_page' ]
        );

        add_submenu_page( 'bookshare', __( 'Settings',  'bookshare' ), __( 'Settings',  'bookshare' ), 'manage_options', 'bookshare-settings',     [ Controllers\AdminController::class, 'settings_page' ] );
    }
}
