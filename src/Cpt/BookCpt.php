<?php
namespace BookShare\Cpt;

use BookShare\Cpt\AuthorCpt;
use BookShare\Cpt\PublisherCpt;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `bs_book` Custom Post Type.
 *
 * All book data is stored as post meta — no custom DB table needed.
 * Author and Publisher are stored by their wp_post ID (bs_author / bs_publisher CPTs).
 *
 * Meta keys
 * ─────────
 * bs_unique_code    – auto-generated, read-only
 * bs_author_id      – post ID of a bs_author post
 * bs_publisher_id   – post ID of a bs_publisher post
 * bs_genre          – text
 * bs_isbn           – text
 * bs_published_year – number
 * bs_pages          – number
 * bs_language       – text  (default: English)
 * bs_cover_url      – url
 * bs_description    – textarea
 */
class BookCPT {

    const BOOK_CPT = 'bs_book';

    const BOOK_FIELDS = [
        'bs_unique_code'    => [ 'label' => 'Unique Code',     'type' => 'text'     ],
        'bs_author_id'      => [ 'label' => 'Author',          'type' => 'post_select' ],
        'bs_publisher_id'   => [ 'label' => 'Publisher',       'type' => 'post_select' ],
        'bs_genre'          => [ 'label' => 'Genre',           'type' => 'text'     ],
        'bs_isbn'           => [ 'label' => 'ISBN',            'type' => 'text'     ],
        'bs_published_year' => [ 'label' => 'Published Year',  'type' => 'number'   ],
        'bs_pages'          => [ 'label' => 'Pages',           'type' => 'number'   ],
        'bs_language'       => [ 'label' => 'Language',        'type' => 'text'     ],
        'bs_cover_url'      => [ 'label' => 'Cover URL',       'type' => 'url'      ],
        'bs_description'    => [ 'label' => 'Description',     'type' => 'textarea' ],
    ];

    // ── Boot ─────────────────────────────────────────────────────────────────
    public static function register(): void {
        add_action( 'init',              [ self::class, 'register_cpt'     ] );
        add_action( 'add_meta_boxes',    [ self::class, 'add_meta_boxes'   ] );
        add_action( 'save_post',         [ self::class, 'save_meta'        ], 10, 2 );

        // List-table columns
        add_filter( 'manage_' . self::BOOK_CPT . '_posts_columns',       [ self::class, 'book_columns'      ] );
        add_action( 'manage_' . self::BOOK_CPT . '_posts_custom_column', [ self::class, 'book_column_data'  ], 10, 2 );
        add_filter( 'manage_edit-' . self::BOOK_CPT . '_sortable_columns', [ self::class, 'sortable_columns' ] );

        // Quick/bulk edit: keep unique_code read-only
        add_action( 'admin_head', [ self::class, 'admin_head_styles' ] );

        // Register meta for REST API
        add_action( 'init', [ self::class, 'register_meta' ] );
    }

    // ── Register CPT ──────────────────────────────────────────────────────────
    public static function register_cpt(): void {
        register_post_type( self::BOOK_CPT, [
            'labels' => [
                'name'               => __( 'Books',              'bookshare' ),
                'singular_name'      => __( 'Book',               'bookshare' ),
                'add_new'            => __( 'Add Book',           'bookshare' ),
                'add_new_item'       => __( 'Add New Book',       'bookshare' ),
                'edit_item'          => __( 'Edit Book',          'bookshare' ),
                'new_item'           => __( 'New Book',           'bookshare' ),
                'view_item'          => __( 'View Book',          'bookshare' ),
                'search_items'       => __( 'Search Books',       'bookshare' ),
                'not_found'          => __( 'No books found.',    'bookshare' ),
                'not_found_in_trash' => __( 'No books in trash.', 'bookshare' ),
                'menu_name'          => __( 'Books',              'bookshare' ),
                'all_items'          => __( 'All Books',          'bookshare' ),
            ],
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => 'bookshare',   // nest under BookCircle menu
            'show_in_rest'       => true,
            'rest_base'          => 'bs-books',
            'query_var'          => true,
            'rewrite'            => [ 'slug' => 'book', 'with_front' => false ],
            'capability_type'    => 'post',
            'has_archive'        => 'books',
            'hierarchical'       => false,
            'menu_icon'          => 'dashicons-book',
            'supports'           => [ 'title', 'thumbnail', 'revisions' ],
            'show_in_nav_menus'  => true,
            'delete_with_user'   => false,
        ] );
    }

