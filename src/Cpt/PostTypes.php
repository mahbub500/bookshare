<?php
namespace BookShare\Cpt;

defined( 'ABSPATH' ) || exit;

/**
 * Registers two WordPress Custom Post Types:
 *   - bs_author    (Book Author)
 *   - bs_publisher (Book Publisher)
 *
 * All data lives in WordPress post meta.
 * No custom DB tables are read or written here.
 */
class PostTypes {

    // ── CPT slugs ─────────────────────────────────────────────────────────────
    const AUTHOR_CPT    = 'bs_author';
    const PUBLISHER_CPT = 'bs_publisher';

    // ── Author meta field definitions ─────────────────────────────────────────
    const AUTHOR_FIELDS = [
        'bs_email'            => [ 'label' => 'Email',              'type' => 'email'    ],
        'bs_website'          => [ 'label' => 'Website',            'type' => 'url'      ],
        'bs_birth_date'       => [ 'label' => 'Birth Date',         'type' => 'date'     ],
        'bs_nationality'      => [ 'label' => 'Nationality',        'type' => 'text'     ],
        'bs_photo_url'        => [ 'label' => 'Photo URL',          'type' => 'url'      ],
        'bs_bio'              => [ 'label' => 'Biography',          'type' => 'textarea' ],
        'bs_social_twitter'   => [ 'label' => 'Twitter / X Handle', 'type' => 'text'     ],
        'bs_social_instagram' => [ 'label' => 'Instagram Handle',   'type' => 'text'     ],
        'bs_social_facebook'  => [ 'label' => 'Facebook URL',       'type' => 'url'      ],
    ];

    // ── Publisher meta field definitions ──────────────────────────────────────
    const PUBLISHER_FIELDS = [
        'bs_email'        => [ 'label' => 'Email',        'type' => 'email'    ],
        'bs_phone'        => [ 'label' => 'Phone',        'type' => 'tel'      ],
        'bs_website'      => [ 'label' => 'Website',      'type' => 'url'      ],
        'bs_address'      => [ 'label' => 'Address',      'type' => 'textarea' ],
        'bs_city'         => [ 'label' => 'City',         'type' => 'text'     ],
        'bs_country'      => [ 'label' => 'Country',      'type' => 'text'     ],
        'bs_founded_year' => [ 'label' => 'Founded Year', 'type' => 'number'   ],
        'bs_logo_url'     => [ 'label' => 'Logo URL',     'type' => 'url'      ],
        'bs_description'  => [ 'label' => 'Description',  'type' => 'textarea' ],
    ];

    // ── Boot ──────────────────────────────────────────────────────────────────
    public static function register(): void {
        add_action( 'init',           [ self::class, 'register_cpts'  ] );
        add_action( 'add_meta_boxes', [ self::class, 'add_meta_boxes' ] );
        add_action( 'save_post',      [ self::class, 'save_meta'      ], 10, 2 );

        // Register meta for REST API
        add_action( 'init', [ self::class, 'register_meta' ] );

        // List-table columns
        add_filter( 'manage_' . self::AUTHOR_CPT    . '_posts_columns',       [ self::class, 'author_columns'        ] );
        add_filter( 'manage_' . self::PUBLISHER_CPT . '_posts_columns',       [ self::class, 'publisher_columns'     ] );
        add_action( 'manage_' . self::AUTHOR_CPT    . '_posts_custom_column', [ self::class, 'author_column_data'    ], 10, 2 );
        add_action( 'manage_' . self::PUBLISHER_CPT . '_posts_custom_column', [ self::class, 'publisher_column_data' ], 10, 2 );

    }

