// local/automation/amd/src/chat_qb.js
define(['jquery'], function($) {
    'use strict';

    // --- State ---

    let selectedCourseId = null;
    let questionType = null; // 'IA' or 'ESE'

    let counts = {
        ia2marks: 0,
        ia5marks: 0,
        ese5marks: 0,
        ese10marks: 0
    };

    // File selection
    let fileMetaById = {};      // fileid -> { name, path, sectionname, courseid }
    let selectedFileIds = [];   // array of selected fileids

    // Edit flow state
    let editMode = false;       // true after first successful generation
    let lastResult = null;      // whatever qb_ajax returns to represent last QB

    // --- Helpers shared with chatbot.js ---

    function reset() {
        selectedCourseId = null;
        questionType = null;

        counts = {
            ia2marks: 0,
            ia5marks: 0,
            ese5marks: 0,
            ese10marks: 0
        };

        fileMetaById = {};
        selectedFileIds = [];
        editMode = false;
        lastResult = null;

        $('#qb-panel').remove();
        $('#qb-controls-fab').remove();
        $('#chatbot-messages').off('.qb');

        $('#chatbot-input').prop('disabled', false);
        $('#chatbot-send-btn').prop('disabled', false).text('Send');
    }

    function beforeSend(text) {
        // Not used for QB; chatbot.js calls generate() directly.
        return text;
    }

    function handleResponse(res, ui) {
        // QB does not consume chatbot_endpoint.php responses.
        return false;
    }

    // Escape HTML helper to avoid XSS
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // --- INLINE STYLING FOR QB PANEL (so we ignore theme CSS) ---

    function applyQbInlineStyles() {
        const $panel = $('#qb-panel');
        if (!$panel.length) return;

        // Keep panel pinned at top of messages area
        $panel.css({
            zIndex: 10,
            background: '#ffffff',
            padding: '8px 10px 10px 10px',
            borderBottom: '1px solid #e0e0e0',
            boxShadow: '0 2px 4px rgba(0,0,0,0.04)'
        });

        $('.qb-row', $panel).css({
            marginBottom: '6px',
            display: 'flex',
            flexWrap: 'wrap',
            alignItems: 'center',
            gap: '6px'
        });

        $('#qb-panel label').css({
            fontSize: '0.8rem'
        });

        // Modern select / number inputs
        $('#qb-course-select, #qb-ia-2, #qb-ia-5, #qb-ese-5, #qb-ese-10').css({
            padding: '4px 8px',
            borderRadius: '6px',
            border: '1px solid #d0d0d0',
            background: '#fafafa',
            fontSize: '0.8rem'
        });

        // Question type radios as pills
        $('.qb-qtype-group', $panel).css({
            display: 'flex',
            gap: '12px',
            fontSize: '0.8rem'
        });

        $('.qb-qtype-group label', $panel).css({
            display: 'flex',
            alignItems: 'center',
            gap: '6px',
            padding: '4px 8px',
            borderRadius: '999px',
            border: '1px solid #e0e0e0',
            cursor: 'pointer',
            background: '#ffffff'
        });

        $('.qb-qtype-group input[type="radio"]', $panel).css({
            accentColor: '#1976d2'
        });

        // File explorer tree box
        $('#qb-files-tree').css({
            width: '100%',
            maxHeight: '220px',
            overflow: 'auto',
            padding: '6px 8px',
            borderRadius: '8px',
            border: '1px solid #e0e0e0',
            background: '#fafafa',
            fontSize: '0.8rem'
        });

        $('.qb-section-title').css({
            fontWeight: 600,
            marginBottom: '3px',
            display: 'flex',
            alignItems: 'center',
            gap: '6px',
            color: '#37474f'
        });

        $('.qb-folder').css({
            marginLeft: '8px'
        });

        $('.qb-folder > summary').css({
            listStyle: 'none',
            cursor: 'pointer',
            padding: '3px 6px',
            borderRadius: '4px',
            display: 'flex',
            alignItems: 'center',
            gap: '6px'
        });

        $('.qb-tree-level').css({
            marginLeft: '14px',
            paddingLeft: '4px',
            borderLeft: '1px dashed #d0d0d0'
        });

        $('.qb-file label').css({
            display: 'flex',
            alignItems: 'center',
            gap: '6px',
            padding: '2px 6px',
            borderRadius: '4px',
            cursor: 'pointer'
        });

        $('.qb-file input[type="checkbox"]').css({
            accentColor: '#1976d2'
        });

        // Hint box
        $('.qb-hint').css({
            flexDirection: 'column',
            alignItems: 'flex-start',
            padding: '6px 8px',
            borderRadius: '6px',
            background: '#f5f5f5'
        });

        $('.qb-hint-title').css({
            fontWeight: 600,
            fontSize: '0.8rem',
            marginBottom: '2px'
        });

        $('.qb-hint-sub').css({
            fontSize: '0.75rem',
            color: '#555'
        });
    }

    // --- UI PANEL CREATION ---

    function ensurePanel() {
        if ($('#qb-panel').length) {
            return;
        }

        const html = `
            <div id="qb-panel" class="qb-panel">
                <div class="qb-row">
                    <label>Subject (course)</label>
                    <select id="qb-course-select">
                        <option value="">Select course...</option>
                        <!-- Options will be loaded via AJAX -->
                    </select>
                </div>

                <div class="qb-row" id="qb-topics-container">
                    <div class="qb-row">
                        <label>
                            <input type="checkbox" id="qb-use-all-resources">
                            Select all supported files (PDF / PPT / Word)
                        </label>
                    </div>
                    <div id="qb-files-tree" class="qb-files-tree">
                        <!-- Sections + folders + files will be rendered here -->
                    </div>
                </div>

                <div class="qb-row">
                    <label>Question type</label>
                    <div class="qb-qtype-group">
                        <label>
                            <input type="radio" name="qb-qtype" value="IA">
                            <span>IA Type (2 marks &amp; 5 marks)</span>
                        </label>
                        <label>
                            <input type="radio" name="qb-qtype" value="ESE">
                            <span>ESE Type (5 marks &amp; 10 marks)</span>
                        </label>
                    </div>
                </div>

                <div id="qb-counts-ia" class="qb-counts" style="display:none;">
                    <div class="qb-row">
                        <label>
                            No. of 2 marks questions:
                            <input type="number" id="qb-ia-2" min="0" max="50" value="0">
                        </label>
                    </div>
                    <div class="qb-row">
                        <label>
                            No. of 5 marks questions:
                            <input type="number" id="qb-ia-5" min="0" max="50" value="0">
                        </label>
                    </div>
                </div>

                <div id="qb-counts-ese" class="qb-counts" style="display:none;">
                    <div class="qb-row">
                        <label>
                            No. of 5 marks questions:
                            <input type="number" id="qb-ese-5" min="0" max="50" value="0">
                        </label>
                    </div>
                    <div class="qb-row">
                        <label>
                            No. of 10 marks questions:
                            <input type="number" id="qb-ese-10" min="0" max="50" value="0">
                        </label>
                    </div>
                </div>

                <div class="qb-row qb-hint">
                    <div class="qb-hint-title">Type Preferences (optional)</div>
                    <div class="qb-hint-sub">
                        Use the box below for extra preferences, e.g.
                        “focus on trees”, “mix all topics”, “avoid proofs”.
                        Leave it empty for a general question bank.
                    </div>
                </div>
            </div>
        `;

        $('#chatbot-messages').prepend(html);

        // Add floating "Controls ↑" button once per chat window (inside scroll area)
        if (!$('#qb-controls-fab').length) {
            $('.chat-messages').append(`
                <button id="qb-controls-fab" title="Back to controls">
                    ▲ Controls
                </button>
            `);
        }


        // Apply inline styles so Moodle theme can't override them
        applyQbInlineStyles();

        bindPanelEvents();
        updateGenerateState();
        loadCourses();
    }

    // --- AJAX LOADING: COURSES & FILE TREE ---

    function loadCourses() {
        $.ajax({
            url: M.cfg.wwwroot + '/local/automation/qb_ajax.php',
            method: 'POST',
            data: JSON.stringify({
                action: 'fetch_courses',
                sesskey: M.cfg.sesskey
            }),
            contentType: 'application/json',
            dataType: 'json',
            success: function(res) {
                if (!res || !res.success) {
                    console.error('QB fetch_courses error:', res);
                    return;
                }
                populateCourseSelect(res.courses || []);
            },
            error: function(xhr) {
                console.error('QB fetch_courses AJAX error:', xhr);
            }
        });
    }

    function populateCourseSelect(courses) {
        const $select = $('#qb-course-select');
        $select.empty();
        $select.append('<option value="">Select course...</option>');

        courses.forEach(c => {
            const label = escapeHtml(c.fullname || c.shortname || ('Course ' + c.id));
            $select.append(
                `<option value="${String(c.id)}">${label}</option>`
            );
        });
    }

    function loadFilesForCourse(courseid) {
        if (!courseid) {
            $('#qb-files-tree').empty();
            fileMetaById = {};
            selectedFileIds = [];
            $('#qb-use-all-resources').prop('checked', false);
            updateGenerateState();
            return;
        }

        $.ajax({
            url: M.cfg.wwwroot + '/local/automation/qb_ajax.php',
            method: 'POST',
            data: JSON.stringify({
                action: 'fetch_files',
                courseid: courseid,
                sesskey: M.cfg.sesskey
            }),
            contentType: 'application/json',
            dataType: 'json',
            success: function(res) {
                if (!res || !res.success) {
                    console.error('QB fetch_files error:', res);
                    $('#qb-files-tree').html('<div class="qb-info">No files found for this course.</div>');
                    fileMetaById = {};
                    selectedFileIds = [];
                    updateGenerateState();
                    return;
                }
                renderFileTree(res.sections || []);
            },
            error: function(xhr) {
                console.error('QB fetch_files AJAX error:', xhr);
                $('#qb-files-tree').html('<div class="qb-info">Failed to load files.</div>');
                fileMetaById = {};
                selectedFileIds = [];
                updateGenerateState();
            }
        });
    }

    // --- TREE RENDERING ---

    function renderFileTree(sections) {
        fileMetaById = {};
        selectedFileIds = [];
        $('#qb-use-all-resources').prop('checked', false);

        if (!sections.length) {
            $('#qb-files-tree').html('<div class="qb-info">No supported files (PDF/PPT/Word) found in this course.</div>');
            updateGenerateState();
            return;
        }

        let html = '';

        sections.forEach(section => {
            const sname = escapeHtml(section.name || 'Topic');
            const files = Array.isArray(section.files) ? section.files : [];

            if (!files.length) {
                return;
            }

            const treeRoot = buildTreeFromFiles(section, files);

            html += `
                <div class="qb-section">
                    <div class="qb-section-title">
                        <span class="qb-icon qb-icon-section">📚</span>
                        ${sname}
                    </div>
                    <div class="qb-section-tree">
                        ${renderTreeNode(treeRoot)}
                    </div>
                </div>
            `;
        });

        if (!html) {
            html = '<div class="qb-info">No supported files (PDF/PPT/Word) found in this course.</div>';
        }

        $('#qb-files-tree').html(html);

        // Re-apply tree related styles now that new nodes exist
        applyQbInlineStyles();
        updateGenerateState();
    }

    function buildTreeFromFiles(section, files) {
        const root = {
            type: 'root',
            name: section.name || 'Section',
            children: {}
        };

        files.forEach(file => {
            const fileid = file.fileid;
            const name = file.name;
            const path = file.path || name;

            fileMetaById[fileid] = {
                name: name,
                path: path,
                sectionname: section.name || '',
                courseid: section.courseid || selectedCourseId
            };

            const parts = path.split('/').filter(Boolean);
            let node = root;

            for (let i = 0; i < parts.length - 1; i++) {
                const folderName = parts[i];
                if (!node.children[folderName]) {
                    node.children[folderName] = {
                        type: 'folder',
                        name: folderName,
                        children: {}
                    };
                }
                node = node.children[folderName];
            }

            const filename = parts[parts.length - 1] || name;
            const key = filename + '__' + fileid;

            node.children[key] = {
                type: 'file',
                name: filename,
                fileid: fileid
            };
        });

        return root;
    }

    function renderTreeNode(node) {
        if (!node || !node.children) {
            return '';
        }

        const keys = Object.keys(node.children);
        if (!keys.length) {
            return '';
        }

        let html = '<div class="qb-tree-level">';

        keys.forEach(key => {
            const child = node.children[key];
            if (child.type === 'folder') {
                html += `
                    <details class="qb-folder">
                        <summary>
                            <span class="qb-icon qb-icon-folder">📁</span>
                            ${escapeHtml(child.name)}
                        </summary>
                        ${renderTreeNode(child)}
                    </details>
                `;
            } else if (child.type === 'file') {
                const fid = String(child.fileid);
                const fname = escapeHtml(child.name);
                html += `
                    <div class="qb-file">
                        <label>
                            <input type="checkbox" class="qb-file-checkbox" data-fileid="${fid}">
                            <span class="qb-icon qb-icon-file">📄</span>
                            ${fname}
                        </label>
                    </div>
                `;
            }
        });

        html += '</div>';
        return html;
    }

    function recomputeSelectedFiles() {
        selectedFileIds = [];
        $('.qb-file-checkbox:checked').each(function() {
            const fid = $(this).data('fileid');
            if (fid !== undefined && fid !== null) {
                selectedFileIds.push(String(fid));
            }
        });
    }

    // --- GENERATE BUTTON ENABLE/DISABLE ---

    function updateGenerateState() {
        const courseidVal = $('#qb-course-select').val();
        selectedCourseId = courseidVal ? parseInt(courseidVal, 10) : null;

        counts.ia2marks   = parseInt($('#qb-ia-2').val() || '0', 10);
        counts.ia5marks   = parseInt($('#qb-ia-5').val() || '0', 10);
        counts.ese5marks  = parseInt($('#qb-ese-5').val() || '0', 10);
        counts.ese10marks = parseInt($('#qb-ese-10').val() || '0', 10);

        const qtype = $('input[name="qb-qtype"]:checked').val();
        questionType = qtype || null;

        let enabled = false;
        const hasCourse = !!selectedCourseId;
        const hasFiles = selectedFileIds.length > 0;
        let hasCounts = false;

        if (questionType === 'IA') {
            if (counts.ia2marks === 0 && counts.ia5marks === 0) {
                counts.ia2marks = 5;
                counts.ia5marks = 5;
                $('#qb-ia-2').val(5);
                $('#qb-ia-5').val(5);
            }
            hasCounts = (counts.ia2marks > 0 || counts.ia5marks > 0);
        } else if (questionType === 'ESE') {
            if (counts.ese5marks === 0 && counts.ese10marks === 0) {
                counts.ese5marks = 5;
                counts.ese10marks = 5;
                $('#qb-ese-5').val(5);
                $('#qb-ese-10').val(5);
            }
            hasCounts = (counts.ese5marks > 0 || counts.ese10marks > 0);
        }

        enabled = hasCourse && hasFiles && !!questionType && hasCounts;

        $('#chatbot-input').prop('disabled', !enabled);
        $('#chatbot-send-btn').prop('disabled', !enabled);
    }

    // --- BIND EVENTS ---

    function bindPanelEvents() {
        const $messages = $('.chat-messages');

        // Show/hide FAB depending on scroll position
        $messages.on('scroll.qb', function() {
            const $fab = $('#qb-controls-fab');
            if (!$fab.length) return;

            if (this.scrollTop > 150) {
                $fab.fadeIn(150);
            } else {
                $fab.fadeOut(150);
            }
        });

        // Scroll back to top (controls) when FAB clicked
        $messages.on('click.qb', '#qb-controls-fab', function() {
            $messages.animate({ scrollTop: 0 }, 200);
        });

        $('#qb-course-select').on('change', function() {
            const cid = $(this).val();
            selectedCourseId = cid ? parseInt(cid, 10) : null;
            loadFilesForCourse(selectedCourseId);
            updateGenerateState();
        });

        $('#qb-use-all-resources').on('change', function() {
            const checked = $(this).is(':checked');
            $('.qb-file-checkbox').prop('checked', checked);
            recomputeSelectedFiles();
            updateGenerateState();
        });

        $('input[name="qb-qtype"]').on('change', function() {
            const val = $(this).val();
            if (val === 'IA') {
                $('#qb-counts-ia').show();
                $('#qb-counts-ese').hide();
            } else if (val === 'ESE') {
                $('#qb-counts-ia').hide();
                $('#qb-counts-ese').show();
            } else {
                $('#qb-counts-ia').hide();
                $('#qb-counts-ese').hide();
            }
            updateGenerateState();
        });

        $('#qb-ia-2, #qb-ia-5, #qb-ese-5, #qb-ese-10').on('input', function() {
            updateGenerateState();
        });

        $('#chatbot-messages').on('change', '.qb-file-checkbox', function() {
            recomputeSelectedFiles();
            const totalFiles = $('.qb-file-checkbox').length;
            const totalSelected = $('.qb-file-checkbox:checked').length;
            $('#qb-use-all-resources').prop('checked', totalFiles > 0 && totalFiles === totalSelected);
            updateGenerateState();
        });

        $('#chatbot-messages')
            .on('click', '.qb-download-btn', function() {
                const url = $(this).data('downloadUrl');
                if (url) {
                    window.open(url, '_blank');
                }
            })
            .on('click', '.qb-upload-btn', function() {
                const $btn = $(this);
                const fileid = $btn.data('fileid');
                const courseid = $btn.data('courseid');
                const qtype = $btn.data('questiontype') || questionType || 'IA';

                if (!fileid || !courseid || $btn.prop('disabled')) {
                    return;
                }

                $btn.prop('disabled', true).text('Uploading...');

                $.ajax({
                    url: M.cfg.wwwroot + '/local/automation/qb_ajax.php',
                    method: 'POST',
                    data: JSON.stringify({
                        action: 'upload_qb',
                        fileid: fileid,
                        courseid: courseid,
                        questiontype: qtype,
                        sesskey: M.cfg.sesskey
                    }),
                    contentType: 'application/json',
                    dataType: 'json',
                    success: function(res) {
                        if (res && res.success) {
                            $btn.text('Uploaded');
                        } else {
                            $btn.prop('disabled', false).text('Upload failed');
                            console.error('QB upload error:', res);
                        }
                    },
                    error: function(xhr) {
                        $btn.prop('disabled', false).text('Upload failed');
                        console.error('QB upload AJAX error:', xhr);
                        alert('Upload failed. See server logs.');
                    }
                });
            });
    }

    // --- MODE ACTIVATION ---

    function onModeActivated() {
        ensurePanel();
        $('#chatbot-send-btn').text('Generate');
        editMode = false;
        updateGenerateState();
    }

    // --- CORE: GENERATE / EDIT ---

    function generate(instructions, ui) {
        updateGenerateState();

        if (!selectedCourseId) {
            ui.appendMessage('⚠️ Please select a course first.', 'bot');
            return;
        }
        if (!questionType) {
            ui.appendMessage('⚠️ Please choose question type (IA / ESE).', 'bot');
            return;
        }
        if (!selectedFileIds.length) {
            ui.appendMessage('⚠️ Please select at least one file.', 'bot');
            return;
        }

        let hasCounts = false;
        if (questionType === 'IA') {
            hasCounts = (counts.ia2marks > 0 || counts.ia5marks > 0);
        } else if (questionType === 'ESE') {
            hasCounts = (counts.ese5marks > 0 || counts.ese10marks > 0);
        }
        if (!hasCounts) {
            ui.appendMessage('⚠️ Please set at least one question count.', 'bot');
            return;
        }

        $('#qb-course-select').prop('disabled', true);
        $('#qb-use-all-resources').prop('disabled', true);
        $('input[name="qb-qtype"]').prop('disabled', true);
        $('#qb-ia-2, #qb-ia-5, #qb-ese-5, #qb-ese-10').prop('disabled', true);
        $('#chatbot-input').prop('disabled', true);
        $('#chatbot-send-btn').prop('disabled', true);

        const trimmed = (instructions || '').trim();
        if (editMode && lastResult && trimmed.length > 0) {
            ui.appendMessage('✏️ Updating question bank...', 'bot');
        } else if (!editMode) {
            ui.appendMessage('⏳ Creating Question Bank...', 'bot');
        } else {
            ui.appendMessage('⏳ Regenerating Question Bank...', 'bot');
        }

        const payload = {
            courseid: selectedCourseId,
            selectedfiles: selectedFileIds,
            filemeta: fileMetaById,
            questiontype: questionType,
            counts: counts,
            instructions: instructions || '',
            sesskey: M.cfg.sesskey
        };

        if (editMode && lastResult) {
            payload.mode = 'edit';
            payload.previous = lastResult;
        } else {
            payload.mode = 'initial';
        }

        $.ajax({
            url: M.cfg.wwwroot + '/local/automation/qb_ajax.php',
            method: 'POST',
            data: JSON.stringify(payload),
            contentType: 'application/json',
            dataType: 'json',
            success: function(res) {
                console.log('QB AJAX response:', res);

                if (res && res.success) {
                    const downloadurl = res.downloadurl || '#';
                    const fileid = res.fileid || 0;
                    const courseid = res.courseid || selectedCourseId;

                    lastResult = res.data || res.questions || null;
                    editMode = true;

                    const html = `
                        <div class="automation-box">
                            <b>QB Generation Completed ✅</b><br>
                            ${res.message ? escapeHtml(res.message) + '<br>' : ''}
                            <button class="qb-download-btn" data-download-url="${escapeHtml(downloadurl)}">
                                Download PDF
                            </button>
                            <button class="qb-upload-btn"
                                    data-fileid="${escapeHtml(String(fileid))}"
                                    data-courseid="${escapeHtml(String(courseid))}"
                                    data-questiontype="${escapeHtml(String(questionType || 'IA'))}">
                                Upload to course
                            </button>
                            <div class="qb-edit-hint">
                                Type any edits or modifications in the box below and click
                                <b>Generate</b> again to update this Question Bank.
                            </div>
                        </div>
                    `;

                    ui.appendMessage(html, 'bot');
                } else {
                    ui.appendMessage(
                        '❌ Error: ' + escapeHtml(res && res.error ? res.error : 'Unknown error'),
                        'bot'
                    );
                }

                $('#qb-course-select').prop('disabled', false);
                $('#qb-use-all-resources').prop('disabled', false);
                $('input[name="qb-qtype"]').prop('disabled', false);
                $('#qb-ia-2, #qb-ia-5, #qb-ese-5, #qb-ese-10').prop('disabled', false);
                $('#chatbot-input').prop('disabled', false);
                $('#chatbot-send-btn').prop('disabled', false);
            },
            error: function(xhr) {
                console.error('QB AJAX error:', xhr);
                let msg = 'Failed to generate question bank. See server logs.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    msg = xhr.responseJSON.error;
                }
                ui.appendMessage('❌ ' + msg, 'bot');

                $('#qb-course-select').prop('disabled', false);
                $('#qb-use-all-resources').prop('disabled', false);
                $('input[name="qb-qtype"]').prop('disabled', false);
                $('#qb-ia-2, #qb-ia-5, #qb-ese-5, #qb-ese-10').prop('disabled', false);
                $('#chatbot-input').prop('disabled', false);
                $('#chatbot-send-btn').prop('disabled', false);
            }
        });
    }

    return {
        beforeSend: beforeSend,
        handleResponse: handleResponse,
        reset: reset,
        onModeActivated: onModeActivated,
        generate: generate
    };
});
