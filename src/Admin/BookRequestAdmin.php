<?php
namespace BookShare\Admin;

use BookShare\Models\BookRequest;

/**
 * Admin page for managing user book-listing requests.
 * Admin can approve (creates the bs_book post) or reject.
 */
class BookRequestAdmin {

    public function __construct() {
        add_action( 'wp_ajax_bs_handle_request', [ $this, 'handle_ajax' ] );
    }

    public function render_page(): void {
        global $wpdb;

        $status_filter = sanitize_text_field( $_GET['bs_status'] ?? 'pending' );
        $table         = $wpdb->prefix . 'bs_book_requests';

        $requests = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*, u.display_name AS reader_name
             FROM $table r
             LEFT JOIN {$wpdb->users} u ON u.ID = r.user_id
             WHERE r.status = %s
             ORDER BY r.created_at DESC",
            $status_filter
        ) );

        $counts = [];
        foreach ( [ 'pending', 'approved', 'rejected' ] as $s ) {
            $counts[$s] = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE status = %s", $s
            ) );
        }
        ?>
        <div class="wrap">
            <h1>📬 Book Listing Requests</h1>
            <p style="color:#718096">Readers submitted these requests when they couldn't find a book in the catalog.</p>

            <!-- Status tabs -->
            <div style="display:flex;gap:.5rem;margin-bottom:1.5rem;border-bottom:2px solid #e5e7eb;padding-bottom:.5rem">
                <?php foreach ( $counts as $s => $count ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=bs-requests&bs_status=' . $s ) ); ?>"
                       style="padding:.4rem 1rem;border-radius:6px 6px 0 0;text-decoration:none;font-weight:500;
                              background:<?php echo $status_filter === $s ? '#fff' : 'transparent'; ?>;
                              color:<?php echo $status_filter === $s ? '#c2401e' : '#6b7280'; ?>;
                              border:<?php echo $status_filter === $s ? '1px solid #e5e7eb' : 'none'; ?>">
                        <?php echo ucfirst($s); ?> (<?php echo $count; ?>)
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ( empty( $requests ) ) : ?>
                <p style="color:#9ca3af;padding:2rem;text-align:center;background:#f9fafb;border-radius:8px">
                    No <?php echo esc_html($status_filter); ?> requests.
                </p>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Book Title</th>
                        <th>Author</th>
                        <th>Publisher</th>
                        <th>ISBN</th>
                        <th>Requested By</th>
                        <th>Date</th>
                        <th>Notes</th>
                        <?php if ( $status_filter === 'pending' ) : ?>
                        <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $requests as $req ) : ?>
                    <tr id="req-row-<?php echo $req->id; ?>">
                        <td><?php echo $req->id; ?></td>
                        <td><strong><?php echo esc_html( $req->title ); ?></strong></td>
                        <td><?php echo esc_html( $req->author ); ?></td>
                        <td><?php echo esc_html( $req->publisher ); ?></td>
                        <td><?php echo esc_html( $req->isbn ); ?></td>
                        <td><?php echo esc_html( $req->reader_name ); ?></td>
                        <td><?php echo esc_html( substr( $req->created_at, 0, 10 ) ); ?></td>
                        <td style="max-width:200px;word-break:break-word"><?php echo esc_html( $req->notes ); ?></td>
                        <?php if ( $status_filter === 'pending' ) : ?>
                        <td>
                            <button class="button button-primary" style="background:#2e6b4f;border-color:#2e6b4f"
                                onclick="bsApproveRequest(<?php echo $req->id; ?>, '<?php echo esc_js($req->title); ?>', '<?php echo esc_js($req->author); ?>')">
                                ✓ Approve
                            </button>
                            &nbsp;
                            <button class="button" style="color:#e53935;border-color:#e53935"
                                onclick="bsRejectRequest(<?php echo $req->id; ?>)">
                                ✕ Reject
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <script>
        function bsApproveRequest(id, title, author) {
            if (!confirm('Approve request and add "' + title + '" to the catalog?')) return;
            bsAjaxAction(id, 'approve');
        }
        function bsRejectRequest(id) {
            if (!confirm('Reject this request?')) return;
            bsAjaxAction(id, 'reject');
        }
        function bsAjaxAction(id, action) {
            jQuery.post(ajaxurl, {
                action: 'bs_handle_request',
                request_id: id,
                request_action: action,
                _ajax_nonce: '<?php echo wp_create_nonce("bs_handle_request"); ?>'
            }, function(resp) {
                if (resp.success) {
                    const row = document.getElementById('req-row-' + id);
                    if (row) {
                        row.style.background = action === 'approve' ? '#ecfdf5' : '#fef2f2';
                        row.cells[row.cells.length - 1].innerHTML =
                            '<em style="color:#6b7280">' + (action === 'approve' ? '✓ Approved' : '✕ Rejected') + '</em>';
                    }
                } else {
                    alert(resp.data || 'Error occurred');
                }
            });
        }
        </script>
        <?php
    }

    public function handle_ajax(): void {
        check_ajax_referer( 'bs_handle_request' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $id     = (int) ( $_POST['request_id']     ?? 0 );
        $action = sanitize_text_field( $_POST['request_action'] ?? '' );

        if ( ! $id || ! in_array( $action, [ 'approve', 'reject' ], true ) ) {
            wp_send_json_error( 'Invalid data' );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bs_book_requests';
        $req   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d", $id ) );

        if ( ! $req ) wp_send_json_error( 'Request not found' );

        if ( $action === 'approve' ) {
            // Create the bs_book post automatically
            $post_id = wp_insert_post( [
                'post_type'   => 'bs_book',
                'post_title'  => sanitize_text_field( $req->title ),
                'post_status' => 'publish',
            ] );

            if ( is_wp_error( $post_id ) ) {
                wp_send_json_error( 'Could not create book post.' );
            }

            // Save meta
            $code = $this->generate_code( $req->title );
            update_post_meta( $post_id, '_bs_unique_code', $code );
            if ( $req->isbn ) update_post_meta( $post_id, '_bs_isbn', sanitize_text_field( $req->isbn ) );

            // Try to match author by name
            $author_posts = get_posts( [ 'post_type' => 'bs_author', 'title' => $req->author, 'numberposts' => 1 ] );
            if ( $author_posts ) {
                update_post_meta( $post_id, '_bs_author_id', $author_posts[0]->ID );
            }

            $wpdb->update( $table,
                [ 'status' => 'approved', 'book_id' => $post_id ],
                [ 'id' => $id ]
            );
        } else {
            $wpdb->update( $table, [ 'status' => 'rejected' ], [ 'id' => $id ] );
        }

        wp_send_json_success();
    }

    private function generate_code( string $title ): string {
        $p = strtoupper( preg_replace( '/[^a-zA-Z]/', '', $title ) );
        return str_pad( substr( $p, 0, 4 ), 4, 'X' ) . strtoupper( substr( uniqid(), -5 ) );
    }
}