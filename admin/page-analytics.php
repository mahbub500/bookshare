<?php defined('ABSPATH') || exit; ?>
<div class="wrap bs-admin-wrap">
<div class="bs-admin-notices"></div>

<div class="bs-admin-header">
    <span class="bs-admin-logo">📊</span>
    <div>
        <h1 class="bs-admin-title">Analytics Dashboard</h1>
        <p class="bs-admin-subtitle">Comprehensive insights for Books, Authors & Publishers</p>
    </div>
</div>

<!-- Overview Stats -->
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
        <span class="bs-stat-icon">📖</span>
        <div><div class="bs-stat-value"><?php echo esc_html($stats['genres']); ?></div><div class="bs-stat-label">Genres</div></div>
    </div>
</div>

<!-- Books Analytics -->
<div class="bs-admin-card">
    <div class="bs-admin-card-header">
        <h2 class="bs-admin-card-title">📚 Books Analytics</h2>
    </div>
    <div class="bs-analytics-grid">
        <div class="bs-analytics-item">
            <div class="bs-analytics-label">Books with Cover Images</div>
            <div class="bs-analytics-value"><?php echo esc_html($books_analytics['with_cover']); ?></div>
            <div class="bs-analytics-bar">
                <div class="bs-analytics-bar-fill" style="width: <?php echo esc_attr($books_analytics['cover_percent']); ?>%"></div>
            </div>
            <div class="bs-analytics-percent"><?php echo esc_html(number_format($books_analytics['cover_percent'], 1)); ?>%</div>
        </div>
        <div class="bs-analytics-item">
            <div class="bs-analytics-label">Books with ISBN</div>
            <div class="bs-analytics-value"><?php echo esc_html($books_analytics['with_isbn']); ?></div>
            <div class="bs-analytics-bar">
                <div class="bs-analytics-bar-fill" style="width: <?php echo esc_attr($books_analytics['isbn_percent']); ?>%"></div>
            </div>
            <div class="bs-analytics-percent"><?php echo esc_html(number_format($books_analytics['isbn_percent'], 1)); ?>%</div>
        </div>
        <div class="bs-analytics-item">
            <div class="bs-analytics-label">Books Linked to Authors</div>
            <div class="bs-analytics-value"><?php echo esc_html($books_analytics['with_author']); ?></div>
            <div class="bs-analytics-bar">
                <div class="bs-analytics-bar-fill" style="width: <?php echo esc_attr($books_analytics['author_percent']); ?>%"></div>
            </div>
            <div class="bs-analytics-percent"><?php echo esc_html(number_format($books_analytics['author_percent'], 1)); ?>%</div>
        </div>
        <div class="bs-analytics-item">
            <div class="bs-analytics-label">Books Linked to Publishers</div>
            <div class="bs-analytics-value"><?php echo esc_html($books_analytics['with_publisher']); ?></div>
            <div class="bs-analytics-bar">
                <div class="bs-analytics-bar-fill" style="width: <?php echo esc_attr($books_analytics['publisher_percent']); ?>%"></div>
            </div>
            <div class="bs-analytics-percent"><?php echo esc_html(number_format($books_analytics['publisher_percent'], 1)); ?>%</div>
        </div>
    </div>
    
    <?php if (!empty($top_genres)) : ?>
    <div class="bs-analytics-section">
        <h3 class="bs-analytics-section-title">Top Genres</h3>
        <div class="bs-tag-cloud">
            <?php foreach ($top_genres as $genre) : ?>
            <span class="bs-tag" style="--tag-size: <?php echo esc_attr(min(2, 0.8 + ($genre['count'] / max(1, $top_genres[0]['count'])) * 1.2)); ?>">
                <?php echo esc_html($genre['name']); ?>
                <span class="bs-tag-count"><?php echo esc_html($genre['count']); ?></span>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Authors Analytics -->
