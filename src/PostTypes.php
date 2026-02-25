<?php
namespace BookShare;

defined( 'ABSPATH' ) || exit;

/**
 * Registers two WordPress Custom Post Types:
 *   - bs_author    (Book Author)
 *   - bs_publisher (Book Publisher)
 *
 * Each CPT stores rich metadata in post meta and syncs to the
 * plugin's custom DB tables on save so the REST API & frontend
 * continue to work without changes.
 */
class PostTypes {

    // ── CPT slugs ────────────────────────────────────────────────────────────
    const AUTHOR_CPT    = 'bs_author';
    const PUBLISHER_CPT = 'bs_publisher';

    // ── Author meta keys ─────────────────────────────────────────────────────
    const AUTHOR_FIELDS = [
        'bs_email'            => [ 'label' => 'Email',             'type' => 'email'  ],
        'bs_website'          => [ 'label' => 'Website',           'type' => 'url'    ],
        'bs_birth_date'       => [ 'label' => 'Birth Date',        'type' => 'date'   ],
        'bs_nationality'      => [ 'label' => 'Nationality',       'type' => 'text'   ],
        'bs_photo_url'        => [ 'label' => 'Photo URL',         'type' => 'url'    ],
        'bs_bio'              => [ 'label' => 'Biography',         'type' => 'textarea' ],
        'bs_social_twitter'   => [ 'label' => 'Twitter / X Handle','type' => 'text'   ],
        'bs_social_instagram' => [ 'label' => 'Instagram Handle',  'type' => 'text'   ],
        'bs_social_facebook'  => [ 'label' => 'Facebook URL',      'type' => 'url'    ],
        'bs_db_id'            => [ 'label' => 'DB ID (auto)',       'type' => 'hidden' ],
    ];

    // ── Publisher meta keys ───────────────────────────────────────────────────
    const PUBLISHER_FIELDS = [
        'bs_email'        => [ 'label' => 'Email',         'type' => 'email'    ],
        'bs_phone'        => [ 'label' => 'Phone',         'type' => 'tel'      ],
        'bs_website'      => [ 'label' => 'Website',       'type' => 'url'      ],
        'bs_address'      => [ 'label' => 'Address',       'type' => 'textarea' ],
        'bs_city'         => [ 'label' => 'City',          'type' => 'text'     ],
        'bs_country'      => [ 'label' => 'Country',       'type' => 'text'     ],
        'bs_founded_year' => [ 'label' => 'Founded Year',  'type' => 'number'   ],
        'bs_logo_url'     => [ 'label' => 'Logo URL',      'type' => 'url'      ],
        'bs_description'  => [ 'label' => 'Description',   'type' => 'textarea' ],
        'bs_db_id'        => [ 'label' => 'DB ID (auto)',   'type' => 'hidden'   ],
    ];

    // ── Boot ─────────────────────────────────────────────────────────────────
    public static function register(): void {
        add_action( 'init',       [ self::class, 'register_cpts'     ] );
        add_action( 'add_meta_boxes', [ self::class, 'add_meta_boxes' ] );
        add_action( 'save_post',  [ self::class, 'save_meta'         ], 10, 2 );
        add_action( 'before_delete_post', [ self::class, 'before_delete' ] );

        // Custom columns
        add_filter( 'manage_' . self::AUTHOR_CPT    . '_posts_columns',       [ self::class, 'author_columns'     ] );
        add_filter( 'manage_' . self::PUBLISHER_CPT . '_posts_columns',       [ self::class, 'publisher_columns'  ] );
        add_action( 'manage_' . self::AUTHOR_CPT    . '_posts_custom_column', [ self::class, 'author_column_data'    ], 10, 2 );
        add_action( 'manage_' . self::PUBLISHER_CPT . '_posts_custom_column', [ self::class, 'publisher_column_data' ], 10, 2 );

        // Admin styles for CPT pages
        add_action( 'admin_head', [ self::class, 'admin_head_styles' ] );
    }

