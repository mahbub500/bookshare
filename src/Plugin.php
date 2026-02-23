<?php
namespace BookShare;

use BookShare\PostTypes\BookCPT;
use BookShare\PostTypes\AuthorCPT;
use BookShare\PostTypes\PublisherCPT;
use BookShare\Admin\AdminMenu;
use BookShare\Admin\BookMetaBox;
use BookShare\Admin\BookRequestAdmin;
use BookShare\Controllers\ShortcodeController;
use BookShare\API\RestAPI;

/**
 * Main Plugin Class — Singleton
 */
final class Plugin {

    private static ?Plugin $instance = null;

    private function __construct() {
        add_action( 'init',                  [ $this, 'register_post_types' ], 5  );
        add_action( 'init',                  [ $this, 'boot'               ], 10 );
        add_action( 'wp_head',               [ $this, 'inline_css'         ], 99 );
        add_action( 'wp_footer',             [ $this, 'inline_js'          ], 20 );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets'       ]     );
    }

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register_post_types(): void {
        ( new BookCPT() )->register();
        ( new AuthorCPT() )->register();
        ( new PublisherCPT() )->register();
    }

    public function boot(): void {
        AdminMenu::register();

        if ( is_admin() ) {
            new BookMetaBox();
            new BookRequestAdmin();
        }

        new RestAPI();
        new ShortcodeController();
    }

    /** Output CSS inline in <head> — works regardless of URL/permalink setup */
    public function inline_css(): void {
        if ( is_admin() ) return;
        $file = BS_PATH . 'assets/css/front.css';
        if ( ! file_exists( $file ) ) return;
        echo '<style id="bookshare-css">' . "\n";
        echo file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        echo "\n</style>\n";
    }

    /** Output JS config + script inline in footer — avoids enqueue URL problems */
    public function inline_js(): void {
        if ( is_admin() ) return;
        $file = BS_PATH . 'assets/js/front.js';
        if ( ! file_exists( $file ) ) return;

        $config = [
            'rest'      => esc_url_raw( rest_url( 'bookshare/v1/' ) ),
            'nonce'     => wp_create_nonce( 'wp_rest' ),
            'user_id'   => (string) get_current_user_id(),
            'is_admin'  => current_user_can( 'manage_options' ) ? '1' : '0',
            'is_logged' => is_user_logged_in() ? '1' : '0',
            'login_url' => wp_login_url(),
        ];

        echo '<script id="bookshare-js">' . "\n";
        echo 'window.BS = ' . wp_json_encode( $config ) . ";\n";
        echo file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        echo "\n</script>\n";
    }

    public function admin_assets( string $hook ): void {
        $screen = get_current_screen();
        if ( ! $screen ) return;

        $our_posts = [ 'bs_book', 'bs_author', 'bs_publisher' ];
        if ( ! in_array( $screen->post_type, $our_posts, true )
            && $screen->id !== 'toplevel_page_bookshare'
            && $screen->id !== 'bookshare_page_bs-requests' ) {
            return;
        }

        wp_enqueue_media();

        $admin_css = BS_PATH . 'assets/css/admin.css';
        if ( file_exists( $admin_css ) ) {
            add_action( 'admin_head', function () use ( $admin_css ) {
                echo '<style>' . file_get_contents( $admin_css ) . '</style>'; // phpcs:ignore
            } );
        }

        wp_enqueue_script( 'jquery' );
        $admin_js = BS_PATH . 'assets/js/admin.js';
        if ( file_exists( $admin_js ) ) {
            add_action( 'admin_footer', function () use ( $admin_js ) {
                echo '<script>' . file_get_contents( $admin_js ) . '</script>'; // phpcs:ignore
            } );
        }
    }
}