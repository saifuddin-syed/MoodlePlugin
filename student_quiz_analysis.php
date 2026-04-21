<?php
require_once('../../config.php');

require_login();

global $DB, $USER, $PAGE, $OUTPUT;

// Get quiz id
$quizid = required_param('quizid', PARAM_INT);

// Fetch quiz attempt
$quiz = $DB->get_record('local_automation_student_quiz', ['id' => $quizid], '*', MUST_EXIST);
$user = $DB->get_record('user', ['id' => $quiz->studentid], '*', MUST_EXIST);

// Get courseid from quiz
$courseid = $quiz->courseid;

// Set context properly
$context = context_course::instance($courseid);
$PAGE->set_context($context);

// Security check
if ($quiz->studentid != $USER->id && !has_capability('moodle/course:update', $context)) {
    throw new moodle_exception('Access denied');
}

// Page setup (AFTER context)
$PAGE->set_url('/local/automation/student_quiz_analysis.php', ['quizid' => $quizid]);
$PAGE->set_title('Quiz Analysis');
$PAGE->set_heading('Quiz Analysis');
$PAGE->set_pagelayout('standard');

$topicFilePath = __DIR__ . '/rag/demo_topics.json';

$topicData = [];
if (file_exists($topicFilePath)) {
    $json = file_get_contents($topicFilePath);
    $topicData = json_decode($json, true);
}

echo $OUTPUT->header();

// ── Derived values ──────────────────────────────────────────────────────────
$studentname = fullname($user);
$score       = (int)$quiz->score;
$total       = (int)$quiz->total;
$accuracy    = $total > 0 ? round($score / $total * 100) : 0;
$datetime    = date('d M Y, h:i A', $quiz->timecreated);

// Grade
$grade = $accuracy >= 90 ? 'A+' : ($accuracy >= 80 ? 'A' : ($accuracy >= 70 ? 'B' : ($accuracy >= 60 ? 'C' : ($accuracy >= 50 ? 'D' : 'F'))));
$gradeColors = [
    'A+' => ['bg' => '#EAF3DE', 'c' => '#3B6D11'],
    'A'  => ['bg' => '#EAF3DE', 'c' => '#3B6D11'],
    'B'  => ['bg' => '#E8F2FC', 'c' => '#185FA5'],
    'C'  => ['bg' => '#FEF3E0', 'c' => '#854F0B'],
    'D'  => ['bg' => '#FEF3E0', 'c' => '#854F0B'],
    'F'  => ['bg' => '#FCEBEB', 'c' => '#A32D2D'],
];
$gc = $gradeColors[$grade];

// Accuracy ring color
$ringColor = $accuracy >= 70 ? '#639922' : ($accuracy >= 40 ? '#EF9F27' : '#E24B4A');

// Topic map
$topicString = $quiz->topic;
$topicMap = [];
if (!empty($topicString)) {
    $units = explode(';', $topicString);
    foreach ($units as $unitEntry) {
        $unitEntry = trim($unitEntry);
        if (strpos($unitEntry, ':') !== false) {
            list($unit, $sectionsStr) = explode(':', $unitEntry);
            $unit = trim($unit);
            $sections = array_map('trim', explode(',', $sectionsStr));
            $topicMap[$unit] = $sections;
        } else {
            $topicMap[$unitEntry] = [];
        }
    }
}

// Questions & per-question correctness (pre-compute for sidebar)
$questions   = $DB->get_records('local_automation_quiz_questions', ['quizattemptid' => $quizid]);
$totalQ      = count($questions);
$correctQ    = 0;
$qResults    = []; // [id => 'correct'|'wrong'|'skip']
foreach ($questions as $q) {
    $opts      = $DB->get_records('local_automation_question_options', ['questionattemptid' => $q->id]);
    $answered  = ($q->selectedoption !== null && $q->selectedoption !== '');
    $correct   = false;
    foreach ($opts as $o) {
        if ($o->iscorrect && $q->selectedoption == $o->optionnumber) {
            $correct = true; break;
        }
    }
    if (!$answered)    { $qResults[$q->id] = 'skip'; }
    elseif ($correct)  { $qResults[$q->id] = 'correct'; $correctQ++; }
    else               { $qResults[$q->id] = 'wrong'; }
}

