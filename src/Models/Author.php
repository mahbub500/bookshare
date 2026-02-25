<?php
namespace BookShare\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Author model — backed entirely by the `bs_author` CPT.
 *
 * Every method mirrors the old DB-based interface so existing callers
 * (REST controllers, shortcodes, admin views) need zero changes.
 *
 * Data layout
 * ───────────
 * post_title          → name
 * bs_bio              → bio
 * bs_email            → email
 * bs_website          → website
 * bs_birth_date       → birth_date
 * bs_nationality      → nationality
 * bs_photo_url        → photo_url
 * bs_social_twitter   → social_twitter
 * bs_social_instagram → social_instagram
 * bs_social_facebook  → social_facebook
 */
class Author {

    const CPT = 'bs_author';

    // ── Meta key map (meta_key => object property) ────────────────────────────
    private const META_MAP = [
        'bs_bio'              => 'bio',
        'bs_email'            => 'email',
        'bs_website'          => 'website',
        'bs_birth_date'       => 'birth_date',
        'bs_nationality'      => 'nationality',
        'bs_photo_url'        => 'photo_url',
        'bs_social_twitter'   => 'social_twitter',
        'bs_social_instagram' => 'social_instagram',
        'bs_social_facebook'  => 'social_facebook',
    ];

    // ── Sanitisation rules (property => type) ─────────────────────────────────
    private const SANITIZE = [
        'bio'              => 'textarea',
        'email'            => 'email',
        'website'          => 'url',
        'birth_date'       => 'text',
        'nationality'      => 'text',
        'photo_url'        => 'url',
        'social_twitter'   => 'text',
        'social_instagram' => 'text',
        'social_facebook'  => 'url',
    ];

    // =========================================================================
    // READ
    // =========================================================================

    /**
     * Return all authors as plain objects with the same shape as the old DB rows.
     *
     * Supported $args:
     *   string $search      – searches post_title, email, nationality
     *   int    $per_page    – default 50  (-1 = all)
     *   int    $offset      – row offset  (mapped to WP page)
     *   int    $paged       – WP page number (alternative to $offset)
     */
    public static function get_all( array $args = [] ): array {
        $query_args = self::build_query_args( $args );
        $posts      = get_posts( $query_args );

        return array_map( [ self::class, 'hydrate' ], $posts );
    }

    /**
     * Total author count (respects optional $search).
     */
    public static function count( array $args = [] ): int {
        $q_args                  = self::build_query_args( $args );
        $q_args['posts_per_page'] = -1;
        $q_args['fields']        = 'ids';
        $q_args['no_found_rows'] = false;

        $q = new \WP_Query( $q_args );
        return (int) $q->found_posts;
    }

    /**
     * Single author by WP post ID.
     */
    public static function get_by_id( int $post_id ): ?object {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== self::CPT ) {
            return null;
        }
        return self::hydrate( $post );
    }

    /**
     * Lightweight id + name list for dropdowns / selects.
     */
    public static function get_list(): array {
        $posts = get_posts( [
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'all',  // need title
        ] );

        return array_map( static function ( \WP_Post $p ): object {
            return (object) [ 'id' => $p->ID, 'name' => $p->post_title ];
        }, $posts );
    }

    // =========================================================================
    // WRITE
    // =========================================================================

    /**
     * Create a new author post and save all meta.
     * Returns the new WP post ID, or false on failure.
     *
     * @param array $data  Keys match the old DB column names:
     *                     name, bio, email, website, birth_date,
     *                     nationality, photo_url, social_twitter,
     *                     social_instagram, social_facebook
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
     * Update an existing author post + meta.
     *
     * @param int   $post_id  WP post ID of the bs_author post.
     * @param array $data     Same keys as create().
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

        $result = wp_update_post( $update, true );
        if ( is_wp_error( $result ) ) {
            return false;
        }

        self::save_meta( $post_id, $data );

        return true;
    }

    /**
     * Delete an author (moves to trash by default).
     *
     * @param int  $post_id     WP post ID.
     * @param bool $force_delete Bypass trash and permanently delete.
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

    /**
     * Build a get_posts / WP_Query args array from the public $args interface.
     */
    private static function build_query_args( array $args ): array {
        $per_page = isset( $args['per_page'] ) ? intval( $args['per_page'] ) : 50;

        // Support offset-based pagination (old API) → convert to paged
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

        // Full-text search across title + meta
        if ( ! empty( $args['search'] ) ) {
            $search = sanitize_text_field( $args['search'] );

            // WP core searches post_title; we extend to email + nationality via meta_query
            $query_args['s']          = $search;
            $query_args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => 'bs_email',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'bs_nationality',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ],
            ];
        }

        return $query_args;
    }

    /**
     * Convert a WP_Post into a plain object that matches the old DB row shape:
     *
     *   id, name, bio, email, website, birth_date, nationality,
     *   photo_url, social_twitter, social_instagram, social_facebook,
     *   created_at, post_id (extra — WP post ID for convenience)
     */
    public static function hydrate( \WP_Post $post ): object {
        $obj = (object) [
            'id'         => $post->ID,      // post ID doubles as the "row id"
            'post_id'    => $post->ID,
            'name'       => $post->post_title,
            'created_at' => $post->post_date,
        ];

        foreach ( self::META_MAP as $meta_key => $prop ) {
            $obj->$prop = (string) get_post_meta( $post->ID, $meta_key, true );
        }

        return $obj;
    }

    /**
     * Persist all writable meta keys for a given post.
     * Skips keys that are not present in $data (partial updates are safe).
     */
    private static function save_meta( int $post_id, array $data ): void {
        // Map old column names → meta keys
        $prop_to_meta = array_flip( self::META_MAP ); // property => meta_key

        foreach ( self::SANITIZE as $prop => $type ) {
            if ( ! array_key_exists( $prop, $data ) ) {
                continue; // allow partial updates
            }

            $meta_key = $prop_to_meta[ $prop ] ?? 'bs_' . $prop;
            $value    = self::sanitize_value( $data[ $prop ], $type );

            update_post_meta( $post_id, $meta_key, $value );
        }
    }

    /**
     * Sanitise a single value according to its declared type.
     */
    private static function sanitize_value( mixed $value, string $type ): string {
        return match ( $type ) {
            'email'    => sanitize_email( (string) $value ),
            'url'      => esc_url_raw( (string) $value ),
            'textarea' => sanitize_textarea_field( (string) $value ),
            default    => sanitize_text_field( (string) $value ),
        };
    }
}