<?php
namespace BookShare\Controllers;

defined( 'ABSPATH' ) || exit;

class LibraryController {

    public static function library_shortcode( $atts ): string {
        ob_start();
        echo '<div id="bs-app" data-init-tab="library">';
        include BS_DIR . 'templates/dashboard.php';
        echo '</div>';
        return ob_get_clean();
    }
}