    // ── Register CPTs ─────────────────────────────────────────────────────────
    public static function register_cpts(): void {

        // ── Book Author ───────────────────────────────────────────────────────
        register_post_type( self::AUTHOR_CPT, [
            'labels' => [
                'name'               => __( 'Book Authors',       'bookshare' ),
                'singular_name'      => __( 'Book Author',        'bookshare' ),
                'add_new'            => __( 'Add Author',          'bookshare' ),
                'add_new_item'       => __( 'Add New Book Author', 'bookshare' ),
                'edit_item'          => __( 'Edit Book Author',    'bookshare' ),
                'new_item'           => __( 'New Book Author',     'bookshare' ),
                'view_item'          => __( 'View Book Author',    'bookshare' ),
                'search_items'       => __( 'Search Authors',      'bookshare' ),
                'not_found'          => __( 'No authors found.',   'bookshare' ),
                'not_found_in_trash' => __( 'No authors in trash.','bookshare' ),
                'menu_name'          => __( 'Book Authors',        'bookshare' ),
                'all_items'          => __( 'All Authors',         'bookshare' ),
            ],
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => 'bookshare',
            'show_in_rest'       => true,
            'rest_base'          => 'bs-authors',
            'query_var'          => true,
            'rewrite'            => [ 'slug' => 'book-author', 'with_front' => false ],
            'capability_type'    => 'post',
            'has_archive'        => 'book-authors',
            'hierarchical'       => false,
            'menu_icon'          => 'dashicons-admin-users',
            'supports'           => [ 'title', 'thumbnail', 'revisions' ],
            'show_in_nav_menus'  => true,
            'delete_with_user'   => false,
        ] );

        // ── Book Publisher ────────────────────────────────────────────────────
        register_post_type( self::PUBLISHER_CPT, [
            'labels' => [
                'name'               => __( 'Book Publishers',        'bookshare' ),
                'singular_name'      => __( 'Book Publisher',         'bookshare' ),
                'add_new'            => __( 'Add Publisher',           'bookshare' ),
                'add_new_item'       => __( 'Add New Book Publisher',  'bookshare' ),
                'edit_item'          => __( 'Edit Book Publisher',     'bookshare' ),
                'new_item'           => __( 'New Book Publisher',      'bookshare' ),
                'view_item'          => __( 'View Book Publisher',     'bookshare' ),
                'search_items'       => __( 'Search Publishers',       'bookshare' ),
                'not_found'          => __( 'No publishers found.',    'bookshare' ),
                'not_found_in_trash' => __( 'No publishers in trash.', 'bookshare' ),
                'menu_name'          => __( 'Book Publishers',         'bookshare' ),
                'all_items'          => __( 'All Publishers',          'bookshare' ),
            ],
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => 'bookshare',
            'show_in_rest'       => true,
            'rest_base'          => 'bs-publishers',
            'query_var'          => true,
            'rewrite'            => [ 'slug' => 'book-publisher', 'with_front' => false ],
            'capability_type'    => 'post',
            'has_archive'        => 'book-publishers',
            'hierarchical'       => false,
            'menu_icon'          => 'dashicons-building',
            'supports'           => [ 'title', 'thumbnail', 'revisions' ],
            'show_in_nav_menus'  => true,
            'delete_with_user'   => false,
        ] );
    }

    // ── Register meta for REST ────────────────────────────────────────────────
    public static function register_meta(): void {
        $auth_cb = fn() => current_user_can( 'edit_posts' );

        // Author string meta
        $author_string_keys = [
            'bs_email', 'bs_website', 'bs_birth_date', 'bs_nationality',
            'bs_photo_url', 'bs_bio', 'bs_social_twitter',
            'bs_social_instagram', 'bs_social_facebook',
        ];
        foreach ( $author_string_keys as $key ) {
            register_post_meta( self::AUTHOR_CPT, $key, [
                'type'           => 'string',
                'single'         => true,
                'show_in_rest'   => true,
                'auth_callback'  => $auth_cb,
            ] );
        }

        // Publisher string meta
        $pub_string_keys = [
            'bs_email', 'bs_phone', 'bs_website', 'bs_address',
            'bs_city', 'bs_country', 'bs_logo_url', 'bs_description',
        ];
        foreach ( $pub_string_keys as $key ) {
            register_post_meta( self::PUBLISHER_CPT, $key, [
                'type'           => 'string',
                'single'         => true,
                'show_in_rest'   => true,
                'auth_callback'  => $auth_cb,
            ] );
        }

        register_post_meta( self::PUBLISHER_CPT, 'bs_founded_year', [
            'type'           => 'integer',
            'single'         => true,
            'show_in_rest'   => true,
            'auth_callback'  => $auth_cb,
        ] );
    }

