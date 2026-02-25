<?php defined('ABSPATH') || exit; ?>
<div class="wrap bs-admin-wrap">
<div class="bs-admin-notices"></div>

<div class="bs-admin-header">
    <span class="bs-admin-logo">👥</span>
    <div>
        <h1 class="bs-admin-title">Community Members</h1>
        <p class="bs-admin-subtitle">Users participating in BookCircle</p>
    </div>
</div>

<div class="bs-admin-card">
    <div class="bs-admin-card-header">
        <h2 class="bs-admin-card-title">Members (<?php echo count($users); ?>)</h2>
    </div>
    <table class="bs-admin-table">
        <thead>
            <tr><th>Avatar</th><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user) :
            $roles = implode(', ', array_map('ucfirst', $user->roles));
        ?>
        <tr>
            <td><?php echo get_avatar($user->ID, 36, '', '', ['class'=>'','style'=>'border-radius:50%']); ?></td>
            <td><strong><?php echo esc_html($user->display_name); ?></strong></td>
            <td><?php echo esc_html($user->user_email); ?></td>
            <td><?php echo esc_html($roles ?: 'Subscriber'); ?></td>
            <td><?php echo esc_html(date('M j, Y', strtotime($user->user_registered))); ?></td>
            <td>
                <a class="bs-admin-btn bs-admin-btn-ghost bs-admin-btn-sm" href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>">Edit</a>
                <a class="bs-admin-btn bs-admin-btn-ghost bs-admin-btn-sm" href="<?php echo esc_url(rest_url('bookshare/v1/library/user/' . $user->ID)); ?>" target="_blank">Library</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>
