<?php
namespace BookShare\Admin;

/**
 * Registers the top-level "BookCircle" admin menu.
 * Call AdminMenu::register() from ShortcodeController or Plugin boot.
 */
class AdminMenu {

    public static function register(): void {
        add_action( 'admin_menu', [ self::class, 'add_menu' ] );
    }

    public static function add_menu(): void {
        add_menu_page(
            __( 'BookCircle', 'bookshare' ),
            __( 'BookCircle', 'bookshare' ),
            'manage_options',
            'bookshare',
            [ self::class, 'dashboard_page' ],
            'dashicons-book',
            25
        );

        add_submenu_page(
            'bookshare',
            __( 'Dashboard', 'bookshare' ),
            __( 'Dashboard', 'bookshare' ),
            'manage_options',
            'bookshare',
            [ self::class, 'dashboard_page' ]
        );

        add_submenu_page(
            'bookshare',
            __( 'Book Requests', 'bookshare' ),
            __( 'Book Requests', 'bookshare' ),
            'manage_options',
            'bs-requests',
            [ self::class, 'requests_page' ]
        );
    }

    public static function dashboard_page(): void {
        echo '<div class="wrap"><h1>📚 BookCircle Dashboard</h1>';
        echo '<p>Manage your community book library from here.</p>';
        global $wpdb;
        $books      = wp_count_posts( 'bs_book' )->publish      ?? 0;
        $authors    = wp_count_posts( 'bs_author' )->publish    ?? 0;
        $publishers = wp_count_posts( 'bs_publisher' )->publish ?? 0;
        $requests   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bs_book_requests WHERE status='pending'" );
        $libraries  = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}bs_library" );
        echo '<div style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:1.5rem">';
        foreach ( [
            [ '📖', $books,      'Published Books'    ],
            [ '✍️',  $authors,    'Authors'            ],
            [ '🏢', $publishers, 'Publishers'         ],
            [ '📬', $requests,   'Pending Requests'   ],
            [ '👥', $libraries,  'Active Readers'     ],
        ] as [ $icon, $count, $label ] ) {
            echo "<div style='background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.25rem 1.75rem;min-width:150px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.05)'>
                    <div style='font-size:2rem'>{$icon}</div>
                    <div style='font-size:2rem;font-weight:700;color:#1a202c'>{$count}</div>
                    <div style='font-size:.8rem;color:#718096;text-transform:uppercase;letter-spacing:.05em'>{$label}</div>
                  </div>";
        }
        echo '</div></div>';
    }

    public static function requests_page(): void {
        $handler = new BookRequestAdmin();
        $handler->render_page();
    }
}