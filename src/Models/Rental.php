<?php
namespace BookShare\Models;

/**
 * Rental Model — manages rental requests between users.
 */
class Rental {

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'bs_rentals';
    }

    /** Create a rental request */
    public static function request( int $book_id, int $owner_id, int $requester_id, string $message = '' ): int|false {
        global $wpdb;

        // Check for existing pending request
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . self::table() . "
             WHERE book_id=%d AND owner_id=%d AND requester_id=%d AND status='pending'",
            $book_id, $owner_id, $requester_id
        ) );
        if ( $exists ) return false;

        $wpdb->insert( self::table(), [
            'book_id'      => $book_id,
            'owner_id'     => $owner_id,
            'requester_id' => $requester_id,
            'status'       => 'pending',
            'message'      => sanitize_textarea_field( $message ),
        ] );
        return $wpdb->insert_id ?: false;
    }

    /** Update rental status (owner action) */
    public static function update_status( int $rental_id, int $owner_id, string $status ): bool {
        global $wpdb;
        $allowed = [ 'approved', 'rejected', 'returned' ];
        if ( ! in_array( $status, $allowed, true ) ) return false;

        $data = [ 'status' => $status ];
        if ( 'approved' === $status ) $data['approved_at'] = current_time( 'mysql' );
        if ( 'returned' === $status ) $data['returned_at'] = current_time( 'mysql' );

        return (bool) $wpdb->update( self::table(), $data, [
            'id'       => $rental_id,
            'owner_id' => $owner_id,
        ] );
    }

    /** Get all rentals for an owner */
    public static function get_owner_requests( int $owner_id ): array {
        global $wpdb;
        $t  = self::table();
        $bt = $wpdb->prefix . 'bs_books';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*, b.title, b.unique_code, b.author,
                    u.display_name AS requester_name
             FROM $t r
             INNER JOIN $bt b ON b.id = r.book_id
             INNER JOIN {$wpdb->users} u ON u.ID = r.requester_id
             WHERE r.owner_id = %d
             ORDER BY r.requested_at DESC",
            $owner_id
        ) );
    }

    /** Get all rentals made by a requester */
    public static function get_user_requests( int $user_id ): array {
        global $wpdb;
        $t  = self::table();
        $bt = $wpdb->prefix . 'bs_books';

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*, b.title, b.unique_code, b.author,
                    u.display_name AS owner_name
             FROM $t r
             INNER JOIN $bt b ON b.id = r.book_id
             INNER JOIN {$wpdb->users} u ON u.ID = r.owner_id
             WHERE r.requester_id = %d
             ORDER BY r.requested_at DESC",
            $user_id
        ) );
    }
}