    // ── Register meta for REST ────────────────────────────────────────────────
    public static function register_meta(): void {
        $common = [
            'object_subtype' => self::BOOK_CPT,
            'single'         => true,
            'show_in_rest'   => true,
            'auth_callback'  => fn() => current_user_can( 'edit_posts' ),
        ];

        $string_keys = [
            'bs_unique_code', 'bs_genre', 'bs_isbn', 'bs_language',
            'bs_cover_url',   'bs_description',
        ];
        foreach ( $string_keys as $key ) {
            register_post_meta( self::BOOK_CPT, $key, array_merge( $common, [ 'type' => 'string' ] ) );
        }

        foreach ( [ 'bs_author_id', 'bs_publisher_id', 'bs_published_year', 'bs_pages' ] as $key ) {
            register_post_meta( self::BOOK_CPT, $key, array_merge( $common, [ 'type' => 'integer' ] ) );
        }
    }

    // ── Meta Boxes ────────────────────────────────────────────────────────────
    public static function add_meta_boxes(): void {
        add_meta_box(
            'bs_book_details',
            '📚 Book Details',
            [ self::class, 'render_book_meta_box' ],
            self::BOOK_CPT,
            'normal',
            'high'
        );

        add_meta_box(
            'bs_book_publishing',
            '🏢 Publishing Info',
            [ self::class, 'render_book_publishing_box' ],
            self::BOOK_CPT,
            'side',
            'default'
        );

        add_meta_box(
            'bs_book_code',
            '🔖 Unique Code',
            [ self::class, 'render_book_code_box' ],
            self::BOOK_CPT,
            'side',
            'high'
        );
    }

