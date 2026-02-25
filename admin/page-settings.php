<?php defined('ABSPATH') || exit; ?>
<div class="wrap bs-admin-wrap">
<div class="bs-admin-notices"></div>

<div class="bs-admin-header">
    <span class="bs-admin-logo">⚙️</span>
    <div>
        <h1 class="bs-admin-title">Settings</h1>
        <p class="bs-admin-subtitle">Configure BookCircle behavior</p>
    </div>
</div>

<div class="bs-admin-card">
    <div class="bs-admin-card-header">
        <h2 class="bs-admin-card-title">Plugin Settings</h2>
    </div>
    <div style="padding:24px">
        <form method="post">
            <?php wp_nonce_field('bs_settings'); ?>
            
            <div class="bs-settings-section">
                <h3>📚 Catalog</h3>
                <div class="bs-settings-row">
                    <div>
                        <div class="bs-settings-label">Books per page</div>
                        <div class="bs-settings-desc">Number of books shown per page in the catalog</div>
                    </div>
                    <input type="number" name="books_per_page" value="<?php echo esc_attr(get_option('bs_books_per_page', 12)); ?>" min="4" max="100" style="width:80px;padding:7px 10px;border:1.5px solid var(--bs-border);border-radius:7px;font-size:14px">
                </div>
            </div>

            <div class="bs-settings-section">
                <h3>🔒 Permissions</h3>
                <div class="bs-settings-row">
                    <div>
                        <div class="bs-settings-label">Allow guest browsing</div>
                        <div class="bs-settings-desc">Visitors who are not logged in can browse the catalog</div>
                    </div>
                    <input type="checkbox" name="allow_guest_browse" value="1" <?php checked(get_option('bs_allow_guest_browse', 1), 1); ?> style="width:18px;height:18px;cursor:pointer">
                </div>
                <div class="bs-settings-row">
                    <div>
                        <div class="bs-settings-label">Require admin approval for new books</div>
                        <div class="bs-settings-desc">Books added by users need admin review before appearing publicly</div>
                    </div>
                    <input type="checkbox" name="require_approval" value="1" <?php checked(get_option('bs_require_approval', 0), 1); ?> style="width:18px;height:18px;cursor:pointer">
                </div>
            </div>

            <div class="bs-settings-section">
                <h3>🔌 Shortcodes</h3>
                <p style="font-size:14px;color:#6B7280">Use these shortcodes on any page or post:</p>
                <table style="width:100%;border-collapse:collapse;font-size:14px">
                    <tr style="border-bottom:1px solid var(--bs-border)">
                        <td style="padding:10px 0;font-weight:700"><code>[bookcircle]</code></td>
                        <td style="padding:10px">Full community dashboard (all tabs in one)</td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--bs-border)">
                        <td style="padding:10px 0;font-weight:700"><code>[bookshare_catalog]</code></td>
                        <td style="padding:10px">Opens dashboard on the Catalog tab</td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--bs-border)">
                        <td style="padding:10px 0;font-weight:700"><code>[bookshare_library]</code></td>
                        <td style="padding:10px">Opens dashboard on My Library tab</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 0;font-weight:700"><code>[bookshare_search]</code></td>
                        <td style="padding:10px">Opens dashboard on Search by Code tab</td>
                    </tr>
                </table>
            </div>

            <div style="margin-top:24px">
                <button type="submit" name="bs_save_settings" class="bs-admin-btn bs-admin-btn-primary">💾 Save Settings</button>
            </div>
        </form>
    </div>
</div>

