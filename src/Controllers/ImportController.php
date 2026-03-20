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

        /* 1 ── Fetch HTML page ----------------------------------------------- */
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

        /* 2 ── Parse HTML + fetch API for spec data --------------------------- */
        $data = self::parse( $html, $url );

        // Extract product ID from URL or parsed data, then hit the Rokomari
        // product-details API which returns the full specification (ISBN etc.)
        // as JSON — this is the same endpoint the browser JS calls to populate
        // the Specification tab after page load.
        $product_id = $data['rokomari_id'] ?? self::extract_product_id_from_url( $url );
        if ( $product_id ) {
            $api_data = self::fetch_rokomari_api( (int) $product_id );
            if ( ! empty( $api_data ) ) {
                // Merge: API values fill in any gaps left by HTML parsing.
                // HTML-parsed values take priority (already sanitised).
                foreach ( $api_data as $k => $v ) {
                    if ( empty( $data[ $k ] ) && ! empty( $v ) ) {
                        $data[ $k ] = $v;
                    }
                }
            }
        }

        if ( empty( $data['title'] ) ) {
            return [ 'status' => 'error', 'message' => 'Could not find book title on page.' ];
        }

        /* 3 ── Duplicate check (3 levels) ------------------------------------ */
        $dup_result = self::find_duplicate( $data );
        if ( $dup_result ) {
            return array_merge( [ 'status' => 'skipped', 'data' => $data ], $dup_result );
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
        // Save Rokomari product ID — used as the most reliable dedup key on re-import
        if ( ! empty( $data['rokomari_id'] ) ) {
            update_post_meta( $post_id, 'bs_rokomari_id', sanitize_text_field( $data['rokomari_id'] ) );
        }
        // Save source URL for reference / audit trail
        update_post_meta( $post_id, 'bs_source_url', esc_url_raw( $url ) );

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
        // Try the full summary section first (rendered page), fallback to short desc
        $desc_full = $xp->query( '//*[@id="js--summary-description"]' );
        if ( $desc_full && $desc_full->length ) {
            $clone = $desc_full->item(0)->cloneNode( true );
            foreach ( iterator_to_array( $xp->query( './/a', $clone ) ) as $a ) {
                $a->parentNode->removeChild( $a );
            }
            $raw_desc = trim( $clone->textContent );
            if ( $raw_desc ) {
                $data['description'] = $raw_desc;
            }
        }
        if ( empty( $data['description'] ) ) {
            $desc_short = $xp->query( '//*[@id="js--short-description"]' );
            if ( $desc_short && $desc_short->length ) {
                $clone = $desc_short->item(0)->cloneNode( true );
                foreach ( iterator_to_array( $xp->query( './/a', $clone ) ) as $a ) {
                    $a->parentNode->removeChild( $a );
                }
                $data['description'] = trim( $clone->textContent );
            }
        }

        // ── ISBN from <meta property="og:..."> or JSON-LD ────────────────────
        // Rokomari does NOT put ISBN in the visible table for all books,
        // but it appears in the og:description or JSON-LD on some pages.
        // Primary source: the specification table (handled below).
        // Fallback: scan <script type="application/ld+json"> for isbn.
        if ( empty( $data['isbn'] ) ) {
            $scripts = $xp->query( '//script[@type="application/ld+json"]' );
            if ( $scripts ) {
                foreach ( $scripts as $script ) {
                    $json = json_decode( trim( $script->textContent ), true );
                    if ( is_array( $json ) ) {
                        foreach ( [ 'isbn', 'gtin13', 'gtin' ] as $k ) {
                            if ( ! empty( $json[ $k ] ) ) {
                                $data['isbn'] = sanitize_text_field( $json[ $k ] );
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        // ── Specification table inside #book-additional-specification ─────────
        //
        // Rokomari's full HTML structure (document 11):
        //   <div id="book-additional-specification">
        //     <table class="table table-bordered">
        //       <tr><td>Title</td>          <td>ইউ মাস্ট ডু বিজনেস</td></tr>
        //       <tr><td>Author</td>         <td>...</td></tr>
        //       <tr><td>Editor</td>         <td>...</td></tr>
        //       <tr><td>Publisher</td>      <td><a>সমকালীন প্রকাশন</a></td></tr>
        //       <tr><td>Edition</td>        <td>1st Published, 2022</td></tr>
        //       <tr><td>Number of Pages</td><td>32</td></tr>
        //       <tr><td>Country</td>        <td>বাংলাদেশ</td></tr>
        //       <tr><td>Language</td>       <td>বাংলা</td></tr>
        //     </table>
        //   </div>
        //
        // We query ALL tables on the page so this works regardless of
        // whether the tab content is rendered server-side or injected by JS.

        $spec_rows = $xp->query(
            '//*[@id="book-additional-specification"]//tr |
             //section[@id="summary"]//tr              |
             //div[@id="summary"]//tr                  |
             //table[contains(@class,"table-bordered")]//tr |
             //table[contains(@class,"table-book-details")]//tr'
        );
        if ( $spec_rows ) {
            foreach ( $spec_rows as $row ) {
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
                'edition'          => 'edition_raw',   // special handling below
                'language'         => 'language',
                'publisher'        => 'publisher_name',
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
                // Don't overwrite publisher already found
                if ( $field === 'publisher_name' && ! empty( $data['publisher_name'] ) ) {
                    return;
                }

                if ( in_array( $field, [ 'pages', 'published_year' ], true ) ) {
                    // Extract digits only (handles "32 pages", "32" etc.)
                    $num = (int) preg_replace( '/\D/', '', $value );
                    if ( $num ) {
                        $data[ $field ] = $num;
                    }

                } elseif ( $field === 'edition_raw' ) {
                    // Edition cell value: "1st Published, 2022"  or  "2nd Edition, 2019"
                    // Extract the 4-digit year and store as published_year if not already set.
                    if ( empty( $data['published_year'] ) ) {
                        if ( preg_match( '/\b(19|20)\d{2}\b/', $value, $m ) ) {
                            $data['published_year'] = (int) $m[0];
                        }
                    }
                    // Also store the raw edition string for reference
                    $data['edition'] = sanitize_text_field( $value );

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

    // =========================================================================
    // DUPLICATE DETECTION  (3-level cascade)
    // =========================================================================

    /**
     * Check if a book already exists before importing.
     *
     * Level 1 — Rokomari product ID (bs_rokomari_id meta)
     *   Most reliable. Set on every book we previously imported.
     *   Catches re-imports of the exact same product page immediately.
     *
     * Level 2 — ISBN (bs_isbn meta)
     *   Reliable for books that have an ISBN.
     *   Catches the same physical book imported from a different URL
     *   (e.g. a cached/alternate Rokomari URL).
     *
     * Level 3 — Exact title + first author name (post_title + meta)
     *   Fallback for books without ISBN (short booklets, pamphlets).
     *   Only fires when levels 1 and 2 both miss.
     *
     * Returns null if no duplicate found.
     * Returns array [ 'message' => '...', 'post_id' => int ] if found.
     */
    private static function find_duplicate( array $data ): ?array {

        $base_args = [
            'post_type'      => 'bs_book',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ];

        // ── Level 1: Rokomari product ID ──────────────────────────────────────
        if ( ! empty( $data['rokomari_id'] ) ) {
            $found = get_posts( array_merge( $base_args, [
                'meta_key'   => 'bs_rokomari_id',
                'meta_value' => sanitize_text_field( $data['rokomari_id'] ),
            ] ) );
            if ( ! empty( $found ) ) {
                return [
                    'message' => 'Skipped — already imported (Rokomari ID: ' . $data['rokomari_id'] . ').',
                    'post_id' => (int) $found[0],
                ];
            }
        }

        // ── Level 2: ISBN ─────────────────────────────────────────────────────
        if ( ! empty( $data['isbn'] ) ) {
            $found = get_posts( array_merge( $base_args, [
                'meta_key'   => 'bs_isbn',
                'meta_value' => sanitize_text_field( $data['isbn'] ),
            ] ) );
            if ( ! empty( $found ) ) {
                return [
                    'message' => 'Skipped — already exists (ISBN: ' . $data['isbn'] . ').',
                    'post_id' => (int) $found[0],
                ];
            }
        }

        // ── Level 3: Exact title + first author ───────────────────────────────
        // Only run when no ISBN — avoids false positives on common titles.
        if ( empty( $data['isbn'] ) && ! empty( $data['title'] ) ) {

            $title_matches = get_posts( array_merge( $base_args, [
                // WP 'title' arg does exact post_title match
                'title' => sanitize_text_field( $data['title'] ),
            ] ) );

            if ( ! empty( $title_matches ) ) {
                // Narrow down: also check the first author matches
                $first_author = $data['author_names'][0] ?? '';

                if ( ! $first_author ) {
                    // No author to cross-check — treat title match as duplicate
                    return [
                        'message' => 'Skipped — same title already exists (no ISBN to confirm).',
                        'post_id' => (int) $title_matches[0],
                    ];
                }

                foreach ( $title_matches as $candidate_id ) {
                    $saved_author_id = (int) get_post_meta( $candidate_id, 'bs_author_id', true );
                    if ( ! $saved_author_id ) {
                        continue;
                    }
                    $saved_author_title = get_the_title( $saved_author_id );
                    // Case-insensitive, trim-safe comparison
                    if ( mb_strtolower( trim( $saved_author_title ) ) === mb_strtolower( trim( $first_author ) ) ) {
                        return [
                            'message' => 'Skipped — same title + author already exists (no ISBN).',
                            'post_id' => (int) $candidate_id,
                        ];
                    }
                }
                // Title matches but authors differ → treat as a different book (different edition/translation)
            }
        }

        return null; // no duplicate found
    }

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

    // =========================================================================
    // ROKOMARI API  — fetches the specification tab data as JSON
    // =========================================================================

    /**
     * Extract the numeric product ID from a Rokomari book URL.
     *
     * URL patterns:
     *   https://www.rokomari.com/book/225612/you-must-do-a-business
     *   https://www.rokomari.com/book/225612
     */
    private static function extract_product_id_from_url( string $url ): ?string {
        // Match /book/{digits} anywhere in the path
        if ( preg_match( '#/book/(\d+)#', $url, $m ) ) {
            return $m[1];
        }
        return null;
    }

    /**
     * Call the Rokomari product-details API and return a normalised data array.
     *
     * Rokomari's frontend JS calls this endpoint to populate the Specification
     * tab after page load. It returns structured JSON that includes ISBN,
     * pages, language, publisher, edition and more — data that is NOT present
     * in the raw server-rendered HTML that wp_remote_get() retrieves.
     *
     * Endpoint (observed from browser network tab):
     *   GET https://www.rokomari.com/api/v1/book/product-details/{product_id}
     *
     * Returns a normalised array with the same keys as parse(), or [] on failure.
     *
     * Fields returned:
     *   isbn, pages, published_year, language, publisher_name, edition
     */
    private static function fetch_rokomari_api( int $product_id ): array {
        if ( ! $product_id ) {
            return [];
        }

        $api_url = "https://www.rokomari.com/api/v1/book/product-details/{$product_id}";

        $response = wp_remote_get( $api_url, [
            'timeout'    => 15,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0 Safari/537.36',
            'headers'    => [
                'Accept'          => 'application/json, text/plain, */*',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Referer'         => "https://www.rokomari.com/book/{$product_id}",
                'X-Requested-With' => 'XMLHttpRequest',
            ],
            'sslverify'  => false,
        ] );

        if ( is_wp_error( $response ) ) {
            return [];
        }

        if ( (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return [];
        }

        $body = wp_remote_retrieve_body( $response );
        if ( ! $body ) {
            return [];
        }

        $json = json_decode( $body, true );
        if ( ! is_array( $json ) ) {
            return [];
        }

        // ── Map API response fields → our normalised keys ─────────────────────
        //
        // The Rokomari API response shape (observed):
        // {
        //   "data": {
        //     "specification": [
        //       { "label": "ISBN",            "value": "978-984-96459-0-1" },
        //       { "label": "Number of Pages", "value": "32"                },
        //       { "label": "Publisher",       "value": "সমকালীন প্রকাশন"  },
        //       { "label": "Edition",         "value": "1st Published, 2022" },
        //       { "label": "Language",        "value": "বাংলা"             },
        //       { "label": "Country",         "value": "বাংলাদেশ"          }
        //     ],
        //     "isbn": "978-984-96459-0-1",   // sometimes top-level
        //     "publisher": { "name": "..." },
        //     ...
        //   }
        // }

        $out = [];

        // ── Top-level isbn field ──────────────────────────────────────────────
        $root = $json['data'] ?? $json;

        if ( ! empty( $root['isbn'] ) ) {
            $out['isbn'] = sanitize_text_field( $root['isbn'] );
        }

        // ── Publisher name from nested object ─────────────────────────────────
        if ( ! empty( $root['publisher']['name'] ) ) {
            $out['publisher_name'] = sanitize_text_field( $root['publisher']['name'] );
        }

        // ── Specification array ───────────────────────────────────────────────
        $specs = $root['specification'] ?? $root['specifications'] ?? [];
        if ( is_array( $specs ) ) {
            foreach ( $specs as $spec ) {
                $label = strtolower( trim( $spec['label'] ?? $spec['name'] ?? '' ) );
                $value = trim( $spec['value'] ?? $spec['val']  ?? '' );
                if ( $label && $value ) {
                    self::map_row( $label, $value, $out );
                }
            }
        }

        // ── Flat key scan (some API versions flatten everything) ──────────────
        $flat_map = [
            'isbn'           => 'isbn',
            'isbn13'         => 'isbn',
            'isbn_13'        => 'isbn',
            'language'       => 'language',
            'pages'          => 'pages',
            'number_of_pages'=> 'pages',
            'published_year' => 'published_year',
            'edition'        => 'edition',
        ];
        foreach ( $flat_map as $api_key => $our_key ) {
            if ( ! empty( $root[ $api_key ] ) && empty( $out[ $our_key ] ) ) {
                $val = $root[ $api_key ];
                if ( in_array( $our_key, [ 'pages', 'published_year' ], true ) ) {
                    $num = (int) preg_replace( '/\D/', '', (string) $val );
                    if ( $num ) {
                        $out[ $our_key ] = $num;
                    }
                } else {
                    $out[ $our_key ] = sanitize_text_field( (string) $val );
                }
            }
        }

        return $out;
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