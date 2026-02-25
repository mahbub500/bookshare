<?php
namespace BookShare\Models;

defined( 'ABSPATH' ) || exit;

/**
 * UserLibrary model — stored in the `bs_user_library` custom table.
 *
 * Schema (unchanged):
 *   id, user_id, book_id, is_public, condition_note, added_at
 *
 * BREAKING CHANGE from old model:
 *   `book_id` is now a WP post ID of a `bs_book` post,
 *   NOT a row ID from the old `bs_books` table.
 *
 * All joined book/author data is fetched from wp_posts + wp_postmeta.
 */
class UserLibrary {

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'bs_user_library';
    }

    // =========================================================================
    // READ
    // =========================================================================

    /**
     * Return all library entries for a user, with book data attached.
     *
     * @param int  $user_id         WP user ID.
     * @param bool $include_private Include private (is_public = 0) entries.
     */
    public static function get_for_user( int $user_id, bool $include_private = true ): array {
        global $wpdb;

        $privacy_clause = $include_private ? '' : 'AND ul.is_public = 1';

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT ul.*
             FROM   {$wpdb->prefix}bs_user_library ul
             WHERE  ul.user_id = %d {$privacy_clause}
             ORDER  BY ul.added_at DESC",
            $user_id
        ) );

        return array_map( [ self::class, 'hydrate_book_data' ], $rows );
    }

    /**
     * Find all public library entries for a book identified by its unique_code.
     * Returns an array of entries with book + holder data.
     *
     * @param string $code The bs_unique_code meta value.
     */
    public static function find_holders_by_code( string $code ): array {
        global $wpdb;

        // Look up the bs_book post by unique_code
        $book_posts = get_posts( [
            'post_type'      => 'bs_book',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_key'       => 'bs_unique_code',
            'meta_value'     => sanitize_text_field( $code ),
            'fields'         => 'ids',
        ] );

        if ( empty( $book_posts ) ) {
            return [];
        }

        $book_id = (int) $book_posts[0];

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT ul.user_id, ul.is_public, ul.condition_note, ul.book_id
             FROM   {$wpdb->prefix}bs_user_library ul
             WHERE  ul.book_id  = %d
               AND  ul.is_public = 1",
            $book_id
        ) );

        return array_map( [ self::class, 'hydrate_book_data' ], $rows );
    }

    /**
     * Check whether a user already has a specific book in their library.
     *
     * @param int $user_id WP user ID.
     * @param int $book_id WP post ID of the bs_book post.
     */
    public static function user_has( int $user_id, int $book_id ): bool {
        global $wpdb;

        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}bs_user_library
             WHERE user_id = %d AND book_id = %d",
            $user_id,
            $book_id
        ) );
    }

    // =========================================================================
    // WRITE
    // =========================================================================

    /**
     * Add a book to a user's library (idempotent — returns existing ID if duplicate).
     *
     * @param int    $user_id        WP user ID.
     * @param int    $book_id        WP post ID of the bs_book post.
     * @param string $condition_note Optional note about the book's condition.
     * @return int|false  Library row ID on success, false on DB error.
     */
    public static function add( int $user_id, int $book_id, string $condition_note = '' ): int|false {
        global $wpdb;

        // Return existing row ID rather than inserting a duplicate
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}bs_user_library
             WHERE user_id = %d AND book_id = %d",
            $user_id,
            $book_id
        ) );

        if ( $existing ) {
            return (int) $existing;
        }

        $ok = $wpdb->insert( self::table(), [
            'user_id'        => $user_id,
            'book_id'        => $book_id,
            'is_public'      => 1,
            'condition_note' => sanitize_text_field( $condition_note ),
            'added_at'       => current_time( 'mysql' ),
        ] );

        return $ok ? $wpdb->insert_id : false;
    }

    /**
     * Remove a book from a user's library.
     *
     * @param int $user_id WP user ID.
     * @param int $book_id WP post ID of the bs_book post.
     */
    public static function remove( int $user_id, int $book_id ): bool {
        global $wpdb;

        return (bool) $wpdb->delete(
            self::table(),
            [ 'user_id' => $user_id, 'book_id' => $book_id ]
        );
    }

    /**
     * Toggle the is_public flag for a library entry.
     *
     * @param int $user_id WP user ID.
     * @param int $book_id WP post ID of the bs_book post.
     * @return bool False if the entry does not exist.
     */
    public static function toggle_visibility( int $user_id, int $book_id ): bool {
        global $wpdb;

        $current = $wpdb->get_var( $wpdb->prepare(
            "SELECT is_public FROM {$wpdb->prefix}bs_user_library
             WHERE user_id = %d AND book_id = %d",
            $user_id,
            $book_id
        ) );

        if ( $current === null ) {
            return false;
        }

        return (bool) $wpdb->update(
            self::table(),
            [ 'is_public' => $current ? 0 : 1 ],
            [ 'user_id' => $user_id, 'book_id' => $book_id ]
        );
    }

    /**
     * Update the condition note for a library entry.
     *
     * @param int    $user_id        WP user ID.
     * @param int    $book_id        WP post ID of the bs_book post.
     * @param string $condition_note New condition note.
     */
    public static function update_condition( int $user_id, int $book_id, string $condition_note ): bool {
        global $wpdb;

        return (bool) $wpdb->update(
            self::table(),
            [ 'condition_note' => sanitize_text_field( $condition_note ) ],
            [ 'user_id' => $user_id, 'book_id' => $book_id ]
        );
    }

    // =========================================================================
    // INTERNALS
    // =========================================================================

    /**
     * Attach book title, unique_code, cover_url, genre, isbn,
     * published_year, author_name to a raw library row.
     *
     * Mirrors the column set returned by the old JOIN query so
     * all templates and REST controllers stay compatible.
     */
    private static function hydrate_book_data( object $row ): object {
        $book_id = (int) $row->book_id;
        $post    = $book_id ? get_post( $book_id ) : null;

        if ( $post && $post->post_type === 'bs_book' ) {
            $row->title          = $post->post_title;
            $row->unique_code    = (string) get_post_meta( $book_id, 'bs_unique_code',    true );
            $row->cover_url      = (string) get_post_meta( $book_id, 'bs_cover_url',      true );
            $row->genre          = (string) get_post_meta( $book_id, 'bs_genre',          true );
            $row->isbn           = (string) get_post_meta( $book_id, 'bs_isbn',           true );
            $row->published_year = (string) get_post_meta( $book_id, 'bs_published_year', true );

            $author_id        = (int) get_post_meta( $book_id, 'bs_author_id', true );
            $row->author_name = $author_id ? get_the_title( $author_id ) : '';
        } else {
            // Book was deleted — safe fallbacks matching old column names
            $row->title          = __( '(deleted book)', 'bookshare' );
            $row->unique_code    = '';
            $row->cover_url      = '';
            $row->genre          = '';
            $row->isbn           = '';
            $row->published_year = '';
            $row->author_name    = '';
        }

        return $row;
    }
}