<?php
namespace BookShare\API;

use BookShare\Models\Book;
use BookShare\Models\UserLibrary;
use BookShare\Models\Rental;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Registers all REST API endpoints under /wp-json/bookshare/v1/
 */
class RestAPI {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void {
        $ns = 'bookshare/v1';

        // ── Books ─────────────────────────────────────────────────────
        register_rest_route( $ns, '/books', [
            [ 'methods' => 'GET',  'callback' => [ $this, 'get_books'   ], 'permission_callback' => '__return_true' ],
            [ 'methods' => 'POST', 'callback' => [ $this, 'create_book' ], 'permission_callback' => [ $this, 'is_logged_in' ] ],
        ] );
        register_rest_route( $ns, '/books/(?P<code>[A-Z0-9]+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_book_by_code' ],
            'permission_callback' => '__return_true',
        ] );

        // ── Library ───────────────────────────────────────────────────
        register_rest_route( $ns, '/library', [
            [ 'methods' => 'GET',    'callback' => [ $this, 'get_my_library'   ], 'permission_callback' => [ $this, 'is_logged_in' ] ],
            [ 'methods' => 'POST',   'callback' => [ $this, 'add_to_library'   ], 'permission_callback' => [ $this, 'is_logged_in' ] ],
            [ 'methods' => 'DELETE', 'callback' => [ $this, 'remove_from_library' ], 'permission_callback' => [ $this, 'is_logged_in' ] ],
        ] );
        register_rest_route( $ns, '/library/toggle', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'toggle_visibility' ],
            'permission_callback' => [ $this, 'is_logged_in' ],
        ] );
        register_rest_route( $ns, '/library/user/(?P<user_id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_user_public_library' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $ns, '/library/search', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'search_by_code' ],
            'permission_callback' => '__return_true',
        ] );

        // ── Rentals ───────────────────────────────────────────────────
        register_rest_route( $ns, '/rentals/request', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'request_rental' ],
            'permission_callback' => [ $this, 'is_logged_in' ],
        ] );
        register_rest_route( $ns, '/rentals/incoming', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_incoming_requests' ],
            'permission_callback' => [ $this, 'is_logged_in' ],
        ] );
        register_rest_route( $ns, '/rentals/outgoing', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_outgoing_requests' ],
            'permission_callback' => [ $this, 'is_logged_in' ],
        ] );
        register_rest_route( $ns, '/rentals/(?P<id>\d+)/status', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'update_rental_status' ],
            'permission_callback' => [ $this, 'is_logged_in' ],
        ] );
    }

    // ── Book handlers ─────────────────────────────────────────────────

    public function get_books( WP_REST_Request $req ): WP_REST_Response {
        $books = Book::all([
            'search' => sanitize_text_field( $req->get_param('search') ?? '' ),
            'genre'  => sanitize_text_field( $req->get_param('genre')  ?? '' ),
            'limit'  => (int) ( $req->get_param('limit')  ?? 20 ),
            'offset' => (int) ( $req->get_param('offset') ?? 0  ),
        ]);
        return new WP_REST_Response( $books, 200 );
    }

    public function get_book_by_code( WP_REST_Request $req ): WP_REST_Response|WP_Error {
        $book = Book::find_by_code( strtoupper( $req->get_param('code') ) );
        if ( ! $book ) return new WP_Error( 'not_found', 'Book not found.', [ 'status' => 404 ] );

        $book->holder_count = UserLibrary::count_holders( $book->id );
        return new WP_REST_Response( $book, 200 );
    }

    public function create_book( WP_REST_Request $req ): WP_REST_Response|WP_Error {
        $data = $req->get_json_params();
        if ( empty( $data['title'] ) || empty( $data['author'] ) ) {
            return new WP_Error( 'validation', 'Title and Author are required.', [ 'status' => 400 ] );
        }
        $id = Book::create( $data );
        if ( ! $id ) return new WP_Error( 'db_error', 'Could not create book.', [ 'status' => 500 ] );
        return new WP_REST_Response( Book::find( $id ), 201 );
    }

    // ── Library handlers ──────────────────────────────────────────────

    public function get_my_library(): WP_REST_Response {
        $books = UserLibrary::get_user_library( get_current_user_id() );
        return new WP_REST_Response( $books, 200 );
    }

    public function get_user_public_library( WP_REST_Request $req ): WP_REST_Response {
        $books = UserLibrary::get_user_library( (int) $req->get_param('user_id'), true );
        return new WP_REST_Response( $books, 200 );
    }

    public function add_to_library( WP_REST_Request $req ): WP_REST_Response|WP_Error {
        $book_id = (int) $req->get_json_params()['book_id'] ?? 0;
        if ( ! $book_id ) return new WP_Error( 'validation', 'book_id required.', [ 'status' => 400 ] );
        $result = UserLibrary::add( get_current_user_id(), $book_id );
        if ( ! $result ) return new WP_Error( 'duplicate', 'Book already in library.', [ 'status' => 409 ] );
        return new WP_REST_Response( [ 'success' => true ], 201 );
    }

    public function remove_from_library( WP_REST_Request $req ): WP_REST_Response {
        $book_id = (int) $req->get_json_params()['book_id'] ?? 0;
        UserLibrary::remove( get_current_user_id(), $book_id );
        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    public function toggle_visibility( WP_REST_Request $req ): WP_REST_Response {
        $book_id = (int) $req->get_json_params()['book_id'] ?? 0;
        UserLibrary::toggle_visibility( get_current_user_id(), $book_id );
        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    public function search_by_code( WP_REST_Request $req ): WP_REST_Response {
        $code    = strtoupper( sanitize_text_field( $req->get_param('code') ?? '' ) );
        $holders = UserLibrary::search_by_unique_code( $code );
        return new WP_REST_Response( $holders, 200 );
    }

    // ── Rental handlers ───────────────────────────────────────────────

    public function request_rental( WP_REST_Request $req ): WP_REST_Response|WP_Error {
        $data = $req->get_json_params();
        $id   = Rental::request(
            (int) ( $data['book_id']   ?? 0 ),
            (int) ( $data['owner_id']  ?? 0 ),
            get_current_user_id(),
            $data['message'] ?? ''
        );
        if ( ! $id ) return new WP_Error( 'duplicate', 'Pending request already exists.', [ 'status' => 409 ] );
        return new WP_REST_Response( [ 'success' => true, 'id' => $id ], 201 );
    }

    public function get_incoming_requests(): WP_REST_Response {
        return new WP_REST_Response( Rental::get_owner_requests( get_current_user_id() ), 200 );
    }

    public function get_outgoing_requests(): WP_REST_Response {
        return new WP_REST_Response( Rental::get_user_requests( get_current_user_id() ), 200 );
    }

    public function update_rental_status( WP_REST_Request $req ): WP_REST_Response|WP_Error {
        $status = sanitize_text_field( $req->get_json_params()['status'] ?? '' );
        $result = Rental::update_status( (int) $req->get_param('id'), get_current_user_id(), $status );
        if ( ! $result ) return new WP_Error( 'not_found', 'Request not found or unauthorized.', [ 'status' => 404 ] );
        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    // ── Auth helper ───────────────────────────────────────────────────

    public function is_logged_in(): bool {
        return is_user_logged_in();
    }
}