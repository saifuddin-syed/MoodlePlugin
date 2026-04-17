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
    function appendMessage(text, sender, timestamp = Math.floor(Date.now() / 1000), store = true) {
        const dateObj = new Date(timestamp * 1000);
        const time = dateObj.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit'
        });

        const dateString = dateObj.toDateString();

        console.log("APPEND CALLED →", {
            text,
            sender,
            timestamp,
            type: typeof timestamp
        });

        if (!timestamp || isNaN(timestamp)) {
            timestamp = Math.floor(Date.now() / 1000);
        }

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

            input.val('');
            // Reset textarea height
            input[0].style.height = 'auto';

            Student.saveMessageToDB(text, 'user').then(function(res) {
                if (typeof res === 'string') {
                    res = JSON.parse(res);
                }

                appendMessage(escapeHtml(text), 'user', parseInt(res.timecreated), false);

                Student.askRAG(text).then(function(res) {

                    if (res && res.ok && res.answer) {
                        const reply = res.answer.replace(/\n/g, '<br>');
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
            const instructions = text;

            if (instructions) {
                appendMessage(escapeHtml(instructions), 'user');
            }

            input.val('');
            handlers.qb.generate(text, {
                appendMessage,
                scrollMessagesToBottom,
                showConfirmButton,
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

    // ===== Analytics helper =====
    function loadStudentDetails(studentid, studentName) {

        $('#chatbot-messages').html('<div>Loading details...</div>');

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

                let html = `
                    <div class="analytics-header">
                        <button id="analytics-back-btn">← Back</button>
                        <div class="analytics-student-name">${escapeHtml(studentName)}</div>
                    </div>
                `;

                html += `<h4>Quiz Attempts</h4>`;

                if (quizzes.length === 0) {
                    html += `<div class="analytics-empty">No quiz attempts found.</div>`;
                } else {

                    html += `
                        <div class="analytics-table-wrapper">
                            <table class="analytics-table">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Score</th>
                                        <th>Topic</th>
                                        <th>Difficulty</th>
                                        <th>Recommendation</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;

                    quizzes.forEach(q => {

                        const date = new Date(parseInt(q.timecreated) * 1000)
                            .toLocaleString();

                        html += `
                            <tr>
                                <td>${date}</td>
                                <td>${q.score}/${q.total}</td>
                                <td>${escapeHtml(q.topic)}</td>
                                <td>${escapeHtml(q.difficulty)}</td>
                                <td>${escapeHtml(q.recommendation)}</td>
                            </tr>
                        `;
                    });

                    html += `
                                </tbody>
                            </table>
                        </div>
                    `;
                }

                html += `
                    <h4 style="margin-top:20px;">Chat History</h4>
                    <div id="analytics-chat-history" class="analytics-chat-box">
                        Loading chat history...
                    </div>
                `;

                $('#chatbot-messages').html(html);

                $('#analytics-back-btn').on('click', function() {

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

                            let html = `
                                <div class="analytics-student-list">
                                    <h4>Enrolled Students</h4>
                            `;

                            students.forEach(s => {
                                const name = escapeHtml((s.firstname || '') + ' ' + (s.lastname || ''));
                                html += `
                                    <div class="analytics-student"
                                         data-studentid="${escapeHtml(String(s.id))}">
                                        ${name}
                                    </div>
                                `;
                            });

                            html += `</div>`;

                            $('#chatbot-messages').html(html);
                        }
                    });
                });

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

        const oldHandler = handlers[currentMode];
        if (oldHandler && typeof oldHandler.reset === 'function') {
            oldHandler.reset();
        }

        currentMode = newMode;
        console.log(`Switched to mode: ${currentMode}`);

        $('.chat-tab').removeClass('active');
        $(`.chat-tab[data-mode="${currentMode}"]`).addClass('active');

        const headerText = {
            assistant: 'AI Assistant',
            qb: 'QB Generator',
            quiz: 'Quiz Generator'
        }[currentMode];
        $('#chat-title').text(headerText);

        const $input = $('#chatbot-input');
        if (currentMode === 'qb') {
            $input.attr('placeholder', 'Type preferences for this question bank (optional)…');
        } else if (currentMode === 'quiz') {
            $input.attr('placeholder', 'Describe the quiz you want to generate…');
        } else {
            $input.attr('placeholder', 'Type your message…');
        }

        clearConfirmButton();
        loadHistory();

        const handler = handlers[currentMode];
        if (handler && typeof handler.onModeActivated === 'function') {
            handler.onModeActivated();
        }

        const $sendBtn = $('#chatbot-send-btn');

        if (currentMode === 'qb' || currentMode === 'quiz') {
            $sendBtn
                .addClass('qb-generate')
                .html(`
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                    </svg>
                    <span>Generate</span>
                `);
        } else {
            $sendBtn
                .removeClass('qb-generate')
                .html(`
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M2 21l21-9L2 3v7l15 2-15 2z"/>
                    </svg>
                `);
        }
    }

    // ===== Event binding =====
    function bindEvents() {
        $('#ai-chatbot-button').off('click');
        $('#chatbot-close-btn').off('click');
        $('#chatbot-send-btn').off('click');
        $('#chatbot-input').off('input keypress');
        $('.chat-tab').off('click');

        $('#ai-chatbot-button').on('click', () => {
            const modal = $('#ai-chatbot-modal');

            modal.toggleClass('hidden');

            if (!modal.hasClass('hidden')) {
                setTimeout(() => {
                    scrollMessagesToBottom();
                }, 50);
            }

            $('#chatbot-input').focus();
        });

        $('#chatbot-close-btn').on('click', () => {
            $('#ai-chatbot-modal').addClass('hidden');
        });

        $('#chatbot-send-btn').on('click', handleSend);

        const $input = $('#chatbot-input');
        $input.on('input', function() {
            const maxHeight = 150;
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
        const $modal = $('#ai-chatbot-modal');
        $modal.css({
            width: '720px',
            maxWidth: '90vw',
            height: '80vh',
            maxHeight: '80vh'
        });

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

    // ===== Smart suggestion chips for student mode =====
    // Fetches quiz history, analyses it, then wires each chip to send a
    // context-rich query. Chips are plain pill-shaped text buttons (no icons)
    // matching the UI design shown in the screenshot.
    function setupSmartSuggestionChips(config) {

        const $chips = $('#suggestion-chips');
        if (!$chips.length) return;

        // Chip definitions: label shown on the button + function that builds
        // the rich query string from the analysed quiz data.
        const chipDefs = [
            {
                label: 'What should I revise?',
                buildQuery: function(analysis) {
                    if (!analysis.weakTopics.length) {
                        return 'Based on my overall quiz performance so far, what topics should I focus on revising to improve my understanding?';
                    }
                    return `Based on my quiz history, I have scored poorly in the following topics: ${analysis.weakTopics.join(', ')}. ` +
                           `My average score is ${analysis.avgScorePct}%. ` +
                           `\n\nCan you suggest a personalised revision plan for me?`;
                }
            },
            {
                label: 'Give me a practice question',
                buildQuery: function(analysis) {
                    const topic      = analysis.weakTopics[0] || analysis.recentTopic || 'the course material';
                    const difficulty = analysis.suggestedDifficulty || 'medium';
                    return `Please give me a ${difficulty}-difficulty practice question on "${topic}" ` +
                           `and let me attempt it before you reveal the answer.`;
                }
            },
            {
                label: 'Show my weak areas',
                buildQuery: function(analysis) {
                    if (!analysis.weakTopics.length && !analysis.totalAttempts) {
                        return 'I have not taken many quizzes yet. Can you tell me what the most important foundational topics in this course are that I should master first?';
                    }
                    return `Here is a summary of my quiz performance: ` +
                           `I have attempted ${analysis.totalAttempts} quiz(zes) with an average score of ${analysis.avgScorePct}%. ` +
                           `My weakest topics are: ${analysis.weakTopics.join(', ') || 'not yet identified'}. ` +
                           `Please give me a detailed breakdown of my weak areas and actionable advice to improve each one.`;
                }
            }
        ];

        // Render pill-shaped chip buttons (disabled while data loads)
        $chips.empty();

        // Apply flex-row scroll layout to the chips container
        $chips.css({
            display: 'grid',
            gridTemplateColumns: 'repeat(3, 1fr)',
            gap: '8px'
        });

        chipDefs.forEach(function(chip, idx) {
            const $btn = $(`
                <button class="suggestion-chip" data-chip-idx="${idx}" disabled
                    style="
                        flex-shrink: 0;
                        white-space: nowrap;
                        padding: 6px 14px;
                        border-radius: 999px;
                        border: 1.5px solid #c8c8c8;
                        background: #ffffff;
                        color: #333333;
                        font-size: 0.8rem;
                        cursor: pointer;
                        transition: background 0.15s, border-color 0.15s;
                        opacity: 0.6;
                    ">
                    ${escapeHtml(chip.label)}
                </button>
            `);
            $chips.append($btn);
        });

        // Fetch student quiz data to personalise the queries
        $.ajax({
            url: M.cfg.wwwroot + '/local/automation/analytics_ajax.php',
            method: 'POST',
            data: {
                action: 'get_student_quiz',
                studentid: M.cfg.userId,
                courseid:  config.currentcourseid,
                sesskey:   M.cfg.sesskey
            },
            success: function(quizzes) {
                const analysis = analyseStudentData(quizzes);
                activateChips(analysis);
            },
            error: function() {
                // Fall back to generic (empty) analysis so chips still work
                activateChips(analyseStudentData([]));
            }
        });

        function activateChips(analysis) {
            $chips.find('.suggestion-chip').each(function() {
                const idx  = parseInt($(this).data('chip-idx'));
                const chip = chipDefs[idx];

                $(this)
                    .prop('disabled', false)
                    .css('opacity', '1')
                    .off('click')
                    .on('click', function() {
                        const query  = chip.buildQuery(analysis);
                        const $input = $('#chatbot-input');

                        $input.val(query);
                        // Trigger auto-resize
                        $input[0].style.height = 'auto';
                        $input[0].style.height = Math.min($input[0].scrollHeight, 150) + 'px';

                        handleSend();
                    })
                    .on('mouseenter', function() {
                        $(this).css({ background: '#f0f4ff', borderColor: '#7c9ef8' });
                    })
                    .on('mouseleave', function() {
                        $(this).css({ background: '#ffffff', borderColor: '#c8c8c8' });
                    });
            });
        }
    }

    /**
     * Analyse an array of quiz attempt objects returned by analytics_ajax.php
     * and return a concise summary used to build personalised chip queries.
     *
     * Each quiz object is expected to have:
     *   { score, total, topic, difficulty, recommendation, timecreated }
     *
     * Returns:
     *   {
     *     weakTopics:          string[]   — topics where avg score < 60%
     *     avgScorePct:         number     — overall average score %
     *     totalAttempts:       number
     *     recentTopic:         string     — topic of the latest attempt
     *     suggestedDifficulty: string     — 'easy' | 'medium' | 'hard'
     *   }
     */
    function analyseStudentData(quizzes) {
        const result = {
            weakTopics:          [],
            avgScorePct:         0,
            totalAttempts:       0,
            recentTopic:         '',
            suggestedDifficulty: 'medium'
        };

        if (!quizzes || quizzes.length === 0) {
            return result;
        }

        result.totalAttempts = quizzes.length;

        // Sort by timecreated descending to find the latest attempt
        const sorted = quizzes.slice().sort(function(a, b) {
            return parseInt(b.timecreated) - parseInt(a.timecreated);
        });
        result.recentTopic = sorted[0].topic || '';

        // Per-topic aggregate
        const topicMap = {}; // topic → { score, total, count }

        let grandScore = 0;
        let grandTotal = 0;

        quizzes.forEach(function(q) {
            const score = parseFloat(q.score) || 0;
            const total = parseFloat(q.total) || 1;

            grandScore += score;
            grandTotal += total;

            const t = q.topic || 'General';
            if (!topicMap[t]) {
                topicMap[t] = { score: 0, total: 0, count: 0 };
            }
            topicMap[t].score += score;
            topicMap[t].total += total;
            topicMap[t].count++;
        });

        result.avgScorePct = grandTotal > 0
            ? Math.round((grandScore / grandTotal) * 100)
            : 0;

        // Identify weak topics (avg score below 60%)
        Object.keys(topicMap).forEach(function(topic) {
            const t   = topicMap[topic];
            const pct = t.total > 0 ? (t.score / t.total) * 100 : 0;
            if (pct < 60) {
                result.weakTopics.push(topic);
            }
        });

        // Suggest difficulty based on overall performance
        if (result.avgScorePct >= 80) {
            result.suggestedDifficulty = 'hard';
        } else if (result.avgScorePct >= 50) {
            result.suggestedDifficulty = 'medium';
        } else {
            result.suggestedDifficulty = 'easy';
        }

        return result;
    }

    // ===== Init =====
    function init(cfg) {
        // Backwards-compat: if called as init("student","21","21")
        if (typeof cfg === 'string') {
            const role            = cfg;
            const currentcourseid = (typeof arguments[1] !== 'undefined') ? arguments[1] : 0;
            const democourseid    = (typeof arguments[2] !== 'undefined') ? arguments[2] : '';
            cfg = {
                role:             role,
                currentcourseid:  parseInt(currentcourseid) || 0,
                democourseid:     String(democourseid)
            };
            console.warn('chatbot.init() called in legacy form — normalized config:', cfg);
        }

        console.trace('INIT TRACE');
        console.log('INIT CALLED WITH:', cfg);

        pageConfig = cfg || {};
        const config = pageConfig;
        console.log("CONFIG TYPE:", typeof config);
        console.log("CONFIG VALUE:", config);

        if (!document.getElementById('ai-chatbot-modal')) {
            Templates.render('local_automation/chatbot', {})
                .then(html => {
                    $('body').append(html);
                    setupUIAndActivate();
                    activateModeAfterRender(config);
                })
                .catch(err => console.error('Chatbot template load failed:', err));
        } else {
            setupUIAndActivate();
            activateModeAfterRender(config);
        }

        console.log("SESSKEY:", M.cfg.sesskey);
    }

    // helper to pick student/teacher flow after template is present
    function activateModeAfterRender(config) {

        if (config && config.role === 'student') {
            console.log("INSIDE STUDENT BLOCK", config);

            // ── Dashboard button ─────────────────────────────────────────────
            if (!$('#chatbot-dashboard-btn').length) {
                $('.chat-input-area').prepend(`
                    <button id="chatbot-dashboard-btn"
                            type="button"
                            title="Student Dashboard"
                            aria-label="Student Dashboard"
                            style="
                                border: none;
                                background: #2196F3;
                                color: white;
                                padding: 6px 10px;
                                border-radius: 8px;
                                cursor: pointer;
                            ">
                        📊
                    </button>
                `);
            }

            $('#chatbot-dashboard-btn').off('click').on('click', function() {
                window.location.href =
                    M.cfg.wwwroot +
                    '/local/automation/student_dashboard.php?courseid=' +
                    config.currentcourseid;
            });

            // If outside demo course → disable button
            if (parseInt(config.currentcourseid) !== parseInt(config.democourseid)) {
                $('#ai-chatbot-button').off('click');
                return;
            }

            currentMode = 'student';
            $('.chat-tabs').hide();
            $('#chat-title').text('AI Course Tutor');
            $('#chatbot-input').attr('placeholder', 'Ask about this course…');

            Student.initConfig(config);

            // Make input area flex
            $('.chat-input-area').css({
                display: 'flex',
                alignItems: 'center',
                gap: '8px'
            });

            $('#chatbot-action-area').empty();

            // Chat button
            if (!$('#chatbot-open-chat-btn').length) {
                $('.chat-input-area').prepend(`
                    <button id="chatbot-open-chat-btn"
                            type="button"
                            title="Chat with Teacher"
                            aria-label="Chat with Teacher">
                        💬
                    </button>
                `);
            }

            // Quiz button
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

            console.log("user:", config.userid);

            $('#chatbot-open-chat-btn').off('click').on('click', function() {
                console.log("CLICK WORKING");
                console.log("course:", config.currentcourseid);
                window.location.href =
                    M.cfg.wwwroot +
                    '/local/automation/chat.php?courseid=' +
                    config.currentcourseid +
                    '&studentid=' +
                    M.cfg.userId;
            });

            $('#chatbot-take-quiz-btn').off('click').on('click', function() {
                window.location.href =
                    M.cfg.wwwroot +
                    '/local/automation/student_quiz.php?courseid=' +
                    config.currentcourseid;
            });

            $('#ai-chatbot-modal').addClass('student-mode');
            $('#ai-chatbot-button').css('background', 'var(--cb-sky, #0284c7)');
            $('.ai-chatbot-fab').css('background', '#0284c7');
            $('#chat-title').text('AI Course Tutor');
            $('#chat-subtitle').text('Powered by Llama 3.1 8B');
            $('#student-status').removeClass('hidden');
            $('#suggestion-chips').removeClass('hidden');
            $('#student-toolbar').removeClass('hidden');
            $('#chat-tabs').addClass('hidden');
            $('#analytics-btn').addClass('hidden');

            Student.loadHistory(appendMessage);

            // ── Wire smart suggestion chips (data-driven) ────────────────────
            setupSmartSuggestionChips(config);

        } else {
            $('#chatbot-send-btn').html(`
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M2 21l21-9L2 3v7l15 2-15 2z"/>
                </svg>
            `);
            $('#analytics-btn').removeClass('hidden');
            $('#chat-tabs').removeClass('hidden');
            $('.chat-tabs').show();
            $('#chat-title').text('AI Assistant');
            $('#chat-subtitle').text('');
            currentMode = 'assistant';
            loadHistory();
        }
    }

    return { init: init };
});