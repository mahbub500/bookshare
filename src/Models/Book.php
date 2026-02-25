<?php
namespace BookShare\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Book model — backed entirely by the `bs_book` CPT.
 *
 * Data layout
 * ───────────
 * post_title          → title
 * bs_unique_code      → unique_code   (auto-generated, read-only after first save)
 * bs_author_id        → author_id     (WP post ID of a bs_author post)
 * bs_publisher_id     → publisher_id  (WP post ID of a bs_publisher post)
 * bs_genre            → genre
 * bs_isbn             → isbn
 * bs_published_year   → published_year
 * bs_pages            → pages
 * bs_language         → language
 * bs_cover_url        → cover_url
 * bs_description      → description
 *
 * Resolved via get_the_title():
 *   author_name, publisher_name
 *
 * Extra on get_by_id():
 *   author_bio, author_photo  (from bs_author post meta)
 */
class Book {

    const CPT = 'bs_book';

    // ── Meta key → object property ────────────────────────────────────────────
    private const META_MAP = [
        'bs_unique_code'    => 'unique_code',
        'bs_author_id'      => 'author_id',
        'bs_publisher_id'   => 'publisher_id',
        'bs_genre'          => 'genre',
        'bs_isbn'           => 'isbn',
        'bs_published_year' => 'published_year',
        'bs_pages'          => 'pages',
        'bs_language'       => 'language',
        'bs_cover_url'      => 'cover_url',
        'bs_description'    => 'description',
    ];

    // ── Integer meta keys ─────────────────────────────────────────────────────
    private const INT_KEYS = [ 'bs_author_id', 'bs_publisher_id', 'bs_published_year', 'bs_pages' ];

    // ── Sanitisation rules (property → type) ─────────────────────────────────
    private const SANITIZE = [
        'genre'          => 'text',
        'isbn'           => 'text',
        'published_year' => 'int',
        'pages'          => 'int',
        'language'       => 'text',
        'cover_url'      => 'url',
        'description'    => 'textarea',
        'author_id'      => 'int',
        'publisher_id'   => 'int',
    ];

    // =========================================================================
    // READ
    // =========================================================================

    /**
     * Return books as plain objects matching the old DB row shape.
     *
     * Supported $args:
     *   string $search       – title, author name, genre, isbn
     *   string $genre        – exact genre match
     *   int    $author_id    – filter by bs_author post ID
     *   int    $publisher_id – filter by bs_publisher post ID
     *   int    $per_page     – default 20  (-1 = all)
     *   int    $offset       – row offset (mapped to WP page)
     *   int    $paged        – WP page number
     */
    public static function get_all( array $args = [] ): array {
        $posts = get_posts( self::build_query_args( $args ) );
        return array_map( [ self::class, 'hydrate' ], $posts );
    }

    /**
     * Total book count (respects optional filters).
     */
    public static function count( array $args = [] ): int {
        $q_args                   = self::build_query_args( $args );
        $q_args['posts_per_page'] = -1;
        $q_args['fields']         = 'ids';
        $q_args['no_found_rows']  = false;

        return (int) ( new \WP_Query( $q_args ) )->found_posts;
    }

    /**
     * Single book by WP post ID, with extended author info.
     */
    public static function get_by_id( int $post_id ): ?object {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== self::CPT ) {
            return null;
        }

        $obj = self::hydrate( $post );

        // Extra author fields (mirrors the old JOIN columns)
        if ( $obj->author_id ) {
            $obj->author_bio   = (string) get_post_meta( $obj->author_id, 'bs_bio',       true );
            $obj->author_photo = (string) get_post_meta( $obj->author_id, 'bs_photo_url', true );
        } else {
            $obj->author_bio   = '';
            $obj->author_photo = '';
        }

