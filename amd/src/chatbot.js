// amd/src/chatbot.js
define([
    'jquery',
    'core/templates',
    'local_automation/chat_assistant',
    'local_automation/chat_qb',
    'local_automation/chat_quiz'
], function($, Templates, Assistant, QB, Quiz) {
    'use strict';

    const STORAGE_PREFIX = 'ai_chat_history_';
    let currentMode = 'assistant'; // Default mode

    // Mode-specific handlers (only assistant for now)
    const handlers = {
        assistant: Assistant,
        qb: QB,
        quiz: Quiz
    };

    // ===== History helpers =====
    function getStorageKey() {
        return STORAGE_PREFIX + currentMode;
    }

    function loadHistory() {
        $('#chatbot-messages').empty();

        let history = localStorage.getItem(getStorageKey());
        if (!history) {
            return;
        }
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

    // ===== UI helpers =====
    function appendMessage(text, sender, store = true) {
        const className = sender === 'user' ? 'msg-user' : 'msg-bot';
        $('#chatbot-messages').append(`<div class="message ${className}">${text}</div>`);

        if (store) {
            saveMessage(text, sender);
        }
        scrollMessagesToBottom();
    }

    function scrollMessagesToBottom() {
        const msgBox = $('#chatbot-messages')[0];
        if (msgBox) {
            msgBox.scrollTo({ top: msgBox.scrollHeight, behavior: 'smooth' });
        }
    }

    // helper to escape HTML to avoid XSS with user-editable data
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ===== Confirm button area (used by assistant handler) =====
    function showConfirmButton(onConfirm) {
        const area = $('#chatbot-action-area');

        area.html(`
            <div id="chatbot-edit-note">Type for any edits...</div>
            <button id="chatbot-confirm-btn">Confirm</button>
        `);

        $('#chatbot-confirm-btn').off('click').on('click', onConfirm);
    }

    function clearConfirmButton() {
        $('#chatbot-action-area').empty();
    }

    // ===== Core send logic =====
    function handleSend() {
        const input = $('#chatbot-input');
        const text = input.val().trim();
        
        // QB mode uses its own endpoint (qb_ajax.php), not chatbot_endpoint.php
        if (currentMode === 'qb' && handlers.qb && typeof handlers.qb.generate === 'function') {
            const instructions = text; // can be empty string

            // Only show a user bubble if they actually typed something
            if (instructions) {
                appendMessage(escapeHtml(instructions), 'user');
            }
        
            input.val('');
            handlers.qb.generate(text, {
                appendMessage,
                scrollMessagesToBottom,
                showConfirmButton,   // not used by QB now, but available
                clearConfirmButton,
                escapeHtml
            });
            return;
        }

        if (!text) {
            return;
        }

        appendMessage(escapeHtml(text), 'user');
        input.val('');

        // Allow mode-specific handler to transform the prompt (e.g., assistant edit flow)
        let promptToSend = text;
        const handler = handlers[currentMode];
        if (handler && typeof handler.beforeSend === 'function') {
            promptToSend = handler.beforeSend(text);
        }

        $.ajax({
            url: M.cfg.wwwroot + '/local/automation/chatbot_endpoint.php',
            method: 'POST',
            data: {
                prompt: promptToSend,
                mode: currentMode,
                sesskey: M.cfg.sesskey
            },
            success: function(res) {
                // Give mode handler a first chance to process structured automation responses
                const handler = handlers[currentMode];
                if (handler && typeof handler.handleResponse === 'function') {
                    const consumed = handler.handleResponse(res, {
                        appendMessage,
                        scrollMessagesToBottom,
                        showConfirmButton,
                        clearConfirmButton,
                        escapeHtml
                    });

                    if (consumed) {
                        return;
                    }
                }

                // Fallback plain-text handling (for qb / quiz / generic assistant replies)
                if (res.reply) {
                    const formattedReply = res.reply.replace(/\n/g, '<br>');
                    appendMessage(formattedReply, 'bot');
                } else {
                    appendMessage('⚠️ ' + (res.error || 'No response from AI'), 'bot');
                }
            },
            error: function(xhr) {
                appendMessage('❌ Failed to connect to server.', 'bot');
                console.error(xhr);
            }
        });
    }

    // ===== Tab switching =====
    function handleTabSwitch(newMode) {
        if (newMode === currentMode) {
            return;
        }

        // Reset previous mode handler (if any state)
        const oldHandler = handlers[currentMode];
        if (oldHandler && typeof oldHandler.reset === 'function') {
            oldHandler.reset();
        }

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

        // Mode-specific placeholder
        const $input = $('#chatbot-input');
        if (currentMode === 'qb') {
            $input.attr('placeholder', 'Type preferences for this question bank (optional)…');
        } else if (currentMode === 'quiz') {
            $input.attr('placeholder', 'Describe the quiz you want to generate…');
        } else {
            // Assistant default
            $input.attr('placeholder', 'Type your message…');
        }

        clearConfirmButton();
        loadHistory();

        const handler = handlers[currentMode];
        if (handler && typeof handler.onModeActivated === 'function') {
            handler.onModeActivated();
        }
    }

    // ===== Event binding =====
    function bindEvents() {
        $('#ai-chatbot-button').on('click', () => {
            $('#ai-chatbot-modal').toggleClass('hidden');
            $('#chatbot-input').focus();
        });

        $('#chatbot-close-btn').on('click', () => {
            $('#ai-chatbot-modal').addClass('hidden');
        });

        $('#chatbot-send-btn').on('click', handleSend);

        // Auto-resize input like a chat textarea
        const $input = $('#chatbot-input');
        $input.on('input', function() {
            const maxHeight = 150; // px – grows up to this height
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, maxHeight) + 'px';
        });

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

    // ===== Init =====
    // function init() {
    //     if (!document.getElementById('chatbot-style')) {
    //         $('head').append(
    //             '<link id="chatbot-style" rel="stylesheet" href="' +
    //             M.cfg.wwwroot +
    //             '/local/automation/style/chatbot.css">'
    //         );
    //     }

    //     Templates.render('local_automation/chatbot', {})
    //         .then(html => {
    //             $('body').append(html);
    //             bindEvents();
    //             loadHistory();
    //         })
    //         .catch(err => console.error('Chatbot template load failed:', err));
    // }
    function init() {
        if (!document.getElementById('chatbot-style')) {
            $('head').append(
                '<link id="chatbot-style" rel="stylesheet" href="' +
                M.cfg.wwwroot +
                '/local/automation/style/chatbot.css">'
            );
        }

        Templates.render('local_automation/chatbot', {})
            .then(html => {
                $('body').append(html);

                // 🔥 Force modal sizing so we can SEE it changed
                const $modal = $('#ai-chatbot-modal');
                $modal.css({
                    width: '720px',
                    maxWidth: '90vw',
                    height: '80vh',
                    maxHeight: '80vh'
                });

                // Slightly smaller bubbles
                $('#chatbot-messages').css({
                    fontSize: '0.85rem',
                    lineHeight: '1.4'
                });
                $('#chatbot-messages .message').css({
                    fontSize: '0.85rem',
                    lineHeight: '1.5',
                    padding: '6px 10px'
                });
                $('#chatbot-input').css({
                    fontSize: '0.85rem',
                    minHeight: '40px',
                    maxHeight: '150px',
                    resize: 'none'
                });

                bindEvents();
                loadHistory();
            })
            .catch(err => console.error('Chatbot template load failed:', err));
    }


    return { init: init };
});
