<?php
require_once('../../config.php');

$courseid = required_param('courseid', PARAM_INT);

require_login($courseid);

$context = context_course::instance($courseid);

$PAGE->set_context($context);
$PAGE->set_url('/local/automation/student_quiz.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Mini Quiz');
$PAGE->set_heading('Mini Quiz');
$PAGE->set_pagelayout('standard');

echo '<style>
.secondary-navigation { display: none !important; }
#page-header { margin-bottom: 0 !important; }
</style>';

$PAGE->requires->js_call_amd(
    'local_automation/student_quiz',
    'init',
    ['courseid' => $courseid]
);

$quizzes = $DB->get_records(
    'local_automation_student_quiz',
    ['studentid' => $USER->id, 'courseid' => $courseid],
    'timecreated DESC'
);

echo $OUTPUT->header();
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:           #F5F4F0;
    --surface:      #FFFFFF;
    --surface-alt:  #F9F8F5;
    --border:       rgba(0,0,0,0.07);
    --border-md:    rgba(0,0,0,0.11);
    --text:         #1A1A1A;
    --muted:        #6B6B6B;
    --hint:         #A8A8A8;

    --weak:         #E24B4A; --weak-bg:    #FCEBEB; --weak-txt:   #A32D2D;
    --avg:          #EF9F27; --avg-bg:     #FEF3E0; --avg-txt:    #854F0B;
    --strong:       #639922; --strong-bg:  #EAF3DE; --strong-txt: #3B6D11;
    --blue:         #378ADD; --blue-bg:    #E8F2FC; --blue-txt:   #185FA5;
    --purple:       #7F77DD; --purple-bg:  #EEEDFE; --purple-txt: #3C3489;

    --r:   12px;
    --rsm: 8px;
    --font: 'DM Sans', system-ui, sans-serif;
    --mono: 'DM Mono', monospace;
    --sh:  0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    --shm: 0 4px 14px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.05);
}

html, body { background: var(--bg) !important; }
body       { padding: 0 !important; margin: 0 !important; }

#page, #page-wrapper, #page-content, #page-content .row,
#region-main-box, #region-main, .region-content, [role="main"],
.main-inner, #maincontent, .course-content,
div[data-region="blocks-column"], .drawers, .drawers .main-inner {
    max-width: none !important; width: 100% !important;
    padding: 0 !important; margin: 0 !important;
    float: none !important; flex: unset !important;
}
#nav-drawer, .drawer, [data-region="fixed-drawer"],
[data-region="right-hand-drawer"], #block-region-side-pre,
#block-region-side-post, .block-region, aside.block-region {
    display: none !important;
}

/* ── Page shell ── */
.sq-page {
    width: 100vw;
    min-height: 100vh;
    background: var(--bg);
    font-family: var(--font);
    color: var(--text);
    font-size: 14px;
    line-height: 1.5;
}

/* ── Nav ── */
.sq-nav {
    position: sticky;
    top: 0;
    z-index: 100;
    background: rgba(245,244,240,0.94);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-bottom: 0.5px solid var(--border-md);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 28px;
    height: 52px;
    gap: 12px;
}
.sq-nav-left  { display: flex; align-items: center; gap: 12px; }
.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-family: var(--font);
    font-size: 12px;
    font-weight: 500;
    color: var(--muted);
    background: var(--surface);
    border: 0.5px solid var(--border-md);
    border-radius: var(--rsm);
    padding: 5px 12px;
    cursor: pointer;
    text-decoration: none;
    transition: color .15s, border-color .15s, box-shadow .15s;
    box-shadow: var(--sh);
}
.back-btn:hover { color: var(--text); border-color: rgba(0,0,0,0.2); box-shadow: var(--shm); }
.back-btn svg  { width: 14px; height: 14px; flex-shrink: 0; }
.page-title    { font-size: 17px; font-weight: 600; letter-spacing: -.01em; }

/* ── Body ── */
.sq-body {
    max-width: 860px;
    margin: 0 auto;
    padding: 24px 24px 64px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* ── Card ── */
.card {
    background: var(--surface);
    border: 0.5px solid var(--border);
    border-radius: var(--r);
    padding: 20px 22px;
    box-shadow: var(--sh);
}
.card-title {
    font-size: 10px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--muted);
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 0.5px solid var(--border);
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ── Generator card ── */
.gen-card-title {
    font-size: 15px;
    font-weight: 600;
    letter-spacing: -.01em;
    margin-bottom: 18px;
}

.unit-container {
    border: 0.5px solid var(--border-md);
    border-radius: var(--rsm);
    background: var(--surface-alt);
    padding: 12px 14px;
    max-height: 200px;
    overflow-y: auto;
    margin-bottom: 16px;
    font-size: 12px;
}
.unit-container::-webkit-scrollbar { width: 4px; }
.unit-container::-webkit-scrollbar-track { background: transparent; }
.unit-container::-webkit-scrollbar-thumb { background: #D3D1C7; border-radius: 4px; }

.unit-group-label {
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--hint);
    margin: 10px 0 5px;
    padding-left: 2px;
}
.unit-group-label:first-child { margin-top: 0; }

.unit-checkbox-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 4px;
    border-radius: 5px;
    cursor: pointer;
    transition: background .12s;
}
.unit-checkbox-row:hover { background: rgba(0,0,0,0.03); }
.unit-checkbox-row input[type="checkbox"] {
    accent-color: var(--blue);
    width: 13px;
    height: 13px;
    flex-shrink: 0;
    cursor: pointer;
}
.unit-checkbox-row span { color: var(--text); line-height: 1.4; }

