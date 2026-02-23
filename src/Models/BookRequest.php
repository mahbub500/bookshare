<?php
namespace BookShare\Models;

class BookRequest {

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'bs_book_requests';
    }

    public static function create( int $user_id, array $data ) {
        global $wpdb;
        $wpdb->insert( self::table(), [
            'user_id'   => $user_id,
            'title'     => sanitize_text_field( $data['title'] ),
            'author'    => sanitize_text_field( $data['author']    ?? '' ),
            'publisher' => sanitize_text_field( $data['publisher'] ?? '' ),
            'isbn'      => sanitize_text_field( $data['isbn']      ?? '' ),
            'notes'     => sanitize_textarea_field( $data['notes'] ?? '' ),
            'status'    => 'pending',
        ] );
        return $wpdb->insert_id ?: false;
    }

    public static function get_user_requests( int $user_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ) );
    }

    public static function pending_count(): int {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table() . " WHERE status='pending'" );
    }
}