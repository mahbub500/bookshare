<?php defined('ABSPATH') || exit; ?>
<div class="wrap bs-admin-wrap">
<div class="bs-admin-notices"></div>

<div class="bs-admin-header">
    <span class="bs-admin-logo">✍️</span>
    <div>
        <h1 class="bs-admin-title">Authors</h1>
        <p class="bs-admin-subtitle">Manage book authors and their profiles</p>
    </div>
</div>

<div class="bs-admin-card">
    <div class="bs-admin-card-header">
        <h2 class="bs-admin-card-title">All Authors</h2>
        <button class="bs-admin-btn bs-admin-btn-primary" id="bs-btn-add-author">＋ Add Author</button>
    </div>
    <table class="bs-admin-table">
        <thead>
            <tr>
                <th>Photo</th><th>Name</th><th>Email</th><th>Nationality</th><th>Birth Date</th><th>Website</th><th>Socials</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($authors as $author) : ?>
        <tr>
            <td><?php if ($author->photo_url) : ?><img src="<?php echo esc_url($author->photo_url); ?>" style="width:38px;height:38px;border-radius:50%;object-fit:cover" alt=""><?php else : ?>👤<?php endif; ?></td>
            <td><strong><?php echo esc_html($author->name); ?></strong><?php if ($author->bio) : ?><br><small style="color:#6B7280"><?php echo esc_html(wp_trim_words($author->bio,10)); ?></small><?php endif; ?></td>
            <td><?php echo esc_html($author->email ?: '—'); ?></td>
            <td><?php echo esc_html($author->nationality ?: '—'); ?></td>
            <td><?php echo esc_html($author->birth_date ?: '—'); ?></td>
            <td><?php if ($author->website) : ?><a href="<?php echo esc_url($author->website); ?>" target="_blank">🌐</a><?php else : ?>—<?php endif; ?></td>
            <td style="display:flex;gap:6px">
                <?php if ($author->social_twitter)   : ?><a href="https://twitter.com/<?php echo esc_attr($author->social_twitter); ?>" target="_blank">𝕏</a><?php endif; ?>
                <?php if ($author->social_instagram) : ?><a href="https://instagram.com/<?php echo esc_attr($author->social_instagram); ?>" target="_blank">📷</a><?php endif; ?>
                <?php if ($author->social_facebook)  : ?><a href="<?php echo esc_url($author->social_facebook); ?>" target="_blank">📘</a><?php endif; ?>
                <?php if (!$author->social_twitter && !$author->social_instagram && !$author->social_facebook) echo '—'; ?>
            </td>
            <td style="display:flex;gap:6px">
                <button class="bs-admin-btn bs-admin-btn-ghost bs-admin-btn-sm" onclick="bsEditAuthor(<?php echo esc_js(json_encode($author)); ?>)">Edit</button>
                <button class="bs-admin-btn bs-admin-btn-danger bs-admin-btn-sm" data-bs-delete="authors/<?php echo esc_attr($author->id); ?>">Delete</button>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($authors)) : ?><tr><td colspan="8" style="text-align:center;padding:32px;color:#6B7280">No authors yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add/Edit Author Modal -->
<div class="bs-admin-modal-overlay" id="bs-admin-author-modal">
    <div class="bs-admin-modal">
        <div class="bs-admin-modal-header">
            <h3>Add / Edit Author</h3>
            <button class="bs-admin-modal-close" data-bs-close-modal="bs-admin-author-modal">✕</button>
        </div>
        <div class="bs-admin-modal-body">
            <form id="bs-admin-author-form">
            <input type="hidden" id="bs-author-edit-id">
            <div class="bs-admin-form-grid">
                <div class="bs-admin-form-group bs-span-2">
                    <label class="bs-admin-label">Full Name *</label>
                    <input type="text" id="bs-author-name" class="bs-admin-input" placeholder="Author full name">
                </div>
                <div class="bs-admin-form-group">
                    <label class="bs-admin-label">Email</label>
                    <input type="email" id="bs-author-email" class="bs-admin-input">
                </div>
                <div class="bs-admin-form-group">
                    <label class="bs-admin-label">Nationality</label>
                    <input type="text" id="bs-author-nationality" class="bs-admin-input" placeholder="American, British…">
                </div>
                <div class="bs-admin-form-group">
                    <label class="bs-admin-label">Birth Date</label>
                    <input type="date" id="bs-author-birth" class="bs-admin-input">
                </div>
                <div class="bs-admin-form-group">
                    <label class="bs-admin-label">Website</label>
                    <input type="url" id="bs-author-website" class="bs-admin-input" placeholder="https://…">
                </div>
                <div class="bs-admin-form-group bs-span-2">
                    <label class="bs-admin-label">Photo URL</label>
                    <input type="url" id="bs-author-photo" class="bs-admin-input" placeholder="https://…">
                </div>
                <div class="bs-admin-form-group bs-span-2">
                    <label class="bs-admin-label">Bio</label>
                    <textarea id="bs-author-bio" class="bs-admin-textarea" rows="3" placeholder="Short biography…"></textarea>
                </div>
                <div class="bs-admin-form-group">
                    <label class="bs-admin-label">Twitter / X Handle</label>
                    <input type="text" id="bs-author-twitter" class="bs-admin-input" placeholder="username (no @)">
                </div>
                <div class="bs-admin-form-group">
                    <label class="bs-admin-label">Instagram Handle</label>
                    <input type="text" id="bs-author-instagram" class="bs-admin-input" placeholder="username (no @)">
                </div>
                <div class="bs-admin-form-group bs-span-2">
                    <label class="bs-admin-label">Facebook URL</label>
                    <input type="url" id="bs-author-facebook" class="bs-admin-input" placeholder="https://facebook.com/…">
                </div>
            </div>
            </form>
        </div>
        <div class="bs-admin-modal-footer">
            <button class="bs-admin-btn bs-admin-btn-ghost" data-bs-close-modal="bs-admin-author-modal">Cancel</button>
            <button class="bs-admin-btn bs-admin-btn-primary" id="bs-btn-save-author">Save Author</button>
        </div>
    </div>
</div>

<script>
function bsEditAuthor(a) {
    document.getElementById('bs-author-edit-id').value      = a.id;
    document.getElementById('bs-author-name').value         = a.name || '';
    document.getElementById('bs-author-email').value        = a.email || '';
    document.getElementById('bs-author-nationality').value  = a.nationality || '';
    document.getElementById('bs-author-birth').value        = a.birth_date || '';
    document.getElementById('bs-author-website').value      = a.website || '';
    document.getElementById('bs-author-photo').value        = a.photo_url || '';
    document.getElementById('bs-author-bio').value          = a.bio || '';
    document.getElementById('bs-author-twitter').value      = a.social_twitter || '';
    document.getElementById('bs-author-instagram').value    = a.social_instagram || '';
    document.getElementById('bs-author-facebook').value     = a.social_facebook || '';
    document.getElementById('bs-admin-author-modal').classList.add('open');
}
</script>
</div><!-- .wrap -->