/* ── Settings row ── */
.settings-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 18px;
}
.setting-block label {
    display: block;
    font-size: 10px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--muted);
    margin-bottom: 6px;
}
.setting-block input[type="number"],
.setting-block select {
    width: 100%;
    font-family: var(--font);
    font-size: 13px;
    padding: 8px 12px;
    border: 0.5px solid var(--border-md);
    border-radius: var(--rsm);
    background: var(--surface);
    color: var(--text);
    outline: none;
    transition: border-color .15s, box-shadow .15s;
    appearance: none;
    -webkit-appearance: none;
    box-shadow: var(--sh);
}
.setting-block input[type="number"]:focus,
.setting-block select:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(55,138,221,0.12);
}
.select-wrap { position: relative; }
.select-wrap::after {
    content: '';
    pointer-events: none;
    position: absolute;
    right: 11px;
    top: 50%;
    transform: translateY(-50%);
    width: 0; height: 0;
    border-left: 4px solid transparent;
    border-right: 4px solid transparent;
    border-top: 5px solid var(--muted);
}

/* ── Generate button ── */
.generate-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-family: var(--font);
    font-size: 13px;
    font-weight: 500;
    background: var(--text);
    color: #FFFFFF;
    border: none;
    border-radius: var(--rsm);
    padding: 9px 20px;
    cursor: pointer;
    transition: opacity .15s, transform .12s, box-shadow .15s;
    box-shadow: 0 2px 6px rgba(0,0,0,0.18);
    letter-spacing: -.01em;
}
.generate-btn:hover  { opacity: .87; box-shadow: 0 4px 12px rgba(0,0,0,0.22); }
.generate-btn:active { transform: scale(.97); }
.generate-btn svg    { width: 14px; height: 14px; flex-shrink: 0; }

/* ── Quiz container (rendered questions) ── */
#quizContainer { margin-top: 18px; }

/* ── Section label (above table) ── */
.sh {
    font-size: 10px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: var(--muted);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.sh::after { content:''; flex:1; height:.5px; background: var(--border-md); }

/* ── Log table ── */
.log-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.log-table thead tr {
    border-bottom: 0.5px solid var(--border-md);
}
.log-table th {
    text-align: left;
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--muted);
    padding: 0 8px 9px;
}
.log-table th:first-child { padding-left: 0; }
.log-table th:last-child  { padding-right: 0; }
.log-table td {
    padding: 10px 8px;
    border-bottom: 0.5px solid var(--border);
    vertical-align: middle;
    color: var(--text);
}
.log-table td:first-child { padding-left: 0; }
.log-table td:last-child  { padding-right: 0; }
.log-table tbody tr:last-child td { border-bottom: none; }
.log-table tbody tr {
    cursor: pointer;
    transition: background .1s;
    border-radius: 4px;
}
.log-table tbody tr:hover td { background: var(--surface-alt); }

/* ── Badges ── */
.badge {
    display: inline-block;
    font-size: 10px;
    font-weight: 500;
    font-family: var(--mono);
    padding: 2px 8px;
    border-radius: 20px;
    white-space: nowrap;
}
.badge-easy   { background: var(--strong-bg); color: var(--strong-txt); }
.badge-medium { background: var(--avg-bg);    color: var(--avg-txt); }
.badge-hard   { background: var(--weak-bg);   color: var(--weak-txt); }

.accuracy-val {
    font-family: var(--mono);
    font-size: 11px;
    font-weight: 500;
}
.score-val {
    font-family: var(--mono);
    font-size: 11px;
    color: var(--muted);
}

.empty-state {
    padding: 28px 0;
    text-align: center;
    font-size: 12px;
    color: var(--hint);
}
.empty-state span { display: block; font-size: 22px; margin-bottom: 6px; }