    // ── Meta Box: Book Details ────────────────────────────────────────────────
    public static function render_book_meta_box( \WP_Post $post ): void {
        wp_nonce_field( 'bs_book_meta', 'bs_book_nonce' );
        $m = self::get_meta( $post->ID );

        // Build author options
        $authors = get_posts( [
            'post_type'      => AuthorCpt::CPT,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ] );

        // Build publisher options
        $publishers = get_posts( [
            'post_type'      => PublisherCpt::CPT,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ] );
        ?>
        <div class="bs-metabox-grid">

            <div class="bs-meta-row">
                <label class="bs-meta-label">Author</label>
                <select name="bs_author_id" class="bs-meta-input">
                    <option value="">— Select Author —</option>
                    <?php foreach ( $authors as $a ) : ?>
                        <option value="<?php echo esc_attr( $a->ID ); ?>"
                            <?php selected( (int) $m['bs_author_id'], $a->ID ); ?>>
                            <?php echo esc_html( $a->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="bs-meta-row">
                <label class="bs-meta-label">Publisher</label>
                <select name="bs_publisher_id" class="bs-meta-input">
                    <option value="">— Select Publisher —</option>
                    <?php foreach ( $publishers as $p ) : ?>
                        <option value="<?php echo esc_attr( $p->ID ); ?>"
                            <?php selected( (int) $m['bs_publisher_id'], $p->ID ); ?>>
                            <?php echo esc_html( $p->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="bs-meta-row">
                <label class="bs-meta-label">Genre</label>
                <input type="text" name="bs_genre" value="<?php echo esc_attr( $m['bs_genre'] ); ?>"
                    class="bs-meta-input" placeholder="Fiction, Sci-Fi, Biography…">
            </div>

            <div class="bs-meta-row">
                <label class="bs-meta-label">ISBN</label>
                <input type="text" name="bs_isbn" value="<?php echo esc_attr( $m['bs_isbn'] ); ?>"
                    class="bs-meta-input" placeholder="978-…">
            </div>

            <div class="bs-meta-row">
                <label class="bs-meta-label">Published Year</label>
                <input type="number" name="bs_published_year" value="<?php echo esc_attr( $m['bs_published_year'] ); ?>"
                    class="bs-meta-input" placeholder="2024" min="1000" max="2099">
            </div>

            <div class="bs-meta-row">
                <label class="bs-meta-label">Pages</label>
                <input type="number" name="bs_pages" value="<?php echo esc_attr( $m['bs_pages'] ); ?>"
                    class="bs-meta-input" placeholder="320" min="1">
            </div>

            <div class="bs-meta-row">
                <label class="bs-meta-label">Language</label>
                <input type="text" name="bs_language" value="<?php echo esc_attr( $m['bs_language'] ?: 'English' ); ?>"
                    class="bs-meta-input" placeholder="English">
            </div>

            <div class="bs-meta-row bs-meta-full">
                <label class="bs-meta-label">Cover Image URL</label>
                <input type="url" name="bs_cover_url" value="<?php echo esc_attr( $m['bs_cover_url'] ); ?>"
                    class="bs-meta-input" placeholder="https://…/cover.jpg">
                <?php if ( $m['bs_cover_url'] ) : ?>
                    <img src="<?php echo esc_url( $m['bs_cover_url'] ); ?>" alt=""
                        style="margin-top:8px;max-height:120px;border-radius:8px;border:2px solid #E5E7F0">
                <?php endif; ?>
            </div>

            <div class="bs-meta-row bs-meta-full">
                <label class="bs-meta-label">Description</label>
                <textarea name="bs_description" class="bs-meta-textarea" rows="4"
                    placeholder="A short synopsis of the book…"><?php echo esc_textarea( $m['bs_description'] ); ?></textarea>
            </div>

        </div>
        <?php
    }

    // ── Meta Box: Publishing Info (sidebar) ───────────────────────────────────
    public static function render_book_publishing_box( \WP_Post $post ): void {
        $m = self::get_meta( $post->ID );

        $author_name    = $m['bs_author_id']    ? get_the_title( (int) $m['bs_author_id'] )    : '—';
        $publisher_name = $m['bs_publisher_id'] ? get_the_title( (int) $m['bs_publisher_id'] ) : '—';

        echo '<table style="width:100%;font-size:13px;border-collapse:collapse">';
        $rows = [
            '✍️ Author'    => esc_html( $author_name ),
            '🏢 Publisher' => esc_html( $publisher_name ),
            '📅 Year'      => esc_html( $m['bs_published_year'] ?: '—' ),
            '📖 Pages'     => esc_html( $m['bs_pages'] ?: '—' ),
            '🌐 Language'  => esc_html( $m['bs_language'] ?: '—' ),
            '🔢 ISBN'      => esc_html( $m['bs_isbn'] ?: '—' ),
        ];
        foreach ( $rows as $label => $value ) {
            printf(
                '<tr><td style="padding:6px 0;color:#6B7280;width:50%%">%s</td><td style="padding:6px 0;font-weight:600">%s</td></tr>',
                esc_html( $label ),
                $value
            );
        }
        echo '</table>';
    }

    // ── Meta Box: Unique Code (sidebar) ──────────────────────────────────────
    public static function render_book_code_box( \WP_Post $post ): void {
        $code = get_post_meta( $post->ID, 'bs_unique_code', true );
        if ( $code ) {
            echo '<p style="text-align:center;margin:8px 0">';
            echo '<code style="font-size:20px;font-weight:700;color:#5B5EDE;background:#EEF0FF;padding:8px 16px;border-radius:8px;letter-spacing:2px">';
            echo esc_html( $code );
            echo '</code></p>';
            echo '<p style="font-size:12px;color:#6B7280;text-align:center;margin-top:8px">Auto-generated · read-only</p>';
        } else {
            echo '<p style="font-size:13px;color:#6B7280;text-align:center">Will be generated on first save.</p>';
        }
    }

    // ── Save Meta ─────────────────────────────────────────────────────────────
    public static function save_meta( int $post_id, \WP_Post $post ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) )                  return;
        if ( $post->post_type !== self::BOOK_CPT )              return;
        if ( ! current_user_can( 'edit_post', $post_id ) )      return;
        if ( ! isset( $_POST['bs_book_nonce'] ) )               return;
        if ( ! wp_verify_nonce( $_POST['bs_book_nonce'], 'bs_book_meta' ) ) return;

        // Generate unique code once
        if ( ! get_post_meta( $post_id, 'bs_unique_code', true ) ) {
            update_post_meta( $post_id, 'bs_unique_code', self::generate_code() );
        }

        // Text / URL fields
        $text_fields = [ 'bs_genre', 'bs_isbn', 'bs_language' ];
        foreach ( $text_fields as $key ) {
            update_post_meta( $post_id, $key, sanitize_text_field( $_POST[ $key ] ?? '' ) );
        }

        $url_fields = [ 'bs_cover_url' ];
        foreach ( $url_fields as $key ) {
            update_post_meta( $post_id, $key, esc_url_raw( $_POST[ $key ] ?? '' ) );
        }

        $textarea_fields = [ 'bs_description' ];
        foreach ( $textarea_fields as $key ) {
            update_post_meta( $post_id, $key, sanitize_textarea_field( $_POST[ $key ] ?? '' ) );
        }

        $int_fields = [ 'bs_author_id', 'bs_publisher_id', 'bs_published_year', 'bs_pages' ];
        foreach ( $int_fields as $key ) {
            $val = intval( $_POST[ $key ] ?? 0 );
            if ( $val > 0 ) {
                update_post_meta( $post_id, $key, $val );
            } else {
                delete_post_meta( $post_id, $key );
            }
        }
    }

