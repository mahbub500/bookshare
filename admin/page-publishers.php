<?php defined('ABSPATH') || exit; ?>
<div class="wrap bs-admin-wrap">
<div class="bs-admin-notices"></div>

<div class="bs-admin-header">
    <span class="bs-admin-logo">🏢</span>
    <div>
        <h1 class="bs-admin-title">Publishers</h1>
        <p class="bs-admin-subtitle">Manage publishing houses and imprints</p>
    </div>
</div>

<div class="bs-admin-card">
    <div class="bs-admin-card-header">
        <h2 class="bs-admin-card-title">All Publishers</h2>
        <button class="bs-admin-btn bs-admin-btn-primary" id="bs-btn-add-publisher">＋ Add Publisher</button>
    </div>
    <table class="bs-admin-table">
        <thead>
            <tr><th>Logo</th><th>Name</th><th>Email</th><th>City</th><th>Country</th><th>Founded</th><th>Website</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($publishers as $pub) : ?>
        <tr>
            <td><?php if ($pub->logo_url) : ?><img src="<?php echo esc_url($pub->logo_url); ?>" style="height:36px;object-fit:contain;max-width:60px" alt=""><?php else : ?>🏢<?php endif; ?></td>
            <td><strong><?php echo esc_html($pub->name); ?></strong></td>
            <td><?php echo esc_html($pub->email ?: '—'); ?></td>
            <td><?php echo esc_html($pub->city ?: '—'); ?></td>
            <td><?php echo esc_html($pub->country ?: '—'); ?></td>
            <td><?php echo esc_html($pub->founded_year ?: '—'); ?></td>
            <td><?php if ($pub->website) : ?><a href="<?php echo esc_url($pub->website); ?>" target="_blank">🌐 Visit</a><?php else : ?>—<?php endif; ?></td>
            <td style="display:flex;gap:6px">
                <button class="bs-admin-btn bs-admin-btn-ghost bs-admin-btn-sm" onclick="bsEditPub(<?php echo esc_js(json_encode($pub)); ?>)">Edit</button>
                <button class="bs-admin-btn bs-admin-btn-danger bs-admin-btn-sm" data-bs-delete="publishers/<?php echo esc_attr($pub->id); ?>">Delete</button>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($publishers)) : ?><tr><td colspan="8" style="text-align:center;padding:32px;color:#6B7280">No publishers yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div class="bs-admin-modal-overlay" id="bs-admin-publisher-modal">
    <div class="bs-admin-modal">
        <div class="bs-admin-modal-header">
            <h3>Add / Edit Publisher</h3>
            <button class="bs-admin-modal-close" data-bs-close-modal="bs-admin-publisher-modal">✕</button>
        </div>
        <div class="bs-admin-modal-body">
            <form id="bs-admin-publisher-form">
            <input type="hidden" id="bs-publisher-edit-id">
            <div class="bs-admin-form-grid">
                <div class="bs-admin-form-group bs-span-2">
                    <label class="bs-admin-label">Publisher Name *</label>
                    <input type="text" id="bs-pub-name" class="bs-admin-input" placeholder="Publisher name">
                </div>
                <div class="bs-admin-form-group">
                    <label class="bs-admin-label">Email</label>
                    <input type="email" id="bs-pub-email" class="bs-admin-input">
                </div>
                <div class="bs-admin-form-group">
                    <label class="bs-admin-label">Phone</label>
                    <input type="tel" id="bs-pub-phone" class="bs-admin-input">
                </div>
                <div class="bs-admin-form-group">
                    <label class="bs-admin-label">City</label>
                    <input type="text" id="bs-pub-city" class="bs-admin-input">
                </div>
                <div class="bs-admin-form-group">
                    <label class="bs-admin-label">Country</label>
                    <input type="text" id="bs-pub-country" class="bs-admin-input">
                </div>
                <div class="bs-admin-form-group">
                    <label class="bs-admin-label">Founded Year</label>
                    <input type="number" id="bs-pub-year" class="bs-admin-input" placeholder="1990">
                </div>
                <div class="bs-admin-form-group">
                    <label class="bs-admin-label">Website</label>
                    <input type="url" id="bs-pub-website" class="bs-admin-input" placeholder="https://…">
                </div>
                <div class="bs-admin-form-group bs-span-2">
                    <label class="bs-admin-label">Address</label>
                    <textarea id="bs-pub-address" class="bs-admin-textarea" rows="2"></textarea>
                </div>
                <div class="bs-admin-form-group bs-span-2">
                    <label class="bs-admin-label">Logo URL</label>
                    <input type="url" id="bs-pub-logo" class="bs-admin-input" placeholder="https://…">
                </div>
                <div class="bs-admin-form-group bs-span-2">
                    <label class="bs-admin-label">Description</label>
                    <textarea id="bs-pub-desc" class="bs-admin-textarea" rows="3"></textarea>
                </div>
            </div>
            </form>
        </div>
        <div class="bs-admin-modal-footer">
            <button class="bs-admin-btn bs-admin-btn-ghost" data-bs-close-modal="bs-admin-publisher-modal">Cancel</button>
            <button class="bs-admin-btn bs-admin-btn-primary" id="bs-btn-save-publisher">Save Publisher</button>
        </div>
    </div>
</div>

<script>
function bsEditPub(p) {
    document.getElementById('bs-publisher-edit-id').value = p.id;
    document.getElementById('bs-pub-name').value    = p.name || '';
    document.getElementById('bs-pub-email').value   = p.email || '';
    document.getElementById('bs-pub-phone').value   = p.phone || '';
    document.getElementById('bs-pub-city').value    = p.city || '';
    document.getElementById('bs-pub-country').value = p.country || '';
    document.getElementById('bs-pub-year').value    = p.founded_year || '';
    document.getElementById('bs-pub-website').value = p.website || '';
    document.getElementById('bs-pub-address').value = p.address || '';
    document.getElementById('bs-pub-logo').value    = p.logo_url || '';
    document.getElementById('bs-pub-desc').value    = p.description || '';
    document.getElementById('bs-admin-publisher-modal').classList.add('open');
}
</script>
</div>
