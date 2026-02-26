<?php
namespace BookShare\Front;

defined( 'ABSPATH' ) || exit;

class Helper {

    public static function register(): void {
        add_action( 'wp_head', [ self::class, 'head' ] );
    }

    public static function head(): void {
        echo '<style>
            .bs-book-cover {
                width: 100%;
                height: 250px;
                object-fit: cover;
            }
        </style>';
    }
}