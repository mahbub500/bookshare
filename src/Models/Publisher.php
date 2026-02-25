<?php
namespace BookShare\Models;

defined( 'ABSPATH' ) || exit;

class Publisher {

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'bs_publishers';
    }

    public static function get_all( array $args = [] ): array {
        global $wpdb;
        $t      = self::table();
        $where  = '1=1';
        $params = [];

        if ( ! empty( $args['search'] ) ) {
            $s      = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where .= ' AND (name LIKE %s OR city LIKE %s OR country LIKE %s)';
            $params = [ $s, $s, $s ];
        }

        $limit  = intval( $args['per_page'] ?? 50 );
        $offset = intval( $args['offset'] ?? 0 );
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$t} WHERE {$where} ORDER BY name ASC LIMIT %d OFFSET %d",
            $params
        ) );
    }

    public static function count(): int {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table() );
    }

    public static function get_by_id( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table() . " WHERE id = %d", $id ) );
    }

    public static function get_list(): array {
        global $wpdb;
        return $wpdb->get_results( "SELECT id, name FROM " . self::table() . " ORDER BY name ASC" );
    }

    public static function create( array $data ): int|false {
        global $wpdb;
        $data['created_at'] = current_time( 'mysql' );
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
}