    // ── Register CPTs ─────────────────────────────────────────────────────────
    public static function register_cpts(): void {

        // ── Book Author ──────────────────────────────────────────────────────
        register_post_type( self::AUTHOR_CPT, [
            'labels' => [
                'name'               => __( 'Book Authors',      'bookshare' ),
                'singular_name'      => __( 'Book Author',       'bookshare' ),
                'add_new'            => __( 'Add Author',         'bookshare' ),
                'add_new_item'       => __( 'Add New Book Author','bookshare' ),
                'edit_item'          => __( 'Edit Book Author',   'bookshare' ),
                'new_item'           => __( 'New Book Author',    'bookshare' ),
                'view_item'          => __( 'View Book Author',   'bookshare' ),
                'search_items'       => __( 'Search Authors',     'bookshare' ),
                'not_found'          => __( 'No authors found.',  'bookshare' ),
                'not_found_in_trash' => __( 'No authors in trash.','bookshare' ),
                'menu_name'          => __( 'Book Authors',       'bookshare' ),
                'all_items'          => __( 'All Authors',        'bookshare' ),
            ],
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => 'bookshare',   // nest under BookCircle menu
            'show_in_rest'        => true,
            'rest_base'           => 'bs-authors',
            'query_var'           => true,
            'rewrite'             => [ 'slug' => 'book-author', 'with_front' => false ],
            'capability_type'     => 'post',
            'has_archive'         => 'book-authors',
            'hierarchical'        => false,
            'menu_position'       => null,
            'menu_icon'           => 'dashicons-admin-users',
            'supports'            => [ 'title', 'thumbnail', 'revisions' ],
            'show_in_nav_menus'   => true,
            'delete_with_user'    => false,
        ] );

        // ── Book Publisher ───────────────────────────────────────────────────
        register_post_type( self::PUBLISHER_CPT, [
            'labels' => [
                'name'               => __( 'Book Publishers',       'bookshare' ),
                'singular_name'      => __( 'Book Publisher',        'bookshare' ),
                'add_new'            => __( 'Add Publisher',          'bookshare' ),
                'add_new_item'       => __( 'Add New Book Publisher', 'bookshare' ),
                'edit_item'          => __( 'Edit Book Publisher',    'bookshare' ),
                'new_item'           => __( 'New Book Publisher',     'bookshare' ),
                'view_item'          => __( 'View Book Publisher',    'bookshare' ),
                'search_items'       => __( 'Search Publishers',      'bookshare' ),
                'not_found'          => __( 'No publishers found.',   'bookshare' ),
                'not_found_in_trash' => __( 'No publishers in trash.','bookshare' ),
                'menu_name'          => __( 'Book Publishers',        'bookshare' ),
                'all_items'          => __( 'All Publishers',         'bookshare' ),
            ],
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => 'bookshare',
            'show_in_rest'        => true,
            'rest_base'           => 'bs-publishers',
            'query_var'           => true,
            'rewrite'             => [ 'slug' => 'book-publisher', 'with_front' => false ],
            'capability_type'     => 'post',
            'has_archive'         => 'book-publishers',
            'hierarchical'        => false,
            'menu_position'       => null,
            'menu_icon'           => 'dashicons-building',
            'supports'            => [ 'title', 'thumbnail', 'revisions' ],
            'show_in_nav_menus'   => true,
            'delete_with_user'    => false,
        ] );
    }

