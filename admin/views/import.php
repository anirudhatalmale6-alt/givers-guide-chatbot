<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap gg-admin">
    <h1>Import Data</h1>

    <?php settings_errors('gg_import'); ?>

    <div class="gg-info-box">
        <h2>Import Resources from CSV</h2>
        <p>Upload a CSV file to import resources into the database. The CSV should have columns matching the Givers' Guide spreadsheet format.</p>
        <p><strong>Expected columns for Resources:</strong> Name, Type, Location, Location Served, Phone, Alt Phone, Fax, Director, Email, Description, Insurance Info, Website, Facebook, Instagram, Twitter, LinkedIn, Notes</p>
        <p><strong>Expected columns for Apps:</strong> Title, Description, Cost, iOS/Android, Notes</p>

        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('gg_import'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="csv_file">CSV File</label></th>
                    <td>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv" required />
                        <p class="description">Select a .csv file to import.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="import_region">Region / Type</label></th>
                    <td>
                        <select id="import_region" name="import_region">
                            <option value="usa">USA Resources</option>
                            <option value="israel">Israel Resources</option>
                            <option value="england">England Resources</option>
                            <option value="apps">Mental Health Apps</option>
                        </select>
                        <p class="description">Which region does this data belong to?</p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="gg_import_csv" class="button-primary" value="Import CSV" />
            </p>
        </form>
    </div>

    <hr />

    <div class="gg-info-box" style="border-left-color:#dc3545">
        <h2>Clear Data</h2>
        <p>Remove all imported data. This cannot be undone.</p>

        <form method="post" onsubmit="return confirm('Are you sure? This will delete all resources for the selected region.')">
            <?php wp_nonce_field('gg_clear_data'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="clear_region">Region to Clear</label></th>
                    <td>
                        <select id="clear_region" name="clear_region">
                            <option value="">All Data (everything)</option>
                            <option value="usa">USA Resources only</option>
                            <option value="israel">Israel Resources only</option>
                            <option value="england">England Resources only</option>
                            <option value="apps">Mental Health Apps only</option>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="gg_clear_data" class="button" style="color:#dc3545;border-color:#dc3545" value="Clear Data" />
            </p>
        </form>
    </div>
</div>
