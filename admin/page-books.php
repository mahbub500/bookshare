<?php defined('ABSPATH') || exit; ?>
<div class="wrap bs-admin-wrap">
<div class="bs-admin-notices"></div>

<div class="bs-admin-header">
    <span class="bs-admin-logo">📚</span>
    <div>
        <h1 class="bs-admin-title">BookCircle</h1>
        <p class="bs-admin-subtitle">Community Book Sharing</p>
    </div>
</div>

<!-- Stats -->
<div class="bs-stat-cards">
    <div class="bs-stat-card">
        <span class="bs-stat-icon">📚</span>
        <div><div class="bs-stat-value"><?php echo esc_html($stats['books']); ?></div><div class="bs-stat-label">Total Books</div></div>
    </div>
    <div class="bs-stat-card">
        <span class="bs-stat-icon">✍️</span>
        <div><div class="bs-stat-value"><?php echo esc_html($stats['authors']); ?></div><div class="bs-stat-label">Authors</div></div>
    </div>
    <div class="bs-stat-card">
        <span class="bs-stat-icon">🏢</span>
        <div><div class="bs-stat-value"><?php echo esc_html($stats['publishers']); ?></div><div class="bs-stat-label">Publishers</div></div>
    </div>
    <div class="bs-stat-card">
        <span class="bs-stat-icon">📬</span>
        <div><div class="bs-stat-value"><?php echo esc_html($stats['rentals']); ?></div><div class="bs-stat-label">Rental Requests</div></div>
    </div>
</div>

<!-- Books Table -->
<div class="bs-admin-card">
    <div class="bs-admin-card-header">
        <h2 class="bs-admin-card-title">📖 Book Catalog</h2>
        <button class="bs-admin-btn bs-admin-btn-primary" id="bs-btn-add-book-admin">＋ Add Book</button>
    </div>
    <table class="bs-admin-table">
        <thead>
            <tr>
                <th>Cover</th>
                <th>Title</th>
                <th>Unique Code</th>
                <th>Author</th>
                <th>Genre</th>
                <th>Year</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($books as $book) : ?>
        <tr>
            <td><?php if ($book->cover_url) : ?><img src="<?php echo esc_url($book->cover_url); ?>" class="bs-book-cover-thumb" alt=""><?php else : ?>📚<?php endif; ?></td>
            <td><strong><?php echo esc_html($book->title); ?></strong></td>
            <td><code style="font-weight:700;color:#5B5EDE"><?php echo esc_html($book->unique_code); ?></code></td>
            <td><?php echo esc_html($book->author_name ?: '—'); ?></td>
            <td><?php echo esc_html($book->genre ?: '—'); ?></td>
            <td><?php echo esc_html($book->published_year ?: '—'); ?></td>
            <td style="display:flex;gap:6px;align-items:center">
                <button class="bs-admin-btn bs-admin-btn-ghost bs-admin-btn-sm" data-edit-book="<?php echo esc_attr($book->id); ?>">Edit</button>
                <button class="bs-admin-btn bs-admin-btn-danger bs-admin-btn-sm" data-bs-delete="books/<?php echo esc_attr($book->id); ?>">Delete</button>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($books)) : ?><tr><td colspan="7" style="text-align:center;padding:32px;color:#6B7280">No books yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add/Edit Book Modal -->
<div class="bs-admin-modal-overlay" id="bs-admin-book-modal">
    <div class="bs-admin-modal">
        <div class="bs-admin-modal-header">
            <h3 id="bs-admin-book-modal-title">Add Book</h3>
            <button class="bs-admin-modal-close" data-bs-close-modal="bs-admin-book-modal">✕</button>
        </div>
        <div class="bs-admin-modal-body">
            <form id="bs-admin-book-form">
            <input type="hidden" id="bs-book-edit-id">
            <div class="bs-admin-form-grid">
                <div class="bs-admin-form-group bs-span-2">
                    <label class="bs-admin-label">Title *</label>
                    <input type="text" id="bs-admin-book-title" class="bs-admin-input" placeholder="Book title">
                </div>
                <div class="bs-admin-form-group">
                    <label class="bs-admin-label">Author</label>
                    <select id="bs-admin-book-author" class="bs-admin-select">
                        <option value="">— Select —</option>
                        <?php foreach ($authors as $a) : ?><option value="<?php echo esc_attr($a->id); ?>"><?php echo esc_html($a->name); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="bs-admin-form-group">
                    <label class="bs-admin-label">Publisher</label>
                    <select id="bs-admin-book-publisher" class="bs-admin-select">
                        <option value="">— Select —</option>
                        <?php foreach ($publishers as $p) : ?><option value="<?php echo esc_attr($p->id); ?>"><?php echo esc_html($p->name); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="bs-admin-form-group">
                    <label class="bs-admin-label">Genre</label>
                    <input type="text" id="bs-admin-book-genre" class="bs-admin-input" placeholder="Fiction, Sci-Fi…">
                </div>
                <div class="bs-admin-form-group">
                    <label class="bs-admin-label">ISBN</label>
                    <input type="text" id="bs-admin-book-isbn" class="bs-admin-input" placeholder="978-…">
                </div>
                <div class="bs-admin-form-group">
                    <label class="bs-admin-label">Published Year</label>
                    <input type="number" id="bs-admin-book-year" class="bs-admin-input" placeholder="2024">
                </div>
                <div class="bs-admin-form-group">
                    <label class="bs-admin-label">Pages</label>
                    <input type="number" id="bs-admin-book-pages" class="bs-admin-input">
                </div>
                <div class="bs-admin-form-group">
                    <label class="bs-admin-label">Language</label>
                    <input type="text" id="bs-admin-book-language" class="bs-admin-input" value="English">
                </div>
                <div class="bs-admin-form-group bs-span-2">
                    <label class="bs-admin-label">Cover URL</label>
                    <input type="url" id="bs-admin-book-cover" class="bs-admin-input" placeholder="https://…">
                </div>
                <div class="bs-admin-form-group bs-span-2">
                    <label class="bs-admin-label">Description</label>
                    <textarea id="bs-admin-book-desc" class="bs-admin-textarea" rows="3"></textarea>
                </div>
            </div>
            </form>
        </div>
        <div class="bs-admin-modal-footer">
            <button class="bs-admin-btn bs-admin-btn-ghost" data-bs-close-modal="bs-admin-book-modal">Cancel</button>
            <button class="bs-admin-btn bs-admin-btn-primary" id="bs-btn-save-book-admin">Save Book</button>
        </div>
    </div>
</div>

</div><!-- .wrap -->
