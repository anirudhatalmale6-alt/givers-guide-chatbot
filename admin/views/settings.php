<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap gg-admin">
    <h1>Givers' Guide Settings</h1>

    <?php settings_errors('gg_settings'); ?>

    <form method="post">
        <?php wp_nonce_field('gg_settings'); ?>

        <h2>Chatbot Settings</h2>
        <table class="form-table">
            <tr>
                <th><label for="gg_chatbot_enabled">Enable Chatbot</label></th>
                <td>
                    <label>
                        <input type="checkbox" id="gg_chatbot_enabled" name="gg_chatbot_enabled" value="1" <?php checked(get_option('gg_chatbot_enabled', '1'), '1'); ?> />
                        Show floating chatbot widget on all pages
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="gg_bot_name">Bot Name</label></th>
                <td>
                    <input type="text" id="gg_bot_name" name="gg_bot_name" class="regular-text" value="<?php echo esc_attr(get_option('gg_bot_name', "Givers' Guide Assistant")); ?>" />
                </td>
            </tr>
            <tr>
                <th><label for="gg_welcome_message">Welcome Message</label></th>
                <td>
                    <textarea id="gg_welcome_message" name="gg_welcome_message" rows="3" class="large-text"><?php echo esc_textarea(get_option('gg_welcome_message', '')); ?></textarea>
                    <p class="description">The first message shown when users open the chatbot.</p>
                </td>
            </tr>
        </table>

        <h2>AI Settings</h2>
        <table class="form-table">
            <tr>
                <th><label for="gg_openai_api_key">OpenAI API Key</label></th>
                <td>
                    <input type="password" id="gg_openai_api_key" name="gg_openai_api_key" class="regular-text" value="<?php echo esc_attr(get_option('gg_openai_api_key', '')); ?>" />
                    <p class="description">Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a>. Without a key, the chatbot will use keyword-based search.</p>
                </td>
            </tr>
            <tr>
                <th><label for="gg_openai_model">AI Model</label></th>
                <td>
                    <select id="gg_openai_model" name="gg_openai_model">
                        <option value="gpt-4o-mini" <?php selected(get_option('gg_openai_model', 'gpt-4o-mini'), 'gpt-4o-mini'); ?>>GPT-4o Mini (fast, affordable)</option>
                        <option value="gpt-4o" <?php selected(get_option('gg_openai_model'), 'gpt-4o'); ?>>GPT-4o (best quality)</option>
                        <option value="gpt-3.5-turbo" <?php selected(get_option('gg_openai_model'), 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo (cheapest)</option>
                    </select>
                    <p class="description">GPT-4o Mini is recommended — good balance of quality and cost.</p>
                </td>
            </tr>
        </table>

        <h2>Appearance</h2>
        <table class="form-table">
            <tr>
                <th><label for="gg_primary_color">Primary Color</label></th>
                <td>
                    <input type="color" id="gg_primary_color" name="gg_primary_color" value="<?php echo esc_attr(get_option('gg_primary_color', '#9355ff')); ?>" />
                    <code><?php echo esc_html(get_option('gg_primary_color', '#9355ff')); ?></code>
                </td>
            </tr>
            <tr>
                <th><label for="gg_accent_color">Accent Color</label></th>
                <td>
                    <input type="color" id="gg_accent_color" name="gg_accent_color" value="<?php echo esc_attr(get_option('gg_accent_color', '#4bfada')); ?>" />
                    <code><?php echo esc_html(get_option('gg_accent_color', '#4bfada')); ?></code>
                </td>
            </tr>
        </table>

        <h2>Directory Settings</h2>
        <table class="form-table">
            <tr>
                <th><label for="gg_results_per_page">Results Per Page</label></th>
                <td>
                    <input type="number" id="gg_results_per_page" name="gg_results_per_page" min="4" max="50" value="<?php echo esc_attr(get_option('gg_results_per_page', '12')); ?>" />
                </td>
            </tr>
        </table>

        <h2>Report Settings</h2>
        <table class="form-table">
            <tr>
                <th><label for="gg_report_email">Report Notification Email</label></th>
                <td>
                    <input type="email" id="gg_report_email" name="gg_report_email" class="regular-text" value="<?php echo esc_attr(get_option('gg_report_email', get_option('admin_email'))); ?>" />
                    <p class="description">Email address to receive notifications when users report incorrect information.</p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="gg_save_settings" class="button-primary" value="Save Settings" />
        </p>
    </form>
</div>
