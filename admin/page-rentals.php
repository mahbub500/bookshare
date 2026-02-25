<?php defined('ABSPATH') || exit; ?>
<div class="wrap bs-admin-wrap">
<div class="bs-admin-notices"></div>

<div class="bs-admin-header">
    <span class="bs-admin-logo">📬</span>
    <div>
        <h1 class="bs-admin-title">Rental Requests</h1>
        <p class="bs-admin-subtitle">Manage all book borrowing requests</p>
    </div>
</div>

<div class="bs-admin-card">
    <div class="bs-admin-card-header">
        <h2 class="bs-admin-card-title">All Requests (<?php echo count($rentals); ?>)</h2>
    </div>
    <table class="bs-admin-table">
        <thead>
            <tr><th>Book</th><th>Owner</th><th>Requester</th><th>Dates</th><th>Message</th><th>Status</th><th>Requested At</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rentals as $rental) : ?>
        <tr>
            <td>
                <?php if ($rental->cover_url) : ?><img src="<?php echo esc_url($rental->cover_url); ?>" class="bs-book-cover-thumb" style="margin-right:8px" alt=""><?php endif; ?>
                <strong><?php echo esc_html($rental->title); ?></strong><br>
                <code style="font-size:11px;color:#5B5EDE"><?php echo esc_html($rental->unique_code); ?></code>
            </td>
            <td><?php echo esc_html($rental->owner_name); ?></td>
            <td><?php echo esc_html($rental->requester_name); ?></td>
            <td style="font-size:12px">
                <?php if ($rental->start_date) echo esc_html($rental->start_date) . ' →<br>' . esc_html($rental->end_date); else echo '—'; ?>
            </td>
            <td style="max-width:180px;font-size:12px;color:#6B7280"><?php echo esc_html(wp_trim_words($rental->message, 12)); ?></td>
            <td>
                <select class="bs-admin-select" style="padding:5px 8px;font-size:12px" data-rental-status="<?php echo esc_attr($rental->id); ?>">
                    <?php foreach (['pending','approved','rejected','returned','cancelled'] as $s) : ?>
                    <option value="<?php echo $s; ?>" <?php selected($rental->status, $s); ?>><?php echo ucfirst($s); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td style="font-size:12px;color:#6B7280"><?php echo esc_html(date('M j, Y', strtotime($rental->created_at))); ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rentals)) : ?><tr><td colspan="7" style="text-align:center;padding:32px;color:#6B7280">No rental requests yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
</div>
