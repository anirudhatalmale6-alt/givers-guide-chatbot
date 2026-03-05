<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap gg-admin">
    <?php settings_errors('gg_resources'); ?>

    <?php if ($editing): ?>
        <h1><?php echo $resource ? 'Edit Resource' : 'Add New Resource'; ?></h1>
        <form method="post" action="">
            <?php wp_nonce_field('gg_resource_edit'); ?>
            <input type="hidden" name="gg_save_resource" value="1" />
            <?php if ($resource): ?>
                <input type="hidden" name="resource_id" value="<?php echo $resource->id; ?>" />
            <?php endif; ?>

            <table class="form-table">
                <tr><th><label for="name">Name *</label></th>
                <td><input type="text" id="name" name="name" class="regular-text" value="<?php echo esc_attr($resource->name ?? ''); ?>" required /></td></tr>

                <tr><th><label for="type">Type</label></th>
                <td><input type="text" id="type" name="type" class="regular-text" value="<?php echo esc_attr($resource->type ?? ''); ?>" /></td></tr>

                <tr><th><label for="category_id">Category</label></th>
                <td><select id="category_id" name="category_id">
                    <option value="0">— None —</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat->id; ?>" <?php selected($resource->category_id ?? 0, $cat->id); ?>>
                            <?php echo esc_html($cat->name . ' (' . $cat->region . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select></td></tr>

                <tr><th><label for="region">Region</label></th>
                <td><select id="region" name="region">
                    <option value="usa" <?php selected($resource->region ?? 'usa', 'usa'); ?>>USA</option>
                    <option value="israel" <?php selected($resource->region ?? '', 'israel'); ?>>Israel</option>
                    <option value="england" <?php selected($resource->region ?? '', 'england'); ?>>England</option>
                </select></td></tr>

                <tr><th><label for="location">Location</label></th>
                <td><input type="text" id="location" name="location" class="regular-text" value="<?php echo esc_attr($resource->location ?? ''); ?>" /></td></tr>

                <tr><th><label for="location_served">Location Served</label></th>
                <td><input type="text" id="location_served" name="location_served" class="regular-text" value="<?php echo esc_attr($resource->location_served ?? ''); ?>" /></td></tr>

                <tr><th><label for="phone">Phone</label></th>
                <td><input type="text" id="phone" name="phone" class="regular-text" value="<?php echo esc_attr($resource->phone ?? ''); ?>" /></td></tr>

                <tr><th><label for="alt_phone">Alt. Phone</label></th>
                <td><input type="text" id="alt_phone" name="alt_phone" class="regular-text" value="<?php echo esc_attr($resource->alt_phone ?? ''); ?>" /></td></tr>

                <tr><th><label for="email">Email</label></th>
                <td><input type="email" id="email" name="email" class="regular-text" value="<?php echo esc_attr($resource->email ?? ''); ?>" /></td></tr>

                <tr><th><label for="director">Director/Manager</label></th>
                <td><input type="text" id="director" name="director" class="regular-text" value="<?php echo esc_attr($resource->director ?? ''); ?>" /></td></tr>

                <tr><th><label for="description">Description</label></th>
                <td><textarea id="description" name="description" rows="4" class="large-text"><?php echo esc_textarea($resource->description ?? ''); ?></textarea></td></tr>

                <tr><th><label for="website">Website</label></th>
                <td><input type="url" id="website" name="website" class="regular-text" value="<?php echo esc_attr($resource->website ?? ''); ?>" /></td></tr>

                <tr><th><label for="insurance_info">Insurance Info</label></th>
                <td><textarea id="insurance_info" name="insurance_info" rows="2" class="large-text"><?php echo esc_textarea($resource->insurance_info ?? ''); ?></textarea></td></tr>

                <tr><th><label for="notes">Notes</label></th>
                <td><textarea id="notes" name="notes" rows="2" class="large-text"><?php echo esc_textarea($resource->notes ?? ''); ?></textarea></td></tr>

                <tr><th>Active</th>
                <td><label><input type="checkbox" name="is_active" value="1" <?php checked($resource->is_active ?? 1, 1); ?> /> Show in directory and chatbot</label></td></tr>
            </table>

            <p class="submit">
                <input type="submit" class="button-primary" value="Save Resource" />
                <a href="<?php echo admin_url('admin.php?page=gg-resources'); ?>" class="button">Cancel</a>
            </p>
        </form>

    <?php else: ?>
        <h1>Resources
            <a href="<?php echo admin_url('admin.php?page=gg-resources&action=add'); ?>" class="page-title-action">Add New</a>
        </h1>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="notice notice-success is-dismissible"><p>Resource deleted.</p></div>
        <?php endif; ?>

        <!-- Search & Filter -->
        <form method="get" class="gg-admin-filters">
            <input type="hidden" name="page" value="gg-resources" />
            <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search resources..." class="regular-text" />
            <select name="region">
                <option value="">All Regions</option>
                <option value="usa" <?php selected($filter_region, 'usa'); ?>>USA</option>
                <option value="israel" <?php selected($filter_region, 'israel'); ?>>Israel</option>
                <option value="england" <?php selected($filter_region, 'england'); ?>>England</option>
            </select>
            <input type="submit" class="button" value="Filter" />
        </form>

        <p class="description">Showing <?php echo count($resources); ?> of <?php echo number_format($total); ?> resources (page <?php echo $page; ?>/<?php echo $total_pages; ?>)</p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:25%">Name</th>
                    <th style="width:15%">Type</th>
                    <th style="width:12%">Category</th>
                    <th style="width:8%">Region</th>
                    <th style="width:15%">Phone</th>
                    <th style="width:15%">Location</th>
                    <th style="width:10%">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($resources)): ?>
                    <tr><td colspan="7">No resources found. <a href="<?php echo admin_url('admin.php?page=gg-import'); ?>">Import data</a> to get started.</td></tr>
                <?php else: ?>
                    <?php foreach ($resources as $r): ?>
                    <tr>
                        <td><strong><a href="<?php echo admin_url('admin.php?page=gg-resources&action=edit&id=' . $r->id); ?>"><?php echo esc_html($r->name); ?></a></strong></td>
                        <td><?php echo esc_html($r->type); ?></td>
                        <td><?php echo esc_html($r->category_name ?? '—'); ?></td>
                        <td><span class="gg-badge gg-badge-<?php echo esc_attr($r->region); ?>"><?php echo esc_html(strtoupper($r->region)); ?></span></td>
                        <td><?php echo esc_html($r->phone); ?></td>
                        <td><?php echo esc_html(wp_trim_words($r->location, 5)); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=gg-resources&action=edit&id=' . $r->id); ?>">Edit</a> |
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=gg-resources&action=delete_resource&id=' . $r->id), 'gg_delete_resource'); ?>" onclick="return confirm('Delete this resource?')" style="color:#dc3545">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links([
                    'base' => admin_url('admin.php?page=gg-resources&paged=%#%' . ($search ? '&s=' . urlencode($search) : '') . ($filter_region ? '&region=' . urlencode($filter_region) : '')),
                    'format' => '',
                    'current' => $page,
                    'total' => $total_pages,
                ]);
                ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
