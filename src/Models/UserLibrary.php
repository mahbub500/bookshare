<?php
namespace BookShare\Models;

class UserLibrary {

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'bs_library';
    }

    private static function books_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'posts';
    }

    public static function add( int $user_id, int $book_id ): bool {
        global $wpdb;
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE user_id=%d AND book_id=%d",
            $user_id, $book_id
        ) );
        if ( $exists ) return false;

        $wpdb->insert( self::table(), [
            'user_id'  => $user_id,
            'book_id'  => $book_id,
            'is_public'=> 1,
            'available'=> 1,
        ] );
        return (bool) $wpdb->insert_id;
    }

    public static function remove( int $user_id, int $book_id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( self::table(), [
            'user_id' => $user_id,
            'book_id' => $book_id,
        ] );
    }

    public static function toggle_visibility( int $user_id, int $book_id ): void {
        global $wpdb;
        $t = self::table();
        $current = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT is_public FROM $t WHERE user_id=%d AND book_id=%d", $user_id, $book_id
        ) );
        $wpdb->update( $t,
            [ 'is_public' => $current ? 0 : 1 ],
            [ 'user_id' => $user_id, 'book_id' => $book_id ]
        );
    }

    /** Get current user's full library (all) */
    public static function get_mine( int $user_id ): array {
        global $wpdb;
        $t = self::table();
        $ids = $wpdb->get_col( $wpdb->prepare( "SELECT book_id FROM $t WHERE user_id=%d", $user_id ) );
        if ( ! $ids ) return [];

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT book_id, is_public, available, added_at FROM $t WHERE user_id=%d", $user_id
        ) );
        $meta_map = [];
        foreach ( $rows as $r ) $meta_map[ $r->book_id ] = $r;

        $books = [];
        foreach ( $ids as $bid ) {
            $post = get_post( (int) $bid );
            if ( ! $post || $post->post_status !== 'publish' ) continue;
            $books[] = self::format_book( $post, $meta_map[$bid] ?? null );
        }
        return $books;
    }

    /** Get another user's public library */
    public static function get_public( int $user_id ): array {
        global $wpdb;
        $t   = self::table();
        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT book_id FROM $t WHERE user_id=%d AND is_public=1", $user_id
        ) );
        if ( ! $ids ) return [];
        $books = [];
        foreach ( $ids as $bid ) {
            $post = get_post( (int) $bid );
            if ( ! $post || $post->post_status !== 'publish' ) continue;
            $books[] = self::format_book( $post );
        }
        return $books;
    }

    /** Find all public holders of a book by unique code */
    public static function find_holders( string $code ): array {
        global $wpdb;
        $t = self::table();

        // Find the book post
        $book_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_bs_unique_code' AND meta_value=%s LIMIT 1",
            strtoupper( $code )
        ) );
        if ( ! $book_id ) return [];

        $post = get_post( $book_id );
        if ( ! $post ) return [];
        $book_data = self::format_book( $post );

        $user_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT user_id FROM $t WHERE book_id=%d AND is_public=1", $book_id
        ) );

        $holders = [];
        foreach ( $user_ids as $uid ) {
            $user = get_userdata( (int) $uid );
            if ( ! $user ) continue;
            $entry          = clone (object) $book_data;
            $entry['user_id']     = (int) $uid;
            $entry['reader_name'] = $user->display_name;
            $holders[] = $entry;
        }
        return $holders;
    }

    /** Format a WP_Post book with all its meta */
    public static function format_book( \WP_Post $post, ?object $lib_meta = null ): array {
        $author_id  = (int) get_post_meta( $post->ID, '_bs_author_id',    true );
        $pub_id     = (int) get_post_meta( $post->ID, '_bs_publisher_id', true );

        return [
            'id'           => $post->ID,
            'title'        => $post->post_title,
            'description'  => wp_strip_all_tags( $post->post_content ),
            'unique_code'  => get_post_meta( $post->ID, '_bs_unique_code', true ),
            'isbn'         => get_post_meta( $post->ID, '_bs_isbn',        true ),
            'genre'        => get_post_meta( $post->ID, '_bs_genre',       true ),
            'pub_year'     => get_post_meta( $post->ID, '_bs_pub_year',    true ),
            'language'     => get_post_meta( $post->ID, '_bs_language',    true ),
            'pages'        => get_post_meta( $post->ID, '_bs_pages',       true ),
            'author_id'    => $author_id,
            'author'       => $author_id ? get_the_title( $author_id ) : '',
            'publisher_id' => $pub_id,
            'publisher'    => $pub_id ? get_the_title( $pub_id ) : '',
            'cover_url'    => get_the_post_thumbnail_url( $post->ID, 'medium' ) ?: '',
            'is_public'    => $lib_meta ? (bool) $lib_meta->is_public : null,
            'available'    => $lib_meta ? (bool) $lib_meta->available : null,
            'added_at'     => $lib_meta ? $lib_meta->added_at : null,
        ];
    }

    /** Check if user has a book */
    public static function has_book( int $user_id, int $book_id ): bool {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE user_id=%d AND book_id=%d",
            $user_id, $book_id
        ) );
    }
}