<?php
namespace BookShare\Models;

defined( 'ABSPATH' ) || exit;

class UserLibrary {

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'bs_user_library';
    }

    public static function get_for_user( int $user_id, bool $include_private = true ): array {
        global $wpdb;
        $tl = self::table();
        $tb = $wpdb->prefix . 'bs_books';
        $ta = $wpdb->prefix . 'bs_authors';
        $privacy = $include_private ? '' : 'AND ul.is_public = 1';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT ul.*, b.title, b.unique_code, b.cover_url, b.genre, b.isbn, b.published_year,
                    a.name AS author_name
             FROM {$tl} ul
             JOIN {$tb} b ON ul.book_id = b.id
             LEFT JOIN {$ta} a ON b.author_id = a.id
             WHERE ul.user_id = %d {$privacy}
             ORDER BY ul.added_at DESC",
            $user_id
        ) );
    }

    public static function find_holders_by_code( string $code ): array {
        global $wpdb;
        $tl = self::table();
        $tb = $wpdb->prefix . 'bs_books';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT ul.user_id, ul.is_public, ul.condition_note, b.title, b.unique_code, b.cover_url
             FROM {$tl} ul
             JOIN {$tb} b ON ul.book_id = b.id
             WHERE b.unique_code = %s AND ul.is_public = 1",
            $code
        ) );
    }

    public static function add( int $user_id, int $book_id, string $condition_note = '' ): int|false {
        global $wpdb;
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE user_id = %d AND book_id = %d",
            $user_id, $book_id
        ) );
        if ( $existing ) return (int) $existing;

        $ok = $wpdb->insert( self::table(), [
            'user_id'        => $user_id,
            'book_id'        => $book_id,
            'is_public'      => 1,
            'condition_note' => $condition_note,
            'added_at'       => current_time( 'mysql' ),
        ] );
        return $ok ? $wpdb->insert_id : false;
    }

    public static function remove( int $user_id, int $book_id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( self::table(), [ 'user_id' => $user_id, 'book_id' => $book_id ] );
    }

    public static function toggle_visibility( int $user_id, int $book_id ): bool {
        global $wpdb;
        $current = $wpdb->get_var( $wpdb->prepare(
            "SELECT is_public FROM " . self::table() . " WHERE user_id = %d AND book_id = %d",
            $user_id, $book_id
        ) );
        if ( $current === null ) return false;
        return (bool) $wpdb->update(
            self::table(),
            [ 'is_public' => $current ? 0 : 1 ],
            [ 'user_id' => $user_id, 'book_id' => $book_id ]
        );
    }

    public static function user_has( int $user_id, int $book_id ): bool {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE user_id = %d AND book_id = %d",
            $user_id, $book_id
        ) );
    }
}
