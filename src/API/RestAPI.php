<?php
namespace BookShare\API;

use BookShare\Models\UserLibrary;
use BookShare\Models\Rental;
use BookShare\Models\BookRequest;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * All REST API endpoints for BookCircle frontend.
 */
class RestAPI {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register' ] );
    }

    public function register(): void {
        $ns = 'bookshare/v1';

        // ── Book catalog (read from CPT) ───────────────────────────────
        register_rest_route( $ns, '/books', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_books' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $ns, '/books/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_book' ],
            'permission_callback' => '__return_true',
        ] );

        // ── Authors & Publishers ──────────────────────────────────────
        register_rest_route( $ns, '/authors', [
            'methods'             => 'GET',
            'callback'            => fn() => $this->get_cpt_list( 'bs_author' ),
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $ns, '/publishers', [
            'methods'             => 'GET',
            'callback'            => fn() => $this->get_cpt_list( 'bs_publisher' ),
            'permission_callback' => '__return_true',
        ] );

        // ── User Library ──────────────────────────────────────────────
        register_rest_route( $ns, '/library', [
            [ 'methods' => 'GET',    'callback' => [ $this, 'get_my_library' ],     'permission_callback' => [ $this, 'auth' ] ],
            [ 'methods' => 'POST',   'callback' => [ $this, 'add_to_library' ],     'permission_callback' => [ $this, 'auth' ] ],
            [ 'methods' => 'DELETE', 'callback' => [ $this, 'remove_from_library'], 'permission_callback' => [ $this, 'auth' ] ],
        ] );
        register_rest_route( $ns, '/library/toggle', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'toggle_visibility' ],
            'permission_callback' => [ $this, 'auth' ],
        ] );
        register_rest_route( $ns, '/library/user/(?P<user_id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_user_library' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( $ns, '/library/search', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'search_by_code' ],
            'permission_callback' => '__return_true',
        ] );

        // ── Book listing requests ─────────────────────────────────────
        register_rest_route( $ns, '/book-requests', [
            [ 'methods' => 'GET',  'callback' => [ $this, 'get_my_requests' ], 'permission_callback' => [ $this, 'auth' ] ],
            [ 'methods' => 'POST', 'callback' => [ $this, 'create_request'  ], 'permission_callback' => [ $this, 'auth' ] ],
        ] );

        // ── Rentals ───────────────────────────────────────────────────
        register_rest_route( $ns, '/rentals/request', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'rent_request' ],
            'permission_callback' => [ $this, 'auth' ],
        ] );
        register_rest_route( $ns, '/rentals/incoming', [
            'methods'             => 'GET',
            'callback'            => fn() => new WP_REST_Response( Rental::get_incoming( get_current_user_id() ), 200 ),
            'permission_callback' => [ $this, 'auth' ],
        ] );
        register_rest_route( $ns, '/rentals/outgoing', [
            'methods'             => 'GET',
            'callback'            => fn() => new WP_REST_Response( Rental::get_outgoing( get_current_user_id() ), 200 ),
            'permission_callback' => [ $this, 'auth' ],
        ] );
        register_rest_route( $ns, '/rentals/(?P<id>\d+)/status', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'update_rental' ],
            'permission_callback' => [ $this, 'auth' ],
        ] );
    }

    // ── Books ─────────────────────────────────────────────────────────

    public function get_books( WP_REST_Request $req ): WP_REST_Response {
        $args = [
            'post_type'      => 'bs_book',
            'post_status'    => 'publish',
            'posts_per_page' => (int) ( $req->get_param('limit') ?? 24 ),
            'offset'         => (int) ( $req->get_param('offset') ?? 0 ),
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        $search = sanitize_text_field( $req->get_param('search') ?? '' );
        if ( $search ) $args['s'] = $search;

        $genre = sanitize_text_field( $req->get_param('genre') ?? '' );
        if ( $genre ) {
            $args['meta_query'] = [ [ 'key' => '_bs_genre', 'value' => $genre ] ];
        }

        // Search by unique code
        $code = strtoupper( sanitize_text_field( $req->get_param('code') ?? '' ) );
        if ( $code ) {
            $args['meta_query'] = [ [ 'key' => '_bs_unique_code', 'value' => $code ] ];
        }

        $query = new \WP_Query( $args );
        $books = [];
        foreach ( $query->posts as $post ) {
            $books[] = UserLibrary::format_book( $post );
        }
        return new WP_REST_Response( [
            'books' => $books,
            'total' => $query->found_posts,
        ], 200 );
    }

    public function get_book( WP_REST_Request $req ){
        $post = get_post( (int) $req->get_param('id') );
        if ( ! $post || $post->post_type !== 'bs_book' ) {
            return new WP_Error( 'not_found', 'Book not found', [ 'status' => 404 ] );
        }
        return new WP_REST_Response( UserLibrary::format_book( $post ), 200 );
    }

    // ── Authors / Publishers ──────────────────────────────────────────

    private function get_cpt_list( string $post_type ): WP_REST_Response {
        $posts = get_posts( [ 'post_type' => $post_type, 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
        $list  = array_map( fn($p) => [
            'id'          => $p->ID,
            'name'        => $p->post_title,
            'description' => wp_strip_all_tags( $p->post_content ),
            'photo'       => get_the_post_thumbnail_url( $p->ID, 'thumbnail' ) ?: '',
        ], $posts );
        return new WP_REST_Response( $list, 200 );
    }

    // ── Library ───────────────────────────────────────────────────────

    public function get_my_library(): WP_REST_Response {
        return new WP_REST_Response( UserLibrary::get_mine( get_current_user_id() ), 200 );
    }

    public function get_user_library( WP_REST_Request $req ): WP_REST_Response {
        return new WP_REST_Response( UserLibrary::get_public( (int) $req->get_param('user_id') ), 200 );
    }

    public function add_to_library( WP_REST_Request $req ){
        $book_id = (int) ( $req->get_json_params()['book_id'] ?? 0 );
        if ( ! $book_id ) return new WP_Error( 'missing', 'book_id required', [ 'status' => 400 ] );

        // Verify book exists
        $post = get_post( $book_id );
        if ( ! $post || $post->post_type !== 'bs_book' || $post->post_status !== 'publish' ) {
            return new WP_Error( 'not_found', 'Book not found', [ 'status' => 404 ] );
        }

        $ok = UserLibrary::add( get_current_user_id(), $book_id );
        if ( ! $ok ) return new WP_Error( 'duplicate', 'Book already in library', [ 'status' => 409 ] );
        return new WP_REST_Response( [ 'success' => true ], 201 );
    }

    public function remove_from_library( WP_REST_Request $req ): WP_REST_Response {
        $book_id = (int) ( $req->get_json_params()['book_id'] ?? 0 );
        UserLibrary::remove( get_current_user_id(), $book_id );
        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    public function toggle_visibility( WP_REST_Request $req ): WP_REST_Response {
        $book_id = (int) ( $req->get_json_params()['book_id'] ?? 0 );
        UserLibrary::toggle_visibility( get_current_user_id(), $book_id );
        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    public function search_by_code( WP_REST_Request $req ): WP_REST_Response {
        $code = strtoupper( sanitize_text_field( $req->get_param('code') ?? '' ) );
        return new WP_REST_Response( UserLibrary::find_holders( $code ), 200 );
    }

    // ── Book listing requests ─────────────────────────────────────────

    public function get_my_requests(): WP_REST_Response {
        return new WP_REST_Response( BookRequest::get_user_requests( get_current_user_id() ), 200 );
    }

    public function create_request( WP_REST_Request $req ){
        $data = $req->get_json_params();
        if ( empty( $data['title'] ) ) {
            return new WP_Error( 'missing', 'Title is required', [ 'status' => 400 ] );
        }
        $id = BookRequest::create( get_current_user_id(), $data );
        if ( ! $id ) return new WP_Error( 'db_error', 'Could not save request', [ 'status' => 500 ] );
        return new WP_REST_Response( [ 'success' => true, 'id' => $id ], 201 );
    }

    // ── Rentals ───────────────────────────────────────────────────────

    public function rent_request( WP_REST_Request $req ){
        $d = $req->get_json_params();
        $id = Rental::request(
            (int) ( $d['book_id']  ?? 0 ),
            (int) ( $d['owner_id'] ?? 0 ),
            get_current_user_id(),
            $d['message'] ?? ''
        );
        if ( ! $id ) return new WP_Error( 'duplicate', 'Request already pending', [ 'status' => 409 ] );
        return new WP_REST_Response( [ 'success' => true, 'id' => $id ], 201 );
    }

    public function update_rental( WP_REST_Request $req ){
        $status = sanitize_text_field( $req->get_json_params()['status'] ?? '' );
        $ok = Rental::update_status( (int) $req->get_param('id'), get_current_user_id(), $status );
        if ( ! $ok ) return new WP_Error( 'not_found', 'Request not found', [ 'status' => 404 ] );
        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    public function auth(): bool {
        return is_user_logged_in();
    }
}