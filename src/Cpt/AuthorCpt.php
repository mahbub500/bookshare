<?php
namespace BookShare\Cpt;

defined( 'ABSPATH' ) || exit;

class AuthorCpt {

    const CPT = 'bs_author';

    const FIELDS = [
        'bs_email'            => [ 'label' => 'Email',              'type' => 'email'    ],
        'bs_website'          => [ 'label' => 'Website',            'type' => 'url'      ],
        'bs_birth_date'       => [ 'label' => 'Birth Date',         'type' => 'date'     ],
        'bs_nationality'      => [ 'label' => 'Nationality',        'type' => 'text'     ],
        'bs_bio'              => [ 'label' => 'Biography',          'type' => 'textarea' ],
        'bs_social_twitter'   => [ 'label' => 'Twitter / X Handle', 'type' => 'text'     ],
        'bs_social_instagram' => [ 'label' => 'Instagram Handle',   'type' => 'text'     ],
        'bs_social_facebook'  => [ 'label' => 'Facebook URL',       'type' => 'url'      ],
    ];

    public static function register(): void {
        add_action( 'init',           [ self::class, 'register_cpt'   ] );
        add_action( 'init',           [ self::class, 'register_meta'  ] );
        add_action( 'add_meta_boxes', [ self::class, 'add_meta_boxes' ] );
        add_action( 'save_post',      [ self::class, 'save_meta'      ], 10, 2 );

        // Single post profile image display
        add_filter( 'the_content', [ self::class, 'prepend_profile_card' ] );

        add_filter( 'manage_' . self::CPT . '_posts_columns',       [ self::class, 'columns'     ] );
        add_action( 'manage_' . self::CPT . '_posts_custom_column', [ self::class, 'column_data' ], 10, 2 );
    }

