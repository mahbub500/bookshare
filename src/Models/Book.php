<?php
namespace BookShare\Models;

/**
 * Book Model — interacts with bs_books table.
 */
class Book {

    private static string $table = '';

    private static function table(): string {
        global $wpdb;
        if ( ! self::$table ) {
            self::$table = $wpdb->prefix . 'bs_books';
        }
        return self::$table;
    }

    /** Get all books with optional search */
    public static function all( array $args = [] ): array {
        global $wpdb;
        $t = self::table();

        $where  = '1=1';
        $values = [];

        if ( ! empty( $args['search'] ) ) {
            $like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where  .= ' AND (title LIKE %s OR author LIKE %s OR publisher LIKE %s OR unique_code = %s)';
            $values  = [ $like, $like, $like, $args['search'] ];
        }

        if ( ! empty( $args['genre'] ) ) {
            $where   .= ' AND genre = %s';
            $values[] = $args['genre'];
        }

        $limit  = isset( $args['limit'] )  ? (int) $args['limit']  : 20;
        $offset = isset( $args['offset'] ) ? (int) $args['offset'] : 0;

        $sql = "SELECT * FROM $t WHERE $where ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $values[] = $limit;
        $values[] = $offset;

        return $wpdb->get_results( $values ? $wpdb->prepare( $sql, $values ) : $sql );
    }

    /** Find by unique code */
    public static function find_by_code( string $code ): ?object {
        global $wpdb;
        $t = self::table();
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE unique_code = %s", $code ) );
    }

    /** Find by ID */
    public static function find( int $id ): ?object {
        global $wpdb;
        $t = self::table();
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id = %d", $id ) );
    }

    /** Create a book; auto-generates unique_code if not provided */
    public static function create( array $data ): int|false {
        global $wpdb;

        if ( empty( $data['unique_code'] ) ) {
            $data['unique_code'] = self::generate_code( $data['title'] ?? '' );
        }

        $wpdb->insert( self::table(), [
            'unique_code' => sanitize_text_field( $data['unique_code'] ),
            'title'       => sanitize_text_field( $data['title'] ),
            'author'      => sanitize_text_field( $data['author'] ),
            'publisher'   => sanitize_text_field( $data['publisher'] ?? '' ),
            'isbn'        => sanitize_text_field( $data['isbn']      ?? '' ),
            'cover_url'   => esc_url_raw( $data['cover_url']         ?? '' ),
            'description' => sanitize_textarea_field( $data['description'] ?? '' ),
            'genre'       => sanitize_text_field( $data['genre']     ?? '' ),
        ] );

        return $wpdb->insert_id ?: false;
    }

    /** Count total books */
    public static function count(): int {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table() );
    }

    private static function generate_code( string $title ): string {
        $prefix = strtoupper( substr( preg_replace( '/[^a-zA-Z]/', '', $title ), 0, 4 ) );
        return $prefix . strtoupper( substr( uniqid(), -6 ) );
    }
}