    // ── Meta Boxes ────────────────────────────────────────────────────────────
    public static function add_meta_boxes(): void {
        // Author
        add_meta_box(
            'bs_author_details',
            '✍️ Author Details',
            [ self::class, 'render_author_meta_box' ],
            self::AUTHOR_CPT, 'normal', 'high'
        );
        add_meta_box(
            'bs_author_social',
            '🔗 Social Media',
            [ self::class, 'render_author_social_box' ],
            self::AUTHOR_CPT, 'side', 'default'
        );
        add_meta_box(
            'bs_author_books',
            '📚 Books by this Author',
            [ self::class, 'render_author_books_box' ],
            self::AUTHOR_CPT, 'normal', 'low'
        );

        // Publisher
        add_meta_box(
            'bs_publisher_details',
            '🏢 Publisher Details',
            [ self::class, 'render_publisher_meta_box' ],
            self::PUBLISHER_CPT, 'normal', 'high'
        );
        add_meta_box(
            'bs_publisher_contact',
            '📞 Contact & Location',
            [ self::class, 'render_publisher_contact_box' ],
            self::PUBLISHER_CPT, 'side', 'default'
        );
        add_meta_box(
            'bs_publisher_books',
            '📚 Books by this Publisher',
            [ self::class, 'render_publisher_books_box' ],
            self::PUBLISHER_CPT, 'normal', 'low'
        );
    }

    // ── Author Meta Box — Main ────────────────────────────────────────────────
    public static function render_author_meta_box( \WP_Post $post ): void {
        wp_nonce_field( 'bs_author_meta', 'bs_author_nonce' );
        $m = self::get_meta( $post->ID, array_keys( self::AUTHOR_FIELDS ) );
        ?>
        <div class="bs-metabox-grid">
            <div class="bs-meta-row">
                <label class="bs-meta-label">Email</label>
                <input type="email" name="bs_email" value="<?php echo esc_attr( $m['bs_email'] ); ?>"
                    class="bs-meta-input" placeholder="author@example.com">
            </div>
            <div class="bs-meta-row">
                <label class="bs-meta-label">Website</label>
                <input type="url" name="bs_website" value="<?php echo esc_attr( $m['bs_website'] ); ?>"
                    class="bs-meta-input" placeholder="https://authorwebsite.com">
            </div>
            <div class="bs-meta-row">
                <label class="bs-meta-label">Date of Birth</label>
                <input type="date" name="bs_birth_date" value="<?php echo esc_attr( $m['bs_birth_date'] ); ?>"
                    class="bs-meta-input">
            </div>
            <div class="bs-meta-row">
                <label class="bs-meta-label">Nationality</label>
                <input type="text" name="bs_nationality" value="<?php echo esc_attr( $m['bs_nationality'] ); ?>"
                    class="bs-meta-input" placeholder="American, British, Nigerian…">
            </div>
            <div class="bs-meta-row bs-meta-full">
                <label class="bs-meta-label">Photo URL</label>
                <input type="url" name="bs_photo_url" value="<?php echo esc_attr( $m['bs_photo_url'] ); ?>"
                    class="bs-meta-input" placeholder="https://…/photo.jpg">
                <?php if ( $m['bs_photo_url'] ) : ?>
                    <img src="<?php echo esc_url( $m['bs_photo_url'] ); ?>" alt=""
                        style="margin-top:8px;max-height:100px;border-radius:8px;border:2px solid #E5E7F0">
                <?php endif; ?>
            </div>
            <div class="bs-meta-row bs-meta-full">
                <label class="bs-meta-label">Biography</label>
                <textarea name="bs_bio" class="bs-meta-textarea" rows="5"
                    placeholder="A short biography of the author…"><?php echo esc_textarea( $m['bs_bio'] ); ?></textarea>
            </div>
        </div>
        <?php
    }

