<?php
namespace BookShare\Controllers;

/**
 * Book Controller — handles shortcodes for catalog display.
 */
class BookController {

    public function shortcode_catalog( array $atts ): string {
        ob_start();
        include BOOKSHARE_PATH . 'templates/catalog.php';
        return ob_get_clean();
    }

    public function shortcode_search( array $atts ): string {
        ob_start();
        include BOOKSHARE_PATH . 'templates/search.php';
        return ob_get_clean();
    }
}