        return $obj;
    }

    /**
     * Single book by unique_code.
     */
    public static function get_by_code( string $code ): ?object {
        $posts = get_posts( [
            'post_type'      => self::CPT,
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'meta_key'       => 'bs_unique_code',
            'meta_value'     => sanitize_text_field( $code ),
        ] );

        return $posts ? self::hydrate( $posts[0] ) : null;
    }

    /**
     * All distinct genres across published books.
     */
    public static function genres(): array {
        global $wpdb;
        return $wpdb->get_col(
            "SELECT DISTINCT meta_value
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = 'bs_genre'
               AND pm.meta_value != ''
               AND p.post_type   = 'bs_book'
               AND p.post_status = 'publish'
             ORDER BY meta_value"
        );
    }

    // =========================================================================
    // WRITE
    // =========================================================================

    /**
     * Create a new book post and save all meta.
     * Unique code is auto-generated.
     * Returns the new WP post ID, or false on failure.
     *
     * @param array $data  Keys match old DB columns:
     *                     title, author_id, publisher_id, genre, isbn,
     *                     published_year, pages, language, cover_url, description
     *                     (added_by is inferred from get_current_user_id())
     */
    public static function create( array $data ): int|false {
        $title = sanitize_text_field( $data['title'] ?? '' );
        if ( ! $title ) {
            return false;
        }

        $post_id = wp_insert_post( [
            'post_type'   => self::CPT,
            'post_title'  => $title,
            'post_status' => 'publish',
            'post_author' => intval( $data['added_by'] ?? get_current_user_id() ),
        ], true );

        if ( is_wp_error( $post_id ) ) {
            return false;
        }

        // Generate unique code
        update_post_meta( $post_id, 'bs_unique_code', self::generate_code() );

        self::save_meta( $post_id, $data );

        return $post_id;
    }

    /**
     * Update an existing book post + meta.
     * Unique code is never changed after creation.
     *
     * @param int   $post_id  WP post ID of the bs_book post.
     * @param array $data     Same keys as create(). Partial updates are safe.
     */
    public static function update( int $post_id, array $data ): bool {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== self::CPT ) {
            return false;
        }

        $update = [ 'ID' => $post_id ];

        if ( ! empty( $data['title'] ) ) {
            $update['post_title'] = sanitize_text_field( $data['title'] );
        }

        if ( is_wp_error( wp_update_post( $update, true ) ) ) {
            return false;
        }

        self::save_meta( $post_id, $data );

        return true;
    }

    /**
     * Delete a book (trash by default).
     *
     * @param int  $post_id      WP post ID.
     * @param bool $force_delete Skip trash and permanently delete.
     */
    public static function delete( int $post_id, bool $force_delete = false ): bool {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== self::CPT ) {
            return false;
        }
        return (bool) wp_delete_post( $post_id, $force_delete );
    }

    // =========================================================================
    // INTERNALS
    // =========================================================================

    private static function build_query_args( array $args ): array {
        $per_page = isset( $args['per_page'] ) ? intval( $args['per_page'] ) : 20;

        $paged = 1;
        if ( ! empty( $args['paged'] ) ) {
            $paged = max( 1, intval( $args['paged'] ) );
        } elseif ( ! empty( $args['offset'] ) && $per_page > 0 ) {
            $paged = (int) floor( intval( $args['offset'] ) / $per_page ) + 1;
        }

        $query_args = [
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ];

        $meta_query = [];

        // Full-text: WP core handles post_title via 's'; we extend to genre + isbn via meta
        if ( ! empty( $args['search'] ) ) {
            $search              = sanitize_text_field( $args['search'] );
            $query_args['s']     = $search;
            $meta_query[]        = [ 'key' => 'bs_genre', 'value' => $search, 'compare' => 'LIKE' ];
            $meta_query[]        = [ 'key' => 'bs_isbn',  'value' => $search, 'compare' => 'LIKE' ];
            $meta_query['relation'] = 'OR';
        }

        if ( ! empty( $args['genre'] ) ) {
            $meta_query[] = [ 'key' => 'bs_genre', 'value' => sanitize_text_field( $args['genre'] ) ];
            if ( ! isset( $meta_query['relation'] ) ) {
                $meta_query['relation'] = 'AND';
            }
        }

        if ( ! empty( $args['author_id'] ) ) {
            $meta_query[] = [ 'key' => 'bs_author_id', 'value' => intval( $args['author_id'] ), 'type' => 'NUMERIC' ];
            if ( ! isset( $meta_query['relation'] ) ) {
                $meta_query['relation'] = 'AND';
            }
        }

        if ( ! empty( $args['publisher_id'] ) ) {
            $meta_query[] = [ 'key' => 'bs_publisher_id', 'value' => intval( $args['publisher_id'] ), 'type' => 'NUMERIC' ];
            if ( ! isset( $meta_query['relation'] ) ) {
                $meta_query['relation'] = 'AND';
            }
        }

        if ( $meta_query ) {
            $query_args['meta_query'] = $meta_query;
        }

        return $query_args;
    }

    /**
     * Convert a WP_Post into a plain object matching the old DB row shape:
     * id, title, unique_code, author_id, publisher_id, author_name,
     * publisher_name, genre, isbn, published_year, pages, language,
     * cover_url, description, created_at, post_id
     */
    public static function hydrate( \WP_Post $post ): object {
        $obj = (object) [
            'id'         => $post->ID,
            'post_id'    => $post->ID,
            'title'      => $post->post_title,
            'created_at' => $post->post_date,
            'added_by'   => $post->post_author,
        ];

        foreach ( self::META_MAP as $meta_key => $prop ) {
            $raw = get_post_meta( $post->ID, $meta_key, true );
            $obj->$prop = in_array( $meta_key, self::INT_KEYS, true ) ? (int) $raw : (string) $raw;
        }

        // Resolved display names
        $obj->author_name    = $obj->author_id    ? get_the_title( $obj->author_id )    : '';
        $obj->publisher_name = $obj->publisher_id ? get_the_title( $obj->publisher_id ) : '';

        return $obj;
    }

    /**
     * Generate a collision-free 8-character alphanumeric unique code.
     */
    public static function generate_code(): string {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ( $i = 0; $i < 8; $i++ ) {
                $code .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
            }
        } while ( self::get_by_code( $code ) );

        return $code;
    }

    private static function save_meta( int $post_id, array $data ): void {
        $prop_to_meta = array_flip( self::META_MAP );
        // Remove read-only key — code is set once in create(), never in save_meta
        unset( $prop_to_meta['unique_code'] );

        foreach ( self::SANITIZE as $prop => $type ) {
            if ( ! array_key_exists( $prop, $data ) ) {
                continue; // allow partial updates
            }
            $meta_key = $prop_to_meta[ $prop ] ?? 'bs_' . $prop;
            $value    = self::sanitize_value( $data[ $prop ], $type );

            if ( $type === 'int' && $value === 0 ) {
                delete_post_meta( $post_id, $meta_key ); // store nothing rather than 0
            } else {
                update_post_meta( $post_id, $meta_key, $value );
            }
        }
    }

    private static function sanitize_value( mixed $value, string $type ): string|int {
        return match ( $type ) {
            'email'    => sanitize_email( (string) $value ),
            'url'      => esc_url_raw( (string) $value ),
            'int'      => intval( $value ),
            'textarea' => sanitize_textarea_field( (string) $value ),
            default    => sanitize_text_field( (string) $value ),
        };
    }
}