<?php
namespace BookShare\Cpt;

defined( 'ABSPATH' ) || exit;

class PublisherCpt {

    const CPT = 'bs_publisher';

    const FIELDS = [
        'bs_email'        => [ 'label' => 'Email',        'type' => 'email'    ],
        'bs_phone'        => [ 'label' => 'Phone',        'type' => 'tel'      ],
        'bs_website'      => [ 'label' => 'Website',      'type' => 'url'      ],
        'bs_address'      => [ 'label' => 'Address',      'type' => 'textarea' ],
        'bs_city'         => [ 'label' => 'City',         'type' => 'text'     ],
        'bs_country'      => [ 'label' => 'Country',      'type' => 'text'     ],
        'bs_founded_year' => [ 'label' => 'Founded Year', 'type' => 'number'   ],
        'bs_description'  => [ 'label' => 'Description',  'type' => 'textarea' ],
    ];

    public static function register(): void {
        add_action( 'init',           [ self::class, 'register_cpt'   ] );
        add_action( 'init',           [ self::class, 'register_meta'  ] );
        add_action( 'add_meta_boxes', [ self::class, 'add_meta_boxes' ] );
        add_action( 'save_post',      [ self::class, 'save_meta'      ], 10, 2 );

        add_filter( 'manage_' . self::CPT . '_posts_columns',       [ self::class, 'columns'     ] );
        add_action( 'manage_' . self::CPT . '_posts_custom_column', [ self::class, 'column_data' ], 10, 2 );
    }

    // ── Register CPT ──────────────────────────────────────────────────────────
    public static function register_cpt(): void {
        register_post_type( self::CPT, [
            'labels' => [
                'name'               => __( 'Book Publishers',         'bookshare' ),
                'singular_name'      => __( 'Book Publisher',          'bookshare' ),
                'add_new'            => __( 'Add Publisher',            'bookshare' ),
                'add_new_item'       => __( 'Add New Book Publisher',   'bookshare' ),
                'edit_item'          => __( 'Edit Book Publisher',      'bookshare' ),
                'new_item'           => __( 'New Book Publisher',       'bookshare' ),
                'view_item'          => __( 'View Book Publisher',      'bookshare' ),
                'search_items'       => __( 'Search Publishers',        'bookshare' ),
                'not_found'          => __( 'No publishers found.',     'bookshare' ),
                'not_found_in_trash' => __( 'No publishers in trash.',  'bookshare' ),
                'menu_name'          => __( 'Book Publishers',          'bookshare' ),
                'all_items'          => __( 'All Publishers',           'bookshare' ),
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

    // ── Register Meta ─────────────────────────────────────────────────────────
    public static function register_meta(): void {
        $auth_cb = fn() => current_user_can( 'edit_posts' );

        $string_keys = [
            'bs_email', 'bs_phone', 'bs_website', 'bs_address',
            'bs_city', 'bs_country', 'bs_description',
        ];

        foreach ( $string_keys as $key ) {
            register_post_meta( self::CPT, $key, [
                'type'          => 'string',
                'single'        => true,
                'show_in_rest'  => true,
                'auth_callback' => $auth_cb,
            ] );
        }

        register_post_meta( self::CPT, 'bs_founded_year', [
            'type'          => 'integer',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => $auth_cb,
        ] );
    }

    // ── Meta Boxes ────────────────────────────────────────────────────────────
    public static function add_meta_boxes(): void {
        add_meta_box(
            'bs_publisher_details',
            '🏢 Publisher Details',
            [ self::class, 'render_details_box' ],
            self::CPT, 'normal', 'high'
        );
        add_meta_box(
            'bs_publisher_contact',
            '📞 Contact & Location',
            [ self::class, 'render_contact_box' ],
            self::CPT, 'side', 'default'
        );
        add_meta_box(
            'bs_publisher_books',
            '📚 Books by this Publisher',
            [ self::class, 'render_books_box' ],
            self::CPT, 'normal', 'low'
        );
    }

    public static function render_details_box( \WP_Post $post ): void {
        wp_nonce_field( 'bs_publisher_meta', 'bs_publisher_nonce' );
        $m = self::get_meta( $post->ID, array_keys( self::FIELDS ) );
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
                <label class="bs-meta-label">Description</label>
                <textarea name="bs_description" class="bs-meta-textarea" rows="4"
                    placeholder="About this publisher…"><?php echo esc_textarea( $m['bs_description'] ); ?></textarea>
            </div>
        </div>
        <p style="margin-top:12px;font-size:12px;color:#6B7280">
            💡 Set the <strong>Featured Image</strong> (top-right panel) to use as this publisher's logo.
        </p>
        <?php
    }

    public static function render_contact_box( \WP_Post $post ): void {
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

    public static function render_books_box( \WP_Post $post ): void {
        $books = get_posts( [
            'post_type'      => 'bs_book',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => [ [ 'key' => 'bs_publisher_id', 'value' => $post->ID, 'type' => 'NUMERIC' ] ],
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
                '<tr><td><strong><a href="%s">%s</a></strong></td><td>%s</td><td><code class="bs-code">%s</code></td><td>%s</td></tr>',
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
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )  return;
        if ( wp_is_post_revision( $post_id ) )                 return;
        if ( ! current_user_can( 'edit_post', $post_id ) )    return;
        if ( $post->post_type !== self::CPT )                  return;
        if ( ! isset( $_POST['bs_publisher_nonce'] ) )         return;
        if ( ! wp_verify_nonce( $_POST['bs_publisher_nonce'], 'bs_publisher_meta' ) ) return;

        $fields = [
            'bs_email'        => 'email',
            'bs_phone'        => 'text',
            'bs_website'      => 'url',
            'bs_address'      => 'textarea',
            'bs_city'         => 'text',
            'bs_country'      => 'text',
            'bs_founded_year' => 'int',
            'bs_description'  => 'textarea',
        ];

        foreach ( $fields as $key => $type ) {
            update_post_meta( $post_id, $key, self::sanitize( $_POST[ $key ] ?? '', $type ) );
        }
    }

    // ── List-table Columns ────────────────────────────────────────────────────
    public static function columns( array $cols ): array {
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

    public static function column_data( string $col, int $post_id ): void {
        switch ( $col ) {
            case 'bs_logo':
                $thumb = get_the_post_thumbnail( $post_id, [ 60, 60 ] );
                echo $thumb
                    ? '<span style="display:inline-block;max-height:36px;max-width:80px;overflow:hidden;line-height:0">' . $thumb . '</span>'
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
                    'meta_query'     => [ [ 'key' => 'bs_publisher_id', 'value' => $post_id, 'type' => 'NUMERIC' ] ],
                ] ) )->found_posts;
                echo '<strong style="color:#5B5EDE">' . $count . '</strong>';
                break;
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    public static function get_logo_url( int $post_id, string $size = 'thumbnail' ): string {
        $thumb_id = get_post_thumbnail_id( $post_id );
        if ( ! $thumb_id ) return '';
        $src = wp_get_attachment_image_src( $thumb_id, $size );
        return $src ? (string) $src[0] : '';
    }

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