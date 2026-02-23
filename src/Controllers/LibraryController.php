<?php
namespace BookShare\Controllers;

/**
 * Library Controller — handles shortcode for user library display.
 */
class LibraryController {

    public function shortcode_library( array $atts ): string {
        ob_start();
        include BOOKSHARE_PATH . 'templates/library.php';
        return ob_get_clean();
    }
}