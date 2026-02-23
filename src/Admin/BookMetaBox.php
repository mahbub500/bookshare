<?php
namespace BookShare\Admin;

/**
 * Adds meta boxes to the bs_book edit screen:
 * - Cover image (uses WP featured image)
 * - Unique Code (auto-generated)
 * - ISBN
 * - Genre
 * - Author (select from bs_author CPT)
 * - Publisher (select from bs_publisher CPT)
 * - Publication Year
 * - Language
 * - Pages
 */
class BookMetaBox {

    public function __construct() {
        add_action( 'add_meta_boxes',    [ $this, 'register'  ] );
        add_action( 'save_post_bs_book', [ $this, 'save'      ] );
        add_action( 'admin_head',        [ $this, 'inline_css'] );
        add_filter( 'manage_bs_book_posts_columns',       [ $this, 'add_columns'  ] );
        add_action( 'manage_bs_book_posts_custom_column', [ $this, 'render_column'], 10, 2 );
    }

    public function register(): void {
        add_meta_box(
            'bs_book_details',
            '📚 Book Details',
            [ $this, 'render' ],
            'bs_book',
            'normal',
            'high'
        );
    }

    public function render( \WP_Post $post ): void {
        wp_nonce_field( 'bs_save_book', 'bs_book_nonce' );

        $unique_code = get_post_meta( $post->ID, '_bs_unique_code',  true );
        $isbn        = get_post_meta( $post->ID, '_bs_isbn',         true );
        $genre       = get_post_meta( $post->ID, '_bs_genre',        true );
        $author_id   = get_post_meta( $post->ID, '_bs_author_id',    true );
        $pub_id      = get_post_meta( $post->ID, '_bs_publisher_id', true );
        $pub_year    = get_post_meta( $post->ID, '_bs_pub_year',     true );
        $language    = get_post_meta( $post->ID, '_bs_language',     true );
        $pages       = get_post_meta( $post->ID, '_bs_pages',        true );

        // Auto-generate unique code if empty
        if ( empty( $unique_code ) ) {
            $unique_code = $this->generate_code( $post->post_title );
        }

        // Fetch all authors and publishers
        $authors    = get_posts( [ 'post_type' => 'bs_author',    'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
        $publishers = get_posts( [ 'post_type' => 'bs_publisher', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );

        $genres = [ 'Fiction','Non-Fiction','Science','History','Fantasy','Sci-Fi','Romance','Mystery',
                    'Thriller','Biography','Self-Help','Finance','Technology','Philosophy','Religion','Children' ];
        ?>
        <div class="bs-meta-grid">

            <div class="bs-meta-row bs-meta-row--full">
                <label>Unique Book Code <span class="bs-required">*</span></label>
                <div style="display:flex;gap:.5rem;align-items:center">
                    <input type="text" name="bs_unique_code" id="bs_unique_code"
                           value="<?php echo esc_attr( $unique_code ); ?>"
                           class="bs-input" style="text-transform:uppercase;font-family:monospace;font-size:1rem;letter-spacing:.1em;max-width:200px"
                           readonly>
                    <button type="button" class="button" onclick="bsRegenCode()">↻ Regenerate</button>
                    <span style="color:#718096;font-size:.82rem">Readers search by this code to find your book</span>
                </div>
            </div>

            <div class="bs-meta-row">
                <label for="bs_author_id">Author <span class="bs-required">*</span></label>
                <select name="bs_author_id" id="bs_author_id" class="bs-input">
                    <option value="">— Select Author —</option>
                    <?php foreach ( $authors as $a ) : ?>
                        <option value="<?php echo $a->ID; ?>" <?php selected( $author_id, $a->ID ); ?>>
                            <?php echo esc_html( $a->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=bs_author' ) ); ?>" target="_blank" class="bs-add-new">+ Add New Author</a>
            </div>

            <div class="bs-meta-row">
                <label for="bs_publisher_id">Publisher</label>
                <select name="bs_publisher_id" id="bs_publisher_id" class="bs-input">
                    <option value="">— Select Publisher —</option>
                    <?php foreach ( $publishers as $p ) : ?>
                        <option value="<?php echo $p->ID; ?>" <?php selected( $pub_id, $p->ID ); ?>>
                            <?php echo esc_html( $p->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=bs_publisher' ) ); ?>" target="_blank" class="bs-add-new">+ Add New Publisher</a>
            </div>

            <div class="bs-meta-row">
                <label for="bs_isbn">ISBN</label>
                <input type="text" name="bs_isbn" id="bs_isbn" value="<?php echo esc_attr( $isbn ); ?>"
                       class="bs-input" placeholder="e.g. 978-0-00-000000-0">
            </div>

            <div class="bs-meta-row">
                <label for="bs_genre">Genre</label>
                <select name="bs_genre" id="bs_genre" class="bs-input">
                    <option value="">— Select Genre —</option>
                    <?php foreach ( $genres as $g ) : ?>
                        <option value="<?php echo esc_attr($g); ?>" <?php selected( $genre, $g ); ?>><?php echo esc_html($g); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="bs-meta-row">
                <label for="bs_pub_year">Publication Year</label>
                <input type="number" name="bs_pub_year" id="bs_pub_year"
                       value="<?php echo esc_attr( $pub_year ); ?>"
                       class="bs-input" min="1000" max="<?php echo date('Y'); ?>" placeholder="<?php echo date('Y'); ?>">
            </div>

            <div class="bs-meta-row">
                <label for="bs_language">Language</label>
                <input type="text" name="bs_language" id="bs_language"
                       value="<?php echo esc_attr( $language ?: 'English' ); ?>"
                       class="bs-input" placeholder="English">
            </div>

            <div class="bs-meta-row">
                <label for="bs_pages">Pages</label>
                <input type="number" name="bs_pages" id="bs_pages"
                       value="<?php echo esc_attr( $pages ); ?>"
                       class="bs-input" min="1" placeholder="e.g. 320">
            </div>

            <div class="bs-meta-row bs-meta-row--full">
                <p style="color:#718096;font-size:.85rem;margin-top:.5rem">
                    💡 <strong>Cover Image:</strong> Use the <em>Featured Image</em> box on the right side of this page to upload the book cover.
                </p>
            </div>

        </div>

        <script>
        function bsRegenCode() {
            const title = jQuery('#title').val() || 'BOOK';
            const prefix = title.replace(/[^a-zA-Z]/g,'').toUpperCase().slice(0,4).padEnd(4,'X');
            const rand = Math.random().toString(36).slice(-5).toUpperCase();
            jQuery('#bs_unique_code').val(prefix + rand);
        }
        </script>
        <?php
    }

    public function save( int $post_id ): void {
        if ( ! isset( $_POST['bs_book_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['bs_book_nonce'], 'bs_save_book' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        $fields = [
            '_bs_unique_code'  => [ 'bs_unique_code',  'sanitize_text_field' ],
            '_bs_isbn'         => [ 'bs_isbn',          'sanitize_text_field' ],
            '_bs_genre'        => [ 'bs_genre',         'sanitize_text_field' ],
            '_bs_author_id'    => [ 'bs_author_id',     'intval'              ],
            '_bs_publisher_id' => [ 'bs_publisher_id',  'intval'              ],
            '_bs_pub_year'     => [ 'bs_pub_year',       'intval'             ],
            '_bs_language'     => [ 'bs_language',      'sanitize_text_field' ],
            '_bs_pages'        => [ 'bs_pages',          'intval'             ],
        ];

        foreach ( $fields as $meta_key => [ $field, $sanitize ] ) {
            $value = isset( $_POST[ $field ] ) ? $sanitize( $_POST[ $field ] ) : '';
            update_post_meta( $post_id, $meta_key, $value );
        }

        // Auto-generate unique_code if still empty
        $code = get_post_meta( $post_id, '_bs_unique_code', true );
        if ( empty( $code ) ) {
            $code = $this->generate_code( get_the_title( $post_id ) );
            update_post_meta( $post_id, '_bs_unique_code', $code );
        }
    }

    /** Add custom columns to books list table */
    public function add_columns( array $cols ): array {
        $new = [];
        foreach ( $cols as $k => $v ) {
            $new[$k] = $v;
            if ( $k === 'title' ) {
                $new['bs_code']      = 'Code';
                $new['bs_author']    = 'Author';
                $new['bs_publisher'] = 'Publisher';
                $new['bs_genre']     = 'Genre';
            }
        }
        return $new;
    }

    public function render_column( string $col, int $post_id ): void {
        switch ( $col ) {
            case 'bs_code':
                $code = get_post_meta( $post_id, '_bs_unique_code', true );
                echo '<code style="background:#f5f0e8;padding:.2rem .5rem;border-radius:4px;font-size:.8rem">' . esc_html( $code ) . '</code>';
                break;
            case 'bs_author':
                $author_id = get_post_meta( $post_id, '_bs_author_id', true );
                echo $author_id ? esc_html( get_the_title( $author_id ) ) : '—';
                break;
            case 'bs_publisher':
                $pub_id = get_post_meta( $post_id, '_bs_publisher_id', true );
                echo $pub_id ? esc_html( get_the_title( $pub_id ) ) : '—';
                break;
            case 'bs_genre':
                echo esc_html( get_post_meta( $post_id, '_bs_genre', true ) ?: '—' );
                break;
        }
    }

    public function inline_css(): void {
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'bs_book' ) return;
        ?>
        <style>
        .bs-meta-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem 1.5rem; padding:.5rem 0; }
        .bs-meta-row { display:flex; flex-direction:column; gap:.4rem; }
        .bs-meta-row--full { grid-column:1/-1; }
        .bs-meta-row label { font-weight:600; font-size:.85rem; color:#374151; }
        .bs-required { color:#e53935; }
        .bs-input { padding:.5rem .75rem; border:1px solid #d1d5db; border-radius:6px; font-size:.9rem; width:100%; }
        .bs-input:focus { border-color:#3b82f6; outline:none; box-shadow:0 0 0 2px rgba(59,130,246,.15); }
        .bs-add-new { font-size:.78rem; color:#3b82f6; margin-top:.25rem; display:inline-block; }
        </style>
        <?php
    }

    private function generate_code( string $title ): string {
        $prefix = strtoupper( preg_replace( '/[^a-zA-Z]/', '', $title ) );
        $prefix = str_pad( substr( $prefix, 0, 4 ), 4, 'X' );
        return $prefix . strtoupper( substr( uniqid(), -5 ) );
    }
}