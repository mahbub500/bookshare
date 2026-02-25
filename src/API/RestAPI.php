<?php
namespace BookShare\API;

defined( 'ABSPATH' ) || exit;

use BookShare\Models\{Book, Author, Publisher, UserLibrary, Rental};
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class RestAPI {

    const NS = 'bookshare/v1';

    public static function register_routes(): void {
        $ns = self::NS;

        // Books
        register_rest_route( $ns, '/books',                 [ [ 'methods' => 'GET',    'callback' => [self::class,'books_list'],       'permission_callback' => '__return_true' ],
                                                              [ 'methods' => 'POST',   'callback' => [self::class,'books_create'],     'permission_callback' => [self::class,'is_logged_in'] ] ] );
        register_rest_route( $ns, '/books/(?P<code>[A-Z0-9]+)', [ 'methods' => 'GET', 'callback' => [self::class,'books_get'],        'permission_callback' => '__return_true' ] );
        register_rest_route( $ns, '/books/(?P<id>\d+)',     [ [ 'methods' => 'PUT',    'callback' => [self::class,'books_update'],     'permission_callback' => [self::class,'is_admin'] ],
                                                              [ 'methods' => 'DELETE', 'callback' => [self::class,'books_delete'],     'permission_callback' => [self::class,'is_admin'] ] ] );

        // Authors (public read, admin write)
        register_rest_route( $ns, '/authors',               [ [ 'methods' => 'GET',    'callback' => [self::class,'authors_list'],    'permission_callback' => '__return_true' ],
                                                              [ 'methods' => 'POST',   'callback' => [self::class,'authors_create'],  'permission_callback' => [self::class,'is_admin'] ] ] );
        register_rest_route( $ns, '/authors/(?P<id>\d+)',   [ [ 'methods' => 'GET',    'callback' => [self::class,'authors_get'],     'permission_callback' => '__return_true' ],
                                                              [ 'methods' => 'PUT',    'callback' => [self::class,'authors_update'],  'permission_callback' => [self::class,'is_admin'] ],
                                                              [ 'methods' => 'DELETE', 'callback' => [self::class,'authors_delete'],  'permission_callback' => [self::class,'is_admin'] ] ] );

        // Publishers
        register_rest_route( $ns, '/publishers',            [ [ 'methods' => 'GET',    'callback' => [self::class,'publishers_list'],   'permission_callback' => '__return_true' ],
                                                              [ 'methods' => 'POST',   'callback' => [self::class,'publishers_create'], 'permission_callback' => [self::class,'is_admin'] ] ] );
        register_rest_route( $ns, '/publishers/(?P<id>\d+)',[ [ 'methods' => 'PUT',    'callback' => [self::class,'publishers_update'], 'permission_callback' => [self::class,'is_admin'] ],
                                                              [ 'methods' => 'DELETE', 'callback' => [self::class,'publishers_delete'], 'permission_callback' => [self::class,'is_admin'] ] ] );

        // Library
        register_rest_route( $ns, '/library',               [ [ 'methods' => 'GET',    'callback' => [self::class,'library_get'],      'permission_callback' => [self::class,'is_logged_in'] ],
                                                              [ 'methods' => 'POST',   'callback' => [self::class,'library_add'],      'permission_callback' => [self::class,'is_logged_in'] ],
                                                              [ 'methods' => 'DELETE', 'callback' => [self::class,'library_remove'],   'permission_callback' => [self::class,'is_logged_in'] ] ] );
        register_rest_route( $ns, '/library/toggle',        [ 'methods' => 'POST', 'callback' => [self::class,'library_toggle'],      'permission_callback' => [self::class,'is_logged_in'] ] );
        register_rest_route( $ns, '/library/user/(?P<id>\d+)', [ 'methods' => 'GET', 'callback' => [self::class,'library_user'],      'permission_callback' => '__return_true' ] );
        register_rest_route( $ns, '/library/search',        [ 'methods' => 'GET',  'callback' => [self::class,'library_search'],      'permission_callback' => '__return_true' ] );

        // Rentals
        register_rest_route( $ns, '/rentals/request',       [ 'methods' => 'POST', 'callback' => [self::class,'rentals_request'],     'permission_callback' => [self::class,'is_logged_in'] ] );
        register_rest_route( $ns, '/rentals/incoming',      [ 'methods' => 'GET',  'callback' => [self::class,'rentals_incoming'],    'permission_callback' => [self::class,'is_logged_in'] ] );
        register_rest_route( $ns, '/rentals/outgoing',      [ 'methods' => 'GET',  'callback' => [self::class,'rentals_outgoing'],    'permission_callback' => [self::class,'is_logged_in'] ] );
        register_rest_route( $ns, '/rentals/(?P<id>\d+)/status', [ 'methods' => 'POST', 'callback' => [self::class,'rentals_status'],'permission_callback' => [self::class,'is_logged_in'] ] );
    }

    // ─── Permission callbacks ──────────────────────────────────────────────

    public static function is_logged_in(): bool|WP_Error {
        if ( is_user_logged_in() ) return true;
        return new WP_Error( 'auth_required', 'You must be logged in.', [ 'status' => 401 ] );
    }

    public static function is_admin(): bool|WP_Error {
        if ( current_user_can( 'manage_options' ) ) return true;
        return new WP_Error( 'forbidden', 'Insufficient permissions.', [ 'status' => 403 ] );
    }

    // ─── Books ────────────────────────────────────────────────────────────

    public static function books_list( WP_REST_Request $r ): WP_REST_Response {
        $args = [
            'search'   => sanitize_text_field( $r->get_param( 'search' ) ?? '' ),
            'genre'    => sanitize_text_field( $r->get_param( 'genre' )  ?? '' ),
            'per_page' => min( intval( $r->get_param( 'per_page' ) ?? 20 ), 100 ),
            'offset'   => intval( $r->get_param( 'offset' ) ?? 0 ),
        ];
        return new WP_REST_Response( [ 'books' => Book::get_all( $args ), 'total' => Book::count( $args ), 'genres' => Book::genres() ], 200 );
    }

    public static function books_get( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $book = Book::get_by_code( strtoupper( $r['code'] ) );
        if ( ! $book ) return new WP_Error( 'not_found', 'Book not found.', [ 'status' => 404 ] );
        // Also find holders
        $holders = UserLibrary::find_holders_by_code( $book->unique_code );
        $book->holders = array_map( function( $h ) {
            $user = get_userdata( (int) $h->user_id );
            $h->display_name = $user ? $user->display_name : 'Unknown';
            $h->avatar = get_avatar_url( (int) $h->user_id, [ 'size' => 48 ] );
            return $h;
        }, $holders );
        return new WP_REST_Response( $book, 200 );
    }

    public static function books_create( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $title = sanitize_text_field( $r->get_param( 'title' ) );
        if ( empty( $title ) ) return new WP_Error( 'missing_title', 'Title is required.', [ 'status' => 400 ] );

        $id = Book::create( [
            'title'          => $title,
            'author_id'      => intval( $r->get_param( 'author_id' ) ) ?: null,
            'publisher_id'   => intval( $r->get_param( 'publisher_id' ) ) ?: null,
            'genre'          => sanitize_text_field( $r->get_param( 'genre' ) ?? '' ),
            'isbn'           => sanitize_text_field( $r->get_param( 'isbn' ) ?? '' ),
            'published_year' => intval( $r->get_param( 'published_year' ) ) ?: null,
            'description'    => sanitize_textarea_field( $r->get_param( 'description' ) ?? '' ),
            'cover_url'      => esc_url_raw( $r->get_param( 'cover_url' ) ?? '' ),
            'language'       => sanitize_text_field( $r->get_param( 'language' ) ?? 'English' ),
            'pages'          => intval( $r->get_param( 'pages' ) ) ?: null,
            'added_by'       => get_current_user_id(),
        ] );

        if ( ! $id ) return new WP_Error( 'insert_failed', 'Could not create book.', [ 'status' => 500 ] );
        return new WP_REST_Response( Book::get_by_id( $id ), 201 );
    }

    public static function books_update( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $id   = intval( $r['id'] );
        $book = Book::get_by_id( $id );
        if ( ! $book ) return new WP_Error( 'not_found', 'Book not found.', [ 'status' => 404 ] );
        $fields = [ 'title', 'genre', 'isbn', 'description', 'language' ];
        $data   = [];
        foreach ( $fields as $f ) {
            $v = $r->get_param( $f );
            if ( $v !== null ) $data[ $f ] = sanitize_text_field( $v );
        }
        foreach ( [ 'author_id', 'publisher_id', 'published_year', 'pages' ] as $f ) {
            $v = $r->get_param( $f );
            if ( $v !== null ) $data[ $f ] = intval( $v ) ?: null;
        }
        if ( $r->get_param( 'cover_url' ) ) $data['cover_url'] = esc_url_raw( $r->get_param( 'cover_url' ) );
        Book::update( $id, $data );
        return new WP_REST_Response( Book::get_by_id( $id ), 200 );
    }

    public static function books_delete( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $id = intval( $r['id'] );
        if ( ! Book::get_by_id( $id ) ) return new WP_Error( 'not_found', 'Book not found.', [ 'status' => 404 ] );
        Book::delete( $id );
        return new WP_REST_Response( [ 'deleted' => true ], 200 );
    }

    // ─── Authors ──────────────────────────────────────────────────────────

    public static function authors_list( WP_REST_Request $r ): WP_REST_Response {
        $args = [ 'search' => sanitize_text_field( $r->get_param('search') ?? '' ), 'per_page' => 100, 'offset' => 0 ];
        return new WP_REST_Response( [ 'authors' => Author::get_all($args), 'total' => Author::count() ], 200 );
    }

    public static function authors_get( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $a = Author::get_by_id( intval($r['id']) );
        if ( ! $a ) return new WP_Error( 'not_found', 'Author not found.', ['status'=>404] );
        return new WP_REST_Response( $a, 200 );
    }

    public static function authors_create( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $name = sanitize_text_field( $r->get_param('name') );
        if ( empty($name) ) return new WP_Error( 'missing', 'Name required.', ['status'=>400] );
        $id = Author::create([
            'name'          => $name,
            'bio'           => sanitize_textarea_field( $r->get_param('bio') ?? '' ),
            'email'         => sanitize_email( $r->get_param('email') ?? '' ),
            'website'       => esc_url_raw( $r->get_param('website') ?? '' ),
            'birth_date'    => sanitize_text_field( $r->get_param('birth_date') ?? '' ) ?: null,
            'nationality'   => sanitize_text_field( $r->get_param('nationality') ?? '' ),
            'photo_url'     => esc_url_raw( $r->get_param('photo_url') ?? '' ),
            'social_twitter'   => sanitize_text_field( $r->get_param('social_twitter') ?? '' ),
            'social_instagram' => sanitize_text_field( $r->get_param('social_instagram') ?? '' ),
            'social_facebook'  => sanitize_text_field( $r->get_param('social_facebook') ?? '' ),
        ]);
        if (!$id) return new WP_Error('insert_failed','Could not create.',['status'=>500]);
        return new WP_REST_Response( Author::get_by_id($id), 201 );
    }

    public static function authors_update( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $id = intval($r['id']);
        if ( ! Author::get_by_id($id) ) return new WP_Error('not_found','Author not found.',['status'=>404]);
        $data = [];
        foreach (['name','bio','email','website','birth_date','nationality','photo_url','social_twitter','social_instagram','social_facebook'] as $f) {
            $v = $r->get_param($f);
            if ($v !== null) $data[$f] = in_array($f,['email']) ? sanitize_email($v) : sanitize_text_field($v);
        }
        Author::update($id,$data);
        return new WP_REST_Response(Author::get_by_id($id),200);
    }

    public static function authors_delete( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $id = intval($r['id']);
        if (!Author::get_by_id($id)) return new WP_Error('not_found','Author not found.',['status'=>404]);
        Author::delete($id);
        return new WP_REST_Response(['deleted'=>true],200);
    }

    // ─── Publishers ───────────────────────────────────────────────────────

    public static function publishers_list( WP_REST_Request $r ): WP_REST_Response {
        $args = ['search'=>sanitize_text_field($r->get_param('search')??''),'per_page'=>100,'offset'=>0];
        return new WP_REST_Response(['publishers'=>Publisher::get_all($args),'total'=>Publisher::count()],200);
    }

    public static function publishers_create( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $name = sanitize_text_field($r->get_param('name'));
        if (empty($name)) return new WP_Error('missing','Name required.',['status'=>400]);
        $id = Publisher::create([
            'name'         => $name,
            'description'  => sanitize_textarea_field($r->get_param('description')??''),
            'email'        => sanitize_email($r->get_param('email')??''),
            'phone'        => sanitize_text_field($r->get_param('phone')??''),
            'website'      => esc_url_raw($r->get_param('website')??''),
            'address'      => sanitize_textarea_field($r->get_param('address')??''),
            'city'         => sanitize_text_field($r->get_param('city')??''),
            'country'      => sanitize_text_field($r->get_param('country')??''),
            'founded_year' => intval($r->get_param('founded_year'))?: null,
            'logo_url'     => esc_url_raw($r->get_param('logo_url')??''),
        ]);
        if (!$id) return new WP_Error('insert_failed','Could not create.',['status'=>500]);
        return new WP_REST_Response(Publisher::get_by_id($id),201);
    }

    public static function publishers_update( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $id = intval($r['id']);
        if (!Publisher::get_by_id($id)) return new WP_Error('not_found','Not found.',['status'=>404]);
        $data = [];
        foreach (['name','description','email','phone','website','address','city','country','logo_url'] as $f) {
            $v = $r->get_param($f);
            if ($v !== null) $data[$f] = sanitize_text_field($v);
        }
        if ($r->get_param('founded_year')) $data['founded_year'] = intval($r->get_param('founded_year'));
        Publisher::update($id,$data);
        return new WP_REST_Response(Publisher::get_by_id($id),200);
    }

    public static function publishers_delete( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $id = intval($r['id']);
        if (!Publisher::get_by_id($id)) return new WP_Error('not_found','Not found.',['status'=>404]);
        Publisher::delete($id);
        return new WP_REST_Response(['deleted'=>true],200);
    }

    // ─── Library ──────────────────────────────────────────────────────────

    public static function library_get(): WP_REST_Response {
        $uid   = get_current_user_id();
        $books = UserLibrary::get_for_user( $uid, true );
        return new WP_REST_Response( $books, 200 );
    }

    public static function library_add( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $book_id = intval( $r->get_param('book_id') );
        $note    = sanitize_text_field( $r->get_param('condition_note') ?? '' );
        if ( ! $book_id ) return new WP_Error('missing','book_id required.',['status'=>400]);
        $id = UserLibrary::add( get_current_user_id(), $book_id, $note );
        if ( ! $id ) return new WP_Error('failed','Could not add.',['status'=>500]);
        return new WP_REST_Response(['added'=>true,'id'=>$id],201);
    }

    public static function library_remove( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $book_id = intval( $r->get_param('book_id') );
        if ( ! $book_id ) return new WP_Error('missing','book_id required.',['status'=>400]);
        $ok = UserLibrary::remove( get_current_user_id(), $book_id );
        return new WP_REST_Response(['removed'=>$ok],200);
    }

    public static function library_toggle( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $book_id = intval( $r->get_param('book_id') );
        $ok = UserLibrary::toggle_visibility( get_current_user_id(), $book_id );
        return new WP_REST_Response(['toggled'=>$ok],200);
    }

    public static function library_user( WP_REST_Request $r ): WP_REST_Response {
        $user_id = intval($r['id']);
        $books   = UserLibrary::get_for_user($user_id, false);
        $user    = get_userdata($user_id);
        return new WP_REST_Response(['user'=>$user?['name'=>$user->display_name,'avatar'=>get_avatar_url($user_id,['size'=>64])]:null,'books'=>$books],200);
    }

    public static function library_search( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $code = strtoupper( sanitize_text_field( $r->get_param('code') ?? '' ) );
        if ( empty($code) ) return new WP_Error('missing','code required.',['status'=>400]);
        $holders = UserLibrary::find_holders_by_code($code);
        foreach ($holders as &$h) {
            $user = get_userdata((int)$h->user_id);
            $h->display_name = $user ? $user->display_name : 'Unknown';
            $h->avatar       = get_avatar_url((int)$h->user_id,['size'=>48]);
        }
        return new WP_REST_Response(['code'=>$code,'holders'=>$holders],200);
    }

    // ─── Rentals ──────────────────────────────────────────────────────────

    public static function rentals_request( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $book_id  = intval($r->get_param('book_id'));
        $owner_id = intval($r->get_param('owner_id'));
        $uid      = get_current_user_id();

        if (!$book_id || !$owner_id) return new WP_Error('missing','book_id and owner_id required.',['status'=>400]);
        if ($owner_id === $uid) return new WP_Error('invalid','Cannot request your own book.',['status'=>400]);
        if (!UserLibrary::user_has($owner_id,$book_id)) return new WP_Error('not_found','Owner does not have this book.',['status'=>404]);
        if (Rental::active_request_exists($book_id,$uid)) return new WP_Error('duplicate','Active request already exists.',['status'=>409]);

        $id = Rental::create([
            'book_id'      => $book_id,
            'owner_id'     => $owner_id,
            'requester_id' => $uid,
            'status'       => 'pending',
            'message'      => sanitize_textarea_field($r->get_param('message')??''),
            'start_date'   => sanitize_text_field($r->get_param('start_date')??'') ?: null,
            'end_date'     => sanitize_text_field($r->get_param('end_date')??'')   ?: null,
        ]);
        if (!$id) return new WP_Error('failed','Could not create request.',['status'=>500]);
        return new WP_REST_Response(Rental::get_by_id($id),201);
    }

    public static function rentals_incoming(): WP_REST_Response {
        return new WP_REST_Response(Rental::get_incoming(get_current_user_id()),200);
    }

    public static function rentals_outgoing(): WP_REST_Response {
        return new WP_REST_Response(Rental::get_outgoing(get_current_user_id()),200);
    }

    public static function rentals_status( WP_REST_Request $r ): WP_REST_Response|WP_Error {
        $id     = intval($r['id']);
        $status = sanitize_text_field($r->get_param('status'));
        $uid    = get_current_user_id();
        $rental = Rental::get_by_id($id);

        if (!$rental) return new WP_Error('not_found','Rental not found.',['status'=>404]);
        $allowed = ['pending','approved','rejected','returned','cancelled'];
        if (!in_array($status,$allowed,true)) return new WP_Error('invalid','Invalid status.',['status'=>400]);
        // Owner can approve/reject/mark returned; requester can cancel
        if (in_array($status,['approved','rejected','returned'],true) && (int)$rental->owner_id !== $uid && !current_user_can('manage_options')) {
            return new WP_Error('forbidden','Only owner can update this status.',['status'=>403]);
        }
        if ($status==='cancelled' && (int)$rental->requester_id !== $uid && !current_user_can('manage_options')) {
            return new WP_Error('forbidden','Only requester can cancel.',['status'=>403]);
        }

        Rental::update_status($id,$status);
        return new WP_REST_Response(Rental::get_by_id($id),200);
    }
}