    // ── Meta Boxes ────────────────────────────────────────────────────────────
    public static function add_meta_boxes(): void {
        add_meta_box(
            'bs_author_details',
            '✍️ Author Details',
            [ self::class, 'render_author_meta_box' ],
            self::AUTHOR_CPT,
            'normal',
            'high'
        );

        add_meta_box(
            'bs_author_social',
            '🔗 Social Media',
            [ self::class, 'render_author_social_box' ],
            self::AUTHOR_CPT,
            'side',
            'default'
        );

        add_meta_box(
            'bs_author_books',
            '📚 Books by this Author',
            [ self::class, 'render_author_books_box' ],
            self::AUTHOR_CPT,
            'normal',
            'low'
        );

        add_meta_box(
            'bs_publisher_details',
            '🏢 Publisher Details',
            [ self::class, 'render_publisher_meta_box' ],
            self::PUBLISHER_CPT,
            'normal',
            'high'
        );

        add_meta_box(
            'bs_publisher_contact',
            '📞 Contact & Location',
            [ self::class, 'render_publisher_contact_box' ],
            self::PUBLISHER_CPT,
            'side',
            'default'
        );

        add_meta_box(
            'bs_publisher_books',
            '📚 Books by this Publisher',
            [ self::class, 'render_publisher_books_box' ],
            self::PUBLISHER_CPT,
            'normal',
            'low'
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
                <input type="email" name="bs_email" value="<?php echo esc_attr( $m['bs_email'] ); ?>" class="bs-meta-input" placeholder="author@example.com">
            </div>
            <div class="bs-meta-row">
                <label class="bs-meta-label">Website</label>
                <input type="url" name="bs_website" value="<?php echo esc_attr( $m['bs_website'] ); ?>" class="bs-meta-input" placeholder="https://authorwebsite.com">
            </div>
            <div class="bs-meta-row">
                <label class="bs-meta-label">Date of Birth</label>
                <input type="date" name="bs_birth_date" value="<?php echo esc_attr( $m['bs_birth_date'] ); ?>" class="bs-meta-input">
            </div>
            <div class="bs-meta-row">
                <label class="bs-meta-label">Nationality</label>
                <input type="text" name="bs_nationality" value="<?php echo esc_attr( $m['bs_nationality'] ); ?>" class="bs-meta-input" placeholder="American, British, Nigerian…">
            </div>
            <div class="bs-meta-row bs-meta-full">
                <label class="bs-meta-label">Photo URL</label>
                <input type="url" name="bs_photo_url" value="<?php echo esc_attr( $m['bs_photo_url'] ); ?>" class="bs-meta-input" placeholder="https://…/photo.jpg">
                <?php if ( $m['bs_photo_url'] ) : ?>
                    <img src="<?php echo esc_url( $m['bs_photo_url'] ); ?>" alt="" style="margin-top:8px;max-height:100px;border-radius:8px;border:2px solid #E5E7F0">
                <?php endif; ?>
            </div>
            <div class="bs-meta-row bs-meta-full">
                <label class="bs-meta-label">Biography</label>
                <textarea name="bs_bio" class="bs-meta-textarea" rows="5" placeholder="A short biography of the author…"><?php echo esc_textarea( $m['bs_bio'] ); ?></textarea>
            </div>
        </div>
        <?php if ( $m['bs_db_id'] ) : ?>
            <p style="margin-top:12px;font-size:12px;color:#6B7280">
                🔗 BookCircle DB ID: <strong><?php echo esc_html( $m['bs_db_id'] ); ?></strong>
                (synced automatically)
            </p>
        <?php endif; ?>
        <?php
    }

    // ── Author Meta Box — Social ──────────────────────────────────────────────
    public static function render_author_social_box( \WP_Post $post ): void {
        $m = self::get_meta( $post->ID, ['bs_social_twitter','bs_social_instagram','bs_social_facebook'] );
        ?>
        <div class="bs-meta-row" style="margin-bottom:10px">
            <label class="bs-meta-label">𝕏 Twitter / X Handle</label>
            <div style="display:flex;align-items:center;gap:6px">
                <span style="color:#6B7280;font-size:13px">@</span>
                <input type="text" name="bs_social_twitter" value="<?php echo esc_attr( $m['bs_social_twitter'] ); ?>" class="bs-meta-input" placeholder="username">
            </div>
        </div>
        <div class="bs-meta-row" style="margin-bottom:10px">
            <label class="bs-meta-label">📷 Instagram Handle</label>
            <div style="display:flex;align-items:center;gap:6px">
                <span style="color:#6B7280;font-size:13px">@</span>
                <input type="text" name="bs_social_instagram" value="<?php echo esc_attr( $m['bs_social_instagram'] ); ?>" class="bs-meta-input" placeholder="username">
            </div>
        </div>
        <div class="bs-meta-row">
            <label class="bs-meta-label">📘 Facebook URL</label>
            <input type="url" name="bs_social_facebook" value="<?php echo esc_attr( $m['bs_social_facebook'] ); ?>" class="bs-meta-input" placeholder="https://facebook.com/…">
        </div>
        <?php
    }

    // ── Author Meta Box — Books ───────────────────────────────────────────────
    public static function render_author_books_box( \WP_Post $post ): void {
        $db_id = (int) get_post_meta( $post->ID, 'bs_db_id', true );
        if ( ! $db_id ) {
            echo '<p style="color:#6B7280;font-size:13px">Save this author first to see their books.</p>';
            return;
        }
        global $wpdb;
        $books = $wpdb->get_results( $wpdb->prepare(
            "SELECT b.id, b.title, b.unique_code, b.genre, b.published_year FROM {$wpdb->prefix}bs_books b WHERE b.author_id = %d ORDER BY b.title ASC",
            $db_id
        ) );
        if ( ! $books ) {
            echo '<p style="color:#6B7280;font-size:13px">No books linked to this author yet.</p>';
            return;
        }
        echo '<table class="bs-cpt-book-table"><thead><tr><th>Title</th><th>Code</th><th>Genre</th><th>Year</th></tr></thead><tbody>';
        foreach ( $books as $book ) {
            printf(
                '<tr><td><strong>%s</strong></td><td><code class="bs-code">%s</code></td><td>%s</td><td>%s</td></tr>',
                esc_html( $book->title ),
                esc_html( $book->unique_code ),
                esc_html( $book->genre ?: '—' ),
                esc_html( $book->published_year ?: '—' )
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
                <input type="number" name="bs_founded_year" value="<?php echo esc_attr( $m['bs_founded_year'] ); ?>" class="bs-meta-input" placeholder="1985" min="1400" max="2099">
            </div>
            <div class="bs-meta-row">
                <label class="bs-meta-label">Website</label>
                <input type="url" name="bs_website" value="<?php echo esc_attr( $m['bs_website'] ); ?>" class="bs-meta-input" placeholder="https://publisher.com">
            </div>
            <div class="bs-meta-row bs-meta-full">
                <label class="bs-meta-label">Logo URL</label>
                <input type="url" name="bs_logo_url" value="<?php echo esc_attr( $m['bs_logo_url'] ); ?>" class="bs-meta-input" placeholder="https://…/logo.png">
                <?php if ( $m['bs_logo_url'] ) : ?>
                    <img src="<?php echo esc_url( $m['bs_logo_url'] ); ?>" alt="" style="margin-top:8px;max-height:60px;object-fit:contain;border:2px solid #E5E7F0;border-radius:6px;background:#f9f9f9;padding:6px">
                <?php endif; ?>
            </div>
            <div class="bs-meta-row bs-meta-full">
                <label class="bs-meta-label">Description</label>
                <textarea name="bs_description" class="bs-meta-textarea" rows="4" placeholder="About this publisher…"><?php echo esc_textarea( $m['bs_description'] ); ?></textarea>
            </div>
        </div>
        <?php if ( $m['bs_db_id'] ) : ?>
            <p style="margin-top:12px;font-size:12px;color:#6B7280">
                🔗 BookCircle DB ID: <strong><?php echo esc_html( $m['bs_db_id'] ); ?></strong>
            </p>
        <?php endif; ?>
        <?php
    }

    // ── Publisher Meta Box — Contact ──────────────────────────────────────────
    public static function render_publisher_contact_box( \WP_Post $post ): void {
        $m = self::get_meta( $post->ID, ['bs_email','bs_phone','bs_address','bs_city','bs_country'] );
        ?>
        <div class="bs-meta-row" style="margin-bottom:10px">
            <label class="bs-meta-label">Email</label>
            <input type="email" name="bs_email" value="<?php echo esc_attr( $m['bs_email'] ); ?>" class="bs-meta-input" placeholder="info@publisher.com">
        </div>
        <div class="bs-meta-row" style="margin-bottom:10px">
            <label class="bs-meta-label">Phone</label>
            <input type="tel" name="bs_phone" value="<?php echo esc_attr( $m['bs_phone'] ); ?>" class="bs-meta-input" placeholder="+1 555 0100">
        </div>
        <div class="bs-meta-row" style="margin-bottom:10px">
            <label class="bs-meta-label">City</label>
            <input type="text" name="bs_city" value="<?php echo esc_attr( $m['bs_city'] ); ?>" class="bs-meta-input" placeholder="New York">
        </div>
        <div class="bs-meta-row" style="margin-bottom:10px">
            <label class="bs-meta-label">Country</label>
            <input type="text" name="bs_country" value="<?php echo esc_attr( $m['bs_country'] ); ?>" class="bs-meta-input" placeholder="USA">
        </div>
        <div class="bs-meta-row">
            <label class="bs-meta-label">Address</label>
            <textarea name="bs_address" class="bs-meta-textarea" rows="3" placeholder="Street address…"><?php echo esc_textarea( $m['bs_address'] ); ?></textarea>
        </div>
        <?php
    }

    // ── Publisher Meta Box — Books ────────────────────────────────────────────
    public static function render_publisher_books_box( \WP_Post $post ): void {
        $db_id = (int) get_post_meta( $post->ID, 'bs_db_id', true );
        if ( ! $db_id ) {
            echo '<p style="color:#6B7280;font-size:13px">Save this publisher first to see their books.</p>';
            return;
        }
        global $wpdb;
        $books = $wpdb->get_results( $wpdb->prepare(
            "SELECT b.id, b.title, b.unique_code, b.genre, b.published_year,
                    a.name AS author_name
             FROM {$wpdb->prefix}bs_books b
             LEFT JOIN {$wpdb->prefix}bs_authors a ON b.author_id = a.id
             WHERE b.publisher_id = %d ORDER BY b.title ASC",
            $db_id
        ) );
        if ( ! $books ) {
            echo '<p style="color:#6B7280;font-size:13px">No books linked to this publisher yet.</p>';
            return;
        }
        echo '<table class="bs-cpt-book-table"><thead><tr><th>Title</th><th>Author</th><th>Code</th><th>Year</th></tr></thead><tbody>';
        foreach ( $books as $book ) {
            printf(
                '<tr><td><strong>%s</strong></td><td>%s</td><td><code class="bs-code">%s</code></td><td>%s</td></tr>',
                esc_html( $book->title ),
                esc_html( $book->author_name ?: '—' ),
                esc_html( $book->unique_code ),
                esc_html( $book->published_year ?: '—' )
            );
        }
        echo '</tbody></table>';
    }

    // ── Save Meta ─────────────────────────────────────────────────────────────
    public static function save_meta( int $post_id, \WP_Post $post ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        if ( $post->post_type === self::AUTHOR_CPT ) {
            if ( ! isset( $_POST['bs_author_nonce'] ) || ! wp_verify_nonce( $_POST['bs_author_nonce'], 'bs_author_meta' ) ) return;
            self::save_author_meta( $post_id, $post );
        }

        if ( $post->post_type === self::PUBLISHER_CPT ) {
            if ( ! isset( $_POST['bs_publisher_nonce'] ) || ! wp_verify_nonce( $_POST['bs_publisher_nonce'], 'bs_publisher_meta' ) ) return;
            self::save_publisher_meta( $post_id, $post );
        }
    }

    private static function save_author_meta( int $post_id, \WP_Post $post ): void {
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
        $data = [];
        foreach ( $fields as $key => $type ) {
            $val = self::sanitize( $_POST[ $key ] ?? '', $type );
            update_post_meta( $post_id, $key, $val );
            $data[ $key ] = $val;
        }

        // Sync to bs_authors table
        $db_id = (int) get_post_meta( $post_id, 'bs_db_id', true );
        $sync  = [
            'name'             => $post->post_title,
            'bio'              => $data['bs_bio'],
            'email'            => $data['bs_email'],
            'website'          => $data['bs_website'],
            'birth_date'       => $data['bs_birth_date'] ?: null,
            'nationality'      => $data['bs_nationality'],
            'photo_url'        => $data['bs_photo_url'],
            'social_twitter'   => $data['bs_social_twitter'],
            'social_instagram' => $data['bs_social_instagram'],
            'social_facebook'  => $data['bs_social_facebook'],
        ];

        if ( $db_id ) {
            Models\Author::update( $db_id, $sync );
        } else {
            $new_id = Models\Author::create( $sync );
            if ( $new_id ) {
                update_post_meta( $post_id, 'bs_db_id', $new_id );
            }
        }
    }

    private static function save_publisher_meta( int $post_id, \WP_Post $post ): void {
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
        $data = [];
        foreach ( $fields as $key => $type ) {
            $val = self::sanitize( $_POST[ $key ] ?? '', $type );
            update_post_meta( $post_id, $key, $val );
            $data[ $key ] = $val;
        }

        // Sync to bs_publishers table
        $db_id = (int) get_post_meta( $post_id, 'bs_db_id', true );
        $sync  = [
            'name'         => $post->post_title,
            'description'  => $data['bs_description'],
            'email'        => $data['bs_email'],
            'phone'        => $data['bs_phone'],
            'website'      => $data['bs_website'],
            'address'      => $data['bs_address'],
            'city'         => $data['bs_city'],
            'country'      => $data['bs_country'],
            'founded_year' => $data['bs_founded_year'] ?: null,
            'logo_url'     => $data['bs_logo_url'],
        ];

        if ( $db_id ) {
            Models\Publisher::update( $db_id, $sync );
        } else {
            $new_id = Models\Publisher::create( $sync );
            if ( $new_id ) {
                update_post_meta( $post_id, 'bs_db_id', $new_id );
            }
        }
    }

    // ── Delete sync ───────────────────────────────────────────────────────────
    public static function before_delete( int $post_id ): void {
        $post = get_post( $post_id );
        if ( ! $post ) return;

        $db_id = (int) get_post_meta( $post_id, 'bs_db_id', true );
        if ( ! $db_id ) return;

        if ( $post->post_type === self::AUTHOR_CPT ) {
            Models\Author::delete( $db_id );
        }
        if ( $post->post_type === self::PUBLISHER_CPT ) {
            Models\Publisher::delete( $db_id );
        }
    }

    // ── Custom Columns ────────────────────────────────────────────────────────
    public static function author_columns( array $cols ): array {
        return [
            'cb'          => $cols['cb'],
            'title'       => __( 'Author Name', 'bookshare' ),
            'bs_photo'    => __( 'Photo', 'bookshare' ),
            'bs_nationality' => __( 'Nationality', 'bookshare' ),
            'bs_email'    => __( 'Email', 'bookshare' ),
            'bs_books'    => __( 'Books', 'bookshare' ),
            'bs_socials'  => __( 'Socials', 'bookshare' ),
            'date'        => __( 'Date', 'bookshare' ),
        ];
    }

    public static function author_column_data( string $col, int $post_id ): void {
        switch ( $col ) {
            case 'bs_photo':
                $url = get_post_meta( $post_id, 'bs_photo_url', true );
                if ( $url ) echo '<img src="' . esc_url( $url ) . '" style="width:40px;height:40px;border-radius:50%;object-fit:cover">';
                else echo '👤';
                break;
            case 'bs_nationality':
                echo esc_html( get_post_meta( $post_id, 'bs_nationality', true ) ?: '—' );
                break;
            case 'bs_email':
                $email = get_post_meta( $post_id, 'bs_email', true );
                echo $email ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '—';
                break;
            case 'bs_books':
                $db_id = (int) get_post_meta( $post_id, 'bs_db_id', true );
                if ( $db_id ) {
                    global $wpdb;
                    $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}bs_books WHERE author_id = %d", $db_id ) );
                    echo '<strong style="color:#5B5EDE">' . intval( $count ) . '</strong>';
                } else echo '—';
                break;
            case 'bs_socials':
                $tw = get_post_meta( $post_id, 'bs_social_twitter', true );
                $ig = get_post_meta( $post_id, 'bs_social_instagram', true );
                $fb = get_post_meta( $post_id, 'bs_social_facebook', true );
                $links = [];
                if ( $tw ) $links[] = '<a href="https://twitter.com/' . esc_attr($tw) . '" target="_blank" title="Twitter">𝕏</a>';
                if ( $ig ) $links[] = '<a href="https://instagram.com/' . esc_attr($ig) . '" target="_blank" title="Instagram">📷</a>';
                if ( $fb ) $links[] = '<a href="' . esc_url($fb) . '" target="_blank" title="Facebook">📘</a>';
                echo $links ? implode( ' ', $links ) : '—';
                break;
        }
    }

    public static function publisher_columns( array $cols ): array {
        return [
            'cb'           => $cols['cb'],
            'title'        => __( 'Publisher Name', 'bookshare' ),
            'bs_logo'      => __( 'Logo', 'bookshare' ),
            'bs_city'      => __( 'City', 'bookshare' ),
            'bs_country'   => __( 'Country', 'bookshare' ),
            'bs_founded'   => __( 'Founded', 'bookshare' ),
            'bs_email'     => __( 'Email', 'bookshare' ),
            'bs_pub_books' => __( 'Books', 'bookshare' ),
            'date'         => __( 'Date', 'bookshare' ),
        ];
    }

    public static function publisher_column_data( string $col, int $post_id ): void {
        switch ( $col ) {
            case 'bs_logo':
                $url = get_post_meta( $post_id, 'bs_logo_url', true );
                if ( $url ) echo '<img src="' . esc_url( $url ) . '" style="max-height:36px;max-width:80px;object-fit:contain">';
                else echo '🏢';
                break;
            case 'bs_city':
                echo esc_html( get_post_meta( $post_id, 'bs_city', true ) ?: '—' );
                break;
            case 'bs_country':
                echo esc_html( get_post_meta( $post_id, 'bs_country', true ) ?: '—' );
                break;
            case 'bs_founded':
                echo esc_html( get_post_meta( $post_id, 'bs_founded_year', true ) ?: '—' );
                break;
            case 'bs_email':
                $email = get_post_meta( $post_id, 'bs_email', true );
                echo $email ? '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>' : '—';
                break;
            case 'bs_pub_books':
                $db_id = (int) get_post_meta( $post_id, 'bs_db_id', true );
                if ( $db_id ) {
                    global $wpdb;
                    $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}bs_books WHERE publisher_id = %d", $db_id ) );
                    echo '<strong style="color:#5B5EDE">' . intval( $count ) . '</strong>';
                } else echo '—';
                break;
        }
    }

    // ── Admin Head Styles ────────────────────────────────────────────────────
    public static function admin_head_styles(): void {
        $screen = get_current_screen();
        if ( ! $screen ) return;
        if ( ! in_array( $screen->post_type, [ self::AUTHOR_CPT, self::PUBLISHER_CPT ], true ) ) return;
        ?>
        <style>
        /* BookCircle CPT Meta Box Styles */
        .bs-metabox-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            padding: 4px 0;
        }
        .bs-meta-row { display: flex; flex-direction: column; gap: 5px; }
        .bs-meta-full { grid-column: 1 / -1; }
        .bs-meta-label {
            font-size: 12px;
            font-weight: 700;
            color: #1E1E2E;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .bs-meta-input {
            padding: 8px 12px;
            border: 1.5px solid #E5E7F0;
            border-radius: 7px;
            font-size: 14px;
            font-family: inherit;
            background: #F8F9FF;
            transition: border-color 0.15s, box-shadow 0.15s;
            width: 100%;
        }
        .bs-meta-input:focus {
            outline: none;
            border-color: #5B5EDE;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(91,94,222,0.1);
        }
        .bs-meta-textarea {
            padding: 8px 12px;
            border: 1.5px solid #E5E7F0;
            border-radius: 7px;
            font-size: 14px;
            font-family: inherit;
            background: #F8F9FF;
            resize: vertical;
            width: 100%;
        }
        .bs-meta-textarea:focus {
            outline: none;
            border-color: #5B5EDE;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(91,94,222,0.1);
        }
        #bs_author_details .inside,
        #bs_publisher_details .inside,
        #bs_author_social .inside,
        #bs_publisher_contact .inside {
            padding: 16px;
        }
        #bs_author_books .inside,
        #bs_publisher_books .inside {
            padding: 0;
        }
        .bs-cpt-book-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .bs-cpt-book-table th {
            text-align: left;
            padding: 10px 14px;
            background: #F8F9FF;
            border-bottom: 2px solid #E5E7F0;
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 700;
            color: #6B7280;
            letter-spacing: 0.5px;
        }
        .bs-cpt-book-table td {
            padding: 10px 14px;
            border-bottom: 1px solid #E5E7F0;
            vertical-align: middle;
        }
        .bs-cpt-book-table tr:last-child td { border-bottom: none; }
        .bs-cpt-book-table tr:hover td { background: #EEF0FF; }
        code.bs-code {
            background: #EEF0FF;
            color: #5B5EDE;
            padding: 2px 7px;
            border-radius: 5px;
            font-weight: 700;
            letter-spacing: 1px;
            font-size: 12px;
        }
        /* Column widths on list table */
        .column-bs_photo,
        .column-bs_logo    { width: 60px; }
        .column-bs_books,
        .column-bs_pub_books { width: 60px; text-align: center; }
        .column-bs_founded { width: 80px; }
        </style>
        <?php
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private static function get_meta( int $post_id, array $keys ): array {
        $out = [];
        foreach ( $keys as $key ) {
            $out[ $key ] = (string) get_post_meta( $post_id, $key, true );
        }
        return $out;
    }

    private static function sanitize( $value, string $type ): string {
        return match ( $type ) {
            'email'    => sanitize_email( $value ),
            'url'      => esc_url_raw( $value ),
            'int'      => (string) intval( $value ),
            'textarea' => sanitize_textarea_field( $value ),
            default    => sanitize_text_field( $value ),
        };
    }
}
