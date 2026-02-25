<?php
namespace BookShare\Controllers;

defined( 'ABSPATH' ) || exit;

class BookController {

    public static function catalog_shortcode( $atts ): string {
        ob_start();
        echo '<div id="bs-app" data-init-tab="catalog">';
        include BS_DIR . 'templates/dashboard.php';
        echo '</div>';
        return ob_get_clean();
    }

    public static function search_shortcode( $atts ): string {
        ob_start();
        echo '<div id="bs-app" data-init-tab="search">';
        include BS_DIR . 'templates/dashboard.php';
        echo '</div>';
        return ob_get_clean();
    }
}