    // ── Admin List Columns ────────────────────────────────────────────────────
    public static function book_columns( array $cols ): array {
        return [
            'cb'              => $cols['cb'],
            'bs_cover'        => __( 'Cover',     'bookshare' ),
            'title'           => __( 'Title',     'bookshare' ),
            'bs_unique_code'  => __( 'Code',      'bookshare' ),
            'bs_author'       => __( 'Author',    'bookshare' ),
            'bs_publisher'    => __( 'Publisher', 'bookshare' ),
            'bs_genre'        => __( 'Genre',     'bookshare' ),
            'bs_year'         => __( 'Year',      'bookshare' ),
            'date'            => __( 'Added',     'bookshare' ),
        ];
    }

    public static function sortable_columns( array $cols ): array {
        $cols['bs_year']  = 'bs_year';
        $cols['bs_genre'] = 'bs_genre';
        return $cols;
    }

    public static function book_column_data( string $col, int $post_id ): void {
        switch ( $col ) {
            case 'bs_cover':
                $url = get_post_meta( $post_id, 'bs_cover_url', true );
                if ( $url ) {
                    echo '<img src="' . esc_url( $url ) . '" style="width:36px;height:50px;object-fit:cover;border-radius:4px;border:1px solid #E5E7F0">';
                } else {
                    echo '📚';
                }
                break;

            case 'bs_unique_code':
                $code = get_post_meta( $post_id, 'bs_unique_code', true );
                echo '<code style="font-weight:700;color:#5B5EDE;background:#EEF0FF;padding:2px 7px;border-radius:5px;letter-spacing:1px;font-size:12px">'
                    . esc_html( $code ?: '—' ) . '</code>';
                break;

            case 'bs_author':
                $author_id = (int) get_post_meta( $post_id, 'bs_author_id', true );
                if ( $author_id ) {
                    $link = get_edit_post_link( $author_id );
                    echo $link
                        ? '<a href="' . esc_url( $link ) . '">' . esc_html( get_the_title( $author_id ) ) . '</a>'
                        : esc_html( get_the_title( $author_id ) );
                } else {
                    echo '—';
                }
                break;

            case 'bs_publisher':
                $pub_id = (int) get_post_meta( $post_id, 'bs_publisher_id', true );
                if ( $pub_id ) {
                    $link = get_edit_post_link( $pub_id );
                    echo $link
                        ? '<a href="' . esc_url( $link ) . '">' . esc_html( get_the_title( $pub_id ) ) . '</a>'
                        : esc_html( get_the_title( $pub_id ) );
                } else {
                    echo '—';
                }
                break;

            case 'bs_genre':
                echo esc_html( get_post_meta( $post_id, 'bs_genre', true ) ?: '—' );
                break;

            case 'bs_year':
                echo esc_html( get_post_meta( $post_id, 'bs_published_year', true ) ?: '—' );
                break;
        }
    }

