<?php
namespace BookShare\Models;

defined( 'ABSPATH' ) || exit;

class Book {

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'bs_books';
    }

    public static function generate_code(): string {
        do {
            $code = strtoupper( substr( str_shuffle( 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789' ), 0, 8 ) );
        } while ( self::get_by_code( $code ) );
        return $code;
    }

    public static function get_all( array $args = [] ): array {
        global $wpdb;
        $t  = self::table();
        $ta = $wpdb->prefix . 'bs_authors';
        $tp = $wpdb->prefix . 'bs_publishers';

        $where  = '1=1';
        $params = [];

        if ( ! empty( $args['search'] ) ) {
            $s      = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where .= " AND (b.title LIKE %s OR a.name LIKE %s OR b.genre LIKE %s OR b.isbn LIKE %s)";
            $params = array_merge( $params, [ $s, $s, $s, $s ] );
        }
        if ( ! empty( $args['genre'] ) ) {
            $where .= ' AND b.genre = %s';
            $params[] = $args['genre'];
        }

        $limit  = intval( $args['per_page'] ?? 20 );
        $offset = intval( $args['offset'] ?? 0 );

        $sql = "SELECT b.*, a.name AS author_name, p.name AS publisher_name
                FROM {$t} b
                LEFT JOIN {$ta} a ON b.author_id = a.id
                LEFT JOIN {$tp} p ON b.publisher_id = p.id
                WHERE {$where}
                ORDER BY b.created_at DESC
                LIMIT %d OFFSET %d";

        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
    }

    public static function count( array $args = [] ): int {
        global $wpdb;
        $t  = self::table();
        $ta = $wpdb->prefix . 'bs_authors';

        $where  = '1=1';
        $params = [];

        if ( ! empty( $args['search'] ) ) {
            $s      = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where .= " AND (b.title LIKE %s OR a.name LIKE %s OR b.genre LIKE %s)";
            $params = array_merge( $params, [ $s, $s, $s ] );
        }

        $sql = "SELECT COUNT(*) FROM {$t} b LEFT JOIN {$ta} a ON b.author_id = a.id WHERE {$where}";
        return (int) ( empty( $params ) ? $wpdb->get_var( $sql ) : $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) );
    }

    public static function get_by_id( int $id ): ?object {
        global $wpdb;
        $t  = self::table();
        $ta = $wpdb->prefix . 'bs_authors';
        $tp = $wpdb->prefix . 'bs_publishers';

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT b.*, a.name AS author_name, a.bio AS author_bio, a.photo_url AS author_photo,
                    p.name AS publisher_name
             FROM {$t} b
             LEFT JOIN {$ta} a ON b.author_id = a.id
             LEFT JOIN {$tp} p ON b.publisher_id = p.id
             WHERE b.id = %d",
            $id
        ) );
    }

    public static function get_by_code( string $code ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE unique_code = %s",
            $code
        ) );
    }

    public static function create( array $data ): int|false {
        global $wpdb;
        $data['unique_code'] = self::generate_code();
        $data['created_at']  = current_time( 'mysql' );
        $ok = $wpdb->insert( self::table(), $data );
        return $ok ? $wpdb->insert_id : false;
    }

    public static function update( int $id, array $data ): bool {
        global $wpdb;
        return (bool) $wpdb->update( self::table(), $data, [ 'id' => $id ] );
    }

    public static function delete( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( self::table(), [ 'id' => $id ] );
    }

    public static function genres(): array {
        global $wpdb;
        return $wpdb->get_col( "SELECT DISTINCT genre FROM " . self::table() . " WHERE genre != '' ORDER BY genre" );
    }
}
