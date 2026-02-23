<?php
namespace BookShare\Models;

/**
 * UserLibrary Model — manages which books belong to which user.
 */
class UserLibrary {

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'bs_user_library';
    }

    /** Add a book to a user's library */
    public static function add( int $user_id, int $book_id, bool $is_public = true ): bool {
        global $wpdb;
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE user_id=%d AND book_id=%d",
            $user_id, $book_id
        ) );

        if ( $exists ) return false; // already exists

        $wpdb->insert( self::table(), [
            'user_id'   => $user_id,
            'book_id'   => $book_id,
            'is_public' => $is_public ? 1 : 0,
            'available' => 1,
        ] );
        return (bool) $wpdb->insert_id;
    }

    /** Remove a book from a user's library */
    public static function remove( int $user_id, int $book_id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( self::table(), [
            'user_id' => $user_id,
            'book_id' => $book_id,
        ] );
    }

    /** Get a user's library (with book details) */
    public static function get_user_library( int $user_id, bool $public_only = false ): array {
        global $wpdb;
        $t  = self::table();
        $bt = $wpdb->prefix . 'bs_books';
        $pub = $public_only ? 'AND ul.is_public = 1' : '';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT b.*, ul.is_public, ul.available, ul.added_at
             FROM $t ul
             INNER JOIN $bt b ON b.id = ul.book_id
             WHERE ul.user_id = %d $pub
             ORDER BY ul.added_at DESC",
            $user_id
        ) );
    }

    /** Toggle public/private visibility */
    public static function toggle_visibility( int $user_id, int $book_id ): bool {
        global $wpdb;
        $t       = self::table();
        $current = $wpdb->get_var( $wpdb->prepare(
            "SELECT is_public FROM $t WHERE user_id=%d AND book_id=%d",
            $user_id, $book_id
        ) );
        if ( null === $current ) return false;

        return (bool) $wpdb->update( $t,
            [ 'is_public' => $current ? 0 : 1 ],
            [ 'user_id' => $user_id, 'book_id' => $book_id ]
        );
    }

    /** Search all public libraries by book unique_code */
    public static function search_by_unique_code( string $code ): array {
        global $wpdb;
        $t  = self::table();
        $bt = $wpdb->prefix . 'bs_books';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT b.*, ul.is_public, ul.available, ul.user_id,
                    u.display_name AS reader_name
             FROM $t ul
             INNER JOIN $bt b ON b.id = ul.book_id
             INNER JOIN {$wpdb->users} u ON u.ID = ul.user_id
             WHERE b.unique_code = %s AND ul.is_public = 1
             ORDER BY ul.added_at DESC",
            $code
        ) );
    }

    /** Count how many public libraries contain a given book */
    public static function count_holders( int $book_id ): int {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::table() . " WHERE book_id=%d AND is_public=1",
            $book_id
        ) );
    }
}