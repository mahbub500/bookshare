<?php
namespace BookShare\Models;

defined( 'ABSPATH' ) || exit;

class Rental {

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'bs_rentals';
    }

    private static function base_select(): string {
        global $wpdb;
        $tr = self::table();
        $tb = $wpdb->prefix . 'bs_books';
        $ta = $wpdb->prefix . 'bs_authors';
        return "SELECT r.*, b.title, b.unique_code, b.cover_url,
                       a.name AS author_name,
                       own.display_name AS owner_name,
                       req.display_name AS requester_name
                FROM {$tr} r
                JOIN {$tb} b ON r.book_id = b.id
                LEFT JOIN {$ta} a ON b.author_id = a.id
                JOIN {$wpdb->users} own ON r.owner_id = own.ID
                JOIN {$wpdb->users} req ON r.requester_id = req.ID";
    }

    public static function get_incoming( int $owner_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            self::base_select() . " WHERE r.owner_id = %d ORDER BY r.created_at DESC",
            $owner_id
        ) );
    }

    public static function get_outgoing( int $user_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            self::base_select() . " WHERE r.requester_id = %d ORDER BY r.created_at DESC",
            $user_id
        ) );
    }

    public static function get_all_admin( array $args = [] ): array {
        global $wpdb;
        $where  = '1=1';
        $params = [];

        if ( ! empty( $args['status'] ) ) {
            $where .= ' AND r.status = %s';
            $params[] = $args['status'];
        }

        $limit  = intval( $args['per_page'] ?? 30 );
        $offset = intval( $args['offset'] ?? 0 );
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results( $wpdb->prepare(
            self::base_select() . " WHERE {$where} ORDER BY r.created_at DESC LIMIT %d OFFSET %d",
            $params
        ) );
    }

    public static function count_admin(): int {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table() );
    }

    public static function get_by_id( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            self::base_select() . " WHERE r.id = %d",
            $id
        ) );
    }

    public static function create( array $data ): int|false {
        global $wpdb;
        $data['created_at'] = current_time( 'mysql' );
        $data['updated_at'] = current_time( 'mysql' );
        $ok = $wpdb->insert( self::table(), $data );
        return $ok ? $wpdb->insert_id : false;
    }

    public static function update_status( int $id, string $status ): bool {
        global $wpdb;
        return (bool) $wpdb->update( self::table(), [ 'status' => $status ], [ 'id' => $id ] );
    }

    public static function active_request_exists( int $book_id, int $requester_id ): bool {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE book_id = %d AND requester_id = %d AND status IN ('pending','approved')",
            $book_id, $requester_id
        ) );
    }
}
