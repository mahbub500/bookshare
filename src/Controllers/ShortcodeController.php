<?php
namespace BookShare\Controllers;

/**
 * Registers all frontend shortcodes.
 */
class ShortcodeController {

    public function __construct() {
        add_shortcode( 'bookcircle_catalog', [ $this, 'catalog' ] );
        add_shortcode( 'bookcircle_library', [ $this, 'library' ] );
        add_shortcode( 'bookcircle_search',  [ $this, 'search'  ] );
    }

    public function catalog(): string {
        ob_start();
        include BS_PATH . 'templates/catalog.php';
        return ob_get_clean();
    }

    public function library(): string {
        ob_start();
        include BS_PATH . 'templates/library.php';
        return ob_get_clean();
    }

    public function search(): string {
        ob_start();
        include BS_PATH . 'templates/search.php';
        return ob_get_clean();
    }
}