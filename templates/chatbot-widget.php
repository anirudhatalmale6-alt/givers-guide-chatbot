<?php if (!defined('ABSPATH')) exit; ?>

<div id="gg-chatbot-widget" class="gg-chatbot-widget" aria-label="Chat with Givers' Guide Assistant">
    <!-- Toggle Button -->
    <button id="gg-chatbot-toggle" class="gg-chatbot-toggle" aria-label="Open chat">
        <svg class="gg-icon-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        <svg class="gg-icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
    </button>

    <!-- Chat Window -->
    <div id="gg-chatbot-window" class="gg-chatbot-window" style="display:none">
        <!-- Header -->
        <div class="gg-chatbot-header">
            <div class="gg-chatbot-header-info">
                <div class="gg-chatbot-avatar">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
                </div>
                <div>
                    <div class="gg-chatbot-name" id="gg-chatbot-name"></div>
                    <div class="gg-chatbot-status">Online</div>
                </div>
            </div>
            <div class="gg-chatbot-header-actions">
                <button id="gg-chatbot-refresh" class="gg-chatbot-header-btn" aria-label="New chat" title="New chat">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                </button>
                <button id="gg-chatbot-minimize" class="gg-chatbot-header-btn" aria-label="Minimize chat" title="Minimize">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </button>
            </div>
        </div>

        <!-- Messages -->
        <div id="gg-chatbot-messages" class="gg-chatbot-messages">
            <!-- Messages injected here by JS -->
        </div>

        <!-- Input -->
        <div class="gg-chatbot-input-area">
            <form id="gg-chatbot-form" class="gg-chatbot-form">
                <input type="text" id="gg-chatbot-input" class="gg-chatbot-input" placeholder="Type your message..." autocomplete="off" />
                <button type="submit" class="gg-chatbot-send" aria-label="Send message">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                </button>
            </form>
            <div class="gg-chatbot-powered">Powered by Givers' Guide</div>
        </div>
    </div>
</div>