<div class="bs-admin-card">
    <div class="bs-admin-card-header">
        <h2 class="bs-admin-card-title">✍️ Authors Analytics</h2>
    </div>
    <div class="bs-analytics-grid">
        <div class="bs-analytics-item">
            <div class="bs-analytics-label">Authors with Bio</div>
            <div class="bs-analytics-value"><?php echo esc_html($authors_analytics['with_bio']); ?></div>
            <div class="bs-analytics-bar">
                <div class="bs-analytics-bar-fill" style="width: <?php echo esc_attr($authors_analytics['bio_percent']); ?>%"></div>
            </div>
            <div class="bs-analytics-percent"><?php echo esc_html(number_format($authors_analytics['bio_percent'], 1)); ?>%</div>
        </div>
        <div class="bs-analytics-item">
            <div class="bs-analytics-label">Authors with Photo</div>
            <div class="bs-analytics-value"><?php echo esc_html($authors_analytics['with_photo']); ?></div>
            <div class="bs-analytics-bar">
                <div class="bs-analytics-bar-fill" style="width: <?php echo esc_attr($authors_analytics['photo_percent']); ?>%"></div>
            </div>
            <div class="bs-analytics-percent"><?php echo esc_html(number_format($authors_analytics['photo_percent'], 1)); ?>%</div>
        </div>
        <div class="bs-analytics-item">
            <div class="bs-analytics-label">Authors with Email</div>
            <div class="bs-analytics-value"><?php echo esc_html($authors_analytics['with_email']); ?></div>
            <div class="bs-analytics-bar">
                <div class="bs-analytics-bar-fill" style="width: <?php echo esc_attr($authors_analytics['email_percent']); ?>%"></div>
            </div>
            <div class="bs-analytics-percent"><?php echo esc_html(number_format($authors_analytics['email_percent'], 1)); ?>%</div>
        </div>
        <div class="bs-analytics-item">
            <div class="bs-analytics-label">Authors with Website</div>
            <div class="bs-analytics-value"><?php echo esc_html($authors_analytics['with_website']); ?></div>
            <div class="bs-analytics-bar">
                <div class="bs-analytics-bar-fill" style="width: <?php echo esc_attr($authors_analytics['website_percent']); ?>%"></div>
            </div>
            <div class="bs-analytics-percent"><?php echo esc_html(number_format($authors_analytics['website_percent'], 1)); ?>%</div>
        </div>
    </div>
    
    <?php if (!empty($authors_by_nationality)) : ?>
    <div class="bs-analytics-section">
        <h3 class="bs-analytics-section-title">Authors by Nationality</h3>
        <div class="bs-analytics-list">
            <?php foreach (array_slice($authors_by_nationality, 0, 10) as $item) : ?>
            <div class="bs-analytics-list-item">
                <span class="bs-analytics-list-label"><?php echo esc_html($item['nationality'] ?: 'Unspecified'); ?></span>
                <span class="bs-analytics-list-count"><?php echo esc_html($item['count']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Publishers Analytics -->
<div class="bs-admin-card">
    <div class="bs-admin-card-header">
        <h2 class="bs-admin-card-title">🏢 Publishers Analytics</h2>
    </div>
    <div class="bs-analytics-grid">
        <div class="bs-analytics-item">
            <div class="bs-analytics-label">Publishers with Description</div>
            <div class="bs-analytics-value"><?php echo esc_html($publishers_analytics['with_description']); ?></div>
            <div class="bs-analytics-bar">
                <div class="bs-analytics-bar-fill" style="width: <?php echo esc_attr($publishers_analytics['description_percent']); ?>%"></div>
            </div>
            <div class="bs-analytics-percent"><?php echo esc_html(number_format($publishers_analytics['description_percent'], 1)); ?>%</div>
        </div>
        <div class="bs-analytics-item">
            <div class="bs-analytics-label">Publishers with Logo</div>
            <div class="bs-analytics-value"><?php echo esc_html($publishers_analytics['with_logo']); ?></div>
            <div class="bs-analytics-bar">
                <div class="bs-analytics-bar-fill" style="width: <?php echo esc_attr($publishers_analytics['logo_percent']); ?>%"></div>
            </div>
            <div class="bs-analytics-percent"><?php echo esc_html(number_format($publishers_analytics['logo_percent'], 1)); ?>%</div>
        </div>
        <div class="bs-analytics-item">
            <div class="bs-analytics-label">Publishers with Email</div>
            <div class="bs-analytics-value"><?php echo esc_html($publishers_analytics['with_email']); ?></div>
            <div class="bs-analytics-bar">
                <div class="bs-analytics-bar-fill" style="width: <?php echo esc_attr($publishers_analytics['email_percent']); ?>%"></div>
            </div>
            <div class="bs-analytics-percent"><?php echo esc_html(number_format($publishers_analytics['email_percent'], 1)); ?>%</div>
        </div>
        <div class="bs-analytics-item">
            <div class="bs-analytics-label">Publishers with Website</div>
            <div class="bs-analytics-value"><?php echo esc_html($publishers_analytics['with_website']); ?></div>
            <div class="bs-analytics-bar">
                <div class="bs-analytics-bar-fill" style="width: <?php echo esc_attr($publishers_analytics['website_percent']); ?>%"></div>
            </div>
            <div class="bs-analytics-percent"><?php echo esc_html(number_format($publishers_analytics['website_percent'], 1)); ?>%</div>
        </div>
    </div>
    
    <?php if (!empty($publishers_by_country)) : ?>
    <div class="bs-analytics-section">
        <h3 class="bs-analytics-section-title">Publishers by Country</h3>
        <div class="bs-analytics-list">
            <?php foreach (array_slice($publishers_by_country, 0, 10) as $item) : ?>
            <div class="bs-analytics-list-item">
                <span class="bs-analytics-list-label"><?php echo esc_html($item['country'] ?: 'Unspecified'); ?></span>
                <span class="bs-analytics-list-count"><?php echo esc_html($item['count']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Books by Author Distribution -->
<div class="bs-admin-card">
    <div class="bs-admin-card-header">
        <h2 class="bs-admin-card-title">📊 Books per Author Distribution</h2>
    </div>
    <?php if (!empty($books_per_author)) : ?>
    <div class="bs-analytics-list">
        <?php foreach (array_slice($books_per_author, 0, 15) as $item) : ?>
        <div class="bs-analytics-list-item">
            <span class="bs-analytics-list-label"><?php echo esc_html($item['author_name'] ?: 'Unassigned'); ?></span>
            <span class="bs-analytics-list-count"><?php echo esc_html($item['count']); ?> books</span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else : ?>
    <p style="padding: 20px; color: #6B7280; text-align: center;">No author data available yet.</p>
    <?php endif; ?>
</div>

<!-- Books by Publisher Distribution -->
<div class="bs-admin-card">
    <div class="bs-admin-card-header">
        <h2 class="bs-admin-card-title">📊 Books per Publisher Distribution</h2>
    </div>
    <?php if (!empty($books_per_publisher)) : ?>
    <div class="bs-analytics-list">
        <?php foreach (array_slice($books_per_publisher, 0, 15) as $item) : ?>
        <div class="bs-analytics-list-item">
            <span class="bs-analytics-list-label"><?php echo esc_html($item['publisher_name'] ?: 'Unassigned'); ?></span>
            <span class="bs-analytics-list-count"><?php echo esc_html($item['count']); ?> books</span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else : ?>
    <p style="padding: 20px; color: #6B7280; text-align: center;">No publisher data available yet.</p>
    <?php endif; ?>
</div>

</div><!-- .wrap -->