    // ── Admin Styles ──────────────────────────────────────────────────────────
    public static function admin_head_styles(): void {
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== self::BOOK_CPT ) return;
        ?>
        <style>
        /* BookCircle Book CPT Styles — reuses bs-metabox-grid from PostTypes */
        .bs-metabox-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            padding: 4px 0;
        }
        .bs-meta-row    { display: flex; flex-direction: column; gap: 5px; }
        .bs-meta-full   { grid-column: 1 / -1; }
        .bs-meta-label  {
            font-size: 12px; font-weight: 700; color: #1E1E2E;
            text-transform: uppercase; letter-spacing: 0.4px;
        }
        .bs-meta-input,
        .bs-meta-textarea {
            padding: 8px 12px;
            border: 1.5px solid #E5E7F0;
            border-radius: 7px;
            font-size: 14px;
            font-family: inherit;
            background: #F8F9FF;
            transition: border-color .15s, box-shadow .15s;
            width: 100%;
        }
        .bs-meta-input:focus,
        .bs-meta-textarea:focus {
            outline: none;
            border-color: #5B5EDE;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(91,94,222,.1);
        }
        .bs-meta-textarea { resize: vertical; }
        select.bs-meta-input { cursor: pointer; }
        #bs_book_details .inside,
        #bs_book_publishing .inside,
        #bs_book_code .inside { padding: 16px; }

        /* Column widths */
        .column-bs_cover       { width: 52px; }
        .column-bs_unique_code { width: 120px; }
        .column-bs_year        { width: 60px; }
        </style>
        <?php
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private static function get_meta( int $post_id ): array {
        $keys = [
            'bs_unique_code', 'bs_author_id', 'bs_publisher_id',
            'bs_genre', 'bs_isbn', 'bs_published_year', 'bs_pages',
            'bs_language', 'bs_cover_url', 'bs_description',
        ];
        $out = [];
        foreach ( $keys as $key ) {
            $out[ $key ] = get_post_meta( $post_id, $key, true );
        }
        return $out;
    }

    /**
     * Generate a unique 8-character alphanumeric code.
     */
    public static function generate_code(): string {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ( $i = 0; $i < 8; $i++ ) {
                $code .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
            }
            // Ensure uniqueness across all bs_book posts
            $existing = get_posts( [
                'post_type'      => self::BOOK_CPT,
                'posts_per_page' => 1,
                'post_status'    => 'any',
                'meta_key'       => 'bs_unique_code',
                'meta_value'     => $code,
                'fields'         => 'ids',
            ] );
        } while ( ! empty( $existing ) );

        return $code;
    }

    /**
     * Query helper — mirrors the old Model::get_all() interface so you can
     * drop-in replace calls in REST controllers / shortcodes.
     *
     * Returns WP_Post objects augmented with author_name, publisher_name,
     * and all bs_ meta keys as direct properties.
     *
     * @param array $args {
     *   string $search       Full-text search against title / author / genre / isbn
     *   string $genre        Filter by exact genre
     *   int    $per_page     Posts per page (default 20)
     *   int    $paged        Page number      (default 1)
     *   int    $author_id    Filter by bs_author post ID
     *   int    $publisher_id Filter by bs_publisher post ID
     * }
     */
    public static function get_all( array $args = [] ): array {
        $query_args = [
            'post_type'      => self::BOOK_CPT,
            'post_status'    => 'publish',
            'posts_per_page' => intval( $args['per_page'] ?? 20 ),
            'paged'          => intval( $args['paged'] ?? 1 ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( ! empty( $args['search'] ) ) {
            $query_args['s'] = sanitize_text_field( $args['search'] );
        }

        $meta_query = [];

        if ( ! empty( $args['genre'] ) ) {
            $meta_query[] = [
                'key'   => 'bs_genre',
                'value' => sanitize_text_field( $args['genre'] ),
            ];
        }

        if ( ! empty( $args['author_id'] ) ) {
            $meta_query[] = [
                'key'   => 'bs_author_id',
                'value' => intval( $args['author_id'] ),
                'type'  => 'NUMERIC',
            ];
        }

        if ( ! empty( $args['publisher_id'] ) ) {
            $meta_query[] = [
                'key'   => 'bs_publisher_id',
                'value' => intval( $args['publisher_id'] ),
                'type'  => 'NUMERIC',
            ];
        }

        if ( $meta_query ) {
            $query_args['meta_query'] = $meta_query;
        }

        $posts = get_posts( $query_args );

        // Hydrate each post with meta + resolved names
        foreach ( $posts as $post ) {
            self::hydrate( $post );
        }

        return $posts;
    }

    /**
     * Get a single book by post ID, hydrated with meta.
     */
    public static function get_by_id( int $post_id ): ?\WP_Post {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== self::BOOK_CPT ) return null;
        self::hydrate( $post );
        return $post;
    }

    /**
     * Get a single book by unique_code, hydrated with meta.
     */
    public static function get_by_code( string $code ): ?\WP_Post {
        $posts = get_posts( [
            'post_type'      => self::BOOK_CPT,
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'meta_key'       => 'bs_unique_code',
            'meta_value'     => sanitize_text_field( $code ),
        ] );
        if ( empty( $posts ) ) return null;
        self::hydrate( $posts[0] );
        return $posts[0];
    }

    /**
     * Attach all meta values + resolved author/publisher names to a WP_Post object.
     */
    private static function hydrate( \WP_Post $post ): void {
        $meta = get_post_meta( $post->ID );
        $scalar_keys = [
            'bs_unique_code', 'bs_genre', 'bs_isbn', 'bs_language',
            'bs_cover_url',   'bs_description',
        ];
        foreach ( $scalar_keys as $key ) {
            $post->$key = isset( $meta[ $key ][0] ) ? $meta[ $key ][0] : '';
        }

        $int_keys = [ 'bs_author_id', 'bs_publisher_id', 'bs_published_year', 'bs_pages' ];
        foreach ( $int_keys as $key ) {
            $post->$key = isset( $meta[ $key ][0] ) ? (int) $meta[ $key ][0] : 0;
        }

        // Resolved display names
        $post->author_name    = $post->bs_author_id    ? get_the_title( $post->bs_author_id )    : '';
        $post->publisher_name = $post->bs_publisher_id ? get_the_title( $post->bs_publisher_id ) : '';

        // Alias for template compatibility
        $post->title         = $post->post_title;
        $post->cover_url     = $post->bs_cover_url;
        $post->genre         = $post->bs_genre;
        $post->published_year = $post->bs_published_year;
        $post->unique_code   = $post->bs_unique_code;
    }

    /**
     * Count books matching optional filters (mirrors old Model::count()).
     */
    public static function count( array $args = [] ): int {
        $query_args             = $args;
        $query_args['per_page'] = -1;
        $query_args['paged']    = 1;
        // Use the same WP_Query path but only fetch IDs
        $q = new \WP_Query( array_merge(
            [
                'post_type'      => self::BOOK_CPT,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ],
            ! empty( $args['search'] ) ? [ 's' => sanitize_text_field( $args['search'] ) ] : [],
            ! empty( $args['genre'] )  ? [ 'meta_query' => [ [ 'key' => 'bs_genre', 'value' => $args['genre'] ] ] ] : []
        ) );
        return (int) $q->found_posts;
    }

    /**
     * List all distinct genres.
     */
    public static function genres(): array {
        global $wpdb;
        return $wpdb->get_col(
            "SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
             WHERE meta_key = 'bs_genre' AND meta_value != ''
             ORDER BY meta_value"
        );
    }
}