/* ── Loading spinner ── */
.loading {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 11px;
    color: var(--muted);
    padding: 14px 0;
}
.spinner {
    width: 12px; height: 12px;
    border: 1.5px solid #D8D6D0;
    border-top-color: var(--blue);
    border-radius: 50%;
    animation: spin .6s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Responsive ── */
@media (max-width: 640px) {
    .sq-nav    { padding: 0 14px; }
    .sq-body   { padding: 14px 12px 50px; }
    .settings-row { grid-template-columns: 1fr; }
    .log-table .col-unit,
    .log-table .col-diff { display: none; }
}
</style>

<div class="sq-page">

    <!-- ════ NAV ════ -->
    <div class="sq-nav">
        <div class="sq-nav-left">
            <a class="back-btn" href="javascript:history.back()">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10 3L5 8l5 5"/>
                </svg>
                Back
            </a>
            <h1 class="page-title">Practice Quiz</h1>
        </div>
    </div>

    <!-- ════ BODY ════ -->
    <div class="sq-body">

        <!-- ── Quiz Generator ── -->
        <div class="card">
            <div class="card-title">✏️ Generate a new quiz</div>

            <label style="display:block;font-size:10px;font-weight:500;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:6px;">
                Units &amp; Sections
            </label>
            <div class="unit-container" id="unitContainer"></div>

            <div class="settings-row">
                <div class="setting-block">
                    <label>Number of Questions</label>
                    <input type="number" id="questionCount" min="1" placeholder="e.g. 10">
                </div>
                <div class="setting-block">
                    <label>Difficulty</label>
                    <div class="select-wrap">
                        <select id="difficulty">
                            <option value="easy">Easy</option>
                            <option value="medium" selected>Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>
                </div>
            </div>

            <button class="generate-btn" id="generateQuizBtn">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8 1v14M1 8h14"/>
                </svg>
                Generate Quiz
            </button>

            <div id="quizContainer"></div>
        </div>

        <!-- ── Quiz Attempt Log ── -->
        <div class="card">
            <div class="card-title">📋 Quiz attempt log</div>

            <?php if (!$quizzes): ?>
                <div class="empty-state">
                    <span>📝</span>
                    No quiz attempts yet — generate your first quiz above.
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="log-table">
                        <thead>
                            <tr>
                                <th>Section / Topic</th>
                                <th class="col-diff">Difficulty</th>
                                <th>Score</th>
                                <th>Accuracy</th>
                                <th>Date &amp; Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quizzes as $q):
                                $accuracy = ($q->total > 0)
                                    ? round(($q->score / $q->total) * 100)
                                    : 0;
                                $acc_color = $accuracy >= 70 ? 'var(--strong)' : ($accuracy >= 40 ? 'var(--avg)' : 'var(--weak)');
                                $diff_lc   = strtolower($q->difficulty);
                                $badge_cls = 'badge-' . $diff_lc;
                                $datetime  = date('d M Y, h:i A', $q->timecreated);
                                $url       = new moodle_url('/local/automation/student_quiz_analysis.php', ['quizid' => $q->id]);
                            ?>
                            <tr onclick="window.location.href='<?php echo $url; ?>'">
                                <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo s($q->topic); ?>">
                                    <?php echo s($q->topic); ?>
                                </td>
                                <td class="col-diff">
                                    <span class="badge <?php echo $badge_cls; ?>"><?php echo s($q->difficulty); ?></span>
                                </td>
                                <td class="score-val"><?php echo (int)$q->score; ?> / <?php echo (int)$q->total; ?></td>
                                <td>
                                    <span class="accuracy-val" style="color:<?php echo $acc_color; ?>"><?php echo $accuracy; ?>%</span>
                                </td>
                                <td style="font-size:11px;color:var(--muted);white-space:nowrap;"><?php echo $datetime; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div><!-- /.sq-body -->
</div><!-- /.sq-page -->

<script>
const courseid = <?php echo (int)$courseid; ?>;
const userid   = <?php echo (int)$USER->id; ?>;

/* ── Lock difficulty options ── */
function loadLocks() {
    fetch(M.cfg.wwwroot + "/local/automation/analytics_ajax.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
            action:    "get_locks",
            studentid: userid,
            courseid:  courseid,
            sesskey:   M.cfg.sesskey
        })
    })
    .then(r => r.json())
    .then(data => {
        const select = document.getElementById("difficulty");
        data.forEach(l => {
            if (l.locked == 1) {
                const opt = select.querySelector(`option[value="${l.difficulty}"]`);
                if (opt) { opt.disabled = true; opt.text += " 🔒"; }
            }
        });
    });
}

/* ── Generate quiz ── */
document.getElementById("generateQuizBtn").addEventListener("click", function () {
    const difficulty = document.getElementById("difficulty").value;
    const count      = document.getElementById("questionCount").value;

    if (!count) {
        alert("Please enter the number of questions.");
        return;
    }

    const btn = this;
    btn.disabled = true;
    btn.innerHTML = `<div class="spinner" style="border-top-color:#fff;"></div> Generating…`;

    fetch(M.cfg.wwwroot + "/local/automation/student_ajax.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
            action:     "generate_quiz",
            courseid:   courseid,
            difficulty: difficulty,
            count:      count,
            sesskey:    M.cfg.sesskey
        })
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = `<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px"><path d="M8 1v14M1 8h14"/></svg> Generate Quiz`;
        if (res.error) {
            alert(res.message);
            return;
        }
        document.getElementById("quizContainer").innerHTML = res.html;
        document.getElementById("quizContainer").scrollIntoView({ behavior: "smooth", block: "start" });
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = `<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px"><path d="M8 1v14M1 8h14"/></svg> Generate Quiz`;
    });
});

/* ── Init ── */
loadLocks();
</script>

<?php echo $OUTPUT->footer(); ?>