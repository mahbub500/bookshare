<?php
namespace BookShare\Controllers;

defined( 'ABSPATH' ) || exit;

class FrontController {

    public static function dashboard( $atts ): string {
        ob_start();
        include BS_DIR . 'templates/dashboard.php';
        return ob_get_clean();
    }
}
