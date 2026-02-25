<?php
namespace BookShare\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Rental model — stored in the `bs_rentals` custom table.
 *
 * Schema (unchanged):
 *   id, book_id, owner_id, requester_id, status, message,
 *   start_date, end_date, created_at, updated_at
 *
 * BREAKING CHANGE from old model:
 *   `book_id` is now a WP post ID of a `bs_book` post,
 *   NOT a row ID from the old `bs_books` table.
 *
 * All joined book/author data is fetched from wp_posts + wp_postmeta
 * rather than the old bs_books / bs_authors tables.
 */
class Rental {

    // ── Valid status values ───────────────────────────────────────────────────
    const STATUSES = [ 'pending', 'approved', 'rejected', 'returned', 'cancelled' ];

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'bs_rentals';
    }

    // =========================================================================
    // READ
    // =========================================================================

    /**
     * Rentals where the current user is the book owner (incoming requests).
     */
    public static function get_incoming( int $owner_id ): array {
        global $wpdb;

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*,
                    own.display_name AS owner_name,
                    req.display_name AS requester_name
             FROM   {$wpdb->prefix}bs_rentals r
             JOIN   {$wpdb->users} own ON r.owner_id     = own.ID
             JOIN   {$wpdb->users} req ON r.requester_id = req.ID
             WHERE  r.owner_id = %d
             ORDER  BY r.created_at DESC",
            $owner_id
        ) );

        return array_map( [ self::class, 'hydrate_book_data' ], $rows );
    }

    /**
     * Rentals where the current user is the requester (outgoing requests).
     */
    public static function get_outgoing( int $user_id ): array {
        global $wpdb;

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*,
                    own.display_name AS owner_name,
                    req.display_name AS requester_name
             FROM   {$wpdb->prefix}bs_rentals r
             JOIN   {$wpdb->users} own ON r.owner_id     = own.ID
             JOIN   {$wpdb->users} req ON r.requester_id = req.ID
             WHERE  r.requester_id = %d
             ORDER  BY r.created_at DESC",
            $user_id
        ) );

        return array_map( [ self::class, 'hydrate_book_data' ], $rows );
    }

    /**
     * All rentals for the admin panel (paginated, optional status filter).
     *
     * @param array $args {
     *   string $status   – one of self::STATUSES
     *   int    $per_page – default 30
     *   int    $offset   – row offset
     *   int    $paged    – WP-style page number
     * }
     */
    public static function get_all_admin( array $args = [] ): array {
        global $wpdb;

        $where  = '1=1';
        $params = [];

        if ( ! empty( $args['status'] ) && in_array( $args['status'], self::STATUSES, true ) ) {
            $where   .= ' AND r.status = %s';
            $params[] = $args['status'];
        }

        $per_page = intval( $args['per_page'] ?? 30 );
        $offset   = self::resolve_offset( $args, $per_page );
        $params[] = $per_page;
        $params[] = $offset;

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*,
                    own.display_name AS owner_name,
                    req.display_name AS requester_name
             FROM   {$wpdb->prefix}bs_rentals r
             JOIN   {$wpdb->users} own ON r.owner_id     = own.ID
             JOIN   {$wpdb->users} req ON r.requester_id = req.ID
             WHERE  {$where}
             ORDER  BY r.created_at DESC
             LIMIT  %d OFFSET %d",
            $params
        ) );

        return array_map( [ self::class, 'hydrate_book_data' ], $rows );
    }

    /**
     * Total rental count (optional status filter).
     */
    public static function count_admin( array $args = [] ): int {
        global $wpdb;

        if ( ! empty( $args['status'] ) && in_array( $args['status'], self::STATUSES, true ) ) {
            return (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}bs_rentals WHERE status = %s",
                $args['status']
            ) );
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bs_rentals" );
    }

    /**
     * Single rental by its ID, with book + user data attached.
     */
    public static function get_by_id( int $id ): ?object {
        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT r.*,
                    own.display_name AS owner_name,
                    req.display_name AS requester_name
             FROM   {$wpdb->prefix}bs_rentals r
             JOIN   {$wpdb->users} own ON r.owner_id     = own.ID
             JOIN   {$wpdb->users} req ON r.requester_id = req.ID
             WHERE  r.id = %d",
            $id
        ) );

        if ( ! $row ) return null;

        return self::hydrate_book_data( $row );
    }

    // =========================================================================
    // WRITE
    // =========================================================================

    /**
     * Create a new rental request.
     *
     * @param array $data {
     *   int    $book_id      WP post ID of the bs_book post
     *   int    $owner_id     WP user ID of the book owner
     *   int    $requester_id WP user ID of the requester
     *   string $message      Optional message from requester
     *   string $start_date   Y-m-d
     *   string $end_date     Y-m-d
     * }
     */
    public static function create( array $data ): int|false {
        global $wpdb;

        $now              = current_time( 'mysql' );
        $data['status']   = $data['status'] ?? 'pending';
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        // Sanitise
        $insert = [
            'book_id'       => intval( $data['book_id'] ),
            'owner_id'      => intval( $data['owner_id'] ),
            'requester_id'  => intval( $data['requester_id'] ),
            'status'        => sanitize_text_field( $data['status'] ),
            'message'       => sanitize_textarea_field( $data['message'] ?? '' ),
            'start_date'    => sanitize_text_field( $data['start_date'] ?? '' ) ?: null,
            'end_date'      => sanitize_text_field( $data['end_date']   ?? '' ) ?: null,
            'created_at'    => $now,
            'updated_at'    => $now,
        ];

        $ok = $wpdb->insert( self::table(), $insert );
        return $ok ? $wpdb->insert_id : false;
    }

    /**
     * Update rental status.
     *
     * @param int    $id     Rental row ID.
     * @param string $status One of self::STATUSES.
     */
    public static function update_status( int $id, string $status ): bool {
        global $wpdb;

        if ( ! in_array( $status, self::STATUSES, true ) ) {
            return false;
        }

        return (bool) $wpdb->update(
            self::table(),
            [ 'status' => $status, 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $id ]
        );
    }

    /**
     * Check whether an active (pending or approved) request already exists
     * for a given book + requester pair.
     *
     * @param int $book_id      WP post ID of the bs_book post.
     * @param int $requester_id WP user ID.
     */
    public static function active_request_exists( int $book_id, int $requester_id ): bool {
        global $wpdb;

        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}bs_rentals
             WHERE  book_id      = %d
               AND  requester_id = %d
               AND  status       IN ('pending','approved')",
            $book_id,
            $requester_id
        ) );
    }

    // =========================================================================
    // INTERNALS
    // =========================================================================

    /**
     * Attach book title, unique_code, cover_url, author_name
     * from the bs_book CPT to a raw rental row.
     *
     * Called after every DB query so all query methods return
     * objects with the same shape as before.
     */
    private static function hydrate_book_data( object $row ): object {
        $book_id = (int) $row->book_id;

        // Fetch post + meta in one go
        $post = $book_id ? get_post( $book_id ) : null;

        if ( $post && $post->post_type === 'bs_book' ) {
            $row->title       = $post->post_title;
            $row->unique_code = (string) get_post_meta( $book_id, 'bs_unique_code', true );
            $row->cover_url   = (string) get_post_meta( $book_id, 'bs_cover_url',   true );

            $author_id        = (int) get_post_meta( $book_id, 'bs_author_id', true );
            $row->author_name = $author_id ? get_the_title( $author_id ) : '';
        } else {
            // Book was deleted — provide safe fallback values
            $row->title       = __( '(deleted book)', 'bookshare' );
            $row->unique_code = '';
            $row->cover_url   = '';
            $row->author_name = '';
        }

        return $row;
    }

    /**
     * Resolve a DB OFFSET from either $args['offset'] or $args['paged'].
     */
    private static function resolve_offset( array $args, int $per_page ): int {
        if ( ! empty( $args['offset'] ) ) {
            return intval( $args['offset'] );
        }
        if ( ! empty( $args['paged'] ) && $per_page > 0 ) {
            return ( max( 1, intval( $args['paged'] ) ) - 1 ) * $per_page;
        }
        return 0;
    }
}