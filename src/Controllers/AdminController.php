<?php
namespace BookShare\Controllers;

defined( 'ABSPATH' ) || exit;

use BookShare\Models\{Book, Author, Publisher, Rental};

class AdminController {

    // public static function main_page(): void {
    //     $stats = [
    //         'books'     => Book::count([]),
    //         'authors'   => Author::count(),
    //         'publishers'=> Publisher::count(),
    //         'rentals'   => Rental::count_admin(),
    //     ];
    //     $books   = Book::get_all( ['per_page'=>50,'offset'=>0] );
    //     $authors = Author::get_list();
    //     $publishers = Publisher::get_list();
    //     include BS_DIR . 'admin/page-books.php';
    // }

    // public static function authors_page(): void {
    //     $authors = Author::get_all( ['per_page'=>100,'offset'=>0] );
    //     include BS_DIR . 'admin/page-authors.php';
    // }

    // public static function publishers_page(): void {
    //     $publishers = Publisher::get_all( ['per_page'=>100,'offset'=>0] );
    //     include BS_DIR . 'admin/page-publishers.php';
    // }

    public static function rentals_page(): void {
        $rentals = Rental::get_all_admin( ['per_page'=>50,'offset'=>0] );
        include BS_DIR . 'admin/page-rentals.php';
    }

    public static function members_page(): void {
        $users = get_users( ['number'=>100,'orderby'=>'registered','order'=>'DESC'] );
        include BS_DIR . 'admin/page-members.php';
    }

    public static function settings_page(): void {
        if ( isset($_POST['bs_save_settings']) && check_admin_referer('bs_settings') ) {
            update_option( 'bs_books_per_page', intval($_POST['books_per_page'] ?? 12) );
            update_option( 'bs_allow_guest_browse', isset($_POST['allow_guest_browse']) ? 1 : 0 );
            update_option( 'bs_require_approval', isset($_POST['require_approval']) ? 1 : 0 );
            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }
        include BS_DIR . 'admin/page-settings.php';
    }
}
