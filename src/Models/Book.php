<?php
namespace BookShare\Cpt;

use BookShare\Cpt\AuthorCpt;
use BookShare\Cpt\PublisherCpt;

defined( 'ABSPATH' ) || exit;

class BookCPT {

    const BOOK_CPT = 'bs_book';

    const BOOK_FIELDS = [
        'bs_author_id'      => [ 'label' => 'Author',         'type' => 'post_select' ],
        'bs_publisher_id'   => [ 'label' => 'Publisher',      'type' => 'post_select' ],
        'bs_isbn'           => [ 'label' => 'ISBN',           'type' => 'text'        ],
        'bs_published_year' => [ 'label' => 'Published Year', 'type' => 'number'      ],
        'bs_pages'          => [ 'label' => 'Pages',          'type' => 'number'      ],
        'bs_language'       => [ 'label' => 'Language',       'type' => 'text'        ],
        'bs_description'    => [ 'label' => 'Description',    'type' => 'textarea'    ],
    ];

    // ── Boot ──────────────────────────────────────────────────────────────────
    public static function register(): void {
        add_action( 'init',           [ self::class, 'register_cpt'       ] );
        add_action( 'init',           [ self::class, 'register_taxonomy'  ] );
        add_action( 'add_meta_boxes', [ self::class, 'add_meta_boxes'     ] );
        add_action( 'save_post',      [ self::class, 'save_meta'          ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );

        add_filter( 'manage_' . self::BOOK_CPT . '_posts_columns',         [ self::class, 'book_columns'      ] );
        add_action( 'manage_' . self::BOOK_CPT . '_posts_custom_column',   [ self::class, 'book_column_data'  ], 10, 2 );
        add_filter( 'manage_edit-' . self::BOOK_CPT . '_sortable_columns', [ self::class, 'sortable_columns'  ] );

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
            'show_in_menu'       => 'bookshare',
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
            'taxonomies'         => [ 'bs_book_tag', 'bs_book_category' ],
        ] );
    }

    // ── Register Taxonomies ───────────────────────────────────────────────────
    public static function register_taxonomy(): void {

        // Book Tags (non-hierarchical, like post tags)
        register_taxonomy( 'bs_book_tag', self::BOOK_CPT, [
            'labels' => [
                'name'              => __( 'Book Tags',        'bookshare' ),
                'singular_name'     => __( 'Book Tag',         'bookshare' ),
                'search_items'      => __( 'Search Tags',      'bookshare' ),
                'all_items'         => __( 'All Tags',         'bookshare' ),
                'edit_item'         => __( 'Edit Tag',         'bookshare' ),
                'update_item'       => __( 'Update Tag',       'bookshare' ),
                'add_new_item'      => __( 'Add New Tag',      'bookshare' ),
                'new_item_name'     => __( 'New Tag Name',     'bookshare' ),
                'menu_name'         => __( 'Book Tags',        'bookshare' ),
                'not_found'         => __( 'No tags found.',   'bookshare' ),
            ],
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'book-tag' ],
        ] );

        // Book Categories (hierarchical, like post categories)
        register_taxonomy( 'bs_book_category', self::BOOK_CPT, [
            'labels' => [
                'name'              => __( 'Book Categories',      'bookshare' ),
                'singular_name'     => __( 'Book Category',        'bookshare' ),
                'search_items'      => __( 'Search Categories',    'bookshare' ),
                'all_items'         => __( 'All Categories',       'bookshare' ),
                'parent_item'       => __( 'Parent Category',      'bookshare' ),
                'parent_item_colon' => __( 'Parent Category:',     'bookshare' ),
                'edit_item'         => __( 'Edit Category',        'bookshare' ),
                'update_item'       => __( 'Update Category',      'bookshare' ),
                'add_new_item'      => __( 'Add New Category',     'bookshare' ),
                'new_item_name'     => __( 'New Category Name',    'bookshare' ),
                'menu_name'         => __( 'Book Categories',      'bookshare' ),
                'not_found'         => __( 'No categories found.', 'bookshare' ),
            ],
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'book-category' ],
        ] );
    }

    // ── Register Meta for REST ────────────────────────────────────────────────
    public static function register_meta(): void {
        $common = [
            'object_subtype' => self::BOOK_CPT,
            'single'         => true,
            'show_in_rest'   => true,
            'auth_callback'  => fn() => current_user_can( 'edit_posts' ),
        ];

        $string_keys = [ 'bs_unique_code', 'bs_isbn', 'bs_language', 'bs_description', 'bs_author_ids' ];
        foreach ( $string_keys as $key ) {
            register_post_meta( self::BOOK_CPT, $key, array_merge( $common, [ 'type' => 'string' ] ) );
        }

        foreach ( [ 'bs_publisher_id', 'bs_published_year', 'bs_pages' ] as $key ) {
            register_post_meta( self::BOOK_CPT, $key, array_merge( $common, [ 'type' => 'integer' ] ) );
        }
    }

    // ── Enqueue Select2 ───────────────────────────────────────────────────────
    public static function enqueue_assets( string $hook ): void {
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== self::BOOK_CPT ) return;
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;

        wp_enqueue_style(
            'select2',
            'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css',
            [],
            '4.0.13'
        );
        wp_enqueue_script(
            'select2',
            'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js',
            [ 'jquery' ],
            '4.0.13',
            true
        );
        wp_add_inline_script( 'select2', '
            jQuery(function($){
                $("#bs_author_ids").select2({
                    placeholder: "— Select Authors —",
                    allowClear: true,
                    width: "100%"
                });
            });
        ' );
        wp_add_inline_style( 'select2', '
            .select2-container .select2-selection--multiple {
                min-height: 38px;
                border: 1.5px solid #E5E7F0 !important;
                border-radius: 7px !important;
                background: #F8F9FF !important;
                padding: 2px 6px;
            }
            .select2-container--default .select2-selection--multiple .select2-selection__choice {
                background-color: #EEF0FF;
                border: 1px solid #c7c9f5;
                color: #3d3fa8;
                border-radius: 5px;
                padding: 2px 8px;
                font-size: 13px;
            }
            .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
                color: #5B5EDE;
                margin-right: 4px;
            }
            .select2-container--focus .select2-selection--multiple,
            .select2-container--open .select2-selection--multiple {
                border-color: #5B5EDE !important;
                box-shadow: 0 0 0 3px rgba(91,94,222,.1) !important;
                background: #fff !important;
            }
        ' );
    }

    // ── Meta Boxes ────────────────────────────────────────────────────────────
    public static function add_meta_boxes(): void {
        add_meta_box(
            'bs_book_details',
            '📚 Book Details',
            [ self::class, 'render_book_meta_box' ],
            self::BOOK_CPT, 'normal', 'high'
        );
        add_meta_box(
            'bs_book_publishing',
            '🏢 Publishing Info',
            [ self::class, 'render_book_publishing_box' ],
            self::BOOK_CPT, 'side', 'default'
        );
        add_meta_box(
            'bs_book_code',
            '🔖 Unique Code',
            [ self::class, 'render_book_code_box' ],
            self::BOOK_CPT, 'side', 'high'
        );
        add_meta_box(
            'bs_book_cover_preview',
            '🖼️ Cover Image',
            [ self::class, 'render_cover_preview_box' ],
            self::BOOK_CPT, 'side', 'low'
        );
    }

    // ── Meta Box: Book Details ────────────────────────────────────────────────
    public static function render_book_meta_box( \WP_Post $post ): void {
        wp_nonce_field( 'bs_book_meta', 'bs_book_nonce' );
        $m = self::get_meta( $post->ID );

        // Saved author IDs (multiple)
        $saved_author_ids = array_filter( array_map( 'intval',
            explode( ',', $m['bs_author_ids'] ?? '' )
        ) );

        // All authors
        $authors = get_posts( [
            'post_type'      => AuthorCpt::CPT,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ] );

        // All publishers
        $publishers = get_posts( [
            'post_type'      => PublisherCpt::CPT,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ] );
        ?>
        <div class="bs-metabox-grid">

            <!-- Authors (Select2 multi-select) -->
            <div class="bs-meta-row bs-meta-full">
                <label class="bs-meta-label" for="bs_author_ids">Authors</label>
                <select name="bs_author_ids[]" id="bs_author_ids" multiple="multiple" class="bs-meta-input">
                    <?php foreach ( $authors as $a ) : ?>
                        <option value="<?php echo esc_attr( $a->ID ); ?>"
                            <?php echo in_array( $a->ID, $saved_author_ids, true ) ? 'selected' : ''; ?>>
                            <?php echo esc_html( $a->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Publisher -->
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

            <!-- ISBN -->
            <div class="bs-meta-row">
                <label class="bs-meta-label">ISBN</label>
                <input type="text" name="bs_isbn" value="<?php echo esc_attr( $m['bs_isbn'] ); ?>"
                    class="bs-meta-input" placeholder="978-…">
            </div>

            <!-- Published Year -->
            <div class="bs-meta-row">
                <label class="bs-meta-label">Published Year</label>
                <input type="number" name="bs_published_year" value="<?php echo esc_attr( $m['bs_published_year'] ); ?>"
                    class="bs-meta-input" placeholder="2024" min="1000" max="2099">
            </div>

            <!-- Pages -->
            <div class="bs-meta-row">
                <label class="bs-meta-label">Pages</label>
                <input type="number" name="bs_pages" value="<?php echo esc_attr( $m['bs_pages'] ); ?>"
                    class="bs-meta-input" placeholder="320" min="1">
            </div>

            <!-- Language -->
            <div class="bs-meta-row">
                <label class="bs-meta-label">Language</label>
                <input type="text" name="bs_language" value="<?php echo esc_attr( $m['bs_language'] ?: 'English' ); ?>"
                    class="bs-meta-input" placeholder="English">
            </div>

            <!-- Description -->
            <div class="bs-meta-row bs-meta-full">
                <label class="bs-meta-label">Description</label>
                <textarea name="bs_description" class="bs-meta-textarea" rows="4"
                    placeholder="A short synopsis of the book…"><?php echo esc_textarea( $m['bs_description'] ); ?></textarea>
            </div>

        </div>
        <?php
    }

    // ── Meta Box: Cover Preview (sidebar) ─────────────────────────────────────
    public static function render_cover_preview_box( \WP_Post $post ): void {
        $thumb_id = get_post_thumbnail_id( $post->ID );
        ?>
        <div style="text-align:center;padding:6px 0">
            <?php if ( $thumb_id ) : ?>
                <?php echo get_the_post_thumbnail( $post->ID, [ 120, 170 ], [
                    'style' => 'width:120px;height:170px;object-fit:cover;border-radius:8px;border:2px solid #E5E7EB;display:block;margin:0 auto 10px'
                ] ); ?>
                <p style="font-size:12px;color:#6B7280;margin:0">✅ Cover image is set.</p>
            <?php else : ?>
                <div style="width:120px;height:170px;border-radius:8px;background:#F3F4F6;border:2px dashed #D1D5DB;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;font-size:40px">
                    📚
                </div>
                <p style="font-size:12px;color:#6B7280;margin:0">Set the <strong>Featured Image</strong> as the book cover.</p>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── Meta Box: Publishing Info (sidebar) ───────────────────────────────────
    public static function render_book_publishing_box( \WP_Post $post ): void {
        $m = self::get_meta( $post->ID );

        // Multiple authors
        $author_ids   = array_filter( array_map( 'intval', explode( ',', $m['bs_author_ids'] ?? '' ) ) );
        $author_names = array_filter( array_map( fn( $id ) => get_the_title( $id ), $author_ids ) );
        $author_str   = $author_names ? implode( ', ', $author_names ) : '—';

        $publisher_name = $m['bs_publisher_id'] ? get_the_title( (int) $m['bs_publisher_id'] ) : '—';

        echo '<table style="width:100%;font-size:13px;border-collapse:collapse">';
        $rows = [
            '✍️ Author(s)'  => esc_html( $author_str ),
            '🏢 Publisher'  => esc_html( $publisher_name ),
            '📅 Year'       => esc_html( $m['bs_published_year'] ?: '—' ),
            '📖 Pages'      => esc_html( $m['bs_pages'] ?: '—' ),
            '🌐 Language'   => esc_html( $m['bs_language'] ?: '—' ),
            '🔢 ISBN'       => esc_html( $m['bs_isbn'] ?: '—' ),
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
            echo '<p style="text-align:center;margin:8px 0">
                <code style="font-size:20px;font-weight:700;color:#5B5EDE;background:#EEF0FF;padding:8px 16px;border-radius:8px;letter-spacing:2px">'
                . esc_html( $code ) .
                '</code></p>
                <p style="font-size:12px;color:#6B7280;text-align:center;margin-top:8px">Auto-generated · read-only</p>';
        } else {
            echo '<p style="font-size:13px;color:#6B7280;text-align:center">Will be generated on first save.</p>';
        }
    }

    // ── Save Meta ─────────────────────────────────────────────────────────────
    public static function save_meta( int $post_id, \WP_Post $post ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) )                return;
        if ( $post->post_type !== self::BOOK_CPT )            return;
        if ( ! current_user_can( 'edit_post', $post_id ) )    return;
        if ( ! isset( $_POST['bs_book_nonce'] ) )             return;
        if ( ! wp_verify_nonce( $_POST['bs_book_nonce'], 'bs_book_meta' ) ) return;

        // Generate unique code once
        if ( ! get_post_meta( $post_id, 'bs_unique_code', true ) ) {
            update_post_meta( $post_id, 'bs_unique_code', self::generate_code() );
        }

        // Multiple authors — store as comma-separated IDs
        $raw_author_ids = isset( $_POST['bs_author_ids'] ) && is_array( $_POST['bs_author_ids'] )
            ? array_filter( array_map( 'intval', $_POST['bs_author_ids'] ) )
            : [];
        update_post_meta( $post_id, 'bs_author_ids', implode( ',', $raw_author_ids ) );

        // Keep bs_author_id as the first author for backwards compatibility
        update_post_meta( $post_id, 'bs_author_id', $raw_author_ids ? reset( $raw_author_ids ) : 0 );

        // Text fields
        foreach ( [ 'bs_isbn', 'bs_language' ] as $key ) {
            update_post_meta( $post_id, $key, sanitize_text_field( $_POST[ $key ] ?? '' ) );
        }

        // Textarea
        update_post_meta( $post_id, 'bs_description', sanitize_textarea_field( $_POST['bs_description'] ?? '' ) );

        // Integer fields
        foreach ( [ 'bs_publisher_id', 'bs_published_year', 'bs_pages' ] as $key ) {
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
            'cb'             => $cols['cb'],
            'bs_cover'       => __( 'Cover',     'bookshare' ),
            'title'          => __( 'Title',     'bookshare' ),
            'bs_unique_code' => __( 'Code',      'bookshare' ),
            'bs_author'      => __( 'Author(s)', 'bookshare' ),
            'bs_publisher'   => __( 'Publisher', 'bookshare' ),
            'bs_year'        => __( 'Year',      'bookshare' ),
            'date'           => __( 'Added',     'bookshare' ),
        ];
    }

    public static function sortable_columns( array $cols ): array {
        $cols['bs_year'] = 'bs_year';
        return $cols;
    }

    public static function book_column_data( string $col, int $post_id ): void {
        switch ( $col ) {
            case 'bs_cover':
                $thumb = get_the_post_thumbnail( $post_id, [ 36, 50 ] );
                echo $thumb
                    ? '<span style="display:inline-block;width:36px;height:50px;overflow:hidden;border-radius:4px;border:1px solid #E5E7F0;line-height:0">' . $thumb . '</span>'
                    : '📚';
                break;

            case 'bs_unique_code':
                $code = get_post_meta( $post_id, 'bs_unique_code', true );
                echo '<code style="font-weight:700;color:#5B5EDE;background:#EEF0FF;padding:2px 7px;border-radius:5px;letter-spacing:1px;font-size:12px">'
                    . esc_html( $code ?: '—' ) . '</code>';
                break;

            case 'bs_author':
                $ids   = array_filter( array_map( 'intval',
                    explode( ',', get_post_meta( $post_id, 'bs_author_ids', true ) )
                ) );
                $names = [];
                foreach ( $ids as $id ) {
                    $link    = get_edit_post_link( $id );
                    $names[] = $link
                        ? '<a href="' . esc_url( $link ) . '">' . esc_html( get_the_title( $id ) ) . '</a>'
                        : esc_html( get_the_title( $id ) );
                }
                echo $names ? implode( ', ', $names ) : '—';
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

            case 'bs_year':
                echo esc_html( get_post_meta( $post_id, 'bs_published_year', true ) ?: '—' );
                break;
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private static function get_meta( int $post_id ): array {
        $keys = [
            'bs_unique_code', 'bs_author_ids', 'bs_author_id', 'bs_publisher_id',
            'bs_isbn', 'bs_published_year', 'bs_pages',
            'bs_language', 'bs_description',
        ];
        $out = [];
        foreach ( $keys as $key ) {
            $out[ $key ] = get_post_meta( $post_id, $key, true );
        }
        return $out;
    }

    public static function generate_code(): string {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ( $i = 0; $i < 8; $i++ ) {
                $code .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
            }
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

        if ( ! empty( $args['author_id'] ) ) {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => 'bs_author_id',  'value' => intval( $args['author_id'] ), 'type' => 'NUMERIC' ],
                [ 'key' => 'bs_author_ids', 'value' => (string) intval( $args['author_id'] ), 'compare' => 'LIKE' ],
            ];
        }

        if ( ! empty( $args['publisher_id'] ) ) {
            $meta_query[] = [ 'key' => 'bs_publisher_id', 'value' => intval( $args['publisher_id'] ), 'type' => 'NUMERIC' ];
        }

        if ( ! empty( $args['tax_query'] ) ) {
            $query_args['tax_query'] = $args['tax_query'];
        }

        if ( $meta_query ) {
            $query_args['meta_query'] = $meta_query;
        }

        $posts = get_posts( $query_args );
        foreach ( $posts as $post ) {
            self::hydrate( $post );
        }
        return $posts;
    }

    public static function get_by_id( int $post_id ): ?\WP_Post {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== self::BOOK_CPT ) return null;
        self::hydrate( $post );
        return $post;
    }

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

    private static function hydrate( \WP_Post $post ): void {
        $meta = get_post_meta( $post->ID );

        $scalar_keys = [ 'bs_unique_code', 'bs_isbn', 'bs_language', 'bs_description', 'bs_author_ids' ];
        foreach ( $scalar_keys as $key ) {
            $post->$key = $meta[ $key ][0] ?? '';
        }

        $int_keys = [ 'bs_author_id', 'bs_publisher_id', 'bs_published_year', 'bs_pages' ];
        foreach ( $int_keys as $key ) {
            $post->$key = isset( $meta[ $key ][0] ) ? (int) $meta[ $key ][0] : 0;
        }

        // Multiple authors
        $author_ids = array_filter( array_map( 'intval', explode( ',', $post->bs_author_ids ) ) );
        $post->author_names = array_filter( array_map( fn( $id ) => get_the_title( $id ), $author_ids ) );
        $post->author_name  = $post->author_names ? implode( ', ', $post->author_names ) : '';

        $post->publisher_name = $post->bs_publisher_id ? get_the_title( $post->bs_publisher_id ) : '';

        // Cover from featured image
        $post->cover_url = get_the_post_thumbnail_url( $post->ID, 'medium' ) ?: '';

        // Aliases
        $post->title          = $post->post_title;
        $post->published_year = $post->bs_published_year;
        $post->unique_code    = $post->bs_unique_code;

        // Taxonomy terms
        $post->tags       = wp_get_post_terms( $post->ID, 'bs_book_tag',      [ 'fields' => 'names' ] );
        $post->categories = wp_get_post_terms( $post->ID, 'bs_book_category', [ 'fields' => 'names' ] );
    }

    public static function count( array $args = [] ): int {
        $q = new \WP_Query( [
            'post_type'      => self::BOOK_CPT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );
        return (int) $q->found_posts;
    }

    public static function genres(): array {
        $terms = get_terms( [ 'taxonomy' => 'bs_book_category', 'hide_empty' => false, 'fields' => 'names' ] );
        return is_wp_error( $terms ) ? [] : $terms;
    }

    // Cover URL helper for external use
    public static function get_cover_url( int $post_id, string $size = 'medium' ): string {
        return get_the_post_thumbnail_url( $post_id, $size ) ?: '';
    }
}