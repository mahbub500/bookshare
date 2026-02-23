<?php
namespace BookShare\Models;

class Rental {

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'bs_rentals';
    }

    public static function request( int $book_id, int $owner_id, int $requester_id, string $message = '' ) {
        global $wpdb;
        $t = self::table();
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $t WHERE book_id=%d AND owner_id=%d AND requester_id=%d AND status='pending'",
            $book_id, $owner_id, $requester_id
        ) );
        if ( $exists ) return false;

        $wpdb->insert( $t, [
            'book_id'      => $book_id,
            'owner_id'     => $owner_id,
            'requester_id' => $requester_id,
            'status'       => 'pending',
            'message'      => sanitize_textarea_field( $message ),
        ] );
        return $wpdb->insert_id ?: false;
    }

    public static function update_status( int $id, int $owner_id, string $status ): bool {
        global $wpdb;
        if ( ! in_array( $status, [ 'approved','rejected','returned' ], true ) ) return false;
        $data = [ 'status' => $status ];
        if ( 'approved' === $status ) $data['approved_at'] = current_time( 'mysql' );
        if ( 'returned' === $status ) $data['returned_at'] = current_time( 'mysql' );
        return (bool) $wpdb->update( self::table(), $data, [ 'id' => $id, 'owner_id' => $owner_id ] );
    }

    public static function get_incoming( int $owner_id ): array {
        global $wpdb;
        $t = self::table();
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*, u.display_name AS requester_name FROM $t r
             JOIN {$wpdb->users} u ON u.ID = r.requester_id
             WHERE r.owner_id=%d ORDER BY r.requested_at DESC",
            $owner_id
        ) );
        foreach ( $rows as $row ) {
            $post = get_post( $row->book_id );
            $row->book_title = $post ? $post->post_title : 'Unknown';
            $row->book_code  = $post ? get_post_meta( $post->ID, '_bs_unique_code', true ) : '';
        }
        return $rows;
    }

    public static function get_outgoing( int $user_id ): array {
        global $wpdb;
        $t = self::table();
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*, u.display_name AS owner_name FROM $t r
             JOIN {$wpdb->users} u ON u.ID = r.owner_id
             WHERE r.requester_id=%d ORDER BY r.requested_at DESC",
            $user_id
        ) );
        foreach ( $rows as $row ) {
            $post = get_post( $row->book_id );
            $row->book_title = $post ? $post->post_title : 'Unknown';
            $row->book_code  = $post ? get_post_meta( $post->ID, '_bs_unique_code', true ) : '';
        }
        return $rows;
    }
}