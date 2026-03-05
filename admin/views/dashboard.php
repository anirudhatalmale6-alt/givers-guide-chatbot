<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap gg-admin">
    <h1>Givers' Guide Dashboard</h1>

    <?php settings_errors(); ?>

    <div class="gg-dashboard-cards">
        <div class="gg-card">
            <div class="gg-card-icon" style="background:#ede9fe;color:#9355ff">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="gg-card-info">
                <div class="gg-card-number"><?php echo number_format($res_count); ?></div>
                <div class="gg-card-label">Resources</div>
            </div>
        </div>

        <div class="gg-card">
            <div class="gg-card-icon" style="background:#dbeafe;color:#2563eb">
                <span class="dashicons dashicons-category"></span>
            </div>
            <div class="gg-card-info">
                <div class="gg-card-number"><?php echo number_format($cat_count); ?></div>
                <div class="gg-card-label">Categories</div>
            </div>
        </div>

        <div class="gg-card">
            <div class="gg-card-icon" style="background:#d1fae5;color:#059669">
                <span class="dashicons dashicons-smartphone"></span>
            </div>
            <div class="gg-card-info">
                <div class="gg-card-number"><?php echo number_format($app_count); ?></div>
                <div class="gg-card-label">Mental Health Apps</div>
            </div>
        </div>

        <div class="gg-card">
            <div class="gg-card-icon" style="background:#fef3c7;color:#d97706">
                <span class="dashicons dashicons-flag"></span>
            </div>
            <div class="gg-card-info">
                <div class="gg-card-number"><?php echo number_format($report_count); ?></div>
                <div class="gg-card-label">Pending Reports</div>
            </div>
        </div>

        <div class="gg-card">
            <div class="gg-card-icon" style="background:#fce7f3;color:#db2777">
                <span class="dashicons dashicons-format-chat"></span>
            </div>
            <div class="gg-card-info">
                <div class="gg-card-number"><?php echo number_format($conv_count); ?></div>
                <div class="gg-card-label">Chat Sessions</div>
            </div>
        </div>
    </div>

    <div class="gg-dashboard-info">
        <div class="gg-info-box">
            <h2>Quick Start</h2>
            <ol>
                <li><strong>Import Data:</strong> Go to <a href="<?php echo admin_url('admin.php?page=gg-import'); ?>">Import Data</a> to upload your resource spreadsheet.</li>
                <li><strong>Configure Chatbot:</strong> Go to <a href="<?php echo admin_url('admin.php?page=gg-settings'); ?>">Settings</a> to add your OpenAI API key and customize the chatbot.</li>
                <li><strong>Add Directory Page:</strong> Create a new page and add the shortcode <code>[gg_directory]</code> to display the resource directory.</li>
                <li><strong>Chatbot Widget:</strong> The floating chatbot appears on all pages automatically when enabled.</li>
            </ol>

            <h3>Available Shortcodes</h3>
            <table class="widefat">
                <thead>
                    <tr><th>Shortcode</th><th>Description</th></tr>
                </thead>
                <tbody>
                    <tr><td><code>[gg_directory]</code></td><td>Full resource directory with search and filters</td></tr>
                    <tr><td><code>[gg_directory region="usa"]</code></td><td>Directory filtered by region (usa, israel, england)</td></tr>
                    <tr><td><code>[gg_directory type="apps"]</code></td><td>Mental health apps directory</td></tr>
                    <tr><td><code>[gg_directory category="Addiction Support"]</code></td><td>Directory filtered by category</td></tr>
                    <tr><td><code>[gg_chatbot]</code></td><td>Inline chatbot (embedded in page content)</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
