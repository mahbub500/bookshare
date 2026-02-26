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

    public static function analytics_page(): void {
        $stats = [
            'books'      => Book::count([]),
            'authors'    => \BookShare\Models\Author::count(),
            'publishers' => \BookShare\Models\Publisher::count(),
            'genres'     => count(Book::genres()),
        ];

        // Books Analytics
        $all_books = Book::get_all(['per_page' => -1]);
        $total_books = count($all_books);
        $books_analytics = [
            'with_cover'     => 0,
            'with_isbn'      => 0,
            'with_author'    => 0,
            'with_publisher' => 0,
            'cover_percent'  => 0,
            'isbn_percent'   => 0,
            'author_percent' => 0,
            'publisher_percent' => 0,
        ];
        foreach ($all_books as $book) {
            if (!empty($book->cover_url)) $books_analytics['with_cover']++;
            if (!empty($book->isbn)) $books_analytics['with_isbn']++;
            if (!empty($book->author_id)) $books_analytics['with_author']++;
            if (!empty($book->publisher_id)) $books_analytics['with_publisher']++;
        }
        if ($total_books > 0) {
            $books_analytics['cover_percent']     = ($books_analytics['with_cover'] / $total_books) * 100;
            $books_analytics['isbn_percent']      = ($books_analytics['with_isbn'] / $total_books) * 100;
            $books_analytics['author_percent']    = ($books_analytics['with_author'] / $total_books) * 100;
            $books_analytics['publisher_percent'] = ($books_analytics['with_publisher'] / $total_books) * 100;
        }

        // Top Genres
        $genres_raw = Book::genres();
        $top_genres = [];
        foreach ($genres_raw as $genre) {
            $count = Book::count(['genre' => $genre]);
            $top_genres[] = ['name' => $genre, 'count' => $count];
        }
        usort($top_genres, fn($a, $b) => $b['count'] - $a['count']);

        // Authors Analytics
        $all_authors = \BookShare\Models\Author::get_all(['per_page' => -1]);
        $total_authors = count($all_authors);
        $authors_analytics = [
            'with_bio'      => 0,
            'with_photo'    => 0,
            'with_email'    => 0,
            'with_website'  => 0,
            'bio_percent'   => 0,
            'photo_percent' => 0,
            'email_percent' => 0,
            'website_percent' => 0,
        ];
        $nationality_counts = [];
        foreach ($all_authors as $author) {
            if (!empty($author->bio)) $authors_analytics['with_bio']++;
            if (!empty($author->photo_url)) $authors_analytics['with_photo']++;
            if (!empty($author->email)) $authors_analytics['with_email']++;
            if (!empty($author->website)) $authors_analytics['with_website']++;
            $nat = $author->nationality ?: 'Unspecified';
            $nationality_counts[$nat] = ($nationality_counts[$nat] ?? 0) + 1;
        }
        if ($total_authors > 0) {
            $authors_analytics['bio_percent']    = ($authors_analytics['with_bio'] / $total_authors) * 100;
            $authors_analytics['photo_percent']  = ($authors_analytics['with_photo'] / $total_authors) * 100;
            $authors_analytics['email_percent']  = ($authors_analytics['with_email'] / $total_authors) * 100;
            $authors_analytics['website_percent'] = ($authors_analytics['with_website'] / $total_authors) * 100;
        }
        $authors_by_nationality = [];
        foreach ($nationality_counts as $nat => $count) {
            $authors_by_nationality[] = ['nationality' => $nat, 'count' => $count];
        }
        usort($authors_by_nationality, fn($a, $b) => $b['count'] - $a['count']);

        // Publishers Analytics
        $all_publishers = \BookShare\Models\Publisher::get_all(['per_page' => -1]);
        $total_publishers = count($all_publishers);
        $publishers_analytics = [
            'with_description' => 0,
            'with_logo'        => 0,
            'with_email'       => 0,
            'with_website'     => 0,
            'description_percent' => 0,
            'logo_percent'        => 0,
            'email_percent'       => 0,
            'website_percent'     => 0,
        ];
        $country_counts = [];
        foreach ($all_publishers as $publisher) {
            if (!empty($publisher->description)) $publishers_analytics['with_description']++;
            if (!empty($publisher->logo_url)) $publishers_analytics['with_logo']++;
            if (!empty($publisher->email)) $publishers_analytics['with_email']++;
            if (!empty($publisher->website)) $publishers_analytics['with_website']++;
            $country = $publisher->country ?: 'Unspecified';
            $country_counts[$country] = ($country_counts[$country] ?? 0) + 1;
        }
        if ($total_publishers > 0) {
            $publishers_analytics['description_percent'] = ($publishers_analytics['with_description'] / $total_publishers) * 100;
            $publishers_analytics['logo_percent']        = ($publishers_analytics['with_logo'] / $total_publishers) * 100;
            $publishers_analytics['email_percent']       = ($publishers_analytics['with_email'] / $total_publishers) * 100;
            $publishers_analytics['website_percent']     = ($publishers_analytics['with_website'] / $total_publishers) * 100;
        }
        $publishers_by_country = [];
        foreach ($country_counts as $country => $count) {
            $publishers_by_country[] = ['country' => $country, 'count' => $count];
        }
        usort($publishers_by_country, fn($a, $b) => $b['count'] - $a['count']);

        // Books per Author
        $author_book_counts = [];
        foreach ($all_books as $book) {
            $author_name = $book->author_name ?: 'Unassigned';
            $author_book_counts[$author_name] = ($author_book_counts[$author_name] ?? 0) + 1;
        }
        $books_per_author = [];
        foreach ($author_book_counts as $name => $count) {
            $books_per_author[] = ['author_name' => $name, 'count' => $count];
        }
        usort($books_per_author, fn($a, $b) => $b['count'] - $a['count']);

        // Books per Publisher
        $publisher_book_counts = [];
        foreach ($all_books as $book) {
            $publisher_name = $book->publisher_name ?: 'Unassigned';
            $publisher_book_counts[$publisher_name] = ($publisher_book_counts[$publisher_name] ?? 0) + 1;
        }
        $books_per_publisher = [];
        foreach ($publisher_book_counts as $name => $count) {
            $books_per_publisher[] = ['publisher_name' => $name, 'count' => $count];
        }
        usort($books_per_publisher, fn($a, $b) => $b['count'] - $a['count']);

        include BS_DIR . 'admin/page-analytics.php';
    }
}
