define(['jquery', 'core/templates'], function($, Templates) {
    'use strict';

    const STORAGE_PREFIX = 'ai_chat_history_';
    let currentMode = 'assistant'; // Default mode

    function getStorageKey() {
        return STORAGE_PREFIX + currentMode;
    }

    function loadHistory() {
        $('#chatbot-messages').empty();

        let history = localStorage.getItem(getStorageKey());
        if (!history) return;
        history = JSON.parse(history);

        history.forEach(msg => appendMessage(msg.text, msg.sender, false));
        scrollMessagesToBottom();
    }

    function saveMessage(text, sender) {
        let history = localStorage.getItem(getStorageKey());
        history = history ? JSON.parse(history) : [];
        history.push({ text, sender });
        localStorage.setItem(getStorageKey(), JSON.stringify(history));
    }

    function appendMessage(text, sender, store = true) {
        const className = sender === 'user' ? 'msg-user' : 'msg-bot';
        $('#chatbot-messages').append(`<div class="message ${className}">${text}</div>`);

        if (store) saveMessage(text, sender);
        scrollMessagesToBottom();
    }

    function scrollMessagesToBottom() {
        const msgBox = $('#chatbot-messages')[0];
        if (msgBox) {
            msgBox.scrollTo({ top: msgBox.scrollHeight, behavior: 'smooth' });
        }
    }

    function handleSend() {
        const input = $('#chatbot-input');
        const text = input.val().trim();
        if (!text) return;

        appendMessage(text, 'user');
        console.log(`[${currentMode}] User typed:`, text);
        input.val('');

        setTimeout(() => {
            appendMessage(`Working in ${currentMode} mode ✅`, 'bot');
        }, 600);
    }

    function handleTabSwitch(newMode) {
        if (newMode === currentMode) return;

        currentMode = newMode;
        console.log(`Switched to mode: ${currentMode}`);

        // Update tab visuals
        $('.chat-tab').removeClass('active');
        $(`.chat-tab[data-mode="${currentMode}"]`).addClass('active');

        // Update header title
        const headerText = {
            assistant: 'AI Assistant',
            qb: 'QB Generator',
            quiz: 'Quiz Generator'
        }[currentMode];
        $('.chat-header span').text(headerText);

        loadHistory();
    }

    function bindEvents() {
        $('#ai-chatbot-button').on('click', () => {
            $('#ai-chatbot-modal').toggleClass('hidden');
            $('#chatbot-input').focus();
        });

        $('#chatbot-close-btn').on('click', () => {
            $('#ai-chatbot-modal').addClass('hidden');
        });

        $('#chatbot-send-btn').on('click', handleSend);

        $('#chatbot-input').on('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleSend();
            }
        });

        $('.chat-tab').on('click', function() {
            const mode = $(this).data('mode');
            handleTabSwitch(mode);
        });
    }

    function init() {
        if (!document.getElementById('chatbot-style')) {
            $('head').append('<link id="chatbot-style" rel="stylesheet" href="'+M.cfg.wwwroot+'/local/automation/style/chatbot.css">');
        }
        
        Templates.render('local_automation/chatbot', {})
            .then(html => {
                $('body').append(html);
                bindEvents();
                loadHistory();
            })
            .catch(err => console.error("Chatbot template load failed:", err));
    }

    return { init: init };
});
