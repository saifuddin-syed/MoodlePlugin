<?php
require_once(__DIR__.'/../../config.php');
require_login();

$courseid  = required_param('courseid',  PARAM_INT);
$studentid = required_param('studentid', PARAM_INT);

$context = context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_url('/local/automation/chat.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Course Chat');
$PAGE->set_heading('Course Chat');

$is_teacher = has_capability('moodle/course:viewhiddenactivities', $context);
$is_student = ($USER->id == $studentid);

echo $OUTPUT->header();
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

#chat-root {
    --bg:          #f5f5f7;
    --surface:     #ffffff;
    --border:      #e8e8ec;
    --text-primary:#1a1a2e;
    --text-muted:  #8a8a9a;
    --blue:        #4a7ef0;
    --blue-light:  #eef3ff;
    --blue-dark:   #2f5ec4;
    --green:       #52b788;
    --green-light: #f0faf5;
    --red:         #e05252;
    --red-light:   #fdf0f0;
    --purple:      #7c6fcd;
    --purple-light:#f5f3ff;

    font-family: 'DM Sans', sans-serif;
    font-size: 15px;
    color: var(--text-primary);
    background: var(--bg);
    min-height: 100vh;
    padding: 28px 24px 48px;
}

/* ── Header ─────────────────────────────────────────────────── */
.chat-header {
    display: flex; align-items: center; gap: 14px;
    margin-bottom: 24px; flex-wrap: wrap;
}
.back-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; border: 1.5px solid var(--border);
    border-radius: 8px; background: var(--surface);
    color: var(--text-primary); font-family: inherit;
    font-size: 13px; font-weight: 500; cursor: pointer;
    text-decoration: none; transition: border-color .15s, box-shadow .15s;
}
.back-btn:hover { border-color: #bbb; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.chat-title { font-size: 20px; font-weight: 700; letter-spacing: -.3px; }
.badge {
    display: inline-flex; align-items: center;
    padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 600;
}
.badge-student { background: #eef3ff; color: var(--blue);   border: 1.5px solid #d0deff; }
.badge-course  { background: #fdf0f0; color: var(--red);    border: 1.5px solid #f5cece; }
.badge-role    { background: #f5f3ff; color: var(--purple); border: 1.5px solid #ddd8f8; }
.timestamp-lbl { margin-left: auto; font-size: 12px; color: var(--text-muted); }

/* ── Panel card ──────────────────────────────────────────────── */
.panel {
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,.05);
    max-width: 820px;
}

.panel-header {
    display: flex; align-items: center; gap: 10px;
    padding: 14px 20px 12px;
    border-bottom: 1.5px solid var(--border);
}
.panel-icon {
    width: 32px; height: 32px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; flex-shrink: 0;
    background: var(--bg);
}
.panel-label {
    font-size: 13px; font-weight: 700; letter-spacing: .2px;
}
.panel-sublabel {
    font-size: 11px; color: var(--text-muted); margin-left: auto;
}

/* ── Thread ──────────────────────────────────────────────────── */
.thread {
    overflow-y: auto;
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-height: 300px;
    max-height: 480px;
}

.state-msg {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 36px 24px; gap: 8px;
    color: var(--text-muted); font-size: 13px;
}
.state-icon {
    font-size: 24px; width: 44px; height: 44px;
    border-radius: 12px; background: var(--bg);
    display: flex; align-items: center; justify-content: center;
}

.bubble-row {
    display: flex; gap: 10px; align-items: flex-end;
    animation: fadeUp .2s ease both;
}
.from-student { flex-direction: row-reverse; }
.from-teacher { flex-direction: row; }

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: translateY(0); }
}

.avatar {
    width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 14px;
}
.avatar-student { background: var(--blue-light);   color: var(--blue); }
.avatar-teacher { background: var(--purple-light); color: var(--purple); }

.bubble-col {
    display: flex; flex-direction: column; gap: 3px; max-width: 72%;
}
.from-student .bubble-col { align-items: flex-end; }
.from-teacher .bubble-col { align-items: flex-start; }

.bubble {
    padding: 9px 13px; border-radius: 14px;
    font-size: 14px; line-height: 1.55; word-break: break-word;
}
.bubble-student {
    background: var(--blue); color: #fff;
    border-bottom-right-radius: 4px;
}
.bubble-teacher {
    background: var(--purple-light); color: var(--text-primary);
    border: 1.5px solid #ddd8f8; border-bottom-left-radius: 4px;
}
.bubble-meta { font-size: 11px; color: var(--text-muted); padding: 0 4px; }

.thread-sep {
    display: flex; align-items: center; gap: 10px;
    color: var(--text-muted); font-size: 10px;
    font-weight: 600; letter-spacing: .8px; text-transform: uppercase;
}
.thread-sep::before, .thread-sep::after {
    content: ''; flex: 1; height: 1px; background: var(--border);
}

/* ── Compose area ────────────────────────────────────────────── */
.compose-wrap {
    padding: 12px 20px 16px;
    display: flex; flex-direction: column; gap: 10px;
    border-top: 1.5px solid var(--border);
}
.compose-row { display: flex; gap: 10px; align-items: flex-end; }
.compose-input {
    flex: 1; resize: vertical; min-height: 56px; max-height: 120px;
    padding: 10px 14px; border: 1.5px solid var(--border);
    border-radius: 10px; font-family: inherit; font-size: 14px;
    color: var(--text-primary); background: var(--bg);
    outline: none; transition: border-color .15s, box-shadow .15s; line-height: 1.5;
}
.compose-input:focus {
    border-color: var(--blue); box-shadow: 0 0 0 3px rgba(74,126,240,.12);
}
.compose-input.teacher-focus:focus {
    border-color: var(--purple); box-shadow: 0 0 0 3px rgba(124,111,205,.12);
}
.compose-input::placeholder { color: var(--text-muted); }
.compose-input:disabled { opacity: .5; cursor: not-allowed; }

.send-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 18px; border: none; border-radius: 10px;
    font-family: inherit; font-size: 13px; font-weight: 600;
    cursor: pointer; transition: background .15s, transform .1s, opacity .15s;
    white-space: nowrap; height: 42px;
}
.send-btn:disabled { opacity: .5; cursor: not-allowed; }
.send-btn:active:not(:disabled) { transform: scale(.97); }
.btn-student { background: var(--blue);   color: #fff; }
.btn-student:hover:not(:disabled) { background: var(--blue-dark); }
.btn-teacher { background: var(--purple); color: #fff; }
.btn-teacher:hover:not(:disabled) { background: #5e52a8; }

/* ── Toast ───────────────────────────────────────────────────── */
.flag-toast {
    display: none; align-items: center; gap: 8px;
    padding: 9px 13px; border-radius: 8px;
    font-size: 13px; font-weight: 500; line-height: 1.45;
}
.flag-toast.show      { display: flex; }
.flag-toast.toast-err { background: var(--red-light);   color: var(--red);   border: 1.5px solid #f5cece; }
.flag-toast.toast-ok  { background: var(--green-light); color: var(--green); border: 1.5px solid #c8eedd; }

/* ── Readonly notice ─────────────────────────────────────────── */
.readonly-notice {
    padding: 10px 20px 14px;
    border-top: 1.5px solid var(--border);
    font-size: 12px; color: var(--text-muted);
    display: flex; align-items: center; gap: 7px;
}
</style>

<div id="chat-root">

    <div class="chat-header">
        <button class="back-btn" onclick="history.back()">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <path d="M9 2L4 7L9 12" stroke="currentColor" stroke-width="1.8"
                      stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Back
        </button>
        <span class="chat-title">Course Chat</span>
        <span class="badge badge-student">Student — ID <?php echo (int)$studentid; ?></span>
        <span class="badge badge-course">Course <?php echo (int)$courseid; ?></span>
        <?php if ($is_teacher): ?>
            <span class="badge badge-role">👩‍🏫 Teacher View</span>
        <?php elseif ($is_student): ?>
            <span class="badge badge-role">🎓 Student View</span>
        <?php endif; ?>
        <span class="timestamp-lbl" id="lastUpdated"></span>
    </div>

    <div class="panel">

        <div class="panel-header">
            <div class="panel-icon">💬</div>
            <span class="panel-label">Conversation</span>
            <span class="panel-sublabel">
                <?php if ($is_teacher): ?>
                    Student questions &amp; your advice
                <?php else: ?>
                    Your questions &amp; instructor feedback
                <?php endif; ?>
            </span>
        </div>

        <!-- Unified thread — all messages chronologically -->
        <div class="thread" id="thread-main">
            <div class="state-msg">
                <div class="state-icon">⏳</div>
                <p>Loading…</p>
            </div>
        </div>

        <!-- Compose: Teacher -->
        <?php if ($is_teacher): ?>
        <div class="compose-wrap">
            <div id="toastTeacher" class="flag-toast"></div>
            <div class="compose-row">
                <textarea
                    id="inputTeacher"
                    class="compose-input teacher-focus"
                    placeholder="Write advice or feedback for this student…"
                ></textarea>
                <button id="btnTeacher" class="send-btn btn-teacher" onclick="sendTeacher()">
                    <svg width="15" height="15" viewBox="0 0 16 16" fill="none">
                        <path d="M14 8L2 2l2 6-2 6 12-6z" stroke="currentColor"
                              stroke-width="1.6" stroke-linejoin="round"/>
                    </svg>
                    Send Advice
                </button>
            </div>
        </div>

        <!-- Compose: Student -->
        <?php elseif ($is_student): ?>
        <div class="compose-wrap">
            <div id="toastStudent" class="flag-toast"></div>
            <div class="compose-row">
                <textarea
                    id="inputStudent"
                    class="compose-input"
                    placeholder="Ask a question related to the course syllabus…"
                ></textarea>
                <button id="btnStudent" class="send-btn btn-student" onclick="sendStudent()">
                    <svg width="15" height="15" viewBox="0 0 16 16" fill="none">
                        <path d="M14 8L2 2l2 6-2 6 12-6z" stroke="currentColor"
                              stroke-width="1.6" stroke-linejoin="round"/>
                    </svg>
                    Ask Question
                </button>
            </div>
        </div>

        <?php else: ?>
        <div class="readonly-notice">
            🔒 You do not have permission to send messages here
        </div>
        <?php endif; ?>

    </div><!-- /panel -->

</div>

<script>
const courseid  = <?php echo (int)$courseid; ?>;
const studentid = <?php echo (int)$studentid; ?>;
const isTeacher = <?php echo $is_teacher ? 'true' : 'false'; ?>;
const isStudent = <?php echo $is_student  ? 'true' : 'false'; ?>;
const SESSKEY   = M.cfg.sesskey;
const WWWROOT   = M.cfg.wwwroot;

const STUDENT_AJAX   = WWWROOT + '/local/automation/student_ajax.php';
const ANALYTICS_AJAX = WWWROOT + '/local/automation/analytics_ajax.php';

let lastMsgCount = 0;

// ── Helpers ──────────────────────────────────────────────────
function post(url, params) {
    return fetch(url, {
        method:  'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body:    new URLSearchParams({ ...params, courseid, sesskey: SESSKEY })
    }).then(r => r.json());
}

function fmtTime(ts) {
    return new Date(ts * 1000).toLocaleString([], {
        day: '2-digit', month: 'short',
        hour: '2-digit', minute: '2-digit'
    });
}

function esc(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;')
        .replace(/\n/g,'<br>');
}

function showToast(el, text, cls) {
    el.textContent = text;
    el.className   = 'flag-toast show ' + cls;
    setTimeout(() => { el.className = 'flag-toast'; }, 4000);
}

// ── Render unified thread ─────────────────────────────────────
function renderThread(messages) {
    const thread = document.getElementById('thread-main');

    if (!messages || messages.length === 0) {
        thread.innerHTML = `
        <div class="state-msg">
            <div class="state-icon">💬</div>
            <p>No messages yet. Start the conversation!</p>
        </div>`;
        lastMsgCount = 0;
        return;
    }

    // Sort all messages chronologically
    messages.sort((a, b) => a.timecreated - b.timecreated);

    let html = '', lastDate = null;

    messages.forEach(msg => {
        const dateStr = new Date(msg.timecreated * 1000).toLocaleDateString([], {
            weekday: 'long', day: '2-digit', month: 'short'
        });

        if (dateStr !== lastDate) {
            html += `<div class="thread-sep">${dateStr}</div>`;
            lastDate = dateStr;
        }

        const fromStudent = msg.sender === 'student';
        html += `
        <div class="bubble-row ${fromStudent ? 'from-student' : 'from-teacher'}">
            <div class="avatar ${fromStudent ? 'avatar-student' : 'avatar-teacher'}">
                ${fromStudent ? '🎓' : '👩‍🏫'}
            </div>
            <div class="bubble-col">
                <div class="bubble ${fromStudent ? 'bubble-student' : 'bubble-teacher'}">
                    ${esc(msg.message)}
                </div>
                <span class="bubble-meta">
                    ${fromStudent ? 'Student' : 'Teacher'} · ${fmtTime(msg.timecreated)}
                </span>
            </div>
        </div>`;
    });

    thread.innerHTML = html;

    if (messages.length !== lastMsgCount) {
        thread.scrollTop = thread.scrollHeight;
    }

    lastMsgCount = messages.length;
}

// ── Load history ──────────────────────────────────────────────
function loadHistory() {
    const url    = isTeacher ? ANALYTICS_AJAX : STUDENT_AJAX;
    const params = { action: 'get_chat_history' };
    if (isTeacher) params.studentid = studentid;

    post(url, params)
        .then(data => {
            renderThread(Array.isArray(data) ? data : []);
            document.getElementById('lastUpdated').textContent =
                'Updated ' + new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
        })
        .catch(() => {
            document.getElementById('thread-main').innerHTML = `
            <div class="state-msg">
                <div class="state-icon">⚠️</div>
                <p>Could not load messages. Retrying…</p>
            </div>`;
        });
}

// ── Send: Teacher advice ──────────────────────────────────────
function sendTeacher() {
    const input = document.getElementById('inputTeacher');
    const btn   = document.getElementById('btnTeacher');
    const toast = document.getElementById('toastTeacher');
    const msg   = input.value.trim();
    if (!msg) return;

    toast.className = 'flag-toast';
    btn.disabled    = true;
    btn.textContent = 'Sending…';

    post(ANALYTICS_AJAX, { action: 'save_advice', studentid, advice: msg })
        .then(data => {
            const ok = data.ok === true || data.status === 'ok';
            if (ok) {
                input.value = '';
                showToast(toast, '✅ Advice sent!', 'toast-ok');
                loadHistory();
            } else {
                showToast(toast, '⚠️ ' + (data.error || 'Could not send.'), 'toast-err');
            }
        })
        .catch(() => showToast(toast, '⚠️ Network error. Please try again.', 'toast-err'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = `<svg width="15" height="15" viewBox="0 0 16 16" fill="none">
                <path d="M14 8L2 2l2 6-2 6 12-6z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
            </svg> Send Advice`;
        });
}

// ── Send: Student question ────────────────────────────────────
function sendStudent() {
    const input = document.getElementById('inputStudent');
    const btn   = document.getElementById('btnStudent');
    const toast = document.getElementById('toastStudent');
    const msg   = input.value.trim();
    if (!msg) return;

    toast.className = 'flag-toast';
    btn.disabled    = true;
    btn.textContent = 'Sending…';

    post(STUDENT_AJAX, { action: 'send_student_msg', message: msg })
        .then(data => {
            const ok = data.ok === true || data.status === 'ok';
            if (ok) {
                input.value = '';
                showToast(toast, '✅ Question sent!', 'toast-ok');
                loadHistory();
            } else {
                showToast(toast, '⚠️ ' + (data.error || 'Could not send message.'), 'toast-err');
            }
        })
        .catch(() => showToast(toast, '⚠️ Network error. Please try again.', 'toast-err'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = `<svg width="15" height="15" viewBox="0 0 16 16" fill="none">
                <path d="M14 8L2 2l2 6-2 6 12-6z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
            </svg> Ask Question`;
        });
}

// ── Keyboard shortcuts ────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (!(e.ctrlKey || e.metaKey) || e.key !== 'Enter') return;
    e.preventDefault();
    if (document.activeElement.id === 'inputTeacher') sendTeacher();
    if (document.activeElement.id === 'inputStudent') sendStudent();
});

// ── Boot ─────────────────────────────────────────────────────
loadHistory();
setInterval(loadHistory, 30000);
</script>

<?php echo $OUTPUT->footer(); ?>