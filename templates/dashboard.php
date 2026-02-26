<?php
defined( 'ABSPATH' ) || exit;
$init_tab = isset($atts['tab']) ? $atts['tab'] : 'catalog';
?>
<div id="bs-app" class="bs-dashboard" data-init-tab="<?php echo esc_attr($init_tab); ?>">

    <!-- ── Header ───────────────────────────────────────────────── -->
    <div class="bs-header">
        <div class="bs-header-brand">
            <span class="bs-logo-icon">📚</span>
            <span class="bs-logo-text">BookCircle</span>
        </div>
        <div class="bs-header-actions">
            <?php if ( is_user_logged_in() ) : ?>
                <div class="bs-user-pill">
                    <?php echo get_avatar( get_current_user_id(), 32, '', '', ['class'=>'bs-avatar'] ); ?>
                    <span><?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
                </div>
            <?php else : ?>
                <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="bs-btn bs-btn-outline">
                    Sign In
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Tabs ─────────────────────────────────────────────────── -->
    <nav class="bs-tabs" role="tablist">
        <button class="bs-tab" data-tab="catalog"  role="tab" aria-selected="true">
            <span class="bs-tab-icon">🌐</span> Catalog
        </button>
        <button class="bs-tab" data-tab="search" role="tab">
            <span class="bs-tab-icon">🔍</span> Find by Code
        </button>
        <?php if ( is_user_logged_in() ) : ?>
        <button class="bs-tab" data-tab="library" role="tab">
            <span class="bs-tab-icon">📖</span> My Library
        </button>
        <button class="bs-tab" data-tab="rentals" role="tab">
            <span class="bs-tab-icon">📬</span> Rental Requests
            <span class="bs-badge" id="bs-badge-rentals" style="display:none">0</span>
        </button>
        <?php endif; ?>
    </nav>

    <!-- ── Loading ──────────────────────────────────────────────── -->
    <div class="bs-loading" id="bs-global-loading">
        <div class="bs-spinner"></div>
    </div>

    <!-- ── Toast ────────────────────────────────────────────────── -->
    <div id="bs-toast" class="bs-toast" role="alert" aria-live="polite"></div>

    <!-- ═══════════════════ CATALOG TAB ═══════════════════════════ -->
    <section class="bs-panel " id="bs-panel-catalog">
        <div class="bs-panel-toolbar">
            <div class="bs-search-wrap">
                <span class="bs-search-icon">🔎</span>
                <input type="text" id="bs-catalog-search" class="bs-input bs-search-input"
                       placeholder="Search by title, author, genre, ISBN…">
            </div>
            <select id="bs-catalog-genre" class="bs-select">
                <option value="">All Genres</option>
            </select>
            <?php if ( is_user_logged_in() ) : ?>
            <button class="bs-btn bs-btn-primary" id="bs-btn-add-book">
                <span>＋</span> Add Book
            </button>
            <?php endif; ?>
        </div>

        <div id="bs-catalog-grid" class="bs-book-grid"></div>
        <div class="bs-pagination" id="bs-catalog-pagination"></div>
    </section>

    <!-- ═══════════════════ SEARCH TAB ════════════════════════════ -->
    <section class="bs-panel" id="bs-panel-search">
        <div class="bs-search-hero">
            <h2 class="bs-search-title">Find who has a book</h2>
            <p class="bs-search-subtitle">Enter the unique 8-character book code to see community members who own it.</p>
            <div class="bs-code-search-row">
                <input type="text" id="bs-code-input" class="bs-input bs-code-input"
                       placeholder="e.g. DUNE3F9A" maxlength="10">
                <button class="bs-btn bs-btn-primary" id="bs-btn-code-search">Search</button>
            </div>
        </div>
        <div id="bs-search-results" class="bs-search-results"></div>
    </section>

    <!-- ═══════════════════ LIBRARY TAB ═══════════════════════════ -->
    <section class="bs-panel" id="bs-panel-library">
        <?php if ( is_user_logged_in() ) : ?>
        <div class="bs-panel-toolbar">
            <h2 class="bs-section-title">My Library</h2>
            <button class="bs-btn bs-btn-primary" id="bs-btn-add-to-library">
                <span>＋</span> Add from Catalog
            </button>
        </div>
        <div id="bs-library-grid" class="bs-book-grid"></div>
        <?php else : ?>
        <div class="bs-empty-state">
            <div class="bs-empty-icon">🔐</div>
            <h3>Sign in to manage your library</h3>
            <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="bs-btn bs-btn-primary">Sign In</a>
        </div>
        <?php endif; ?>
    </section>

    <!-- ═══════════════════ RENTALS TAB ═══════════════════════════ -->
    <section class="bs-panel" id="bs-panel-rentals">
        <?php if ( is_user_logged_in() ) : ?>
        <div class="bs-rentals-split">
            <div class="bs-rentals-col">
                <h3 class="bs-col-title incoming">📥 Incoming Requests</h3>
                <div id="bs-rentals-incoming"></div>
            </div>
            <div class="bs-rentals-col">
                <h3 class="bs-col-title outgoing">📤 My Requests</h3>
                <div id="bs-rentals-outgoing"></div>
            </div>
        </div>
        <?php else : ?>
        <div class="bs-empty-state">
            <div class="bs-empty-icon">🔐</div>
            <h3>Sign in to manage rentals</h3>
            <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="bs-btn bs-btn-primary">Sign In</a>
        </div>
        <?php endif; ?>
    </section>

    <!-- ═══════════════════ MODALS ════════════════════════════════ -->

    <!-- Add / Edit Book Modal -->
    <div class="bs-modal-overlay" id="bs-modal-book" style="display:none">
        <div class="bs-modal">
            <div class="bs-modal-header">
                <h3 id="bs-modal-book-title">Add New Book</h3>
                <button class="bs-modal-close" data-close="bs-modal-book">✕</button>
            </div>
            <div class="bs-modal-body">
                <input type="hidden" id="bs-book-edit-id">
                <div class="bs-form-grid">
                    <div class="bs-form-group bs-span-2">
                        <label class="bs-label">Title <span class="bs-required">*</span></label>
                        <input type="text" id="bs-book-title" class="bs-input" placeholder="Book title">
                    </div>
                    <div class="bs-form-group">
                        <label class="bs-label">Author</label>
                        <select id="bs-book-author" class="bs-select">
                            <option value="">— Select Author —</option>
                        </select>
                    </div>
                    <div class="bs-form-group">
                        <label class="bs-label">Publisher</label>
                        <select id="bs-book-publisher" class="bs-select">
                            <option value="">— Select Publisher —</option>
                        </select>
                    </div>
                    <div class="bs-form-group">
                        <label class="bs-label">Genre</label>
                        <input type="text" id="bs-book-genre" class="bs-input" placeholder="Fiction, Sci-Fi…">
                    </div>
                    <div class="bs-form-group">
                        <label class="bs-label">ISBN</label>
                        <input type="text" id="bs-book-isbn" class="bs-input" placeholder="978-…">
                    </div>
                    <div class="bs-form-group">
                        <label class="bs-label">Published Year</label>
                        <input type="number" id="bs-book-year" class="bs-input" placeholder="2024">
                    </div>
                    <div class="bs-form-group">
                        <label class="bs-label">Pages</label>
                        <input type="number" id="bs-book-pages" class="bs-input" placeholder="320">
                    </div>
                    <div class="bs-form-group">
                        <label class="bs-label">Language</label>
                        <input type="text" id="bs-book-language" class="bs-input" value="English">
                    </div>
                    <div class="bs-form-group bs-span-2">
                        <label class="bs-label">Cover URL</label>
                        <input type="url" id="bs-book-cover" class="bs-input" placeholder="https://…">
                    </div>
                    <div class="bs-form-group bs-span-2">
                        <label class="bs-label">Description</label>
                        <textarea id="bs-book-desc" class="bs-textarea" rows="3" placeholder="Short description…"></textarea>
                    </div>
                </div>
            </div>
            <div class="bs-modal-footer">
                <button class="bs-btn bs-btn-ghost" data-close="bs-modal-book">Cancel</button>
                <button class="bs-btn bs-btn-primary" id="bs-btn-save-book">Save Book</button>
            </div>
        </div>
    </div>

    <!-- Rental Request Modal -->
    <div class="bs-modal-overlay" id="bs-modal-rental" style="display:none">
        <div class="bs-modal bs-modal-sm">
            <div class="bs-modal-header">
                <h3>Request to Borrow</h3>
                <button class="bs-modal-close" data-close="bs-modal-rental">✕</button>
            </div>
            <div class="bs-modal-body">
                <input type="hidden" id="bs-rental-book-id">
                <input type="hidden" id="bs-rental-owner-id">
                <p class="bs-modal-desc">You're requesting <strong id="bs-rental-book-name"></strong></p>
                <div class="bs-form-group">
                    <label class="bs-label">Message (optional)</label>
                    <textarea id="bs-rental-message" class="bs-textarea" rows="3" placeholder="Hi, I'd love to borrow this…"></textarea>
                </div>
                <div class="bs-form-row">
                    <div class="bs-form-group">
                        <label class="bs-label">From</label>
                        <input type="date" id="bs-rental-start" class="bs-input">
                    </div>
                    <div class="bs-form-group">
                        <label class="bs-label">To</label>
                        <input type="date" id="bs-rental-end" class="bs-input">
                    </div>
                </div>
            </div>
            <div class="bs-modal-footer">
                <button class="bs-btn bs-btn-ghost" data-close="bs-modal-rental">Cancel</button>
                <button class="bs-btn bs-btn-primary" id="bs-btn-send-rental">Send Request</button>
            </div>
        </div>
    </div>

    <!-- Book Detail Modal -->
    <div class="bs-modal-overlay" id="bs-modal-detail" style="display:none">
        <div class="bs-modal bs-modal-lg">
            <button class="bs-modal-close" data-close="bs-modal-detail">✕</button>
            <div id="bs-modal-detail-content"></div>
        </div>
    </div>

</div><!-- /#bs-app -->