    // ── Author Meta Box — Social ──────────────────────────────────────────────
    public static function render_author_social_box( \WP_Post $post ): void {
        $m = self::get_meta( $post->ID, [ 'bs_social_twitter', 'bs_social_instagram', 'bs_social_facebook' ] );
        ?>
        <div class="bs-meta-row" style="margin-bottom:10px">
            <label class="bs-meta-label">𝕏 Twitter / X Handle</label>
            <div style="display:flex;align-items:center;gap:6px">
                <span style="color:#6B7280;font-size:13px">@</span>
                <input type="text" name="bs_social_twitter"
                    value="<?php echo esc_attr( $m['bs_social_twitter'] ); ?>"
                    class="bs-meta-input" placeholder="username">
            </div>
        </div>
        <div class="bs-meta-row" style="margin-bottom:10px">
            <label class="bs-meta-label">📷 Instagram Handle</label>
            <div style="display:flex;align-items:center;gap:6px">
                <span style="color:#6B7280;font-size:13px">@</span>
                <input type="text" name="bs_social_instagram"
                    value="<?php echo esc_attr( $m['bs_social_instagram'] ); ?>"
                    class="bs-meta-input" placeholder="username">
            </div>
        </div>
        <div class="bs-meta-row">
            <label class="bs-meta-label">📘 Facebook URL</label>
            <input type="url" name="bs_social_facebook"
                value="<?php echo esc_attr( $m['bs_social_facebook'] ); ?>"
                class="bs-meta-input" placeholder="https://facebook.com/…">
        </div>
        <?php
    }

    // ── Author Meta Box — Books ───────────────────────────────────────────────
    public static function render_author_books_box( \WP_Post $post ): void {
        // Query bs_book posts whose bs_author_id meta equals this post's ID
        $books = get_posts( [
            'post_type'      => 'bs_book',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => [ [
                'key'   => 'bs_author_id',
                'value' => $post->ID,
                'type'  => 'NUMERIC',
            ] ],
        ] );

        if ( empty( $books ) ) {
            echo '<p style="color:#6B7280;font-size:13px;padding:16px">No books linked to this author yet.</p>';
            return;
        }

        echo '<table class="bs-cpt-book-table"><thead><tr>
            <th>Title</th><th>Code</th><th>Genre</th><th>Year</th>
        </tr></thead><tbody>';

        foreach ( $books as $book ) {
            $code  = get_post_meta( $book->ID, 'bs_unique_code',    true );
            $genre = get_post_meta( $book->ID, 'bs_genre',          true );
            $year  = get_post_meta( $book->ID, 'bs_published_year', true );
            $edit  = get_edit_post_link( $book->ID );

            printf(
                '<tr>
                    <td><strong><a href="%s">%s</a></strong></td>
                    <td><code class="bs-code">%s</code></td>
                    <td>%s</td>
                    <td>%s</td>
                </tr>',
                esc_url( $edit ?? '#' ),
                esc_html( $book->post_title ),
                esc_html( $code ?: '—' ),
                esc_html( $genre ?: '—' ),
                esc_html( $year  ?: '—' )
            );
        }

        echo '</tbody></table>';
    }