    // ── Register CPT ──────────────────────────────────────────────────────────
    public static function register_cpt(): void {
        register_post_type( self::CPT, [
            'labels' => [
                'name'               => __( 'Book Authors',        'bookshare' ),
                'singular_name'      => __( 'Book Author',         'bookshare' ),
                'add_new'            => __( 'Add Author',           'bookshare' ),
                'add_new_item'       => __( 'Add New Book Author',  'bookshare' ),
                'edit_item'          => __( 'Edit Book Author',     'bookshare' ),
                'new_item'           => __( 'New Book Author',      'bookshare' ),
                'view_item'          => __( 'View Book Author',     'bookshare' ),
                'search_items'       => __( 'Search Authors',       'bookshare' ),
                'not_found'          => __( 'No authors found.',    'bookshare' ),
                'not_found_in_trash' => __( 'No authors in trash.', 'bookshare' ),
                'menu_name'          => __( 'Book Authors',         'bookshare' ),
                'all_items'          => __( 'All Authors',          'bookshare' ),
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
    }

    // ── Register Meta ─────────────────────────────────────────────────────────
    public static function register_meta(): void {
        $auth_cb = fn() => current_user_can( 'edit_posts' );

        $keys = [
            'bs_email', 'bs_website', 'bs_birth_date', 'bs_nationality',
            'bs_bio', 'bs_social_twitter', 'bs_social_instagram', 'bs_social_facebook',
        ];

        foreach ( $keys as $key ) {
            register_post_meta( self::CPT, $key, [
                'type'          => 'string',
                'single'        => true,
                'show_in_rest'  => true,
                'auth_callback' => $auth_cb,
            ] );
        }
    }

    // ── Meta Boxes ────────────────────────────────────────────────────────────
    public static function add_meta_boxes(): void {
        add_meta_box(
            'bs_author_profile',
            '🖼️ Profile Image',
            [ self::class, 'render_profile_hint_box' ],
            self::CPT, 'side', 'high'
        );
        add_meta_box(
            'bs_author_details',
            '✍️ Author Details',
            [ self::class, 'render_details_box' ],
            self::CPT, 'normal', 'high'
        );
        add_meta_box(
            'bs_author_social',
            '🔗 Social Media',
            [ self::class, 'render_social_box' ],
            self::CPT, 'side', 'default'
        );
        add_meta_box(
            'bs_author_books',
            '📚 Books by this Author',
            [ self::class, 'render_books_box' ],
            self::CPT, 'normal', 'low'
        );
    }

    // ── Profile Image Hint Box (sidebar) ──────────────────────────────────────
    public static function render_profile_hint_box( \WP_Post $post ): void {
        $thumb_id = get_post_thumbnail_id( $post->ID );
        ?>
        <div style="text-align:center;padding:8px 0">
            <?php if ( $thumb_id ) : ?>
                <?php echo get_the_post_thumbnail( $post->ID, [ 120, 120 ], [
                    'style' => 'width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid #E5E7EB;display:block;margin:0 auto 10px'
                ] ); ?>
                <p style="font-size:12px;color:#6B7280;margin:0">✅ Profile image is set.</p>
            <?php else : ?>
                <div style="width:100px;height:100px;border-radius:50%;background:#F3F4F6;border:3px dashed #D1D5DB;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;font-size:32px">
                    👤
                </div>
                <p style="font-size:12px;color:#6B7280;margin:0">Set the <strong>Featured Image</strong> below as the author's profile photo.</p>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── Author Details Box ────────────────────────────────────────────────────
    public static function render_details_box( \WP_Post $post ): void {
        wp_nonce_field( 'bs_author_meta', 'bs_author_nonce' );
        $m = self::get_meta( $post->ID, array_keys( self::FIELDS ) );
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
                <label class="bs-meta-label">Biography</label>
                <textarea name="bs_bio" class="bs-meta-textarea" rows="5"
                    placeholder="A short biography of the author…"><?php echo esc_textarea( $m['bs_bio'] ); ?></textarea>
            </div>
        </div>
        <?php
    }

    // ── Social Media Box ──────────────────────────────────────────────────────
    public static function render_social_box( \WP_Post $post ): void {
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

    // ── Books Box ─────────────────────────────────────────────────────────────
    public static function render_books_box( \WP_Post $post ): void {
        $books = get_posts( [
            'post_type'      => 'bs_book',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => [ [ 'key' => 'bs_author_id', 'value' => $post->ID, 'type' => 'NUMERIC' ] ],
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
                '<tr><td><strong><a href="%s">%s</a></strong></td><td><code class="bs-code">%s</code></td><td>%s</td><td>%s</td></tr>',
                esc_url( $edit ?? '#' ),
                esc_html( $book->post_title ),
                esc_html( $code  ?: '—' ),
                esc_html( $genre ?: '—' ),
                esc_html( $year  ?: '—' )
            );
        }

        echo '</tbody></table>';
    }

    // ── Profile Card on Single Post ───────────────────────────────────────────
    public static function prepend_profile_card( string $content ): string {
        if ( ! is_singular( self::CPT ) || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        global $post;

        $name        = get_the_title( $post );
        $nationality = get_post_meta( $post->ID, 'bs_nationality', true );
        $birth_date  = get_post_meta( $post->ID, 'bs_birth_date',  true );
        $email       = get_post_meta( $post->ID, 'bs_email',       true );
        $website     = get_post_meta( $post->ID, 'bs_website',     true );
        $twitter     = get_post_meta( $post->ID, 'bs_social_twitter',   true );
        $instagram   = get_post_meta( $post->ID, 'bs_social_instagram', true );
        $facebook    = get_post_meta( $post->ID, 'bs_social_facebook',  true );
        $thumb_id    = get_post_thumbnail_id( $post->ID );

        // Profile image
        if ( $thumb_id ) {
            $avatar = get_the_post_thumbnail( $post->ID, [ 120, 120 ], [
                'style' => 'width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid #E5E7EB'
            ] );
        } else {
            $avatar = '<div style="width:120px;height:120px;border-radius:50%;background:#F3F4F6;border:3px solid #E5E7EB;display:flex;align-items:center;justify-content:center;font-size:48px">👤</div>';
        }

        // Meta rows
        $meta_rows = '';
        if ( $nationality ) $meta_rows .= self::profile_row( '🌍', 'Nationality', esc_html( $nationality ) );
        if ( $birth_date  ) $meta_rows .= self::profile_row( '🎂', 'Born', esc_html( date_format( date_create( $birth_date ), 'F j, Y' ) ) );
        if ( $email       ) $meta_rows .= self::profile_row( '✉️', 'Email', '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' );
        if ( $website     ) $meta_rows .= self::profile_row( '🌐', 'Website', '<a href="' . esc_url( $website ) . '" target="_blank" rel="noopener">' . esc_html( $website ) . '</a>' );

        // Social links
        $socials = '';
        if ( $twitter   ) $socials .= '<a href="https://twitter.com/' . esc_attr( $twitter ) . '" target="_blank" rel="noopener" style="text-decoration:none;font-size:20px" title="Twitter / X">𝕏</a>';
        if ( $instagram ) $socials .= '<a href="https://instagram.com/' . esc_attr( $instagram ) . '" target="_blank" rel="noopener" style="text-decoration:none;font-size:20px" title="Instagram">📷</a>';
        if ( $facebook  ) $socials .= '<a href="' . esc_url( $facebook ) . '" target="_blank" rel="noopener" style="text-decoration:none;font-size:20px" title="Facebook">📘</a>';

        if ( $socials ) {
            $meta_rows .= '<div style="display:flex;gap:12px;margin-top:12px;padding-top:12px;border-top:1px solid #F3F4F6">' . $socials . '</div>';
        }

        $card = '
        <div style="display:flex;gap:24px;align-items:flex-start;background:#ffffff;border:1px solid #E5E7EB;border-radius:14px;padding:24px;margin-bottom:28px;box-shadow:0 1px 4px rgba(0,0,0,0.06)">
            <div style="flex-shrink:0">' . $avatar . '</div>
            <div style="flex:1;min-width:0">
                <h2 style="margin:0 0 4px;font-size:22px;font-weight:700;color:#111827">' . esc_html( $name ) . '</h2>
                <p style="margin:0 0 14px;font-size:13px;color:#6B7280;text-transform:uppercase;letter-spacing:.05em">Author</p>
                ' . $meta_rows . '
            </div>
        </div>';

        return $card . $content;
    }

    // ── Save Meta ─────────────────────────────────────────────────────────────
    public static function save_meta( int $post_id, \WP_Post $post ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )  return;
        if ( wp_is_post_revision( $post_id ) )                 return;
        if ( ! current_user_can( 'edit_post', $post_id ) )    return;
        if ( $post->post_type !== self::CPT )                  return;
        if ( ! isset( $_POST['bs_author_nonce'] ) )            return;
        if ( ! wp_verify_nonce( $_POST['bs_author_nonce'], 'bs_author_meta' ) ) return;

        $fields = [
            'bs_email'            => 'email',
            'bs_website'          => 'url',
            'bs_birth_date'       => 'text',
            'bs_nationality'      => 'text',
            'bs_bio'              => 'textarea',
            'bs_social_twitter'   => 'text',
            'bs_social_instagram' => 'text',
            'bs_social_facebook'  => 'url',
        ];

        foreach ( $fields as $key => $type ) {
            update_post_meta( $post_id, $key, self::sanitize( $_POST[ $key ] ?? '', $type ) );
        }
    }

    // ── List-table Columns ────────────────────────────────────────────────────
    public static function columns( array $cols ): array {
        return [
            'cb'             => $cols['cb'],
            'title'          => __( 'Author Name', 'bookshare' ),
            'bs_profile'     => __( 'Photo',       'bookshare' ),
            'bs_nationality' => __( 'Nationality', 'bookshare' ),
            'bs_email'       => __( 'Email',       'bookshare' ),
            'bs_books'       => __( 'Books',       'bookshare' ),
            'bs_socials'     => __( 'Socials',     'bookshare' ),
            'date'           => __( 'Date',        'bookshare' ),
        ];
    }

    public static function column_data( string $col, int $post_id ): void {
        switch ( $col ) {
            case 'bs_profile':
                $thumb = get_the_post_thumbnail( $post_id, [ 50, 50 ] );
                echo $thumb
                    ? '<span style="display:inline-block;width:40px;height:40px;border-radius:50%;overflow:hidden;line-height:0">' . $thumb . '</span>'
                    : '<span style="display:inline-flex;width:40px;height:40px;border-radius:50%;background:#F3F4F6;align-items:center;justify-content:center;font-size:20px">👤</span>';
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
                $count = (int) ( new \WP_Query( [
                    'post_type'      => 'bs_book',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'no_found_rows'  => false,
                    'meta_query'     => [ [ 'key' => 'bs_author_id', 'value' => $post_id, 'type' => 'NUMERIC' ] ],
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

    // ── Public Helper ─────────────────────────────────────────────────────────
    public static function get_profile_image_url( int $post_id, string $size = 'thumbnail' ): string {
        $thumb_id = get_post_thumbnail_id( $post_id );
        if ( ! $thumb_id ) return '';
        $src = wp_get_attachment_image_src( $thumb_id, $size );
        return $src ? (string) $src[0] : '';
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private static function profile_row( string $icon, string $label, string $value ): string {
        return '<div style="display:flex;align-items:baseline;gap:8px;margin-bottom:7px;font-size:14px">
            <span style="font-size:15px">' . $icon . '</span>
            <span style="color:#6B7280;min-width:80px">' . esc_html( $label ) . ':</span>
            <span style="color:#111827">' . $value . '</span>
        </div>';
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
            'textarea' => sanitize_textarea_field( (string) $value ),
            default    => sanitize_text_field( (string) $value ),
        };
    }
}