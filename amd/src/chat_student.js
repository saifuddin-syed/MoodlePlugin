define(['jquery'], function($) {
    'use strict';

    let config = null;

    function initConfig(cfg) {
        config = cfg;
    }

    function saveMessageToDB(message, sender) {
        if (!config || !config.currentcourseid) {
            console.error('Student config not initialized');
            return;
        }
        return $.ajax({
            url: M.cfg.wwwroot + '/local/automation/student_ajax.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'save_message',
                courseid: config.currentcourseid,
                message: message,
                sender: sender,
                sesskey: M.cfg.sesskey
            }
        });
    }

    function loadHistory(appendMessage) {
        if (!config || !config.currentcourseid) {
            console.error('Student config not initialized');
            return;
        }
        $.ajax({
            url: M.cfg.wwwroot + '/local/automation/student_ajax.php',
            method: 'POST',
            data: {
                action: 'fetch_history',
                courseid: config.currentcourseid,
                sesskey: M.cfg.sesskey
            },
            success: function(res) {
                if (typeof res === 'string') {
                    res = JSON.parse(res);
                }
                $('#chatbot-messages').empty();

                res.forEach(msg => {
                    appendMessage(msg.message, msg.sender, parseInt(msg.timecreated), false);
                });
            }
        });
    }

    function insertDummyQuiz() {
        if (!config || !config.currentcourseid) {
            console.error('Student config not initialized');
            return;
        }
        return $.ajax({
            url: M.cfg.wwwroot + '/local/automation/student_ajax.php',
            method: 'POST',
            data: {
                action: 'insert_dummy_quiz',
                courseid: config.currentcourseid,
                sesskey: M.cfg.sesskey
            }
        });
    }

    function askRAG(message) {
        return $.ajax({
            url: M.cfg.wwwroot + '/local/automation/student_ajax.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'ask_rag',
                courseid: config.currentcourseid,
                question: message,
                sesskey: M.cfg.sesskey
            }
        });
    }

    return {
        initConfig: initConfig,
        saveMessageToDB: saveMessageToDB,
        loadHistory: loadHistory,
        insertDummyQuiz: insertDummyQuiz,
        askRAG: askRAG
    };
});