    // ── Publisher Meta Box — Main ─────────────────────────────────────────────
    public static function render_publisher_meta_box( \WP_Post $post ): void {
        wp_nonce_field( 'bs_publisher_meta', 'bs_publisher_nonce' );
        $m = self::get_meta( $post->ID, array_keys( self::PUBLISHER_FIELDS ) );
        ?>
        <div class="bs-metabox-grid">
            <div class="bs-meta-row">
                <label class="bs-meta-label">Founded Year</label>
                <input type="number" name="bs_founded_year"
                    value="<?php echo esc_attr( $m['bs_founded_year'] ); ?>"
                    class="bs-meta-input" placeholder="1985" min="1400" max="2099">
            </div>
            <div class="bs-meta-row">
                <label class="bs-meta-label">Website</label>
                <input type="url" name="bs_website"
                    value="<?php echo esc_attr( $m['bs_website'] ); ?>"
                    class="bs-meta-input" placeholder="https://publisher.com">
            </div>
            <div class="bs-meta-row bs-meta-full">
                <label class="bs-meta-label">Logo URL</label>
                <input type="url" name="bs_logo_url"
                    value="<?php echo esc_attr( $m['bs_logo_url'] ); ?>"
                    class="bs-meta-input" placeholder="https://…/logo.png">
                <?php if ( $m['bs_logo_url'] ) : ?>
                    <img src="<?php echo esc_url( $m['bs_logo_url'] ); ?>" alt=""
                        style="margin-top:8px;max-height:60px;object-fit:contain;border:2px solid #E5E7F0;border-radius:6px;background:#f9f9f9;padding:6px">
                <?php endif; ?>
            </div>
            <div class="bs-meta-row bs-meta-full">
                <label class="bs-meta-label">Description</label>
                <textarea name="bs_description" class="bs-meta-textarea" rows="4"
                    placeholder="About this publisher…"><?php echo esc_textarea( $m['bs_description'] ); ?></textarea>
            </div>
        </div>
        <?php
    }

    // ── Publisher Meta Box — Contact ──────────────────────────────────────────
    public static function render_publisher_contact_box( \WP_Post $post ): void {
        $m = self::get_meta( $post->ID, [ 'bs_email', 'bs_phone', 'bs_address', 'bs_city', 'bs_country' ] );
        ?>
        <div class="bs-meta-row" style="margin-bottom:10px">
            <label class="bs-meta-label">Email</label>
            <input type="email" name="bs_email"
                value="<?php echo esc_attr( $m['bs_email'] ); ?>"
                class="bs-meta-input" placeholder="info@publisher.com">
        </div>
        <div class="bs-meta-row" style="margin-bottom:10px">
            <label class="bs-meta-label">Phone</label>
            <input type="tel" name="bs_phone"
                value="<?php echo esc_attr( $m['bs_phone'] ); ?>"
                class="bs-meta-input" placeholder="+1 555 0100">
        </div>
        <div class="bs-meta-row" style="margin-bottom:10px">
            <label class="bs-meta-label">City</label>
            <input type="text" name="bs_city"
                value="<?php echo esc_attr( $m['bs_city'] ); ?>"
                class="bs-meta-input" placeholder="New York">
        </div>
        <div class="bs-meta-row" style="margin-bottom:10px">
            <label class="bs-meta-label">Country</label>
            <input type="text" name="bs_country"
                value="<?php echo esc_attr( $m['bs_country'] ); ?>"
                class="bs-meta-input" placeholder="USA">
        </div>
        <div class="bs-meta-row">
            <label class="bs-meta-label">Address</label>
            <textarea name="bs_address" class="bs-meta-textarea" rows="3"
                placeholder="Street address…"><?php echo esc_textarea( $m['bs_address'] ); ?></textarea>
        </div>
        <?php
    }

    // ── Publisher Meta Box — Books ────────────────────────────────────────────
    public static function render_publisher_books_box( \WP_Post $post ): void {
        $books = get_posts( [
            'post_type'      => 'bs_book',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => [ [
                'key'   => 'bs_publisher_id',
                'value' => $post->ID,
                'type'  => 'NUMERIC',
            ] ],
        ] );

        if ( empty( $books ) ) {
            echo '<p style="color:#6B7280;font-size:13px;padding:16px">No books linked to this publisher yet.</p>';
            return;
        }

        echo '<table class="bs-cpt-book-table"><thead><tr>
            <th>Title</th><th>Author</th><th>Code</th><th>Year</th>
        </tr></thead><tbody>';

        foreach ( $books as $book ) {
            $code      = get_post_meta( $book->ID, 'bs_unique_code',    true );
            $year      = get_post_meta( $book->ID, 'bs_published_year', true );
            $author_id = (int) get_post_meta( $book->ID, 'bs_author_id', true );
            $author    = $author_id ? get_the_title( $author_id ) : '—';
            $edit      = get_edit_post_link( $book->ID );

            printf(
                '<tr>
                    <td><strong><a href="%s">%s</a></strong></td>
                    <td>%s</td>
                    <td><code class="bs-code">%s</code></td>
                    <td>%s</td>
                </tr>',
                esc_url( $edit ?? '#' ),
                esc_html( $book->post_title ),
                esc_html( $author ),
                esc_html( $code ?: '—' ),
                esc_html( $year ?: '—' )
            );
        }

        echo '</tbody></table>';
    }

