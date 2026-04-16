<?php

require_once(__DIR__.'/../../config.php');
require_login();

$courseid  = required_param('courseid',  PARAM_INT);
$studentid = required_param('studentid', PARAM_INT);

$context = context_course::instance($courseid);

$PAGE->set_context($context);
$PAGE->set_url('/local/automation/chat.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Teacher Advice');
$PAGE->set_heading('Teacher Advice');

echo $OUTPUT->header();
?>

<!-- Google Fonts: DM Sans (matches the dashboard's geometric sans-serif feel) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">

<style>
/* ── Reset & Base ────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

#advice-root {
    --bg:          #f5f5f7;
    --surface:     #ffffff;
    --border:      #e8e8ec;
    --text-primary:#1a1a2e;
    --text-muted:  #8a8a9a;
    --text-label:  #5c5c72;

    /* accent palette (mirrors dashboard badge colours) */
    --red:         #e05252;
    --red-light:   #fdf0f0;
    --orange:      #f0994a;
    --orange-light:#fff8f0;
    --blue:        #4a7ef0;
    --blue-light:  #f0f4ff;
    --green:       #52b788;
    --green-light: #f0faf5;
    --purple:      #7c6fcd;
    --purple-light:#f5f3ff;

    font-family: 'DM Sans', sans-serif;
    font-size:   15px;
    color:       var(--text-primary);
    background:  var(--bg);
    min-height:  100vh;
    padding:     32px 24px 48px;
}

/* ── Page Header ─────────────────────────────────────────────── */
.advice-header {
    display:         flex;
    align-items:     center;
    gap:             16px;
    margin-bottom:   28px;
    flex-wrap:       wrap;
}

.back-btn {
    display:         inline-flex;
    align-items:     center;
    gap:             6px;
    padding:         7px 14px;
    border:          1.5px solid var(--border);
    border-radius:   8px;
    background:      var(--surface);
    color:           var(--text-primary);
    font-family:     inherit;
    font-size:       13px;
    font-weight:     500;
    cursor:          pointer;
    transition:      border-color .15s, box-shadow .15s;
    text-decoration: none;
}
.back-btn:hover {
    border-color: #bbb;
    box-shadow:   0 2px 8px rgba(0,0,0,.06);
}
.back-btn svg { flex-shrink: 0; }

.advice-title {
    font-size:   20px;
    font-weight: 700;
    letter-spacing: -.3px;
}

.badge {
    display:       inline-flex;
    align-items:   center;
    padding:       4px 12px;
    border-radius: 999px;
    font-size:     12px;
    font-weight:   600;
    letter-spacing:.2px;
}
.badge-student {
    background: #eef3ff;
    color:      var(--blue);
    border:     1.5px solid #d0deff;
}
.badge-course {
    background: #fdf0f0;
    color:      var(--red);
    border:     1.5px solid #f5cece;
}

.timestamp {
    margin-left: auto;
    font-size:   12px;
    color:       var(--text-muted);
    font-weight: 400;
}

/* ── Section Label ───────────────────────────────────────────── */
.section-label {
    font-size:      11px;
    font-weight:    600;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    color:          var(--text-muted);
    margin-bottom:  14px;
}

/* ── Card Shell ──────────────────────────────────────────────── */
.advice-card-wrap {
    background:    var(--surface);
    border:        1.5px solid var(--border);
    border-radius: 14px;
    padding:       20px 24px;
    box-shadow:    0 1px 4px rgba(0,0,0,.04);
}

