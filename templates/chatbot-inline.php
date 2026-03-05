<?php if (!defined('ABSPATH')) exit; ?>
<div id="gg-chatbot-inline" class="gg-chatbot-inline">
    <div class="gg-chatbot-header">
        <div class="gg-chatbot-header-info">
            <div class="gg-chatbot-avatar">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
            </div>
            <div>
                <div class="gg-chatbot-name"><?php echo esc_html(get_option('gg_bot_name', "Givers' Guide Assistant")); ?></div>
                <div class="gg-chatbot-status">Online</div>
            </div>
        </div>
    </div>
    <div id="gg-chatbot-messages-inline" class="gg-chatbot-messages"></div>
    <div class="gg-chatbot-input-area">
        <form id="gg-chatbot-form-inline" class="gg-chatbot-form">
            <input type="text" id="gg-chatbot-input-inline" class="gg-chatbot-input" placeholder="Type your message..." autocomplete="off" />
            <button type="submit" class="gg-chatbot-send" aria-label="Send">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
            </button>
        </form>
    </div>
</div>