    // ── Save Meta ─────────────────────────────────────────────────────────────
    public static function save_meta( int $post_id, \WP_Post $post ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) )               return;
        if ( ! current_user_can( 'edit_post', $post_id ) )   return;

        if ( $post->post_type === self::AUTHOR_CPT ) {
            if ( ! isset( $_POST['bs_author_nonce'] ) ) return;
            if ( ! wp_verify_nonce( $_POST['bs_author_nonce'], 'bs_author_meta' ) ) return;
            self::save_author_meta( $post_id );
        }

        if ( $post->post_type === self::PUBLISHER_CPT ) {
            if ( ! isset( $_POST['bs_publisher_nonce'] ) ) return;
            if ( ! wp_verify_nonce( $_POST['bs_publisher_nonce'], 'bs_publisher_meta' ) ) return;
            self::save_publisher_meta( $post_id );
        }
    }

    private static function save_author_meta( int $post_id ): void {
        $fields = [
            'bs_email'            => 'email',
            'bs_website'          => 'url',
            'bs_birth_date'       => 'text',
            'bs_nationality'      => 'text',
            'bs_photo_url'        => 'url',
            'bs_bio'              => 'textarea',
            'bs_social_twitter'   => 'text',
            'bs_social_instagram' => 'text',
            'bs_social_facebook'  => 'url',
        ];

        foreach ( $fields as $key => $type ) {
            update_post_meta(
                $post_id,
                $key,
                self::sanitize( $_POST[ $key ] ?? '', $type )
            );
        }
    }

    private static function save_publisher_meta( int $post_id ): void {
        $fields = [
            'bs_email'        => 'email',
            'bs_phone'        => 'text',
            'bs_website'      => 'url',
            'bs_address'      => 'textarea',
            'bs_city'         => 'text',
            'bs_country'      => 'text',
            'bs_founded_year' => 'int',
            'bs_logo_url'     => 'url',
            'bs_description'  => 'textarea',
        ];

        foreach ( $fields as $key => $type ) {
            update_post_meta(
                $post_id,
                $key,
                self::sanitize( $_POST[ $key ] ?? '', $type )
            );
        }
    }

    // ── List-table Columns — Author ───────────────────────────────────────────
    public static function author_columns( array $cols ): array {
        return [
            'cb'             => $cols['cb'],
            'title'          => __( 'Author Name', 'bookshare' ),
            'bs_photo'       => __( 'Photo',       'bookshare' ),
            'bs_nationality' => __( 'Nationality', 'bookshare' ),
            'bs_email'       => __( 'Email',       'bookshare' ),
            'bs_books'       => __( 'Books',       'bookshare' ),
            'bs_socials'     => __( 'Socials',     'bookshare' ),
            'date'           => __( 'Date',        'bookshare' ),
        ];
    }

    public static function author_column_data( string $col, int $post_id ): void {
        switch ( $col ) {
            case 'bs_photo':
                $url = get_post_meta( $post_id, 'bs_photo_url', true );
                echo $url
                    ? '<img src="' . esc_url( $url ) . '" style="width:40px;height:40px;border-radius:50%;object-fit:cover">'
                    : '👤';
                break;

            case 'bs_nationality':
                echo esc_html( get_post_meta( $post_id, 'bs_nationality', true ) ?: '—' );
                break;

            case 'bs_email':
                $email = get_post_meta( $post_id, 'bs_email', true );
                echo $email
                    ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>'
                    : '—';
                break;

            case 'bs_books':
                // Count bs_book posts linked to this author
                $count = (int) ( new \WP_Query( [
                    'post_type'      => 'bs_book',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'no_found_rows'  => false,
                    'meta_query'     => [ [
                        'key'   => 'bs_author_id',
                        'value' => $post_id,
                        'type'  => 'NUMERIC',
                    ] ],
                ] ) )->found_posts;
                echo '<strong style="color:#5B5EDE">' . $count . '</strong>';
                break;

            case 'bs_socials':
                $tw    = get_post_meta( $post_id, 'bs_social_twitter',   true );
                $ig    = get_post_meta( $post_id, 'bs_social_instagram', true );
                $fb    = get_post_meta( $post_id, 'bs_social_facebook',  true );
                $links = [];
                if ( $tw ) $links[] = '<a href="https://twitter.com/'   . esc_attr( $tw ) . '" target="_blank">𝕏</a>';
                if ( $ig ) $links[] = '<a href="https://instagram.com/' . esc_attr( $ig ) . '" target="_blank">📷</a>';
                if ( $fb ) $links[] = '<a href="' . esc_url( $fb ) . '" target="_blank">📘</a>';
                echo $links ? implode( ' ', $links ) : '—';
                break;
        }
    }

    // ── List-table Columns — Publisher ────────────────────────────────────────
    public static function publisher_columns( array $cols ): array {
        return [
            'cb'           => $cols['cb'],
            'title'        => __( 'Publisher Name', 'bookshare' ),
            'bs_logo'      => __( 'Logo',           'bookshare' ),
            'bs_city'      => __( 'City',           'bookshare' ),
            'bs_country'   => __( 'Country',        'bookshare' ),
            'bs_founded'   => __( 'Founded',        'bookshare' ),
            'bs_email'     => __( 'Email',          'bookshare' ),
            'bs_pub_books' => __( 'Books',          'bookshare' ),
            'date'         => __( 'Date',           'bookshare' ),
        ];
    }

    public static function publisher_column_data( string $col, int $post_id ): void {
        switch ( $col ) {
            case 'bs_logo':
                $url = get_post_meta( $post_id, 'bs_logo_url', true );
                echo $url
                    ? '<img src="' . esc_url( $url ) . '" style="max-height:36px;max-width:80px;object-fit:contain">'
                    : '🏢';
                break;

            case 'bs_city':
                echo esc_html( get_post_meta( $post_id, 'bs_city',    true ) ?: '—' );
                break;

            case 'bs_country':
                echo esc_html( get_post_meta( $post_id, 'bs_country', true ) ?: '—' );
                break;

            case 'bs_founded':
                echo esc_html( get_post_meta( $post_id, 'bs_founded_year', true ) ?: '—' );
                break;

            case 'bs_email':
                $email = get_post_meta( $post_id, 'bs_email', true );
                echo $email
                    ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>'
                    : '—';
                break;

            case 'bs_pub_books':
                $count = (int) ( new \WP_Query( [
                    'post_type'      => 'bs_book',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'no_found_rows'  => false,
                    'meta_query'     => [ [
                        'key'   => 'bs_publisher_id',
                        'value' => $post_id,
                        'type'  => 'NUMERIC',
                    ] ],
                ] ) )->found_posts;
                echo '<strong style="color:#5B5EDE">' . $count . '</strong>';
                break;
        }
    }

    

    // ── Helpers ───────────────────────────────────────────────────────────────
    private static function get_meta( int $post_id, array $keys ): array {
        $out = [];
        foreach ( $keys as $key ) {
            $out[ $key ] = (string) get_post_meta( $post_id, $key, true );
        }
        return $out;
    }

    private static function sanitize( mixed $value, string $type ): string {
        return match ( $type ) {
            'email'    => sanitize_email( (string) $value ),
            'url'      => esc_url_raw( (string) $value ),
            'int'      => (string) intval( $value ),
            'textarea' => sanitize_textarea_field( (string) $value ),
            default    => sanitize_text_field( (string) $value ),
        };
    }
}