?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
/* ── Reset & Variables ─────────────────────────────────────────────────────── */
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

    --weak:         #E24B4A; --weak-bg:    #FCEBEB; --weak-txt:    #A32D2D;
    --avg:          #EF9F27; --avg-bg:     #FEF3E0; --avg-txt:     #854F0B;
    --strong:       #639922; --strong-bg:  #EAF3DE; --strong-txt:  #3B6D11;
    --blue:         #378ADD; --blue-bg:    #E8F2FC; --blue-txt:    #185FA5;
    --purple:       #7F77DD; --purple-bg:  #EEEDFE; --purple-txt:  #3C3489;
    --cyan:         #1B9E9E; --cyan-bg:    #E0F5F5; --cyan-txt:    #0D5C5C;

    --r:   12px;
    --rsm: 8px;
    --font: 'DM Sans', system-ui, sans-serif;
    --mono: 'DM Mono', monospace;
    --sh:  0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    --shm: 0 4px 14px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.05);
}

/* ── Moodle overrides ────────────────────────────────────────────────────── */
.secondary-navigation { display: none !important; }
#page-header { margin-bottom: 0 !important; }
html, body { background: var(--bg) !important; }
body { padding: 0 !important; margin: 0 !important; }
#page, #page-wrapper, #page-content, #page-content .row,
#region-main-box, #region-main, .region-content, [role="main"],
.main-inner, #maincontent, .course-content {
    max-width: none !important; width: 100% !important;
    padding: 0 !important; margin: 0 !important;
    float: none !important; flex: unset !important;
}
#nav-drawer, .drawer, [data-region="fixed-drawer"],
[data-region="right-hand-drawer"], #block-region-side-pre,
#block-region-side-post, .block-region, aside.block-region { display: none !important; }

/* ── Page shell ──────────────────────────────────────────────────────────── */
.qa {
    width: 100vw;
    min-height: 100vh;
    background: var(--bg);
    font-family: var(--font);
    color: var(--text);
    font-size: 14px;
    line-height: 1.5;
}

/* ── Nav ─────────────────────────────────────────────────────────────────── */
.qa-nav {
    position: sticky; top: 0; z-index: 100;
    background: rgba(245,244,240,0.94);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-bottom: 0.5px solid var(--border-md);
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 28px; height: 52px; gap: 12px;
}
.qa-nav-left  { display: flex; align-items: center; gap: 12px; }
.qa-nav-right { display: flex; align-items: center; gap: 10px; }

.back-btn {
    display: inline-flex; align-items: center; gap: 6px;
    font-family: var(--font); font-size: 12px; font-weight: 500;
    color: var(--muted); background: var(--surface);
    border: 0.5px solid var(--border-md); border-radius: var(--rsm);
    padding: 5px 12px; cursor: pointer; text-decoration: none;
    transition: color .15s, border-color .15s, box-shadow .15s;
    box-shadow: var(--sh);
}
.back-btn:hover { color: var(--text); border-color: rgba(0,0,0,0.2); box-shadow: var(--shm); }
.back-btn svg { width: 14px; height: 14px; flex-shrink: 0; }

.page-title { font-size: 17px; font-weight: 600; letter-spacing: -.01em; }
.pill {
    font-family: var(--mono); font-size: 11px; padding: 3px 11px; border-radius: 20px;
    border: 0.5px solid rgba(55,138,221,0.25); background: var(--blue-bg); color: var(--blue-txt);
}
.grade-pill { font-size: 12px; font-weight: 600; padding: 3px 13px; border-radius: 20px; }
.nav-ts     { font-family: var(--mono); font-size: 10px; color: var(--hint); }

