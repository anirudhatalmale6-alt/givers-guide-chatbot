<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap gg-admin">
    <h1>Categories</h1>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Category Name</th>
                <th>Slug</th>
                <th>Region</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($categories)): ?>
                <tr><td colspan="3">No categories found. <a href="<?php echo admin_url('admin.php?page=gg-import'); ?>">Import data</a> to create categories.</td></tr>
            <?php else: ?>
                <?php foreach ($categories as $cat): ?>
                <tr>
                    <td><strong><?php echo esc_html($cat->name); ?></strong></td>
                    <td><code><?php echo esc_html($cat->slug); ?></code></td>
                    <td><span class="gg-badge gg-badge-<?php echo esc_attr($cat->region); ?>"><?php echo esc_html(strtoupper($cat->region)); ?></span></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
