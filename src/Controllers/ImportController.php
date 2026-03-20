<?php
namespace BookShare\Controllers;

defined( 'ABSPATH' ) || exit;

/**
 * ImportController — Rokomari book importer
 *
 * REST:       POST /wp-json/bookshare/v1/import/rokomari
 * Admin page: BookCircle → 📥 Import Books
 *
 * HOW TO WIRE UP — one line in Plugin.php → init_hooks():
 *
 *   ImportController::init();
 */
class ImportController {

    // =========================================================================
    // BOOT
    // =========================================================================

    public static function init(): void {
        add_action( 'rest_api_init',         [ self::class, 'register_routes' ] );
        add_action( 'admin_menu',            [ self::class, 'add_menu'        ], 20 );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets'  ] );
    }

    // =========================================================================
    // ADMIN MENU
    // =========================================================================

    public static function add_menu(): void {
        add_submenu_page(
            'bookshare',
            __( 'Import Books', 'bookshare' ),
            __( '📥 Import Books', 'bookshare' ),
            'manage_options',
            'bookshare-import',
            [ self::class, 'import_page' ]
        );
    }

    // =========================================================================
    // ASSETS  — separate CSS + JS files, only on our page
    // =========================================================================

    public static function enqueue_assets( string $hook ): void {
        if ( $hook !== 'bookshare_page_bookshare-import' ) {
            return;
        }

        wp_enqueue_style(
            'bs-importer',
            BS_URL . 'assets/css/importer.css',
            [],
            BS_VERSION
        );

        wp_enqueue_script(
            'bs-importer',
            BS_URL . 'assets/js/importer.js',
            [ 'jquery' ],
            BS_VERSION,
            true
        );

        wp_localize_script( 'bs-importer', 'BSImporter', [
            'endpoint' => rest_url( 'bookshare/v1/import/rokomari' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'edit_url' => admin_url( 'post.php' ),
        ] );
    }

    // =========================================================================
    // REST ROUTE
    // =========================================================================

    public static function register_routes(): void {
        register_rest_route( 'bookshare/v1', '/import/rokomari', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ self::class, 'handle_import' ],
            'permission_callback' => fn() => current_user_can( 'edit_posts' ),
        ] );
    }

    // =========================================================================
    // REST HANDLER
    // =========================================================================

    public static function handle_import( \WP_REST_Request $request ): \WP_REST_Response {
        $body = $request->get_json_params();
        $urls = $body['urls'] ?? [];

        if ( ! is_array( $urls ) ) {
            $urls = array_filter( array_map( 'trim', explode( "\n", (string) $urls ) ) );
        }

        $urls    = array_values( array_filter( array_map( 'esc_url_raw', (array) $urls ) ) );
        $results = [];

        foreach ( $urls as $url ) {
            $results[] = array_merge( [ 'url' => $url ], self::import_single( $url ) );
        }

        return new \WP_REST_Response( [ 'success' => true, 'results' => $results ], 200 );
    }

    // =========================================================================
    // IMPORT ONE BOOK
    // =========================================================================

    private static function import_single( string $url ): array {

        /* 1 ── Fetch ---------------------------------------------------------- */
        $response = wp_remote_get( $url, [
            'timeout'    => 25,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0 Safari/537.36',
            'headers'    => [
                'Accept'          => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Referer'         => 'https://www.rokomari.com/',
            ],
            'sslverify'  => false,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'status' => 'error', 'message' => $response->get_error_message() ];
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );
        if ( $http_code !== 200 ) {
            return [ 'status' => 'error', 'message' => "HTTP {$http_code} from Rokomari." ];
        }

        $html = wp_remote_retrieve_body( $response );
        if ( ! $html ) {
            return [ 'status' => 'error', 'message' => 'Empty response from Rokomari.' ];
        }

        /* 2 ── Parse ---------------------------------------------------------- */
        $data = self::parse( $html, $url );

        if ( empty( $data['title'] ) ) {
            return [ 'status' => 'error', 'message' => 'Could not find book title on page.' ];
        }

        /* 3 ── Duplicate check ----------------------------------------------- */
        if ( ! empty( $data['isbn'] ) ) {
            $dup = get_posts( [
                'post_type'      => 'bs_book',
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_key'       => 'bs_isbn',
                'meta_value'     => $data['isbn'],
                'no_found_rows'  => true,
            ] );
            if ( ! empty( $dup ) ) {
                return [
                    'status'  => 'skipped',
                    'message' => 'Already exists (ISBN match).',
                    'post_id' => $dup[0],
                    'data'    => $data,
                ];
            }
        }

        /* 4 ── Resolve / create ALL authors ----------------------------------- */
        // author_names is string[] — could be 1 or many
        $author_ids = [];
        foreach ( $data['author_names'] ?? [] as $name ) {
            $aid = self::get_or_create_author( $name );
            if ( $aid ) {
                $author_ids[] = $aid;
            }
        }

        /* 4b ── Resolve / create Publisher ------------------------------------ */
        $publisher_id = 0;
        if ( ! empty( $data['publisher_name'] ) ) {
            $publisher_id = self::get_or_create_publisher( $data['publisher_name'] );
        }

        /* 5 ── Create book post ----------------------------------------------- */
        $post_id = wp_insert_post( [
            'post_type'   => 'bs_book',
            'post_title'  => sanitize_text_field( $data['title'] ),
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ], true );

        if ( is_wp_error( $post_id ) ) {
            return [ 'status' => 'error', 'message' => $post_id->get_error_message() ];
        }

        /* 6 ── Unique code ---------------------------------------------------- */
        if ( ! get_post_meta( $post_id, 'bs_unique_code', true ) ) {
            update_post_meta( $post_id, 'bs_unique_code', \BookShare\Models\Book::generate_code() );
        }

        /* 7 ── Scalar meta ---------------------------------------------------- */
        update_post_meta( $post_id, 'bs_isbn',           sanitize_text_field( $data['isbn']            ?? '' ) );
        update_post_meta( $post_id, 'bs_language',       sanitize_text_field( $data['language']        ?? 'Bangla' ) );
        update_post_meta( $post_id, 'bs_description',    sanitize_textarea_field( $data['description'] ?? '' ) );
        update_post_meta( $post_id, 'bs_published_year', intval( $data['published_year']               ?? 0 ) );
        update_post_meta( $post_id, 'bs_pages',          intval( $data['pages']                        ?? 0 ) );

        /* 8 ── Author meta (multiple) ----------------------------------------- */
        if ( ! empty( $author_ids ) ) {
            // All author IDs comma-separated (used by BookCPT::hydrate)
            update_post_meta( $post_id, 'bs_author_ids', implode( ',', $author_ids ) );
            // First author only for backwards compat
            update_post_meta( $post_id, 'bs_author_id',  $author_ids[0] );
        }

        /* 9 ── Publisher meta ------------------------------------------------- */
        if ( $publisher_id ) {
            update_post_meta( $post_id, 'bs_publisher_id', $publisher_id );
        }

        /* 10 ── Cover image --------------------------------------------------- */
        if ( ! empty( $data['cover_url'] ) ) {
            $att_id = self::sideload_image( $data['cover_url'], $post_id, $data['title'] );
            if ( $att_id && ! is_wp_error( $att_id ) ) {
                set_post_thumbnail( $post_id, $att_id );
            }
        }

        /* 11 ── Category taxonomy --------------------------------------------- */
        if ( ! empty( $data['category'] ) ) {
            $cat_name = sanitize_text_field( $data['category'] );
            $term     = get_term_by( 'name', $cat_name, 'bs_book_category' );
            if ( ! $term ) {
                $ins     = wp_insert_term( $cat_name, 'bs_book_category' );
                $term_id = ! is_wp_error( $ins ) ? $ins['term_id'] : 0;
            } else {
                $term_id = $term->term_id;
            }
            if ( ! empty( $term_id ) ) {
                wp_set_post_terms( $post_id, [ $term_id ], 'bs_book_category' );
            }
        }

        return [
            'status'  => 'imported',
            'message' => 'Imported successfully.',
            'post_id' => $post_id,
            'data'    => $data,
        ];
    }

    // =========================================================================
    // HTML PARSER
    // =========================================================================

    /**
     * Parse a Rokomari product page.
     *
     * Returns:
     *   title          string
     *   author_names   string[]   one entry per author / editor <a> link
     *   publisher_name string
     *   cover_url      string
     *   category       string
     *   description    string
     *   isbn           string
     *   language       string
     *   published_year int
     *   pages          int
     *   price          float
     *   rokomari_id    string
     */
    private static function parse( string $html, string $page_url ): array {
        $prev = libxml_use_internal_errors( true );
        $doc  = new \DOMDocument();
        $doc->loadHTML( '<?xml encoding="UTF-8">' . $html );
        libxml_clear_errors();
        libxml_use_internal_errors( $prev );

        $xp   = new \DOMXPath( $doc );
        $data = [];

        // ── Hidden inputs ─────────────────────────────────────────────────────
        $hidden_map = [
            'js--product-en-name'       => '_title_en',
            'js--product-author-name'   => '_author_fallback',
            'js--product-img'           => '_img_filename',
            'js--product-id'            => 'rokomari_id',
            'js--product-price'         => 'price',
            'js--product-category-name' => 'category',
        ];
        foreach ( $hidden_map as $id => $key ) {
            $node = $xp->query( "//*[@id='{$id}']" );
            if ( $node && $node->length ) {
                $data[ $key ] = trim( $node->item(0)->getAttribute( 'value' ) );
            }
        }

        // ── Title ─────────────────────────────────────────────────────────────
        $h1 = $xp->query( '//div[contains(@class,"details-book-main-info__header")]//h1' );
        if ( $h1 && $h1->length ) {
            $clone = $h1->item(0)->cloneNode( true );
            // Remove format badge spans like (পেপারব্যাক)
            foreach ( iterator_to_array( $xp->query( './/span', $clone ) ) as $span ) {
                $span->parentNode->removeChild( $span );
            }
            $raw = trim( $clone->textContent );
            if ( $raw ) {
                $data['title'] = $raw;
            }
        }
        if ( empty( $data['title'] ) && ! empty( $data['_title_en'] ) ) {
            $data['title'] = $data['_title_en'];
        }

        // ── Authors — every <a> in .details-book-info__content-author ─────────
        //
        // Single author example (doc 7):
        //   <a href="/book/author/13863"> সাইফুর রহমান খান </a>
        //
        // Multiple authors example (doc 9):
        //   <a href="/book/author/84661"> শাইখ ড. তাওফিক চৌধুরি </a> ,
        //   <a href="/book/author/13927"> উস্তায আবুল হাসানাত কাসিম (সম্পাদক) </a> ,
        //   <a href="/book/author/73100"> মুরসালিন নিলয় (সম্পাদক) </a> ,
        //   <a href="/book/author/85682"> আব্দুল্লাহ আমান (সম্পাদক) </a>

        $author_links = $xp->query(
            '//p[contains(@class,"details-book-info__content-author")]//a'
        );

        $author_names = [];
        if ( $author_links && $author_links->length ) {
            foreach ( $author_links as $link ) {
                // Only follow links that point to /book/author/ paths
                $href = $link->getAttribute( 'href' );
                if ( ! str_contains( $href, '/book/author/' ) && ! str_contains( $href, '/author/' ) ) {
                    continue;
                }
                $name = trim( $link->textContent );
                if ( $name !== '' ) {
                    $author_names[] = $name;
                }
            }
        }

        // Fallback: use primary hidden input name
        if ( empty( $author_names ) && ! empty( $data['_author_fallback'] ) ) {
            $author_names[] = $data['_author_fallback'];
        }

        $data['author_names'] = $author_names;

        // ── Cover image ───────────────────────────────────────────────────────
        $img = $xp->query(
            '//div[contains(@class,"image-container")]//img[contains(@class,"look-inside")]'
        );
        if ( $img && $img->length ) {
            $src = trim( $img->item(0)->getAttribute( 'src' ) );
            if ( $src ) {
                $data['cover_url'] = self::abs( $src, $page_url );
            }
        }
        if ( empty( $data['cover_url'] ) && ! empty( $data['_img_filename'] ) ) {
            $data['cover_url'] = 'https://rokbucket.rokomari.io/ProductNew20190903/260X372/'
                . $data['_img_filename'];
        }

        // ── Category fallback ─────────────────────────────────────────────────
        if ( empty( $data['category'] ) ) {
            $cat = $xp->query(
                '//div[contains(@class,"details-book-info__content-category")]//a'
            );
            if ( $cat && $cat->length ) {
                $data['category'] = trim( $cat->item(0)->textContent );
            }
        }

        // ── Description ───────────────────────────────────────────────────────
        $desc = $xp->query( '//*[@id="js--short-description"]' );
        if ( $desc && $desc->length ) {
            $clone = $desc->item(0)->cloneNode( true );
            foreach ( iterator_to_array( $xp->query( './/a', $clone ) ) as $a ) {
                $a->parentNode->removeChild( $a );
            }
            $data['description'] = trim( $clone->textContent );
        }

        // ── Publisher — dedicated XPath (before table scan) ───────────────────
        $pub_xpaths = [
            '//td[contains(translate(normalize-space(.),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"publisher")]/following-sibling::td[1]',
            '//th[contains(translate(normalize-space(.),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"publisher")]/following-sibling::td[1]',
        ];
        foreach ( $pub_xpaths as $xpath_str ) {
            $nodes = $xp->query( $xpath_str );
            if ( $nodes && $nodes->length ) {
                $val = trim( $nodes->item(0)->textContent );
                if ( $val ) {
                    $data['publisher_name'] = sanitize_text_field( $val );
                    break;
                }
            }
        }

        // ── Detail table rows ─────────────────────────────────────────────────
        $rows = $xp->query(
            '//section[@id="summary"]//tr | //div[@id="summary"]//tr |
             //table[contains(@class,"table-book-details")]//tr'
        );
        if ( $rows ) {
            foreach ( $rows as $row ) {
                $tds = $xp->query( './/td', $row );
                if ( ! $tds || $tds->length < 2 ) {
                    continue;
                }
                $label = strtolower( trim( $tds->item(0)->textContent ) );
                $value = trim( $tds->item(1)->textContent );
                self::map_row( $label, $value, $data );
            }
        }

        // ── <li> fallback ─────────────────────────────────────────────────────
        $lis = $xp->query(
            '//ul[contains(@class,"book-details")]//li |
             //div[contains(@class,"details-book-info")]//li'
        );
        if ( $lis ) {
            foreach ( $lis as $li ) {
                $parts = explode( ':', $li->textContent, 2 );
                if ( count( $parts ) === 2 ) {
                    self::map_row( strtolower( trim( $parts[0] ) ), trim( $parts[1] ), $data );
                }
            }
        }

        // Cleanup internal-only keys
        unset( $data['_title_en'], $data['_author_fallback'], $data['_img_filename'] );

        return $data;
    }

    // =========================================================================
    // ROW MAPPER
    // =========================================================================

    private static function map_row( string $label, string $value, array &$data ): void {
        if ( ! $value ) {
            return;
        }

        static $patterns = null;
        if ( $patterns === null ) {
            $patterns = [
                'isbn-13'          => 'isbn',
                'isbn-10'          => 'isbn',
                'isbn'             => 'isbn',
                'number of pages'  => 'pages',
                'pages'            => 'pages',
                'page'             => 'pages',
                'published year'   => 'published_year',
                'published'        => 'published_year',
                'year'             => 'published_year',
                'language'         => 'language',
                'publisher'        => 'publisher_name',
                'edition'          => 'edition',
                'country'          => 'country',
                // Bangla labels
                "\u09AA\u09CD\u09B0\u0995\u09BE\u09B6\u09A8\u09C0" => 'publisher_name',
                "\u09AA\u09CD\u09B0\u0995\u09BE\u09B6\u0995"       => 'publisher_name',
                "\u09AA\u09C3\u09B7\u09CD\u09A0\u09BE"             => 'pages',
                "\u09AD\u09BE\u09B7\u09BE"                          => 'language',
            ];
        }

        foreach ( $patterns as $keyword => $field ) {
            if ( str_contains( $label, $keyword ) ) {
                // Don't overwrite publisher already found by XPath
                if ( $field === 'publisher_name' && ! empty( $data['publisher_name'] ) ) {
                    return;
                }
                if ( in_array( $field, [ 'pages', 'published_year' ], true ) ) {
                    $num = (int) preg_replace( '/\D/', '', $value );
                    if ( $num ) {
                        $data[ $field ] = $num;
                    }
                } else {
                    $data[ $field ] = sanitize_text_field( $value );
                }
                return;
            }
        }
    }

    // =========================================================================
    // CPT HELPERS
    // =========================================================================

    private static function get_or_create_author( string $name ): int {
        $name = sanitize_text_field( trim( $name ) );
        if ( ! $name ) {
            return 0;
        }
        $existing = get_posts( [
            'post_type'      => 'bs_author',
            'post_status'    => 'publish',
            'title'          => $name,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );
        if ( ! empty( $existing ) ) {
            return (int) $existing[0];
        }
        $id = wp_insert_post( [
            'post_type'   => 'bs_author',
            'post_title'  => $name,
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ], true );
        return is_wp_error( $id ) ? 0 : (int) $id;
    }

    private static function get_or_create_publisher( string $name ): int {
        $name = sanitize_text_field( trim( $name ) );
        if ( ! $name ) {
            return 0;
        }
        $existing = get_posts( [
            'post_type'      => 'bs_publisher',
            'post_status'    => 'publish',
            'title'          => $name,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );
        if ( ! empty( $existing ) ) {
            return (int) $existing[0];
        }
        $id = wp_insert_post( [
            'post_type'   => 'bs_publisher',
            'post_title'  => $name,
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ], true );
        return is_wp_error( $id ) ? 0 : (int) $id;
    }

    // =========================================================================
    // MEDIA
    // =========================================================================

    private static function sideload_image( string $url, int $post_id, string $title ): int|\WP_Error {
        if ( ! function_exists( 'media_sideload_image' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        return media_sideload_image( $url, $post_id, $title, 'id' );
    }

    private static function abs( string $src, string $base ): string {
        if ( str_starts_with( $src, 'http' ) ) {
            return $src;
        }
        if ( str_starts_with( $src, '//' ) ) {
            return 'https:' . $src;
        }
        $p = wp_parse_url( $base );
        return $p['scheme'] . '://' . $p['host'] . '/' . ltrim( $src, '/' );
    }

    // =========================================================================
    // ADMIN PAGE  — pure HTML only, no inline CSS or JS
    // =========================================================================

    public static function import_page(): void {
        ?>
        <div class="wrap bs-import-wrap">

            <div class="bs-import-header">
                <h1><span class="bs-import-icon">📥</span> <?php esc_html_e( 'Import Books from Rokomari', 'bookshare' ); ?></h1>
                <p><?php esc_html_e( 'Paste one or more Rokomari book URLs — one per line — then click Import. Title, all author(s), publisher, cover image, ISBN and more are imported automatically.', 'bookshare' ); ?></p>
            </div>

            <div class="bs-import-card">
                <div class="bs-import-field">
                    <label for="bs-import-urls">
                        <?php esc_html_e( 'Rokomari Book URLs', 'bookshare' ); ?>
                        <span class="bs-import-hint"><?php esc_html_e( '(one per line)', 'bookshare' ); ?></span>
                    </label>
                    <textarea
                        id="bs-import-urls"
                        rows="10"
                        placeholder="https://www.rokomari.com/book/371309/english-shikhbo-bangla-ortho-buje&#10;https://www.rokomari.com/book/225612/you-must-do-a-business"
                    ></textarea>
                    <p class="bs-import-note">
                        📌 <?php esc_html_e( 'Duplicate books (same ISBN) are skipped. Multiple authors are all imported and linked.', 'bookshare' ); ?>
                    </p>
                </div>

                <div class="bs-import-actions">
                    <button id="bs-import-btn" class="button button-primary bs-btn-import">
                        📥 <?php esc_html_e( 'Import Books', 'bookshare' ); ?>
                    </button>
                    <span id="bs-import-spinner" class="spinner"></span>
                    <span id="bs-import-progress" class="bs-import-progress"></span>
                </div>
            </div>

            <div id="bs-import-results" hidden>
                <div class="bs-results-header">
                    <h2><?php esc_html_e( 'Results', 'bookshare' ); ?></h2>
                    <span id="bs-import-summary" class="bs-import-summary"></span>
                </div>
                <table class="wp-list-table widefat fixed striped bs-import-table">
                    <thead>
                        <tr>
                            <th class="col-url"><?php esc_html_e( 'URL', 'bookshare' ); ?></th>
                            <th class="col-title"><?php esc_html_e( 'Title', 'bookshare' ); ?></th>
                            <th class="col-authors"><?php esc_html_e( 'Author(s)', 'bookshare' ); ?></th>
                            <th class="col-publisher"><?php esc_html_e( 'Publisher', 'bookshare' ); ?></th>
                            <th class="col-status"><?php esc_html_e( 'Status', 'bookshare' ); ?></th>
                            <th class="col-note"><?php esc_html_e( 'Note', 'bookshare' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="bs-import-tbody"></tbody>
                </table>
            </div>

        </div><!-- .bs-import-wrap -->

        <!-- ── Modal overlay ──────────────────────────────────────────────────── -->
        <div id="bs-modal-overlay" class="bs-modal-overlay" hidden>
            <div class="bs-modal" role="dialog" aria-modal="true" aria-labelledby="bs-modal-title">
                <div class="bs-modal-header">
                    <span id="bs-modal-icon" class="bs-modal-icon"></span>
                    <h3 id="bs-modal-title" class="bs-modal-title"></h3>
                    <button id="bs-modal-x" class="bs-modal-x" aria-label="Close">&times;</button>
                </div>
                <div class="bs-modal-body">
                    <p id="bs-modal-message" class="bs-modal-message"></p>
                </div>
                <div class="bs-modal-footer">
                    <button id="bs-modal-ok" class="button button-primary bs-modal-ok-btn">
                        <?php esc_html_e( 'OK', 'bookshare' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
}