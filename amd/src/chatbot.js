// amd/src/chatbot.js
define([
    'jquery',
    'core/templates',
    'local_automation/chat_assistant',
    'local_automation/chat_qb',
    'local_automation/chat_quiz',
    'local_automation/chat_student'
], function($, Templates, Assistant, QB, Quiz, Student) {
    'use strict';

    const STORAGE_PREFIX = 'ai_chat_history_';
    let currentMode = 'assistant'; // Default mode

    // Page-level config passed from PHP (accessible to all functions)
    let pageConfig = {};

    // Mode-specific handlers (only assistant for now)
    const handlers = {
    assistant: Assistant,
    qb: QB,
    quiz: Quiz,
    student: Student,
    analytics: {}
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
    function appendMessage(text, sender, timestamp, store = true) {

            console.log("APPEND CALLED →", {
            text,
            sender,
            timestamp,
            type: typeof timestamp
        });

        if (!timestamp) {
            console.warn("⚠️ TIMESTAMP IS INVALID:", timestamp);
        }

        const dateObj = new Date(parseInt(timestamp) * 1000);
        const time = dateObj.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit'
        });

        const dateString = dateObj.toDateString();
    
        const container = $('#chatbot-messages');
    
        const lastDate = container.data('last-date');
    
        // Add date separator if new day
        if (lastDate !== dateString) {
            container.append(`
                <div class="chat-date-separator">
                    ${dateString}
                </div>
            `);
            container.data('last-date', dateString);
        }
    
        const className = sender === 'user' ? 'msg-user' : 'msg-bot';
    
        const messageHtml = `
            <div class="message ${className}">
                <div class="message-text">${text}</div>
                <div class="message-time">${time}</div>
            </div>
        `;
    
        container.append(messageHtml);
    
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

        if (currentMode === 'student') {
            if (!text) return;

            // appendMessage(escapeHtml(text), 'user', false);
            input.val('');

            Student.saveMessageToDB(text, 'user').then(function(res) {
                if (typeof res === 'string') {
                    res = JSON.parse(res);
                }
            
                appendMessage(escapeHtml(text), 'user', parseInt(res.timecreated), false);
            
                Student.askRAG(text).then(function(res) {
                
                    if (res && res.ok && res.answer) {
                        const reply = res.answer.replace(/\n/g, '<br>');
                        // appendMessage(reply, 'bot', false);
                        // Student.saveMessageToDB(res.answer, 'bot');
                        Student.saveMessageToDB(res.answer, 'bot').then(function(botRes) {

                            if (typeof botRes === 'string') {
                                botRes = JSON.parse(botRes);
                            }
                        
                            appendMessage(reply, 'bot', parseInt(botRes.timecreated), false);
                        });
                    } else {
                        appendMessage('⚠️ Tutor failed to respond.', 'bot', false);
                    }
                
                }).catch(function(err) {
                    console.error(err);
                    appendMessage('❌ AI service unavailable.', 'bot', false);
                });
            
            });
        
            return;
        }
        
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

        if (currentMode === 'quiz' && handlers.quiz && typeof handlers.quiz.generate === 'function') {
            const instructions = text;

            if (instructions) {
                appendMessage(escapeHtml(instructions), 'user');
            }
        
            input.val('');
            handlers.quiz.generate(text, {
                appendMessage,
                scrollMessagesToBottom,
                showConfirmButton,
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

    //analytics helper
    function loadStudentDetails(studentid) {
        $('#chatbot-messages').html('<div>Loading details...</div>');

        // First fetch quiz attempts
        $.ajax({
            url: M.cfg.wwwroot + '/local/automation/analytics_ajax.php',
            method: 'POST',
            data: {
                action: 'get_student_quiz',
                studentid: studentid,
                courseid: pageConfig.currentcourseid,
                sesskey: M.cfg.sesskey
            },
            success: function(quizzes) {

                let html = '<h4>Quiz Attempts</h4>';

                if (quizzes.length === 0) {
                    html += '<div>No quiz attempts found.</div>';
                } else {
                    quizzes.forEach(q => {
                        html += `
                            <div>
                                Score: ${q.score}/${q.total}
                                | Difficulty: ${q.difficulty}
                                | Topic: ${q.topic}
                            </div>
                        `;
                    });
                }

                // Add Chat History section placeholder
                html += `
                    <h4 style="margin-top:20px;">Chat History</h4>
                    <div id="analytics-chat-history"
                         style="max-height:200px; overflow-y:auto; border:1px solid #ccc; padding:10px;">
                        Loading chat history...
                    </div>
                `;

                $('#chatbot-messages').html(html);

                // Now fetch chat history
                fetchStudentChat(studentid);
            }
        });
    }

    function fetchStudentChat(studentid) {
        $.ajax({
            url: M.cfg.wwwroot + '/local/automation/analytics_ajax.php',
            method: 'POST',
            data: {
                action: 'get_student_chat',
                studentid: studentid,
                courseid: pageConfig.currentcourseid,
                sesskey: M.cfg.sesskey
            },
            success: function(messages) {

                let chatHtml = '';

                if (messages.length === 0) {
                    chatHtml = '<div>No chat history found.</div>';
                } else {
                    messages.forEach(msg => {
                        const senderLabel = msg.sender === 'user' ? 'Student' : 'AI';
                        chatHtml += `
                            <div style="margin-bottom:8px;">
                                <strong>${senderLabel}:</strong>
                                ${msg.message}
                            </div>
                        `;
                    });
                }

                $('#analytics-chat-history').html(chatHtml);
            }
        });
    }

    // ===== Tab switching =====
    function handleTabSwitch(newMode) {
        if (newMode === currentMode) {
            return;
        }
        console.log("TAB SWITCH TO:", newMode);

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
        } else if (currentMode === 'analytics') {
            // hide input area for analytics mode
            const $inputArea = $('.chat-input-area');
            $inputArea.hide();
            $input.attr('placeholder', ''); // no placeholder

            // Load analytics content
            $('#chatbot-messages').html('<div>Loading students...</div>');

            $.ajax({
                url: M.cfg.wwwroot + '/local/automation/analytics_ajax.php',
                method: 'POST',
                data: {
                    action: 'get_students',
                    courseid: pageConfig.currentcourseid,
                    sesskey: M.cfg.sesskey
                },
                success: function(students) {
                    let html = '<h4>Enrolled Students</h4>';
                    students.forEach(s => {
                        const name = escapeHtml((s.firstname || '') + ' ' + (s.lastname || ''));
                        html += `
                            <div class="analytics-student" data-studentid="${escapeHtml(String(s.id))}">
                                ${name}
                            </div>
                        `;
                    });

                    $('#chatbot-messages').html(html);

                    // delegated click handler (safer)
                    $('#chatbot-messages').off('click', '.analytics-student');
                    $('#chatbot-messages').on('click', '.analytics-student', function() {
                        const studentid = $(this).data('studentid');
                        loadStudentDetails(studentid);
                    });
                },
                error: function(xhr) {
                    $('#chatbot-messages').html('<div>Error fetching students.</div>');
                }
            });

            clearConfirmButton();
            return;
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
        // Unbind first to avoid duplicate handlers if init runs twice
        $('#ai-chatbot-button').off('click');
        $('#chatbot-close-btn').off('click');
        $('#chatbot-send-btn').off('click');
        $('#chatbot-input').off('input keypress');
        $('.chat-tab').off('click');

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

    // Helper to set up the UI after template is present (idempotent)
    function setupUIAndActivate() {
        // ensure modal element exists; if not, render caller handles append
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
    }

    // ===== Init =====
    function init(cfg) {
        // Backwards-compat: if called as init("student","21","21")
        if (typeof cfg === 'string') {
            // cfg is role string; build the config object from arguments
            const role = cfg;
            const currentcourseid = (typeof arguments[1] !== 'undefined') ? arguments[1] : 0;
            const democourseid = (typeof arguments[2] !== 'undefined') ? arguments[2] : '';
            cfg = {
                role: role,
                currentcourseid: parseInt(currentcourseid) || 0,
                democourseid: String(democourseid)
            };
            console.warn('chatbot.init() called in legacy form — normalized config:', cfg);
        }

        console.trace('INIT TRACE');
        console.log('INIT CALLED WITH:', cfg);

        // Normalize and store page-level config (module scope)
        pageConfig = cfg || {};
        const config = pageConfig; // alias used inside init
        console.log("CONFIG TYPE:", typeof config);
        console.log("CONFIG VALUE:", config);

        // only render template if it doesn't already exist
        if (!document.getElementById('ai-chatbot-modal')) {
            Templates.render('local_automation/chatbot', {})
                .then(html => {
                    $('body').append(html);
                    setupUIAndActivate();
                    activateModeAfterRender(config);
                })
                .catch(err => console.error('Chatbot template load failed:', err));
        } else {
            // already present in DOM — just set up UI and activate mode
            setupUIAndActivate();
            activateModeAfterRender(config);
        }

        console.log("SESSKEY:", M.cfg.sesskey);
    }

    // helper to pick student/teacher flow after template present
    function activateModeAfterRender(config) {
        // For safety, ensure Student knows config
        if (config && config.role === 'student') {
            console.log("INSIDE STUDENT BLOCK");

            // If outside demo course → disable button
            if (parseInt(config.currentcourseid) !== parseInt(config.democourseid)) {
                $('#ai-chatbot-button').off('click');
                return;
            }

            currentMode = 'student';
            $('.chat-tabs').hide();
            $('.chat-header span').text('AI Course Tutor');
            $('#chatbot-input').attr('placeholder', 'Ask about this course…');

            Student.initConfig(config);

            // Make input area flex (ensures proper alignment)
            $('.chat-input-area').css({
                display: 'flex',
                alignItems: 'center',
                gap: '8px'
            });

            // Remove old action-area button
            $('#chatbot-action-area').empty();

            // Add Take Quiz icon button to LEFT of input
            if (!$('#chatbot-take-quiz-btn').length) {
                $('.chat-input-area').prepend(`
                    <button id="chatbot-take-quiz-btn"
                            type="button"
                            title="Take Quiz"
                            aria-label="Take Quiz">
                        ✍️
                    </button>
                `);
            }

            // Keep SAME redirect logic as before
            $('#chatbot-take-quiz-btn').off('click').on('click', function () {
                window.location.href =
                    M.cfg.wwwroot +
                    '/local/automation/student_quiz.php?courseid=' +
                    config.currentcourseid;
            });

            // Modify Send button styling + icon
            $('#chatbot-send-btn')
                .html('✉️')
                .attr('title', 'Send Message')
                .attr('aria-label', 'Send Message')
                .css({
                    borderRadius: '14px',
                    padding: '6px 14px',
                    fontSize: '18px'
                });

            Student.loadHistory(appendMessage);

        } else {
            // Teacher → use localStorage history
            $('.chat-tabs').show();
            $('.chat-header span').text('AI Assistant');
            currentMode = 'assistant';
            loadHistory();
        }
    }

    return { init: init };
});
