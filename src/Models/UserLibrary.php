<?php
namespace BookShare\Models;

defined( 'ABSPATH' ) || exit;

/**
 * UserLibrary model — stored in the `bs_user_library` custom table.
 *
 * Schema:
 *   id, user_id, book_id, is_public, condition_note, added_at
 *
 * `book_id` = WP post ID of a `bs_book` post
 *
 * Book, author, and publisher data is resolved entirely from CPT posts
 * and post meta — no custom bs_books / bs_authors / bs_publishers tables.
 *
 * Performance: hydrate_rows() loads all book posts and all author posts
 * in two bulk queries (+ one meta cache warm), so N+1 is avoided even
 * for large libraries.
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
     * All library entries for a user, hydrated with book/author/publisher data.
     *
     * @param int  $user_id         WP user ID.
     * @param bool $include_private Include private (is_public = 0) entries.
     * @return object[]
     */
    public static function get_for_user( int $user_id, bool $include_private = true ): array {
        global $wpdb;

        $privacy = $include_private ? '' : 'AND ul.is_public = 1';

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT ul.*
             FROM   {$wpdb->prefix}bs_user_library ul
             WHERE  ul.user_id = %d {$privacy}
             ORDER  BY ul.added_at DESC",
            $user_id
        ) );

        return empty( $rows ) ? [] : self::hydrate_rows( $rows );
    }

    /**
     * All public holders of a book, looked up by unique code.
     *
     * Resolves the unique_code → bs_book post ID, then queries the
     * library table and hydrates each row with full CPT data.
     *
     * @param string $code The bs_unique_code meta value (e.g. "AB12CD34").
     * @return object[]
     */
    public static function find_holders_by_code( string $code ): array {
        global $wpdb;

        $book_id = self::resolve_book_id_by_code( sanitize_text_field( $code ) );
        if ( ! $book_id ) {
            return [];
        }

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT ul.*
             FROM   {$wpdb->prefix}bs_user_library ul
             WHERE  ul.book_id   = %d
               AND  ul.is_public = 1
             ORDER  BY ul.added_at ASC",
            $book_id
        ) );

        return empty( $rows ) ? [] : self::hydrate_rows( $rows );
    }

    /**
     * All public library entries for books by a specific author (bs_author post ID).
     *
     * @param int $author_post_id WP post ID of the bs_author post.
     * @return object[]
     */
    public static function find_by_author( int $author_post_id ): array {
        global $wpdb;

        // Find all bs_book post IDs whose bs_author_id meta matches
        $book_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE  meta_key   = 'bs_author_id'
               AND  meta_value = %d",
            $author_post_id
        ) );

        if ( empty( $book_ids ) ) {
            return [];
        }

        return self::get_entries_for_book_ids( $book_ids );
    }

    /**
     * All public library entries for books by a specific publisher (bs_publisher post ID).
     *
     * @param int $publisher_post_id WP post ID of the bs_publisher post.
     * @return object[]
     */
    public static function find_by_publisher( int $publisher_post_id ): array {
        global $wpdb;

        $book_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE  meta_key   = 'bs_publisher_id'
               AND  meta_value = %d",
            $publisher_post_id
        ) );

        if ( empty( $book_ids ) ) {
            return [];
        }

        return self::get_entries_for_book_ids( $book_ids );
    }

    /**
     * Check whether a user has a specific bs_book in their library.
     *
     * @param int $user_id WP user ID.
     * @param int $book_id WP post ID of the bs_book post.
     */
    public static function user_has( int $user_id, int $book_id ): bool {
        global $wpdb;

        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}bs_user_library
             WHERE  user_id = %d AND book_id = %d
             LIMIT  1",
            $user_id,
            $book_id
        ) );
    }

    /**
     * Count how many users publicly hold a specific bs_book.
     *
     * @param int $book_id WP post ID of the bs_book post.
     */
    public static function count_holders( int $book_id ): int {
        global $wpdb;

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}bs_user_library
             WHERE book_id = %d AND is_public = 1",
            $book_id
        ) );
    }

    // =========================================================================
    // WRITE
    // =========================================================================

    /**
     * Add a bs_book to a user's library (idempotent).
     *
     * Validates that $book_id is a real bs_book post before inserting.
     * Returns the library row ID (existing or new), or false on failure.
     *
     * @param int    $user_id        WP user ID.
     * @param int    $book_id        WP post ID of the bs_book post.
     * @param string $condition_note Optional condition note.
     */
    public static function add( int $user_id, int $book_id ): int|false {
        global $wpdb;



        // Confirm the post is a bs_book
        $book_post = get_post( $book_id );
        if ( ! $book_post || $book_post->post_type !== 'bs_book' ) {
            return false;
        }



        // Idempotent — return existing row ID if already in library
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}bs_user_library
             WHERE  user_id = %d AND book_id = %d
             LIMIT  1",
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
            'added_at'       => current_time( 'mysql' ),
        ], [ '%d', '%d', '%d', '%s', '%s' ] );

        return $ok ? (int) $wpdb->insert_id : false;
    }

    /**
     * Remove a bs_book from a user's library.
     *
     * @param int $user_id WP user ID.
     * @param int $book_id WP post ID of the bs_book post.
     */
    public static function remove( int $user_id, int $book_id ): bool {
        global $wpdb;

        return (bool) $wpdb->delete(
            self::table(),
            [ 'user_id' => $user_id, 'book_id' => $book_id ],
            [ '%d', '%d' ]
        );
    }

    /**
     * Toggle the is_public flag for a library entry.
     * Returns false if the entry does not exist.
     *
     * @param int $user_id WP user ID.
     * @param int $book_id WP post ID of the bs_book post.
     */
    public static function toggle_visibility( int $user_id, int $book_id ): bool {
        global $wpdb;

        $current = $wpdb->get_var( $wpdb->prepare(
            "SELECT is_public FROM {$wpdb->prefix}bs_user_library
             WHERE  user_id = %d AND book_id = %d
             LIMIT  1",
            $user_id,
            $book_id
        ) );

        if ( $current === null ) {
            return false;
        }

        return (bool) $wpdb->update(
            self::table(),
            [ 'is_public' => (int) $current ? 0 : 1 ],
            [ 'user_id'   => $user_id, 'book_id' => $book_id ],
            [ '%d' ],
            [ '%d', '%d' ]
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
            [ 'user_id' => $user_id, 'book_id' => $book_id ],
            [ '%s' ],
            [ '%d', '%d' ]
        );
    }

    // =========================================================================
    // INTERNALS
    // =========================================================================

    /**
     * Fetch public library entries for a list of book post IDs and hydrate them.
     *
     * @param int[] $book_ids Array of bs_book WP post IDs.
     * @return object[]
     */
    private static function get_entries_for_book_ids( array $book_ids ): array {
        global $wpdb;

        $book_ids     = array_map( 'intval', $book_ids );
        $placeholders = implode( ',', array_fill( 0, count( $book_ids ), '%d' ) );

        // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ul.*
                 FROM   {$wpdb->prefix}bs_user_library ul
                 WHERE  ul.book_id IN ( {$placeholders} )
                   AND  ul.is_public = 1
                 ORDER  BY ul.added_at DESC",
                $book_ids
            )
        );

        return empty( $rows ) ? [] : self::hydrate_rows( $rows );
    }

    /**
     * Bulk-hydrate an array of raw bs_user_library rows with full CPT data.
     *
     * Resolves bs_book → post title + all meta
     * Resolves bs_author → post title + photo + bio      (from bs_author CPT)
     * Resolves bs_publisher → post title                 (from bs_publisher CPT)
     *
     * Uses bulk get_posts() + update_post_meta_cache() so the entire
     * hydration is O(queries) = 4, regardless of library size:
     *   1. get_posts( post__in book_ids )       — fetch all book posts
     *   2. update_post_meta_cache( book_ids )   — warm book meta
     *   3. get_posts( post__in author_ids )     — fetch all author posts
     *   4. update_post_meta_cache( author_ids ) — warm author meta
     *
     * @param object[] $rows Raw DB rows.
     * @return object[]
     */
    private static function hydrate_rows( array $rows ): array {

        // ── 1. Collect all distinct book post IDs ─────────────────────────────
        $book_ids = array_values( array_unique(
            array_map( static fn( $r ) => (int) $r->book_id, $rows )
        ) );

        // ── 2. Fetch all book posts in one query ──────────────────────────────
        $book_posts_raw = get_posts( [
            'post_type'      => 'bs_book',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'post__in'       => $book_ids,
            'orderby'        => 'post__in',
        ] );

        // Warm book meta cache (avoids individual get_post_meta queries per row)
        if ( ! empty( $book_posts_raw ) ) {
            update_meta_cache( 'post', wp_list_pluck( $book_posts_raw, 'ID' ) );
        }

        // Index books by post ID for O(1) access
        $books = [];
        foreach ( $book_posts_raw as $p ) {
            $books[ $p->ID ] = $p;
        }

        // ── 3. Collect all distinct author post IDs from book meta ────────────
        $author_ids = [];
        foreach ( $book_ids as $bid ) {
            $aid = (int) get_post_meta( $bid, 'bs_author_id', true );
            if ( $aid > 0 ) {
                $author_ids[ $aid ] = $aid;
            }
        }

        // ── 4. Fetch all author posts + warm their meta in one pass ───────────
        if ( ! empty( $author_ids ) ) {
            $author_posts_raw = get_posts( [
                'post_type'      => 'bs_author',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'post__in'       => array_values( $author_ids ),
                'orderby'        => 'post__in',
            ] );
            update_meta_cache( 'post', wp_list_pluck( $author_posts_raw, 'ID' ) );
        }

        // ── 5. Collect all distinct publisher post IDs from book meta ─────────
        $publisher_ids = [];
        foreach ( $book_ids as $bid ) {
            $pid = (int) get_post_meta( $bid, 'bs_publisher_id', true );
            if ( $pid > 0 ) {
                $publisher_ids[ $pid ] = $pid;
            }
        }

        // ── 6. Fetch all publisher posts in one pass ──────────────────────────
        if ( ! empty( $publisher_ids ) ) {
            $publisher_posts_raw = get_posts( [
                'post_type'      => 'bs_publisher',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'post__in'       => array_values( $publisher_ids ),
                'orderby'        => 'post__in',
            ] );
            update_post_meta_cache( wp_list_pluck( $publisher_posts_raw, 'ID' ) );
        }

        // ── 7. Map each row, pulling from warmed caches ───────────────────────
        return array_map( static function ( object $row ) use ( $books ): object {

            $book_id  = (int) $row->book_id;
            $book_post = $books[ $book_id ] ?? null;

            if ( $book_post ) {

                // ── Book fields ───────────────────────────────────────────────
                $row->title          = $book_post->post_title;
                $row->unique_code    = (string) get_post_meta( $book_id, 'bs_unique_code',    true );
                $row->cover_url      = (string) get_post_meta( $book_id, 'bs_cover_url',      true );
                $row->genre          = (string) get_post_meta( $book_id, 'bs_genre',          true );
                $row->isbn           = (string) get_post_meta( $book_id, 'bs_isbn',           true );
                $row->published_year = (string) get_post_meta( $book_id, 'bs_published_year', true );
                $row->language       = (string) get_post_meta( $book_id, 'bs_language',       true );
                $row->pages          = (int)    get_post_meta( $book_id, 'bs_pages',          true );
                $row->description    = (string) get_post_meta( $book_id, 'bs_description',    true );

                // ── Author fields (from bs_author CPT) ────────────────────────
                $author_id = (int) get_post_meta( $book_id, 'bs_author_id', true );
                if ( $author_id ) {
                    $row->author_id    = $author_id;
                    $row->author_name  = get_the_title( $author_id );          // warmed
                    $row->author_photo = (string) get_post_meta( $author_id, 'bs_photo_url', true );
                    $row->author_bio   = (string) get_post_meta( $author_id, 'bs_bio',       true );
                } else {
                    $row->author_id    = 0;
                    $row->author_name  = '';
                    $row->author_photo = '';
                    $row->author_bio   = '';
                }

                // ── Publisher fields (from bs_publisher CPT) ──────────────────
                $publisher_id = (int) get_post_meta( $book_id, 'bs_publisher_id', true );
                if ( $publisher_id ) {
                    $row->publisher_id   = $publisher_id;
                    $row->publisher_name = get_the_title( $publisher_id );     // warmed
                } else {
                    $row->publisher_id   = 0;
                    $row->publisher_name = '';
                }

            } else {

                // ── Deleted book — safe fallbacks ─────────────────────────────
                $row->title          = __( '(deleted book)', 'bookshare' );
                $row->unique_code    = '';
                $row->cover_url      = '';
                $row->genre          = '';
                $row->isbn           = '';
                $row->published_year = '';
                $row->language       = '';
                $row->pages          = 0;
                $row->description    = '';
                $row->author_id      = 0;
                $row->author_name    = '';
                $row->author_photo   = '';
                $row->author_bio     = '';
                $row->publisher_id   = 0;
                $row->publisher_name = '';
            }

            return $row;

        }, $rows );
    }

    /**
     * Resolve a bs_unique_code string to a bs_book WP post ID.
     * Returns 0 if not found.
     *
     * @param string $code Sanitised unique code.
     */
    private static function resolve_book_id_by_code( string $code ): int {
        $posts = get_posts( [
            'post_type'      => 'bs_book',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [ [
                'key'   => 'bs_unique_code',
                'value' => $code,
            ] ],
        ] );

        return ! empty( $posts ) ? (int) $posts[0] : 0;
    }
}
