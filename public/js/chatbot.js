(function() {
    'use strict';

    if (typeof ggChatbot === 'undefined') return;

    const API = ggChatbot.ajaxUrl;
    const config = ggChatbot;
    let sessionId = localStorage.getItem('gg_session') || generateId();
    let history = [];
    let isOpen = false;

    localStorage.setItem('gg_session', sessionId);

    // DOM elements
    const toggle = document.getElementById('gg-chatbot-toggle');
    const window_ = document.getElementById('gg-chatbot-window');
    const messages = document.getElementById('gg-chatbot-messages');
    const form = document.getElementById('gg-chatbot-form');
    const input = document.getElementById('gg-chatbot-input');
    const minimize = document.getElementById('gg-chatbot-minimize');
    const refresh = document.getElementById('gg-chatbot-refresh');
    const iconChat = toggle ? toggle.querySelector('.gg-icon-chat') : null;
    const iconClose = toggle ? toggle.querySelector('.gg-icon-close') : null;
    const botName = document.getElementById('gg-chatbot-name');

    if (botName) botName.textContent = config.botName;

    // Also init inline chatbot if present
    const inlineMessages = document.getElementById('gg-chatbot-messages-inline');
    const inlineForm = document.getElementById('gg-chatbot-form-inline');
    const inlineInput = document.getElementById('gg-chatbot-input-inline');

    // Toggle chat
    if (toggle) {
        toggle.addEventListener('click', function() {
            isOpen = !isOpen;
            window_.style.display = isOpen ? 'flex' : 'none';
            iconChat.style.display = isOpen ? 'none' : 'block';
            iconClose.style.display = isOpen ? 'block' : 'none';

            if (isOpen && messages.children.length === 0) {
                showWelcome(messages);
            }

            if (isOpen) input.focus();
        });
    }

    if (minimize) {
        minimize.addEventListener('click', function() {
            isOpen = false;
            window_.style.display = 'none';
            iconChat.style.display = 'block';
            iconClose.style.display = 'none';
        });
    }

    // Refresh / New chat
    if (refresh) {
        refresh.addEventListener('click', function() {
            // Clear messages
            messages.innerHTML = '';
            // Reset history and session
            history = [];
            sessionId = generateId();
            localStorage.setItem('gg_session', sessionId);
            // Show fresh welcome
            showWelcome(messages);
            if (input) input.focus();
        });
    }

    // Form submit (floating widget)
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            sendMessage(input, messages);
        });
    }

    // Form submit (inline)
    if (inlineForm) {
        inlineForm.addEventListener('submit', function(e) {
            e.preventDefault();
            sendMessage(inlineInput, inlineMessages);
        });

        if (inlineMessages && inlineMessages.children.length === 0) {
            showWelcome(inlineMessages);
        }
    }

    function showWelcome(container) {
        addBotMessage(container, config.welcomeMessage);
        addQuickSuggestions(container, [
            'Find addiction support',
            'Mental health services',
            'Resources in Israel',
            'Mental health apps'
        ]);
    }

    function sendMessage(inputEl, container) {
        const text = inputEl.value.trim();
        if (!text) return;

        // Add user message
        addUserMessage(container, text);
        inputEl.value = '';

        // Show typing
        const typing = addTyping(container);

        // Send to API
        fetch(API + 'chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce,
            },
            body: JSON.stringify({
                message: text,
                session_id: sessionId,
                history: history.slice(-10), // last 10 messages for context
            }),
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            removeTyping(typing);

            if (data.message) {
                addBotMessage(container, data.message);
                history.push({ role: 'user', content: text });
                history.push({ role: 'assistant', content: data.message });
            }

            // Show resource cards
            if (data.resources && data.resources.length > 0) {
                data.resources.forEach(function(r) {
                    addResourceCard(container, r);
                });
            }

            // Show app cards
            if (data.apps && data.apps.length > 0) {
                data.apps.forEach(function(a) {
                    addAppCard(container, a);
                });
            }
        })
        .catch(function() {
            removeTyping(typing);
            addBotMessage(container, "I'm sorry, something went wrong. Please try again or browse our resource directory.");
        });
    }

    function addUserMessage(container, text) {
        var div = document.createElement('div');
        div.className = 'gg-msg gg-msg-user';
        div.textContent = text;
        container.appendChild(div);
        scrollBottom(container);
    }

    function addBotMessage(container, text) {
        var div = document.createElement('div');
        div.className = 'gg-msg gg-msg-bot';
        div.innerHTML = formatMessage(text);
        container.appendChild(div);
        scrollBottom(container);
    }

    function addResourceCard(container, r) {
        var div = document.createElement('div');
        div.className = 'gg-chat-resource';

        var html = '<div class="gg-chat-resource-name">' + escapeHtml(r.name) + '</div>';
        if (r.type) html += '<div class="gg-chat-resource-type">' + escapeHtml(r.type) + '</div>';

        if (r.phone) {
            html += '<div class="gg-chat-resource-detail">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>' +
                '<span>' + escapeHtml(r.phone) + '</span></div>';
        }
        if (r.email) {
            html += '<div class="gg-chat-resource-detail">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>' +
                '<a href="mailto:' + escapeHtml(r.email) + '">' + escapeHtml(r.email) + '</a></div>';
        }
        if (r.website) {
            var url = r.website;
            if (!url.match(/^https?:\/\//)) url = 'https://' + url;
            html += '<div class="gg-chat-resource-detail">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>' +
                '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener">' + escapeHtml(r.website) + '</a></div>';
        }
        if (r.location) {
            html += '<div class="gg-chat-resource-detail">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>' +
                '<span>' + escapeHtml(r.location) + '</span></div>';
        }

        div.innerHTML = html;
        container.appendChild(div);
        scrollBottom(container);
    }

    function addAppCard(container, a) {
        var div = document.createElement('div');
        div.className = 'gg-chat-resource';

        var html = '<div class="gg-chat-resource-name">' + escapeHtml(a.title) + '</div>';
        if (a.category) html += '<div class="gg-chat-resource-type">' + escapeHtml(a.category) + '</div>';
        if (a.description) html += '<div style="font-size:13px;color:#4b5563;margin-bottom:4px">' + escapeHtml(a.description).substring(0, 150) + '</div>';
        if (a.cost) html += '<div class="gg-chat-resource-detail"><strong>Cost:</strong>&nbsp;' + escapeHtml(a.cost) + '</div>';
        if (a.platform) html += '<div class="gg-chat-resource-detail"><strong>Platform:</strong>&nbsp;' + escapeHtml(a.platform) + '</div>';

        div.innerHTML = html;
        container.appendChild(div);
        scrollBottom(container);
    }

    function addQuickSuggestions(container, suggestions) {
        var div = document.createElement('div');
        div.className = 'gg-quick-suggestions';

        suggestions.forEach(function(s) {
            var btn = document.createElement('button');
            btn.className = 'gg-quick-btn';
            btn.textContent = s;
            btn.addEventListener('click', function() {
                var inputEl = container === messages ? input : inlineInput;
                if (inputEl) {
                    inputEl.value = s;
                    sendMessage(inputEl, container);
                }
                div.remove();
            });
            div.appendChild(btn);
        });

        container.appendChild(div);
        scrollBottom(container);
    }

    function addTyping(container) {
        var div = document.createElement('div');
        div.className = 'gg-typing';
        div.innerHTML = '<div class="gg-typing-dot"></div><div class="gg-typing-dot"></div><div class="gg-typing-dot"></div>';
        container.appendChild(div);
        scrollBottom(container);
        return div;
    }

    function removeTyping(el) {
        if (el && el.parentNode) el.parentNode.removeChild(el);
    }

    function formatMessage(text) {
        // Convert **bold** to <strong>
        text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        // Convert newlines to <br>
        text = text.replace(/\n/g, '<br>');
        // Convert URLs to links
        text = text.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
        return text;
    }

    function scrollBottom(container) {
        setTimeout(function() {
            container.scrollTop = container.scrollHeight;
        }, 50);
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function generateId() {
        return 'gg_' + Math.random().toString(36).substring(2) + Date.now().toString(36);
    }

})();
