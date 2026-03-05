<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap gg-admin">
    <h1>Reports (<?php echo $pending_count; ?> pending)</h1>

    <?php settings_errors('gg_reports'); ?>

    <ul class="subsubsub">
        <li><a href="<?php echo admin_url('admin.php?page=gg-reports'); ?>" <?php echo empty($status) ? 'class="current"' : ''; ?>>All (<?php echo $total_count; ?>)</a> |</li>
        <li><a href="<?php echo admin_url('admin.php?page=gg-reports&status=pending'); ?>" <?php echo $status === 'pending' ? 'class="current"' : ''; ?>>Pending (<?php echo $pending_count; ?>)</a> |</li>
        <li><a href="<?php echo admin_url('admin.php?page=gg-reports&status=resolved'); ?>" <?php echo $status === 'resolved' ? 'class="current"' : ''; ?>>Resolved</a></li>
    </ul>

    <table class="wp-list-table widefat fixed striped" style="margin-top:16px">
        <thead>
            <tr>
                <th style="width:5%">ID</th>
                <th style="width:10%">Resource</th>
                <th style="width:12%">Issue Type</th>
                <th style="width:25%">Description</th>
                <th style="width:12%">Reporter</th>
                <th style="width:10%">Status</th>
                <th style="width:12%">Date</th>
                <th style="width:14%">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reports)): ?>
                <tr><td colspan="8">No reports found.</td></tr>
            <?php else: ?>
                <?php foreach ($reports as $r): ?>
                <tr>
                    <td>#<?php echo $r->id; ?></td>
                    <td>
                        <?php
                        $res = GG_Database::get_resource($r->resource_id);
                        echo $res ? '<a href="' . admin_url('admin.php?page=gg-resources&action=edit&id=' . $r->resource_id) . '">' . esc_html($res->name) . '</a>' : '#' . $r->resource_id;
                        ?>
                    </td>
                    <td><?php echo esc_html(str_replace('_', ' ', $r->issue_type)); ?></td>
                    <td><?php echo esc_html($r->description); ?></td>
                    <td>
                        <?php echo esc_html($r->reporter_name ?: '—'); ?>
                        <?php if ($r->reporter_email): ?><br><small><?php echo esc_html($r->reporter_email); ?></small><?php endif; ?>
                    </td>
                    <td>
                        <span class="gg-badge gg-badge-<?php echo esc_attr($r->status); ?>">
                            <?php echo esc_html(ucfirst($r->status)); ?>
                        </span>
                    </td>
                    <td><?php echo date('M j, Y', strtotime($r->created_at)); ?></td>
                    <td>
                        <?php if ($r->status === 'pending'): ?>
                        <form method="post" style="display:inline">
                            <?php wp_nonce_field('gg_report_action'); ?>
                            <input type="hidden" name="gg_update_report" value="1" />
                            <input type="hidden" name="report_id" value="<?php echo $r->id; ?>" />
                            <input type="hidden" name="status" value="resolved" />
                            <button type="submit" class="button button-small">Mark Resolved</button>
                        </form>
                        <form method="post" style="display:inline">
                            <?php wp_nonce_field('gg_report_action'); ?>
                            <input type="hidden" name="gg_update_report" value="1" />
                            <input type="hidden" name="report_id" value="<?php echo $r->id; ?>" />
                            <input type="hidden" name="status" value="dismissed" />
                            <button type="submit" class="button button-small" style="color:#dc3545">Dismiss</button>
                        </form>
                        <?php else: ?>
                            <?php echo esc_html(ucfirst($r->status)); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