/* ── Reading progress ────────────────────────────────────────────────────── */
.qa-progress { height: 2px; background: var(--border); }
.qa-progress-fill {
    height: 100%; width: 0%;
    background: linear-gradient(90deg, #378ADD, #639922);
    transition: width .1s linear; border-radius: 2px;
}

/* ── Body layout ─────────────────────────────────────────────────────────── */
.qa-body {
    max-width: 1260px; margin: 0 auto;
    padding: 20px 24px 72px;
    display: grid;
    grid-template-columns: 292px 1fr;
    gap: 14px;
    align-items: start;
}
.col-sidebar { grid-column: 1; display: flex; flex-direction: column; gap: 12px; position: sticky; top: 66px; }
.col-main    { grid-column: 2; display: flex; flex-direction: column; gap: 14px; }

/* ── Card ────────────────────────────────────────────────────────────────── */
.card {
    background: var(--surface); border: 0.5px solid var(--border);
    border-radius: var(--r); padding: 16px 18px; box-shadow: var(--sh);
}
.card-title {
    font-size: 10px; font-weight: 500; text-transform: uppercase;
    letter-spacing: .08em; color: var(--muted);
    margin-bottom: 13px; padding-bottom: 9px;
    border-bottom: 0.5px solid var(--border);
    display: flex; align-items: center; gap: 6px;
}

/* ── Score ring card ─────────────────────────────────────────────────────── */
.score-ring-card {
    background: var(--surface); border: 0.5px solid var(--border);
    border-radius: var(--r); padding: 20px 18px 16px; box-shadow: var(--sh);
    display: flex; flex-direction: column; align-items: center; gap: 14px;
}
.ring-wrap { position: relative; width: 100px; height: 100px; }
.ring-wrap svg { transform: rotate(-90deg); }
.ring-bg   { fill: none; stroke: #EDECE8; stroke-width: 7; }
.ring-fill { fill: none; stroke-width: 7; stroke-linecap: round;
             transition: stroke-dashoffset 1.1s cubic-bezier(.16,1,.3,1); }
.ring-label {
    position: absolute; inset: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center; line-height: 1;
}
.ring-pct { font-family: var(--mono); font-size: 22px; font-weight: 600; letter-spacing: -.03em; }
.ring-sub { font-size: 9px; color: var(--hint); margin-top: 2px; font-family: var(--mono); }

.score-meta  { text-align: center; }
.score-frac  { font-family: var(--mono); font-size: 15px; font-weight: 600; }
.score-label { font-size: 10px; color: var(--muted); margin-top: 2px; }
.score-divider { width: 100%; height: .5px; background: var(--border); }

/* ── Meta rows ───────────────────────────────────────────────────────────── */
.meta-row {
    display: flex; align-items: center; gap: 10px;
    padding: 6px 0; border-bottom: .5px solid var(--border);
}
.meta-row:last-child { border-bottom: none; }
.meta-icon  { font-size: 14px; width: 22px; text-align: center; flex-shrink: 0; }
.meta-label { font-size: 10px; color: var(--muted); flex: 1; }
.meta-val   { font-family: var(--mono); font-size: 11px; font-weight: 500; }

/* ── Mini metrics ────────────────────────────────────────────────────────── */
.mini-metrics { display: grid; grid-template-columns: 1fr 1fr; gap: 7px; }
.mini-metric {
    background: var(--surface); border: 0.5px solid var(--border);
    border-radius: var(--rsm); padding: 10px 12px; box-shadow: var(--sh);
    position: relative; overflow: hidden;
}
.mini-metric::before {
    content: ''; position: absolute; top: 0; left: 0; bottom: 0; width: 3px;
    background: var(--accent, #D3D1C7); border-radius: var(--rsm) 0 0 var(--rsm);
}
.mm-icon  { font-size: 11px; margin-bottom: 3px; display: block; }
.mm-label { font-size: 9px; font-weight: 500; text-transform: uppercase; letter-spacing: .07em; color: var(--muted); }
.mm-val   { font-size: 17px; font-weight: 600; font-family: var(--mono); line-height: 1; letter-spacing: -.02em; }
.mm-sub   { font-size: 9px; color: var(--hint); margin-top: 2px; }

/* ── Topics covered ──────────────────────────────────────────────────────── */
.unit-block { margin-bottom: 10px; }
.unit-block:last-child { margin-bottom: 0; }
.unit-name {
    font-size: 11px; font-weight: 600;
    display: flex; align-items: center; gap: 6px; margin-bottom: 6px;
}
.unit-tag {
    font-family: var(--mono); font-size: 9px; padding: 2px 7px; border-radius: 20px;
    background: var(--blue-bg); color: var(--blue-txt); border: 0.5px solid rgba(55,138,221,0.25);
}
.sec-chip-grid { display: flex; flex-direction: column; gap: 3px; }
.sec-chip {
    display: flex; align-items: center; gap: 8px;
    background: var(--surface-alt); border: 0.5px solid var(--border);
    border-radius: 6px; padding: 5px 8px; font-size: 10px;
}
.sec-num   { font-family: var(--mono); color: var(--muted); min-width: 28px; flex-shrink: 0; }
.sec-title { color: var(--text); flex: 1; }

/* ── Recommendation ──────────────────────────────────────────────────────── */
.reco {
    border-radius: var(--rsm); padding: 11px 12px 11px 16px; margin-bottom: 8px;
    position: relative; overflow: hidden; border: .5px solid var(--border);
    background: var(--surface); box-shadow: var(--sh); transition: box-shadow .15s;
}
.reco:last-child { margin-bottom: 0; }
.reco:hover { box-shadow: var(--shm); }
.reco-stripe { position: absolute; left: 0; top: 0; bottom: 0; width: 3px; }
.reco-type   { font-size: 9px; font-weight: 500; text-transform: uppercase; letter-spacing: .09em; color: var(--muted); margin-bottom: 2px; }
.reco-title  { font-size: 12px; font-weight: 500; line-height: 1.4; color: var(--text); }

/* ── Section heading ─────────────────────────────────────────────────────── */
.sh {
    font-size: 10px; font-weight: 500; text-transform: uppercase; letter-spacing: .1em;
    color: var(--muted); margin-bottom: 10px; display: flex; align-items: center; gap: 8px;
}
.sh::after { content: ''; flex: 1; height: .5px; background: var(--border-md); }
.sh-count {
    font-family: var(--mono); font-size: 10px; background: var(--surface-alt);
    border: 0.5px solid var(--border-md); padding: 1px 7px; border-radius: 20px; color: var(--muted);
}

/* ── Q navigator ─────────────────────────────────────────────────────────── */
.q-nav { display: flex; gap: 5px; flex-wrap: wrap; }
.q-nav-dot {
    width: 28px; height: 28px; border-radius: 6px;
    font-family: var(--mono); font-size: 10px; font-weight: 500;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; border: 0.5px solid transparent;
    transition: all .15s; flex-shrink: 0; text-decoration: none;
}
.q-nav-dot:hover { transform: translateY(-1px); box-shadow: var(--shm); }
.qnd-correct { background: var(--strong-bg); color: var(--strong-txt); border-color: rgba(99,153,34,0.3); }
.qnd-wrong   { background: var(--weak-bg);   color: var(--weak-txt);   border-color: rgba(226,75,74,0.3); }
.qnd-skip    { background: #F0EEE8;          color: var(--hint);        border-color: var(--border-md); }

/* ── Question card ───────────────────────────────────────────────────────── */
.q-card {
    background: var(--surface); border: 0.5px solid var(--border);
    border-radius: var(--r); box-shadow: var(--sh); overflow: hidden;
    transition: box-shadow .15s;
    scroll-margin-top: 76px;
}
.q-card:hover { box-shadow: var(--shm); }

.q-card-head {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 14px 16px 12px; border-bottom: .5px solid var(--border);
}
.q-number {
    font-family: var(--mono); font-size: 10px; font-weight: 500;
    background: var(--surface-alt); border: .5px solid var(--border-md);
    border-radius: var(--rsm); padding: 4px 9px; white-space: nowrap; flex-shrink: 0; color: var(--muted);
}
.qn-correct { background: var(--strong-bg); color: var(--strong-txt); border-color: rgba(99,153,34,0.3); }
.qn-wrong   { background: var(--weak-bg);   color: var(--weak-txt);   border-color: rgba(226,75,74,0.3); }
.qn-skip    { background: #F0EEE8;          color: var(--hint);        border-color: var(--border-md); }

.q-text-wrap { flex: 1; min-width: 0; }
.q-text      { font-size: 13px; font-weight: 500; line-height: 1.55; margin-bottom: 6px; }
.q-topic-line{ display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }

.q-result-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 10px; font-weight: 500; font-family: var(--mono);
    padding: 2px 9px; border-radius: 20px; flex-shrink: 0; white-space: nowrap;
}
.qrb-correct { background: var(--strong-bg); color: var(--strong-txt); }
.qrb-wrong   { background: var(--weak-bg);   color: var(--weak-txt); }
.qrb-skip    { background: #F0EEE8;          color: var(--hint); }

/* ── Options ─────────────────────────────────────────────────────────────── */
.q-options { padding: 12px 16px; display: flex; flex-direction: column; gap: 6px; }
.opt-item {
    border-radius: 8px; border: 1px solid transparent;
    padding: 10px 12px; display: flex; align-items: flex-start; gap: 10px;
    transition: background .12s; position: relative; overflow: hidden;
}
.opt-item::before {
    content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px;
    border-radius: 8px 0 0 8px;
}
.opt-neutral             { background: var(--surface-alt); border-color: var(--border-md); }
.opt-neutral::before     { background: transparent; }
.opt-correct             { background: var(--strong-bg); border-color: rgba(99,153,34,0.4); }
.opt-correct::before     { background: #639922; }
.opt-wrong               { background: var(--weak-bg); border-color: rgba(226,75,74,0.35); }
.opt-wrong::before       { background: #E24B4A; }
.opt-missed              { background: rgba(99,153,34,0.06); border-color: rgba(99,153,34,0.25); border-style: dashed; }
.opt-missed::before      { background: rgba(99,153,34,0.35); }

.opt-letter {
    font-family: var(--mono); font-size: 10px; font-weight: 600;
    width: 22px; height: 22px; border-radius: 5px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; margin-top: 1px;
}
.ol-neutral { background: #EDECE8; color: var(--muted); }
.ol-correct { background: #639922; color: #fff; }
.ol-wrong   { background: #E24B4A; color: #fff; }
.ol-missed  { background: rgba(99,153,34,0.18); color: var(--strong-txt); }

.opt-body { flex: 1; min-width: 0; }
.opt-text { font-size: 12px; font-weight: 500; line-height: 1.45; }
.opt-icon { font-size: 13px; flex-shrink: 0; margin-top: 2px; }

/* ── Explanation toggle ──────────────────────────────────────────────────── */
.expl-toggle {
    background: none; border: none; cursor: pointer;
    font-family: var(--font); font-size: 10px; color: var(--blue);
    padding: 0; margin-top: 5px;
    display: inline-flex; align-items: center; gap: 4px;
    transition: color .15s;
}
.expl-toggle:hover { color: var(--blue-txt); }
.expl-toggle svg { width: 11px; height: 11px; }
.expl-body {
    display: none; margin-top: 6px; font-size: 11px; color: var(--muted);
    line-height: 1.55; padding-top: 6px; border-top: .5px solid var(--border);
}
.expl-body.open { display: block; }

/* ── Badges ──────────────────────────────────────────────────────────────── */
.badge {
    display: inline-block; font-size: 10px; font-weight: 500;
    font-family: var(--mono); padding: 2px 7px; border-radius: 20px; white-space: nowrap;
}
.bw { background: var(--weak-bg);   color: var(--weak-txt); }
.ba { background: var(--avg-bg);    color: var(--avg-txt); }
.bs { background: var(--strong-bg); color: var(--strong-txt); }
.bb { background: var(--blue-bg);   color: var(--blue-txt); }
.bp { background: var(--purple-bg); color: var(--purple-txt); }
.bc { background: var(--cyan-bg);   color: var(--cyan-txt); }
.bm { background: #F0EEE8;          color: var(--muted); }

/* ── Animations ──────────────────────────────────────────────────────────── */
.fi { animation: fadeUp .3s ease both; }
@keyframes fadeUp { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:none; } }

/* ── Scroll-to-top ───────────────────────────────────────────────────────── */
.scroll-top {
    position: fixed; bottom: 24px; right: 24px;
    width: 36px; height: 36px;
    background: var(--surface); border: .5px solid var(--border-md);
    border-radius: 50%; box-shadow: var(--shm);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 15px; color: var(--muted);
    transition: all .15s; opacity: 0; pointer-events: none; z-index: 200;
}
.scroll-top.visible { opacity: 1; pointer-events: auto; }
.scroll-top:hover { transform: translateY(-2px); color: var(--text); box-shadow: 0 6px 20px rgba(0,0,0,0.12); }

/* ── Empty state ─────────────────────────────────────────────────────────── */
.empty { font-size: 11px; color: var(--hint); padding: 8px 0; }

/* ── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 960px) {
    .qa-body { grid-template-columns: 1fr; padding: 14px 14px 60px; }
    .col-sidebar { position: static; }
}
@media (max-width: 600px) {
    .qa-body { padding: 10px 10px 50px; }
    .qa-nav  { padding: 0 12px; }
    .page-title { font-size: 15px; }
    .pill { display: none; }
}
</style>

<div class="qa">

<!-- ════ NAV ════ -->
<div class="qa-nav">
    <div class="qa-nav-left">
        <<a class="back-btn" href="javascript:void(0)" onclick="
            if (sessionStorage.getItem('from_quiz_attempt') === '1') {
                sessionStorage.removeItem('from_quiz_attempt');
                window.location.href = '<?php echo new moodle_url('/course/view.php', ['id' => $courseid]); ?>';
            } else {
                history.back();
            }
        ">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M10 3L5 8l5 5"/>
            </svg>
            Back
        </a>
        <h1 class="page-title">Quiz Analysis</h1>
        <span class="pill"><?php echo htmlspecialchars($studentname); ?></span>
        <span class="grade-pill"
              style="background:<?php echo $gc['bg']; ?>;color:<?php echo $gc['c']; ?>;border:0.5px solid <?php echo $gc['c']; ?>44;">
            Grade <?php echo $grade; ?>
        </span>
    </div>
    <div class="qa-nav-right">
        <span class="nav-ts"><?php echo $datetime; ?></span>
    </div>
</div>

<!-- Reading-progress stripe -->
<div class="qa-progress">
    <div class="qa-progress-fill" id="readingBar"></div>
</div>

<!-- ════ 2-COL LAYOUT ════ -->
<div class="qa-body">

    <!-- ══ SIDEBAR ══ -->
    <div class="col-sidebar">

        <!-- ── Score ring ── -->
        <div class="score-ring-card fi" style="animation-delay:0ms">
            <div class="ring-wrap">
                <svg width="100" height="100" viewBox="0 0 100 100">
                    <circle class="ring-bg"   cx="50" cy="50" r="40"/>
                    <circle class="ring-fill" cx="50" cy="50" r="40"
                        stroke="<?php echo $ringColor; ?>"
                        stroke-dasharray="<?php echo round(2*M_PI*40, 1); ?>"
                        stroke-dashoffset="<?php echo round(2*M_PI*40, 1); ?>"
                        id="scoreRingFill"/>
                </svg>
                <div class="ring-label">
                    <span class="ring-pct" style="color:<?php echo $ringColor; ?>"><?php echo $accuracy; ?>%</span>
                    <span class="ring-sub">accuracy</span>
                </div>
            </div>

            <div class="score-meta">
                <div class="score-frac" style="color:<?php echo $ringColor; ?>"><?php echo $score; ?> / <?php echo $total; ?></div>
                <div class="score-label">marks scored</div>
            </div>

            <div class="score-divider"></div>

            <div style="width:100%">
                <?php
                $metas = [
                    ['🗓', 'Attempted on',   $datetime],
                    ['📋', 'Questions',      $correctQ . ' / ' . $totalQ . ' correct'],
                    ['🎯', 'Difficulty',     ucfirst($quiz->difficulty ?? '—')],
                ];
                foreach ($metas as $m) {
                    echo "<div class='meta-row'>
                            <span class='meta-icon'>{$m[0]}</span>
                            <span class='meta-label'>{$m[1]}</span>
                            <span class='meta-val'>".htmlspecialchars($m[2])."</span>
                          </div>";
                }
                ?>
            </div>
        </div>

        <!-- ── Mini metrics ── -->
        <div class="mini-metrics fi" style="animation-delay:40ms">
            <?php
            $correctPct = $totalQ > 0 ? round($correctQ / $totalQ * 100) : 0;
            $wrongQ     = $totalQ - $correctQ;
            $hitColor   = $correctPct >= 70 ? '#639922' : ($correctPct >= 40 ? '#EF9F27' : '#E24B4A');
            $mmItems = [
                ['icon'=>'✅','label'=>'Correct',  'val'=>$correctQ,      'sub'=>'questions',       'accent'=>'#639922'],
                ['icon'=>'❌','label'=>'Wrong',    'val'=>$wrongQ,        'sub'=>'questions',        'accent'=>'#E24B4A'],
                ['icon'=>'📝','label'=>'Total',    'val'=>$totalQ,        'sub'=>'questions',        'accent'=>'#378ADD'],
                ['icon'=>'🎯','label'=>'Hit Rate', 'val'=>$correctPct.'%','sub'=>'questions correct','accent'=>$hitColor],
            ];
            foreach ($mmItems as $mm) {
                echo "<div class='mini-metric' style='--accent:{$mm['accent']}'>
                        <span class='mm-icon'>{$mm['icon']}</span>
                        <div class='mm-label'>{$mm['label']}</div>
                        <div class='mm-val' style='color:{$mm['accent']}'>{$mm['val']}</div>
                        <div class='mm-sub'>{$mm['sub']}</div>
                      </div>";
            }
            ?>
        </div>

        <!-- ── Topics covered ── -->
        <?php if (!empty($topicMap)): ?>
        <div class="card fi" style="animation-delay:70ms">
            <div class="card-title">🗺 Topics Covered</div>
            <?php foreach ($topicMap as $unit => $sections): ?>
            <div class="unit-block">
                <div class="unit-name">
                    <span class="unit-tag"><?php echo htmlspecialchars($unit); ?></span>
                </div>
                <?php if (!empty($sections)): ?>
                <div class="sec-chip-grid">
                    <?php foreach ($sections as $sec):
                        $title = $sec;
                        if (isset($topicData[$unit][$sec]['title'])) {
                            $title = $topicData[$unit][$sec]['title'];
                        }
                    ?>
                    <div class="sec-chip">
                        <span class="sec-num"><?php echo htmlspecialchars($sec); ?></span>
                        <span class="sec-title"><?php echo htmlspecialchars($title); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- ── Recommendation ── -->
        <?php if (!empty($quiz->recommendation)): ?>
        <div class="card fi" style="animation-delay:90ms">
            <div class="card-title">✨ Recommendation</div>
            <div class="reco">
                <div class="reco-stripe" style="background:#378ADD"></div>
                <div class="reco-type" style="color:#378ADD">AI Insight</div>
                <div class="reco-title"><?php echo format_text($quiz->recommendation); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Question navigator ── -->
        <div class="card fi" style="animation-delay:110ms">
            <div class="card-title">🧭 Question Navigator</div>
            <div class="q-nav">
                <?php
                $idx = 0;
                foreach ($questions as $q) {
                    $idx++;
                    $res = $qResults[$q->id] ?? 'skip';
                    $cls = $res === 'correct' ? 'qnd-correct' : ($res === 'wrong' ? 'qnd-wrong' : 'qnd-skip');
                    echo "<a class='q-nav-dot $cls' href='#q{$idx}' title='Q{$idx}'>$idx</a>";
                }
                ?>
            </div>
            <div style="margin-top:10px;display:flex;gap:7px;flex-wrap:wrap">
                <span class="badge bs">✓ Correct</span>
                <span class="badge bw">✗ Wrong</span>
                <span class="badge bm">— Skipped</span>
            </div>
        </div>

    </div><!-- /col-sidebar -->

    <!-- ══ MAIN ══ -->
    <div class="col-main">

        <div class="sh">
            Questions
            <span class="sh-count"><?php echo $totalQ; ?> total · <?php echo $correctQ; ?> correct · <?php echo ($totalQ - $correctQ); ?> wrong</span>
        </div>

        <?php
        $qIdx = 0;
        foreach ($questions as $q) {
            $qIdx++;
            $unit  = $q->unit;
            $topic = $q->topic;

            $topicTitle = $topic;
            if (isset($topicData[$unit][$topic]['title'])) {
                $topicTitle = $topicData[$unit][$topic]['title'];
            }

            $opts    = $DB->get_records('local_automation_question_options', ['questionattemptid' => $q->id]);
            $res     = $qResults[$q->id] ?? 'skip';
            $isAns   = $res !== 'skip';
            $isCorr  = $res === 'correct';

            $resultLabel = !$isAns ? 'Skipped' : ($isCorr ? 'Correct' : 'Incorrect');
            $resultIcon  = !$isAns ? '—'       : ($isCorr ? '✓'       : '✗');
            $resultClass = !$isAns ? 'qrb-skip' : ($isCorr ? 'qrb-correct' : 'qrb-wrong');
            $numClass    = !$isAns ? 'qn-skip'  : ($isCorr ? 'qn-correct'  : 'qn-wrong');
            $delay       = min(130 + $qIdx * 35, 500);
        ?>
        <div class="q-card fi" id="q<?php echo $qIdx; ?>" style="animation-delay:<?php echo $delay; ?>ms">

            <!-- Head -->
            <div class="q-card-head">
                <span class="q-number <?php echo $numClass; ?>">Q<?php echo $qIdx; ?></span>
                <div class="q-text-wrap">
                    <div class="q-text"><?php echo format_text($q->questiontext); ?></div>
                    <div class="q-topic-line">
                        <span class="badge bb"><?php echo htmlspecialchars($unit); ?></span>
                        <span class="badge bp"><?php echo htmlspecialchars($topic); ?>: <?php echo htmlspecialchars($topicTitle); ?></span>
                    </div>
                </div>
                <span class="q-result-badge <?php echo $resultClass; ?>"><?php echo $resultIcon; ?> <?php echo $resultLabel; ?></span>
            </div>

            <!-- Options -->
            <div class="q-options">
                <?php foreach ($opts as $opt):
                    $isOptCorrect = (bool)$opt->iscorrect;
                    $isSelected   = ($q->selectedoption == $opt->optionnumber);
                    $letter       = chr(65 + $opt->optionnumber);

                    if ($isOptCorrect && $isSelected) {
                        $itemCls  = 'opt-correct'; $lCls = 'ol-correct'; $icon = '✅';
                    } elseif (!$isOptCorrect && $isSelected) {
                        $itemCls  = 'opt-wrong';   $lCls = 'ol-wrong';   $icon = '❌';
                    } elseif ($isOptCorrect && !$isSelected) {
                        $itemCls  = 'opt-missed';  $lCls = 'ol-missed';  $icon = '💡';
                    } else {
                        $itemCls  = 'opt-neutral'; $lCls = 'ol-neutral'; $icon = '';
                    }

                    $eid = "expl_{$qIdx}_{$opt->optionnumber}";
                ?>
                <div class="opt-item <?php echo $itemCls; ?>">
                    <span class="opt-letter <?php echo $lCls; ?>"><?php echo $letter; ?></span>
                    <div class="opt-body">
                        <div class="opt-text"><?php echo format_text($opt->optiontext); ?></div>
                        <?php if (!empty($opt->explanation)): ?>
                        <button class="expl-toggle" onclick="toggleExpl('<?php echo $eid; ?>')">
                            <svg viewBox="0 0 16 16" fill="currentColor">
                                <path d="M8 1a7 7 0 100 14A7 7 0 008 1zm0 2a1 1 0 110 2 1 1 0 010-2zm-1 4h2v5H7V7z"/>
                            </svg>
                            Why this answer?
                        </button>
                        <div class="expl-body" id="<?php echo $eid; ?>">
                            <?php echo format_text($opt->explanation); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($icon) echo "<span class='opt-icon'>$icon</span>"; ?>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
        <?php } ?>

    </div><!-- /col-main -->
</div><!-- /.qa-body -->
</div><!-- /.qa -->

<!-- Scroll-to-top -->
<button class="scroll-top" id="scrollTop"
        onclick="window.scrollTo({top:0,behavior:'smooth'})" title="Back to top">↑</button>

<script>
// ── Score ring animation ──────────────────────────────────────────────────
(function(){
    const fill = document.getElementById('scoreRingFill');
    if (!fill) return;
    const C   = parseFloat(fill.getAttribute('stroke-dasharray'));
    const pct = <?php echo $accuracy; ?>;
    requestAnimationFrame(()=>requestAnimationFrame(()=>{
        fill.style.strokeDashoffset = C - C * pct / 100;
    }));
})();

// ── Reading-progress bar ──────────────────────────────────────────────────
const bar = document.getElementById('readingBar');
const stt = document.getElementById('scrollTop');
function updateScroll(){
    const scrolled = window.scrollY, total = document.body.scrollHeight - window.innerHeight;
    if (bar && total > 0) bar.style.width = Math.min(100, scrolled / total * 100) + '%';
    if (stt) stt.classList.toggle('visible', window.scrollY > 300);
}
window.addEventListener('scroll', updateScroll, {passive: true});

// ── Explanation toggle ────────────────────────────────────────────────────
function toggleExpl(id) {
    const el  = document.getElementById(id);
    if (!el) return;
    el.classList.toggle('open');
    const btn = el.previousElementSibling;
    if (btn) btn.innerHTML = el.classList.contains('open')
        ? '<svg viewBox="0 0 16 16" fill="currentColor" style="width:11px;height:11px"><path d="M8 1a7 7 0 100 14A7 7 0 008 1zm0 2a1 1 0 110 2 1 1 0 010-2zm-1 4h2v5H7V7z"/></svg> Hide explanation'
        : '<svg viewBox="0 0 16 16" fill="currentColor" style="width:11px;height:11px"><path d="M8 1a7 7 0 100 14A7 7 0 008 1zm0 2a1 1 0 110 2 1 1 0 010-2zm-1 4h2v5H7V7z"/></svg> Why this answer?';
}

// ── Smooth scroll for navigator dots ─────────────────────────────────────
document.querySelectorAll('.q-nav-dot').forEach(dot => {
    dot.addEventListener('click', e => {
        e.preventDefault();
        const target = document.querySelector(dot.getAttribute('href'));
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
});
</script>

<?php echo $OUTPUT->footer(); ?>