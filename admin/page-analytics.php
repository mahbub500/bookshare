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

<!-- Top Genres -->
<?php if (!empty($top_genres)) : ?>
<div class="bs-admin-card">
    <div class="bs-admin-card-header">
        <h2 class="bs-admin-card-title">📖 Top Genres</h2>
    </div>
    <div class="bs-analytics-section">
        <div class="bs-tag-cloud">
            <?php foreach ($top_genres as $genre) : ?>
            <span class="bs-tag" style="--tag-size: <?php echo esc_attr(min(2, 0.8 + ($genre['count'] / max(1, $top_genres[0]['count'])) * 1.2)); ?>">
                <?php echo esc_html($genre['name']); ?>
                <span class="bs-tag-count"><?php echo esc_html($genre['count']); ?></span>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

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