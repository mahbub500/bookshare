<?php
namespace BookShare\API;

defined( 'ABSPATH' ) || exit;

use BookShare\Models\{Book, Author, Publisher, UserLibrary, Rental};
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * BookShare REST API — v1
 *
 * All resource IDs are WordPress post IDs (bs_book, bs_author, bs_publisher CPTs).
 * Rental IDs and UserLibrary IDs are still custom-table row IDs.
 *
 * Base namespace: bookshare/v1
 */
class RestAPI {

    const NS = 'bookshare/v1';

    // =========================================================================
    // Route Registration
    // =========================================================================

    public static function register_routes(): void {
        $ns = self::NS;

        // ── Books ─────────────────────────────────────────────────────────────
        register_rest_route( $ns, '/books', [
            [
                'methods'             => 'GET',
                'callback'            => [ self::class, 'books_list' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'search'   => [ 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ],
                    'genre'    => [ 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ],
                    'per_page' => [ 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ],
                    'paged'    => [ 'type' => 'integer', 'default' => 1,  'minimum' => 1 ],
                ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ self::class, 'books_create' ],
                'permission_callback' => [ self::class, 'is_logged_in' ],
            ],
        ] );

        // Look up by unique code (e.g. GET /books/AB12CD34)
        register_rest_route( $ns, '/books/(?P<code>[A-Z0-9]{6,12})', [
            'methods'             => 'GET',
            'callback'            => [ self::class, 'books_get_by_code' ],
            'permission_callback' => '__return_true',
        ] );

        // CRUD by WP post ID
        register_rest_route( $ns, '/books/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ self::class, 'books_get' ],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [ self::class, 'books_update' ],
                'permission_callback' => [ self::class, 'is_admin' ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ self::class, 'books_delete' ],
                'permission_callback' => [ self::class, 'is_admin' ],
            ],
        ] );

        // ── Authors ───────────────────────────────────────────────────────────
        register_rest_route( $ns, '/authors', [
            [
                'methods'             => 'GET',
                'callback'            => [ self::class, 'authors_list' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'search'   => [ 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ],
                    'per_page' => [ 'type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 200 ],
                    'paged'    => [ 'type' => 'integer', 'default' => 1,  'minimum' => 1 ],
                ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ self::class, 'authors_create' ],
                'permission_callback' => [ self::class, 'is_admin' ],
            ],
        ] );

        register_rest_route( $ns, '/authors/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ self::class, 'authors_get' ],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [ self::class, 'authors_update' ],
                'permission_callback' => [ self::class, 'is_admin' ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ self::class, 'authors_delete' ],
                'permission_callback' => [ self::class, 'is_admin' ],
            ],
        ] );

        // ── Publishers ────────────────────────────────────────────────────────
        register_rest_route( $ns, '/publishers', [
            [
                'methods'             => 'GET',
                'callback'            => [ self::class, 'publishers_list' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'search'   => [ 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ],
                    'per_page' => [ 'type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 200 ],
                    'paged'    => [ 'type' => 'integer', 'default' => 1,  'minimum' => 1 ],
                ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ self::class, 'publishers_create' ],
                'permission_callback' => [ self::class, 'is_admin' ],
            ],
        ] );

        register_rest_route( $ns, '/publishers/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ self::class, 'publishers_get' ],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [ self::class, 'publishers_update' ],
                'permission_callback' => [ self::class, 'is_admin' ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ self::class, 'publishers_delete' ],
                'permission_callback' => [ self::class, 'is_admin' ],
            ],
        ] );

        // ── Library ───────────────────────────────────────────────────────────
        register_rest_route( $ns, '/library', [
            [
                'methods'             => 'GET',
                'callback'            => [ self::class, 'library_get' ],
                'permission_callback' => [ self::class, 'is_logged_in' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ self::class, 'library_add' ],
                'permission_callback' => [ self::class, 'is_logged_in' ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ self::class, 'library_remove' ],
                'permission_callback' => [ self::class, 'is_logged_in' ],
            ],
        ] );

        register_rest_route( $ns, '/library/toggle', [
            'methods'             => 'POST',
            'callback'            => [ self::class, 'library_toggle' ],
            'permission_callback' => [ self::class, 'is_logged_in' ],
        ] );

        // Public view of another user's library
        register_rest_route( $ns, '/library/user/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ self::class, 'library_user' ],
            'permission_callback' => '__return_true',
        ] );

        // Find holders by book code
        register_rest_route( $ns, '/library/search', [
            'methods'             => 'GET',
            'callback'            => [ self::class, 'library_search' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'code' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            ],
        ] );

        // ── Rentals ───────────────────────────────────────────────────────────
        register_rest_route( $ns, '/rentals/request', [
            'methods'             => 'POST',
            'callback'            => [ self::class, 'rentals_request' ],
            'permission_callback' => [ self::class, 'is_logged_in' ],
        ] );

        register_rest_route( $ns, '/rentals/incoming', [
            'methods'             => 'GET',
            'callback'            => [ self::class, 'rentals_incoming' ],
            'permission_callback' => [ self::class, 'is_logged_in' ],
        ] );

        register_rest_route( $ns, '/rentals/outgoing', [
            'methods'             => 'GET',
            'callback'            => [ self::class, 'rentals_outgoing' ],
            'permission_callback' => [ self::class, 'is_logged_in' ],
        ] );

        register_rest_route( $ns, '/rentals/(?P<id>\d+)/status', [
            'methods'             => 'POST',
            'callback'            => [ self::class, 'rentals_update_status' ],
            'permission_callback' => [ self::class, 'is_logged_in' ],
        ] );
    }

    // =========================================================================
    // Permission Callbacks
    // =========================================================================

    public static function is_logged_in(): bool|WP_Error {
        if ( is_user_logged_in() ) return true;
        return new WP_Error( 'auth_required', 'You must be logged in.', [ 'status' => 401 ] );
    }

    public static function is_admin(): bool|WP_Error {
        if ( current_user_can( 'manage_options' ) ) return true;
        return new WP_Error( 'forbidden', 'Insufficient permissions.', [ 'status' => 403 ] );
    }

    // =========================================================================
    // Books
    // =========================================================================

    public static function books_list( WP_REST_Request $r ): WP_REST_Response {
        $args = [
            'search'   => (string) $r->get_param( 'search' ),
            'genre'    => (string) $r->get_param( 'genre' ),
            'per_page' => (int)    $r->get_param( 'per_page' ),
            'paged'    => (int)    $r->get_param( 'paged' ),
        ];

        return new WP_REST_Response( [
            'books'  => Book::get_all( $args ),
            'total'  => Book::count( $args ),
            'genres' => Book::genres(),
        ], 200 );
    }

    /** GET /books/{id} — fetch by WP post ID */
    public static function books_get( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $book = Book::get_by_id( (int) $r['id'] );
        if ( ! $book ) {
            return new WP_Error( 'not_found', 'Book not found.', [ 'status' => 404 ] );
        }
        $book->holders = self::enrich_holders(
            UserLibrary::find_holders_by_code( $book->unique_code )
        );
        return new WP_REST_Response( $book, 200 );
    }

    /** GET /books/{CODE} — fetch by unique code */
    public static function books_get_by_code( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $book = Book::get_by_code( strtoupper( (string) $r['code'] ) );
        if ( ! $book ) {
            return new WP_Error( 'not_found', 'Book not found.', [ 'status' => 404 ] );
        }
        $book->holders = self::enrich_holders(
            UserLibrary::find_holders_by_code( $book->unique_code )
        );
        return new WP_REST_Response( $book, 200 );
    }

    public static function books_create( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $title = sanitize_text_field( (string) $r->get_param( 'title' ) );
        if ( empty( $title ) ) {
            return new WP_Error( 'missing_title', 'Title is required.', [ 'status' => 400 ] );
        }

        $id = Book::create( [
            'title'          => $title,
            'author_id'      => (int) $r->get_param( 'author_id' )      ?: 0,
            'publisher_id'   => (int) $r->get_param( 'publisher_id' )   ?: 0,
            'genre'          => sanitize_text_field( (string) $r->get_param( 'genre' ) ),
            'isbn'           => sanitize_text_field( (string) $r->get_param( 'isbn' ) ),
            'published_year' => (int) $r->get_param( 'published_year' ) ?: 0,
            'description'    => sanitize_textarea_field( (string) $r->get_param( 'description' ) ),
            'cover_url'      => esc_url_raw( (string) $r->get_param( 'cover_url' ) ),
            'language'       => sanitize_text_field( (string) $r->get_param( 'language' ) ) ?: 'English',
            'pages'          => (int) $r->get_param( 'pages' ) ?: 0,
            'added_by'       => get_current_user_id(),
        ] );

        if ( ! $id ) {
            return new WP_Error( 'create_failed', 'Could not create book.', [ 'status' => 500 ] );
        }

        return new WP_REST_Response( Book::get_by_id( $id ), 201 );
    }

    public static function books_update( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $id   = (int) $r['id'];
        $book = Book::get_by_id( $id );
        if ( ! $book ) {
            return new WP_Error( 'not_found', 'Book not found.', [ 'status' => 404 ] );
        }

        $data = [];

        foreach ( [ 'title', 'genre', 'isbn', 'language' ] as $field ) {
            $v = $r->get_param( $field );
            if ( $v !== null ) $data[ $field ] = sanitize_text_field( (string) $v );
        }

        $v = $r->get_param( 'description' );
        if ( $v !== null ) $data['description'] = sanitize_textarea_field( (string) $v );

        $v = $r->get_param( 'cover_url' );
        if ( $v !== null ) $data['cover_url'] = esc_url_raw( (string) $v );

        foreach ( [ 'author_id', 'publisher_id', 'published_year', 'pages' ] as $field ) {
            $v = $r->get_param( $field );
            if ( $v !== null ) $data[ $field ] = (int) $v ?: 0;
        }

        Book::update( $id, $data );

        return new WP_REST_Response( Book::get_by_id( $id ), 200 );
    }

    public static function books_delete( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $id = (int) $r['id'];
        if ( ! Book::get_by_id( $id ) ) {
            return new WP_Error( 'not_found', 'Book not found.', [ 'status' => 404 ] );
        }
        Book::delete( $id );
        return new WP_REST_Response( [ 'deleted' => true, 'id' => $id ], 200 );
    }

    // =========================================================================
    // Authors
    // =========================================================================

    public static function authors_list( WP_REST_Request $r ): WP_REST_Response {
        $args = [
            'search'   => (string) $r->get_param( 'search' ),
            'per_page' => (int)    $r->get_param( 'per_page' ),
            'paged'    => (int)    $r->get_param( 'paged' ),
        ];

        return new WP_REST_Response( [
            'authors' => Author::get_all( $args ),
            'total'   => Author::count( $args ),
        ], 200 );
    }

    public static function authors_get( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $author = Author::get_by_id( (int) $r['id'] );
        if ( ! $author ) {
            return new WP_Error( 'not_found', 'Author not found.', [ 'status' => 404 ] );
        }
        return new WP_REST_Response( $author, 200 );
    }

    public static function authors_create( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $name = sanitize_text_field( (string) $r->get_param( 'name' ) );
        if ( empty( $name ) ) {
            return new WP_Error( 'missing_name', 'Name is required.', [ 'status' => 400 ] );
        }

        $id = Author::create( [
            'name'             => $name,
            'bio'              => sanitize_textarea_field( (string) $r->get_param( 'bio' ) ),
            'email'            => sanitize_email( (string) $r->get_param( 'email' ) ),
            'website'          => esc_url_raw( (string) $r->get_param( 'website' ) ),
            'birth_date'       => sanitize_text_field( (string) $r->get_param( 'birth_date' ) ),
            'nationality'      => sanitize_text_field( (string) $r->get_param( 'nationality' ) ),
            'photo_url'        => esc_url_raw( (string) $r->get_param( 'photo_url' ) ),
            'social_twitter'   => sanitize_text_field( (string) $r->get_param( 'social_twitter' ) ),
            'social_instagram' => sanitize_text_field( (string) $r->get_param( 'social_instagram' ) ),
            'social_facebook'  => esc_url_raw( (string) $r->get_param( 'social_facebook' ) ),
        ] );

        if ( ! $id ) {
            return new WP_Error( 'create_failed', 'Could not create author.', [ 'status' => 500 ] );
        }

        return new WP_REST_Response( Author::get_by_id( $id ), 201 );
    }

    public static function authors_update( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $id = (int) $r['id'];
        if ( ! Author::get_by_id( $id ) ) {
            return new WP_Error( 'not_found', 'Author not found.', [ 'status' => 404 ] );
        }

        $data = [];

        foreach ( [ 'name', 'bio', 'birth_date', 'nationality', 'social_twitter', 'social_instagram' ] as $field ) {
            $v = $r->get_param( $field );
            if ( $v !== null ) $data[ $field ] = sanitize_text_field( (string) $v );
        }

        $v = $r->get_param( 'bio' );
        if ( $v !== null ) $data['bio'] = sanitize_textarea_field( (string) $v );

        $v = $r->get_param( 'email' );
        if ( $v !== null ) $data['email'] = sanitize_email( (string) $v );

        foreach ( [ 'website', 'photo_url', 'social_facebook' ] as $field ) {
            $v = $r->get_param( $field );
            if ( $v !== null ) $data[ $field ] = esc_url_raw( (string) $v );
        }

        Author::update( $id, $data );

        return new WP_REST_Response( Author::get_by_id( $id ), 200 );
    }

    public static function authors_delete( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $id = (int) $r['id'];
        if ( ! Author::get_by_id( $id ) ) {
            return new WP_Error( 'not_found', 'Author not found.', [ 'status' => 404 ] );
        }
        Author::delete( $id );
        return new WP_REST_Response( [ 'deleted' => true, 'id' => $id ], 200 );
    }

    // =========================================================================
    // Publishers
    // =========================================================================

    public static function publishers_list( WP_REST_Request $r ): WP_REST_Response {
        $args = [
            'search'   => (string) $r->get_param( 'search' ),
            'per_page' => (int)    $r->get_param( 'per_page' ),
            'paged'    => (int)    $r->get_param( 'paged' ),
        ];

        return new WP_REST_Response( [
            'publishers' => Publisher::get_all( $args ),
            'total'      => Publisher::count( $args ),
        ], 200 );
    }

    public static function publishers_get( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $publisher = Publisher::get_by_id( (int) $r['id'] );
        if ( ! $publisher ) {
            return new WP_Error( 'not_found', 'Publisher not found.', [ 'status' => 404 ] );
        }
        return new WP_REST_Response( $publisher, 200 );
    }

    public static function publishers_create( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $name = sanitize_text_field( (string) $r->get_param( 'name' ) );
        if ( empty( $name ) ) {
            return new WP_Error( 'missing_name', 'Name is required.', [ 'status' => 400 ] );
        }

        $id = Publisher::create( [
            'name'         => $name,
            'description'  => sanitize_textarea_field( (string) $r->get_param( 'description' ) ),
            'email'        => sanitize_email( (string) $r->get_param( 'email' ) ),
            'phone'        => sanitize_text_field( (string) $r->get_param( 'phone' ) ),
            'website'      => esc_url_raw( (string) $r->get_param( 'website' ) ),
            'address'      => sanitize_textarea_field( (string) $r->get_param( 'address' ) ),
            'city'         => sanitize_text_field( (string) $r->get_param( 'city' ) ),
            'country'      => sanitize_text_field( (string) $r->get_param( 'country' ) ),
            'founded_year' => (int) $r->get_param( 'founded_year' ) ?: 0,
            'logo_url'     => esc_url_raw( (string) $r->get_param( 'logo_url' ) ),
        ] );

        if ( ! $id ) {
            return new WP_Error( 'create_failed', 'Could not create publisher.', [ 'status' => 500 ] );
        }

        return new WP_REST_Response( Publisher::get_by_id( $id ), 201 );
    }

    public static function publishers_update( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $id = (int) $r['id'];
        if ( ! Publisher::get_by_id( $id ) ) {
            return new WP_Error( 'not_found', 'Publisher not found.', [ 'status' => 404 ] );
        }

        $data = [];

        foreach ( [ 'name', 'phone', 'city', 'country' ] as $field ) {
            $v = $r->get_param( $field );
            if ( $v !== null ) $data[ $field ] = sanitize_text_field( (string) $v );
        }

        foreach ( [ 'description', 'address' ] as $field ) {
            $v = $r->get_param( $field );
            if ( $v !== null ) $data[ $field ] = sanitize_textarea_field( (string) $v );
        }

        $v = $r->get_param( 'email' );
        if ( $v !== null ) $data['email'] = sanitize_email( (string) $v );

        foreach ( [ 'website', 'logo_url' ] as $field ) {
            $v = $r->get_param( $field );
            if ( $v !== null ) $data[ $field ] = esc_url_raw( (string) $v );
        }

        $v = $r->get_param( 'founded_year' );
        if ( $v !== null ) $data['founded_year'] = (int) $v ?: 0;

        Publisher::update( $id, $data );

        return new WP_REST_Response( Publisher::get_by_id( $id ), 200 );
    }

    public static function publishers_delete( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $id = (int) $r['id'];
        if ( ! Publisher::get_by_id( $id ) ) {
            return new WP_Error( 'not_found', 'Publisher not found.', [ 'status' => 404 ] );
        }
        Publisher::delete( $id );
        return new WP_REST_Response( [ 'deleted' => true, 'id' => $id ], 200 );
    }

    // =========================================================================
    // Library
    // =========================================================================

    /** GET /library — current user's full library (public + private) */
    public static function library_get(): WP_REST_Response {
        return new WP_REST_Response(
            UserLibrary::get_for_user( get_current_user_id(), true ),
            200
        );
    }

    /** POST /library — add a book (book_id = WP post ID) */
    public static function library_add( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $book_id = (int) $r->get_param( 'book_id' );
        if ( ! $book_id ) {
            return new WP_Error( 'missing_book_id', 'book_id is required.', [ 'status' => 400 ] );
        }
        // Verify the post exists and is a bs_book
        if ( ! Book::get_by_id( $book_id ) ) {
            return new WP_Error( 'not_found', 'Book not found.', [ 'status' => 404 ] );
        }

        $id = UserLibrary::add(
            get_current_user_id(),
            $book_id,
        );

        if ( ! $id ) {
            return new WP_Error( 'add_failed', 'Could not add book to library.', [ 'status' => 500 ] );
        }

        return new WP_REST_Response( [ 'added' => true, 'id' => $id ], 201 );
    }

    /** DELETE /library — remove a book */
    public static function library_remove( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $book_id = (int) $r->get_param( 'book_id' );
        if ( ! $book_id ) {
            return new WP_Error( 'missing_book_id', 'book_id is required.', [ 'status' => 400 ] );
        }

        $ok = UserLibrary::remove( get_current_user_id(), $book_id );
        return new WP_REST_Response( [ 'removed' => $ok ], 200 );
    }

    /** POST /library/toggle — toggle public/private */
    public static function library_toggle( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $book_id = (int) $r->get_param( 'book_id' );
        if ( ! $book_id ) {
            return new WP_Error( 'missing_book_id', 'book_id is required.', [ 'status' => 400 ] );
        }

        $ok = UserLibrary::toggle_visibility( get_current_user_id(), $book_id );
        return new WP_REST_Response( [ 'toggled' => $ok ], 200 );
    }

    /** GET /library/user/{id} — public view of another user's library */
    public static function library_user( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $user_id = (int) $r['id'];
        $user    = get_userdata( $user_id );

        if ( ! $user ) {
            return new WP_Error( 'not_found', 'User not found.', [ 'status' => 404 ] );
        }

        return new WP_REST_Response( [
            'user'  => [
                'id'     => $user_id,
                'name'   => $user->display_name,
                'avatar' => get_avatar_url( $user_id, [ 'size' => 64 ] ),
            ],
            'books' => UserLibrary::get_for_user( $user_id, false ),
        ], 200 );
    }

    /** GET /library/search?code=XXXXXXXX — find who holds a book */
    public static function library_search( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $code = strtoupper( sanitize_text_field( (string) $r->get_param( 'code' ) ) );
        if ( empty( $code ) ) {
            return new WP_Error( 'missing_code', 'code is required.', [ 'status' => 400 ] );
        }

        $holders = self::enrich_holders( UserLibrary::find_holders_by_code( $code ) );

        return new WP_REST_Response( [ 'code' => $code, 'holders' => $holders ], 200 );
    }

    // =========================================================================
    // Rentals
    // =========================================================================

    /** POST /rentals/request */
    public static function rentals_request( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $book_id  = (int) $r->get_param( 'book_id' );
        $owner_id = (int) $r->get_param( 'owner_id' );
        $uid      = get_current_user_id();

        if ( ! $book_id || ! $owner_id ) {
            return new WP_Error( 'missing_params', 'book_id and owner_id are required.', [ 'status' => 400 ] );
        }
        if ( $owner_id === $uid ) {
            return new WP_Error( 'invalid_request', 'You cannot request your own book.', [ 'status' => 400 ] );
        }
        if ( ! UserLibrary::user_has( $owner_id, $book_id ) ) {
            return new WP_Error( 'not_found', 'The owner does not have this book in their library.', [ 'status' => 404 ] );
        }
        if ( Rental::active_request_exists( $book_id, $uid ) ) {
            return new WP_Error( 'duplicate_request', 'You already have an active request for this book.', [ 'status' => 409 ] );
        }

        $id = Rental::create( [
            'book_id'      => $book_id,
            'owner_id'     => $owner_id,
            'requester_id' => $uid,
            'message'      => sanitize_textarea_field( (string) $r->get_param( 'message' ) ),
            'start_date'   => sanitize_text_field( (string) $r->get_param( 'start_date' ) ) ?: null,
            'end_date'     => sanitize_text_field( (string) $r->get_param( 'end_date' ) )   ?: null,
        ] );

        if ( ! $id ) {
            return new WP_Error( 'create_failed', 'Could not create rental request.', [ 'status' => 500 ] );
        }

        return new WP_REST_Response( Rental::get_by_id( $id ), 201 );
    }

    /** GET /rentals/incoming — requests for books the current user owns */
    public static function rentals_incoming(): WP_REST_Response {
        return new WP_REST_Response(
            Rental::get_incoming( get_current_user_id() ),
            200
        );
    }

    /** GET /rentals/outgoing — requests made by the current user */
    public static function rentals_outgoing(): WP_REST_Response {
        return new WP_REST_Response(
            Rental::get_outgoing( get_current_user_id() ),
            200
        );
    }

    /** POST /rentals/{id}/status */
    public static function rentals_update_status( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $id     = (int) $r['id'];
        $status = sanitize_text_field( (string) $r->get_param( 'status' ) );
        $uid    = get_current_user_id();

        $rental = Rental::get_by_id( $id );
        if ( ! $rental ) {
            return new WP_Error( 'not_found', 'Rental not found.', [ 'status' => 404 ] );
        }

        if ( ! in_array( $status, Rental::STATUSES, true ) ) {
            return new WP_Error( 'invalid_status', 'Invalid status value.', [ 'status' => 400 ] );
        }

        $is_owner     = (int) $rental->owner_id     === $uid;
        $is_requester = (int) $rental->requester_id === $uid;
        $is_admin     = current_user_can( 'manage_options' );

        // Owner-only transitions
        if ( in_array( $status, [ 'approved', 'rejected', 'returned' ], true ) ) {
            if ( ! $is_owner && ! $is_admin ) {
                return new WP_Error( 'forbidden', 'Only the book owner can perform this action.', [ 'status' => 403 ] );
            }
        }

        // Requester-only transition
        if ( $status === 'cancelled' ) {
            if ( ! $is_requester && ! $is_admin ) {
                return new WP_Error( 'forbidden', 'Only the requester can cancel a request.', [ 'status' => 403 ] );
            }
        }

        Rental::update_status( $id, $status );

        return new WP_REST_Response( Rental::get_by_id( $id ), 200 );
    }

    // =========================================================================
    // Shared helpers
    // =========================================================================

    /**
     * Attach display_name and avatar to an array of holder objects.
     * Used by both books_get_by_code and library_search.
     */
    private static function enrich_holders( array $holders ): array {
        return array_map( static function ( object $h ): object {
            $user              = get_userdata( (int) $h->user_id );
            $h->display_name   = $user ? $user->display_name : 'Unknown';
            $h->avatar         = get_avatar_url( (int) $h->user_id, [ 'size' => 48 ] );
            return $h;
        }, $holders );
    }
}