/* ── Empty / Loading States ──────────────────────────────────── */
.state-msg {
    display:         flex;
    flex-direction:  column;
    align-items:     center;
    justify-content: center;
    padding:         52px 24px;
    gap:             12px;
    color:           var(--text-muted);
}
.state-msg .state-icon {
    width:  48px;
    height: 48px;
    border-radius: 12px;
    background: var(--bg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}
.state-msg p { font-size: 14px; }

/* ── Advice Items ────────────────────────────────────────────── */
.advice-list {
    display:        flex;
    flex-direction: column;
    gap:            12px;
}

.advice-item {
    display:       flex;
    gap:           14px;
    padding:       14px 16px;
    border-radius: 10px;
    border:        1.5px solid transparent;
    transition:    background .15s, border-color .15s;
    position:      relative;
    overflow:      hidden;
}
.advice-item::before {
    content:       '';
    position:      absolute;
    left:          0; top: 0; bottom: 0;
    width:         4px;
    border-radius: 4px 0 0 4px;
}

/* colour variants — cycles through accent colours */
.advice-item.c-blue   { background: var(--blue-light);   border-color: #d6e4ff; }
.advice-item.c-blue::before   { background: var(--blue); }

.advice-item.c-orange { background: var(--orange-light); border-color: #ffe8cc; }
.advice-item.c-orange::before { background: var(--orange); }

.advice-item.c-green  { background: var(--green-light);  border-color: #c8eedd; }
.advice-item.c-green::before  { background: var(--green); }

.advice-item.c-red    { background: var(--red-light);    border-color: #f5cece; }
.advice-item.c-red::before    { background: var(--red); }

.advice-item.c-purple { background: var(--purple-light); border-color: #ddd8f8; }
.advice-item.c-purple::before { background: var(--purple); }

.advice-icon {
    flex-shrink:   0;
    width:         34px;
    height:        34px;
    border-radius: 8px;
    background:    rgba(255,255,255,.7);
    display:       flex;
    align-items:   center;
    justify-content:center;
    font-size:     16px;
    margin-top:    1px;
}

.advice-body { flex: 1; min-width: 0; }

.advice-tag {
    font-size:      10px;
    font-weight:    700;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-bottom:  4px;
    display:        block;
}
.c-blue   .advice-tag { color: var(--blue); }
.c-orange .advice-tag { color: var(--orange); }
.c-green  .advice-tag { color: var(--green); }
.c-red    .advice-tag { color: var(--red); }
.c-purple .advice-tag { color: var(--purple); }

.advice-text {
    font-size:   14px;
    font-weight: 500;
    line-height: 1.55;
    color:       var(--text-primary);
    margin-bottom: 6px;
}

.advice-meta {
    font-size:  12px;
    color:      var(--text-muted);
    font-weight:400;
}

/* ── Skeleton Loader ─────────────────────────────────────────── */
@keyframes shimmer {
    0%   { background-position: -600px 0; }
    100% { background-position:  600px 0; }
}
.skeleton {
    border-radius: 6px;
    background: linear-gradient(90deg, #ececec 25%, #f8f8f8 50%, #ececec 75%);
    background-size: 1200px 100%;
    animation: shimmer 1.4s infinite linear;
}
.skeleton-item {
    display: flex; gap: 14px; padding: 14px 16px;
    border: 1.5px solid var(--border); border-radius: 10px;
    background: var(--surface);
}
.skeleton-icon  { width:34px; height:34px; border-radius:8px; flex-shrink:0; }
.skeleton-lines { flex:1; display:flex; flex-direction:column; gap:8px; padding-top:4px; }
.skeleton-line  { height:12px; }
.skeleton-line.w-60 { width:60%; }
.skeleton-line.w-85 { width:85%; }
.skeleton-line.w-40 { width:40%; }

/* ── Refresh indicator ───────────────────────────────────────── */
.refresh-row {
    display:         flex;
    align-items:     center;
    justify-content: flex-end;
    gap:             6px;
    margin-top:      16px;
    font-size:       12px;
    color:           var(--text-muted);
}
.pulse-dot {
    width:  8px; height: 8px;
    border-radius: 50%;
    background: var(--green);
    animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse {
    0%,100% { opacity:1; transform:scale(1); }
    50%      { opacity:.4; transform:scale(.8); }
}
</style>

<div id="advice-root">

    <!-- Header -->
    <div class="advice-header">
        <button class="back-btn" onclick="goBack()">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <path d="M9 2L4 7L9 12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Back
        </button>

        <span class="advice-title">Teacher Advice</span>

        <span class="badge badge-student">Student — ID <?php echo (int)$studentid; ?></span>
        <span class="badge badge-course">Course <?php echo (int)$courseid; ?></span>

        <span class="timestamp" id="lastUpdated"></span>
    </div>

    <!-- Content card -->
    <div class="advice-card-wrap">
        <div class="section-label">📋 &nbsp;Advice for this student</div>
        <div id="adviceBox">
            <!-- Skeleton placeholders shown while loading -->
            <div class="advice-list" id="skeletonList">
                <?php for($i = 0; $i < 3; $i++): ?>
                <div class="skeleton-item">
                    <div class="skeleton skeleton-icon"></div>
                    <div class="skeleton-lines">
                        <div class="skeleton skeleton-line w-40"></div>
                        <div class="skeleton skeleton-line w-85"></div>
                        <div class="skeleton skeleton-line w-60"></div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="refresh-row">
            <span class="pulse-dot"></span>
            Auto-refreshing every 5 s
        </div>
    </div>

</div>

<script>
const courseid  = <?php echo (int)$courseid; ?>;
const studentid = <?php echo (int)$studentid; ?>;

/* Colour cycle for advice cards */
const COLOURS = ['c-blue','c-orange','c-green','c-red','c-purple'];
const ICONS   = ['💡','📝','🎯','⚠️','✅','📌','🔍','📊'];
const TAGS    = ['Advice','Note','Priority','Reminder','Tip'];

function colourFor(index){ return COLOURS[index % COLOURS.length]; }
function iconFor(index)  { return ICONS[index  % ICONS.length];   }
function tagFor(index)   { return TAGS[index   % TAGS.length];    }

function formatDate(ts){
    return new Date(ts * 1000).toLocaleString([], {
        day:'2-digit', month:'short', year:'numeric',
        hour:'2-digit', minute:'2-digit'
    });
}

/* ── Load advice ──────────────────────────────────────────────── */
function loadAdvice(){
    fetch(M.cfg.wwwroot + "/local/automation/analytics_ajax.php", {
        method:  'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body:    new URLSearchParams({
            action:    'get_advice',
            courseid:  courseid,
            studentid: studentid,
            sesskey:   M.cfg.sesskey
        })
    })
    .then(r => r.json())
    .then(data => {
        const box = document.getElementById('adviceBox');

        /* update timestamp */
        const now = new Date();
        document.getElementById('lastUpdated').textContent =
            'Updated ' + now.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});

        if (!data || data.length === 0) {
            box.innerHTML = `
            <div class="state-msg">
                <div class="state-icon">🗒️</div>
                <p>No advice recorded yet for this student.</p>
            </div>`;
            return;
        }

        let html = '<div class="advice-list">';
        data.forEach((a, i) => {
            const cls  = colourFor(i);
            const icon = iconFor(i);
            const tag  = tagFor(i);
            html += `
            <div class="advice-item ${cls}">
                <div class="advice-icon">${icon}</div>
                <div class="advice-body">
                    <span class="advice-tag">${tag}</span>
                    <p class="advice-text">${a.advice}</p>
                    <span class="advice-meta">${formatDate(a.timecreated)}</span>
                </div>
            </div>`;
        });
        html += '</div>';
        box.innerHTML = html;
    })
    .catch(() => {
        document.getElementById('adviceBox').innerHTML = `
        <div class="state-msg">
            <div class="state-icon">⚠️</div>
            <p>Could not load advice. Will retry shortly.</p>
        </div>`;
    });
}

/* ── Back ─────────────────────────────────────────────────────── */
function goBack(){ window.history.back(); }

/* ── Init ─────────────────────────────────────────────────────── */
loadAdvice();
setInterval(loadAdvice, 5000);
</script>

<?php echo $OUTPUT->footer(); ?>