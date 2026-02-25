<?php
namespace BookShare\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Publisher model — backed entirely by the `bs_publisher` CPT.
 *
 * Data layout
 * ───────────
 * post_title        → name
 * bs_description    → description
 * bs_email          → email
 * bs_phone          → phone
 * bs_website        → website
 * bs_address        → address
 * bs_city           → city
 * bs_country        → country
 * bs_founded_year   → founded_year  (integer)
 * bs_logo_url       → logo_url
 */
class Publisher {

    const CPT = 'bs_publisher';

    // ── Meta key → object property ────────────────────────────────────────────
    private const META_MAP = [
        'bs_description'  => 'description',
        'bs_email'        => 'email',
        'bs_phone'        => 'phone',
        'bs_website'      => 'website',
        'bs_address'      => 'address',
        'bs_city'         => 'city',
        'bs_country'      => 'country',
        'bs_founded_year' => 'founded_year',
        'bs_logo_url'     => 'logo_url',
    ];

    // ── Sanitisation rules (property → type) ─────────────────────────────────
    private const SANITIZE = [
        'description'  => 'textarea',
        'email'        => 'email',
        'phone'        => 'text',
        'website'      => 'url',
        'address'      => 'textarea',
        'city'         => 'text',
        'country'      => 'text',
        'founded_year' => 'int',
        'logo_url'     => 'url',
    ];

    // =========================================================================
    // READ
    // =========================================================================

    /**
     * Return all publishers as plain objects matching the old DB row shape.
     *
     * Supported $args:
     *   string $search   – searches name, city, country
     *   int    $per_page – default 50  (-1 = all)
     *   int    $offset   – row offset (mapped to WP page)
     *   int    $paged    – WP page number (alternative to $offset)
     */
    public static function get_all( array $args = [] ): array {
        $posts = get_posts( self::build_query_args( $args ) );
        return array_map( [ self::class, 'hydrate' ], $posts );
    }

    /**
     * Total publisher count (respects optional $search).
     */
    public static function count( array $args = [] ): int {
        $q_args                   = self::build_query_args( $args );
        $q_args['posts_per_page'] = -1;
        $q_args['fields']         = 'ids';
        $q_args['no_found_rows']  = false;

        return (int) ( new \WP_Query( $q_args ) )->found_posts;
    }

    /**
     * Single publisher by WP post ID.
     */
    public static function get_by_id( int $post_id ): ?object {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== self::CPT ) {
            return null;
        }
        return self::hydrate( $post );
    }

    /**
     * Lightweight id + name list for dropdowns.
     */
    public static function get_list(): array {
        $posts = get_posts( [
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        return array_map( static fn( \WP_Post $p ): object => (object) [
            'id'   => $p->ID,
            'name' => $p->post_title,
        ], $posts );
    }

    // =========================================================================
    // WRITE
    // =========================================================================

    /**
     * Create a new publisher post and save all meta.
     * Returns the new WP post ID, or false on failure.
     *
     * @param array $data  Keys match old DB columns:
     *                     name, description, email, phone, website,
     *                     address, city, country, founded_year, logo_url
     */
    public static function create( array $data ): int|false {
        $name = sanitize_text_field( $data['name'] ?? '' );
        if ( ! $name ) {
            return false;
        }

        $post_id = wp_insert_post( [
            'post_type'   => self::CPT,
            'post_title'  => $name,
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ], true );

        if ( is_wp_error( $post_id ) ) {
            return false;
        }

        self::save_meta( $post_id, $data );

        return $post_id;
    }

    /**
     * Update an existing publisher post + meta.
     *
     * @param int   $post_id  WP post ID of the bs_publisher post.
     * @param array $data     Same keys as create(). Partial updates are safe.
     */
    public static function update( int $post_id, array $data ): bool {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== self::CPT ) {
            return false;
        }

        $update = [ 'ID' => $post_id ];

        if ( ! empty( $data['name'] ) ) {
            $update['post_title'] = sanitize_text_field( $data['name'] );
        }

        if ( is_wp_error( wp_update_post( $update, true ) ) ) {
            return false;
        }

        self::save_meta( $post_id, $data );

        return true;
    }

    /**
     * Delete a publisher (trash by default).
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
        $per_page = isset( $args['per_page'] ) ? intval( $args['per_page'] ) : 50;

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
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ];

        if ( ! empty( $args['search'] ) ) {
            $search = sanitize_text_field( $args['search'] );
            $query_args['s'] = $search;
            $query_args['meta_query'] = [
                'relation' => 'OR',
                [ 'key' => 'bs_city',    'value' => $search, 'compare' => 'LIKE' ],
                [ 'key' => 'bs_country', 'value' => $search, 'compare' => 'LIKE' ],
            ];
        }

        return $query_args;
    }

    /**
     * Convert a WP_Post into a plain object matching the old DB row shape:
     * id, name, description, email, phone, website, address,
     * city, country, founded_year, logo_url, created_at, post_id
     */
    public static function hydrate( \WP_Post $post ): object {
        $obj = (object) [
            'id'         => $post->ID,
            'post_id'    => $post->ID,
            'name'       => $post->post_title,
            'created_at' => $post->post_date,
        ];

        foreach ( self::META_MAP as $meta_key => $prop ) {
            $raw        = get_post_meta( $post->ID, $meta_key, true );
            $obj->$prop = $prop === 'founded_year' ? (int) $raw : (string) $raw;
        }

        return $obj;
    }

    private static function save_meta( int $post_id, array $data ): void {
        $prop_to_meta = array_flip( self::META_MAP );

        foreach ( self::SANITIZE as $prop => $type ) {
            if ( ! array_key_exists( $prop, $data ) ) {
                continue;
            }
            $meta_key = $prop_to_meta[ $prop ] ?? 'bs_' . $prop;
            update_post_meta( $post_id, $meta_key, self::sanitize_value( $data[ $prop ], $type ) );
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