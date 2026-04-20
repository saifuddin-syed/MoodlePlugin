<?php

require_once(__DIR__.'/../../config.php');
require_login();

$courseid = get_config('local_automation', 'student_ai_courseid');

if (!$courseid) {
    print_error('Student AI course not configured.');
}

$context = context_course::instance($courseid);
require_capability('moodle/course:view', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/automation/analytics_overview.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Course Overview Analytics');
$PAGE->set_heading('Course Overview Analytics');

echo '<style>
.secondary-navigation { display: none !important; }
#page-header { margin-bottom: 0 !important; }
</style>';

echo $OUTPUT->header();
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════════════════════
   RESET & DESIGN TOKENS  (matches analytics_student.php exactly)
══════════════════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:#F5F4F0; --surface:#FFFFFF; --surface-alt:#F9F8F5;
    --border:rgba(0,0,0,0.07); --border-md:rgba(0,0,0,0.11);
    --text:#1A1A1A; --muted:#6B6B6B; --hint:#A8A8A8;
    --weak:#E24B4A;   --weak-bg:#FCEBEB;   --weak-txt:#A32D2D;
    --avg:#EF9F27;    --avg-bg:#FEF3E0;    --avg-txt:#854F0B;
    --strong:#639922; --strong-bg:#EAF3DE; --strong-txt:#3B6D11;
    --blue:#378ADD;   --blue-bg:#E8F2FC;   --blue-txt:#185FA5;
    --purple:#7F77DD; --purple-bg:#EEEDFE; --purple-txt:#3C3489;
    --cyan:#1B9E9E;   --cyan-bg:#E0F5F5;   --cyan-txt:#0D5C5C;
    --r:12px; --rsm:8px;
    --font:'DM Sans',system-ui,sans-serif;
    --mono:'DM Mono',monospace;
    --sh:0 1px 3px rgba(0,0,0,0.06),0 1px 2px rgba(0,0,0,0.04);
    --shm:0 4px 14px rgba(0,0,0,0.08),0 2px 4px rgba(0,0,0,0.05);
}

/* ── Moodle reset ── */
html,body{background:var(--bg)!important;}
body{padding:0!important;margin:0!important;}
#page,#page-wrapper,#page-content,#page-content .row,
#region-main-box,#region-main,.region-content,[role="main"],
.main-inner,#maincontent,.course-content,
div[data-region="blocks-column"],.drawers,.drawers .main-inner
{max-width:none!important;width:100%!important;padding:0!important;margin:0!important;float:none!important;flex:unset!important;}
#nav-drawer,.drawer,[data-region="fixed-drawer"],[data-region="right-hand-drawer"],
#block-region-side-pre,#block-region-side-post,.block-region,aside.block-region{display:none!important;}

/* ══════════════════════════════════════════════════════════════
   SHELL
══════════════════════════════════════════════════════════════ */
.ov{width:100vw;min-height:100vh;background:var(--bg);font-family:var(--font);color:var(--text);font-size:14px;line-height:1.5;}

/* ══════════════════════════════════════════════════════════════
   NAV  (same style as student page)
══════════════════════════════════════════════════════════════ */
.ov-nav{
    position:sticky;top:0;z-index:100;
    background:rgba(245,244,240,0.94);
    backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);
    border-bottom:0.5px solid var(--border-md);
    display:flex;align-items:center;justify-content:space-between;
    padding:0 28px;height:52px;gap:12px;
}
.ov-nav-left{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.ov-nav-right{display:flex;align-items:center;gap:10px;}
.nav-title{font-size:17px;font-weight:600;letter-spacing:-.01em;}
.nav-sub{font-size:11px;color:var(--muted);}
.nav-ts{font-family:var(--mono);font-size:10px;color:var(--hint);}
.nav-icon{width:32px;height:32px;border-radius:50%;background:var(--purple-bg);color:var(--purple-txt);font-size:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}

/* ══════════════════════════════════════════════════════════════
   DARK HERO STRIP
══════════════════════════════════════════════════════════════ */
.ov-hero{
    background:var(--text);
    display:grid;
    grid-template-columns:repeat(7,1fr);
    border-bottom:1px solid rgba(255,255,255,0.06);
}
.hero-cell{
    padding:18px 22px;
    border-right:0.5px solid rgba(255,255,255,0.07);
    display:flex;flex-direction:column;gap:3px;
    animation:fadeUp .4s ease both;
}
.hero-cell:last-child{border-right:none;}
.hero-label{font-size:9px;font-weight:500;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.4);font-family:var(--mono);}
.hero-val{font-size:26px;font-weight:600;color:#fff;line-height:1;letter-spacing:-.02em;font-family:var(--mono);}
.hero-sub{font-size:10px;color:rgba(255,255,255,.32);}
.hero-chip{display:inline-flex;align-items:center;gap:3px;font-size:10px;font-family:var(--mono);margin-top:2px;width:fit-content;padding:1px 7px;border-radius:10px;}
.hc-green{background:rgba(99,153,34,.25);color:#8CCC44;}
.hc-red{background:rgba(226,75,74,.25);color:#F07070;}
.hc-grey{background:rgba(255,255,255,.08);color:rgba(255,255,255,.4);}

/* ══════════════════════════════════════════════════════════════
   BODY LAYOUT  — 3-col + sticky right
══════════════════════════════════════════════════════════════ */
.ov-body{
    display:grid;
    grid-template-columns:1fr 1fr 1fr 272px;
    grid-template-rows:auto;
    gap:12px;
    padding:16px 22px 60px;
    align-items:start;
}

/* column helpers */
.col-span2{grid-column:span 2;}
.col-span3{grid-column:span 3;}
.col-span4{grid-column:1/-1;}
.col-right{grid-column:4;}

/* ══════════════════════════════════════════════════════════════
   SECTION HEADINGS (inline divider)
══════════════════════════════════════════════════════════════ */
.sec-head{
    grid-column:1/-1;
    display:flex;align-items:center;gap:10px;
    font-size:10px;font-weight:500;text-transform:uppercase;
    letter-spacing:.1em;color:var(--muted);
    margin-top:6px;
}
.sec-head::after{content:'';flex:1;height:.5px;background:var(--border-md);}

/* ══════════════════════════════════════════════════════════════
   CARDS  (identical to student page)
══════════════════════════════════════════════════════════════ */
.card{background:var(--surface);border:0.5px solid var(--border);border-radius:var(--r);padding:16px 18px;box-shadow:var(--sh);animation:fadeUp .35s ease both;}
.card-title{font-size:10px;font-weight:500;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:13px;padding-bottom:9px;border-bottom:0.5px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:8px;}
.card-title-l{display:flex;align-items:center;gap:5px;}

/* ══════════════════════════════════════════════════════════════
   METRIC CARDS  (same as student page)
══════════════════════════════════════════════════════════════ */
.metrics-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.metric{background:var(--surface);border:0.5px solid var(--border);border-radius:var(--r);padding:12px 14px;position:relative;overflow:hidden;box-shadow:var(--sh);transition:box-shadow .15s,transform .15s;animation:fadeUp .35s ease both;}
.metric:hover{box-shadow:var(--shm);transform:translateY(-1px);}
.metric::before{content:'';position:absolute;top:0;left:0;bottom:0;width:3px;background:var(--accent,#D3D1C7);border-radius:var(--r) 0 0 var(--r);}
.metric-icon{font-size:13px;margin-bottom:5px;display:block;}
.metric-label{font-size:9px;font-weight:500;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:2px;}
.metric-val{font-size:20px;font-weight:600;font-family:var(--mono);line-height:1;letter-spacing:-.02em;}
.metric-sub{font-size:10px;color:var(--hint);margin-top:3px;}

/* ══════════════════════════════════════════════════════════════
   BADGES
══════════════════════════════════════════════════════════════ */
.badge{display:inline-block;font-size:10px;font-weight:500;font-family:var(--mono);padding:2px 7px;border-radius:20px;white-space:nowrap;}
.bw{background:var(--weak-bg);color:var(--weak-txt);} .ba{background:var(--avg-bg);color:var(--avg-txt);}
.bs{background:var(--strong-bg);color:var(--strong-txt);} .bb{background:var(--blue-bg);color:var(--blue-txt);}
.bp{background:var(--purple-bg);color:var(--purple-txt);} .bc{background:var(--cyan-bg);color:var(--cyan-txt);}
.bm{background:#F0EEE8;color:var(--muted);}

/* ══════════════════════════════════════════════════════════════
   BAR ROWS  (same as student page)
══════════════════════════════════════════════════════════════ */
.bar-row{margin-bottom:8px;} .bar-row:last-child{margin-bottom:0;}
.bar-meta{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:3px;}
.bar-name{font-size:11px;flex:1;margin-right:8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.bar-pct{font-family:var(--mono);font-size:11px;white-space:nowrap;}
.bar-track{height:4px;background:#EDECE8;border-radius:4px;overflow:hidden;}
.bar-fill{height:4px;border-radius:4px;transition:width .8s cubic-bezier(.16,1,.3,1);}

/* ══════════════════════════════════════════════════════════════
   HEATMAP  (same as student page)
══════════════════════════════════════════════════════════════ */
.heatmap-table{width:100%;border-collapse:separate;border-spacing:3px;font-size:11px;}
.heatmap-table th{font-size:9px;font-weight:500;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);padding:3px 4px;text-align:center;}
.heatmap-table td{padding:7px 5px;text-align:center;border-radius:5px;font-family:var(--mono);font-size:11px;font-weight:500;cursor:default;transition:filter .12s,transform .12s;}
.heatmap-table td:hover:not(.rl){filter:brightness(.92);transform:scale(1.06);}
.heatmap-table .rl{text-align:left;font-family:var(--font);color:var(--text);font-size:11px;padding-right:6px;white-space:nowrap;}

/* ══════════════════════════════════════════════════════════════
   SCORE PILLS
══════════════════════════════════════════════════════════════ */
.score-pill{font-family:var(--mono);font-size:11px;font-weight:500;padding:2px 7px;border-radius:5px;display:inline-block;}
.score-high{background:var(--strong-bg);color:var(--strong-txt);}
.score-mid{background:var(--avg-bg);color:var(--avg-txt);}
.score-low{background:var(--weak-bg);color:var(--weak-txt);}

/* ══════════════════════════════════════════════════════════════
   STUDENT TABLE
══════════════════════════════════════════════════════════════ */
.stu-table{width:100%;border-collapse:collapse;font-size:12px;}
.stu-table th{text-align:left;font-size:9px;font-weight:500;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);padding:6px 8px;border-bottom:.5px solid var(--border-md);position:sticky;top:0;background:var(--surface);z-index:2;}
.stu-table td{padding:8px 8px;border-bottom:.5px solid var(--border);vertical-align:middle;}
.stu-table tr:last-child td{border-bottom:none;}
.stu-table tr:hover td{background:var(--surface-alt);cursor:pointer;}
.stu-scroll{max-height:420px;overflow-y:auto;}
.stu-scroll::-webkit-scrollbar{width:3px;}
.stu-scroll::-webkit-scrollbar-thumb{background:var(--border-md);border-radius:3px;}
.stu-av{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:600;font-family:var(--mono);flex-shrink:0;}

/* ══════════════════════════════════════════════════════════════
   PI-RING  (reused from student page)
══════════════════════════════════════════════════════════════ */
.pi-ring-wrap{position:relative;width:64px;height:64px;flex-shrink:0;}
.pi-ring-wrap svg{transform:rotate(-90deg);}
.pi-ring-bg{fill:none;stroke:#EDECE8;stroke-width:6;}
.pi-ring-fill{fill:none;stroke-width:6;stroke-linecap:round;transition:stroke-dashoffset 1.1s cubic-bezier(.16,1,.3,1);}
.pi-ring-label{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:var(--mono);font-weight:600;line-height:1;}

/* ══════════════════════════════════════════════════════════════
   DISTRIBUTION STRIP
══════════════════════════════════════════════════════════════ */
.dist-strip{height:8px;border-radius:6px;overflow:hidden;display:flex;gap:0;margin:10px 0 8px;}
.dist-seg{height:100%;transition:width .9s cubic-bezier(.16,1,.3,1);}
.dist-legend{display:flex;gap:10px;flex-wrap:wrap;}
.dist-item{display:flex;align-items:center;gap:4px;font-size:10px;color:var(--muted);}
.dist-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;}

/* ══════════════════════════════════════════════════════════════
   ATTEMPT LOG GRID  (same pattern as student page)
══════════════════════════════════════════════════════════════ */
.ag{display:grid;align-items:center;padding:7px 8px;}
.ag-5{grid-template-columns:minmax(0,2.2fr) minmax(0,1fr) 72px 72px 64px;}
.ag-4{grid-template-columns:minmax(0,2.2fr) minmax(0,1fr) 80px 64px;}
.ag-head{font-size:9px;font-weight:500;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);border-bottom:.5px solid var(--border-md);padding-bottom:7px;}
.ag-row{border-bottom:.5px solid var(--border);transition:background .1s;border-radius:4px;cursor:pointer;}
.ag-row:last-child{border-bottom:none;} .ag-row:hover{background:var(--surface-alt);}

/* ══════════════════════════════════════════════════════════════
   SPARKLINE
══════════════════════════════════════════════════════════════ */
.spark{display:inline-flex;align-items:flex-end;gap:1.5px;height:16px;vertical-align:middle;}
.spark-b{width:3px;border-radius:1px;transition:height .4s;}

/* ══════════════════════════════════════════════════════════════
   SEARCH / FILTER
══════════════════════════════════════════════════════════════ */
.filters{display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap;align-items:center;}
.filters select,.search-inp{font-family:var(--font);font-size:11px;padding:5px 9px;border:.5px solid var(--border-md);border-radius:var(--rsm);background:var(--surface);color:var(--text);cursor:pointer;outline:none;box-shadow:var(--sh);}
.filters select:focus,.search-inp:focus{border-color:var(--blue);}
.search-inp{padding-left:26px;}
.search-wrap{position:relative;}
.search-wrap::before{content:'🔍';position:absolute;left:8px;top:50%;transform:translateY(-50%);font-size:10px;pointer-events:none;}

/* ══════════════════════════════════════════════════════════════
   INSIGHTS ROWS  (same as student page)
══════════════════════════════════════════════════════════════ */
.ins-row{display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:.5px solid var(--border);}
.ins-row:last-child{border-bottom:none;}
.ins-val{font-size:17px;font-weight:600;font-family:var(--mono);min-width:42px;letter-spacing:-.02em;}
.ins-label{font-size:11px;font-weight:500;} .ins-sub{font-size:10px;color:var(--hint);}

/* ══════════════════════════════════════════════════════════════
   RANKING LIST
══════════════════════════════════════════════════════════════ */
.rank-row{display:flex;align-items:center;gap:9px;padding:7px 0;border-bottom:.5px solid var(--border);cursor:pointer;transition:background .1s;border-radius:4px;}
.rank-row:hover{background:var(--surface-alt);padding-left:4px;}
.rank-row:last-child{border-bottom:none;}
.rank-num{font-family:var(--mono);font-size:11px;color:var(--hint);width:18px;text-align:right;flex-shrink:0;}
.rank-av{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:600;font-family:var(--mono);flex-shrink:0;}
.rank-info{flex:1;min-width:0;}
.rank-name{font-size:11px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.rank-detail{font-size:10px;color:var(--muted);}
.rank-score{font-family:var(--mono);font-size:12px;font-weight:500;white-space:nowrap;}

/* ══════════════════════════════════════════════════════════════
   CHART CONTAINERS
══════════════════════════════════════════════════════════════ */
.ch160{position:relative;height:160px;width:100%;}
.ch180{position:relative;height:180px;width:100%;}
.ch200{position:relative;height:200px;width:100%;}
.ch240{position:relative;height:240px;width:100%;}
.ch280{position:relative;height:280px;width:100%;}
.ch320{position:relative;height:320px;width:100%;}

/* ══════════════════════════════════════════════════════════════
   SCATTER GRID (custom scatter using CSS grid)
══════════════════════════════════════════════════════════════ */
.bubble-grid{position:relative;}
.bubble-dot{position:absolute;border-radius:50%;transform:translate(-50%,-50%);transition:opacity .2s;cursor:pointer;}
.bubble-dot:hover{opacity:.8;z-index:10;}

/* ══════════════════════════════════════════════════════════════
   PROGRESS RING (small inline)
══════════════════════════════════════════════════════════════ */
.ring-sm{display:inline-flex;align-items:center;justify-content:center;position:relative;}

/* ══════════════════════════════════════════════════════════════
   MISC
══════════════════════════════════════════════════════════════ */
.loading{display:flex;align-items:center;gap:7px;font-size:11px;color:var(--muted);padding:14px 0;}
.spinner{width:12px;height:12px;border:1.5px solid #D8D6D0;border-top-color:var(--blue);border-radius:50%;animation:spin .6s linear infinite;}
@keyframes spin{to{transform:rotate(360deg);}}
@keyframes fadeUp{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
.fi{animation:fadeUp .3s ease both;}
.sh-row{font-size:10px;font-weight:500;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);margin-bottom:8px;display:flex;align-items:center;gap:8px;}
.sh-row::after{content:'';flex:1;height:.5px;background:var(--border-md);}

/* ══════════════════════════════════════════════════════════════
   RESPONSIVE
══════════════════════════════════════════════════════════════ */
@media(max-width:1280px){
    .ov-body{grid-template-columns:1fr 1fr 1fr 248px;padding:12px 14px 50px;}
    .ov-hero{grid-template-columns:repeat(4,1fr);}
    .hero-cell:nth-child(n+5){display:none;}
}
@media(max-width:1024px){
    .ov-body{grid-template-columns:1fr 1fr;}
    .col-right{grid-column:1/-1;display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    .ov-hero{grid-template-columns:repeat(3,1fr);}
    .hero-cell:nth-child(n+4){display:none;}
    .col-span3{grid-column:span 2;}
}
@media(max-width:640px){
    .ov-body{grid-template-columns:1fr;padding:10px 10px 50px;}
    .col-right{grid-template-columns:1fr;}
    .ov-hero{grid-template-columns:1fr 1fr;}
    .hero-cell:nth-child(n+3){display:none;}
    .ov-nav{padding:0 14px;}
    .col-span2,.col-span3,.col-span4{grid-column:1;}
}
</style>

<div class="ov">

<!-- ═══════════ NAV ═══════════ -->
<nav class="ov-nav">
    <div class="ov-nav-left">
        <div class="nav-icon">📊</div>
        <div>
            <div class="nav-title">Class Analytics</div>
            <div class="nav-sub">Teacher overview · Course <?php echo (int)$courseid; ?></div>
        </div>
        <span class="badge bb" id="navStudentCount"><span class="spinner" style="width:9px;height:9px;display:inline-block"></span></span>
    </div>
    <div class="ov-nav-right">
        <span class="badge bp" id="navAttempts">—</span>
        <span class="nav-ts" id="navTs">—</span>
    </div>
</nav>

<!-- ═══════════ DARK HERO ═══════════ -->
<div class="ov-hero" id="heroStrip">
    <div class="hero-cell" style="animation-delay:0ms"><div class="hero-label">Students</div><div class="hero-val" id="h-students">—</div><div class="hero-sub">enrolled</div></div>
    <div class="hero-cell" style="animation-delay:40ms"><div class="hero-label">Class Avg</div><div class="hero-val" id="h-avg">—</div><div class="hero-sub" id="h-avg-sub">overall accuracy</div></div>
    <div class="hero-cell" style="animation-delay:80ms"><div class="hero-label">Total Attempts</div><div class="hero-val" id="h-attempts">—</div><div class="hero-sub">quiz submissions</div></div>
    <div class="hero-cell" style="animation-delay:120ms"><div class="hero-label">Strong ≥70%</div><div class="hero-val" id="h-strong">—</div><div class="hero-sub" id="h-strong-sub">students</div></div>
    <div class="hero-cell" style="animation-delay:160ms"><div class="hero-label">At Risk &lt;40%</div><div class="hero-val" id="h-atrisk">—</div><div class="hero-sub">need support</div></div>
    <div class="hero-cell" style="animation-delay:200ms"><div class="hero-label">Hard Accuracy</div><div class="hero-val" id="h-hard">—</div><div class="hero-sub">hard-level only</div></div>
    <div class="hero-cell" style="animation-delay:240ms"><div class="hero-label">Best Unit</div><div class="hero-val" id="h-bestunit">—</div><div class="hero-sub" id="h-bestunit-sub">class avg</div></div>
</div>

<!-- ═══════════ BODY ═══════════ -->
<div class="ov-body">

    <!-- ████  SECTION A: OVERVIEW  ████ -->
    <div class="sec-head">📊 Class Performance Overview</div>

    <!-- Stacked Bar — spans 3 cols -->
    <div class="card col-span3" style="animation-delay:60ms">
        <div class="card-title">
            <div class="card-title-l">📊 Student performance — unit × difficulty (stacked)</div>
            <div style="display:flex;gap:6px">
                <select class="filters" style="margin:0" id="chartUnitF" onchange="rebuildOverallChart()">
                    <option value="ALL">All Units</option>
                    <option value="U1">U1</option><option value="U2">U2</option><option value="U3">U3</option>
                    <option value="U4">U4</option><option value="U5">U5</option><option value="U6">U6</option>
                </select>
                <select class="filters" style="margin:0" id="chartDiffF" onchange="rebuildOverallChart()">
                    <option value="ALL">All Difficulties</option>
                    <option value="easy">Easy</option><option value="medium">Medium</option><option value="hard">Hard</option>
                </select>
            </div>
        </div>
        <div class="ch280"><canvas id="overallChart"></canvas></div>
    </div>

    <!-- Grade donut — right col, row-span 2 -->
    <div class="card col-right" style="animation-delay:70ms;grid-row:span 2">
        <div class="card-title"><div class="card-title-l">🍩 Grade distribution</div></div>
        <div class="ch180"><canvas id="gradeDonut"></canvas></div>
        <div class="dist-strip" id="distStrip"></div>
        <div class="dist-legend" id="distLegend"></div>
        <div style="margin-top:14px">
            <div class="sh-row">Class insight</div>
            <div id="distInsights"></div>
        </div>
    </div>

    <!-- Metric cards row — spans 3 -->
    <div class="metrics-grid col-span3" id="classMetrics" style="animation-delay:80ms">
        <div class="metric"><div class="loading"><div class="spinner"></div></div></div>
        <div class="metric"><div class="loading"><div class="spinner"></div></div></div>
        <div class="metric"><div class="loading"><div class="spinner"></div></div></div>
        <div class="metric"><div class="loading"><div class="spinner"></div></div></div>
        <div class="metric"><div class="loading"><div class="spinner"></div></div></div>
        <div class="metric"><div class="loading"><div class="spinner"></div></div></div>
    </div>

    <!-- ████  SECTION B: ANALYTICS CHARTS  ████ -->
    <div class="sec-head">📈 Analytics & Trends</div>

    <!-- Class accuracy trend -->
    <div class="card" style="animation-delay:100ms">
        <div class="card-title"><div class="card-title-l">📈 Class accuracy trend</div><span class="badge bb" id="trendDayCount">—</span></div>
        <div class="ch180"><canvas id="classTrend"></canvas></div>
    </div>

    <!-- Unit mastery radar -->
    <div class="card" style="animation-delay:110ms">
        <div class="card-title"><div class="card-title-l">🕸 Unit mastery radar</div></div>
        <div class="ch180"><canvas id="classRadar"></canvas></div>
    </div>

    <!-- Difficulty breakdown grouped bar -->
    <div class="card" style="animation-delay:120ms">
        <div class="card-title"><div class="card-title-l">💪 Difficulty accuracy</div></div>
        <div class="ch180"><canvas id="diffBar"></canvas></div>
    </div>

    <!-- Class heatmap — right -->
    <div class="card col-right" style="animation-delay:130ms">
        <div class="card-title"><div class="card-title-l">🌡 Class heatmap</div></div>
        <div id="classHeatmap"><div class="loading"><div class="spinner"></div></div></div>
    </div>

    <!-- Attempts per unit stacked -->
    <div class="card" style="animation-delay:140ms">
        <div class="card-title"><div class="card-title-l">🔢 Attempts by unit</div></div>
        <div class="ch180"><canvas id="attemptsUnit"></canvas></div>
    </div>

    <!-- Score distribution histogram -->
    <div class="card" style="animation-delay:150ms">
        <div class="card-title"><div class="card-title-l">📊 Score distribution histogram</div></div>
        <div class="ch180"><canvas id="scoreHistogram"></canvas></div>
    </div>

    <!-- Hard vs Easy scatter -->
    <div class="card" style="animation-delay:160ms">
        <div class="card-title"><div class="card-title-l">⚖️ Easy vs Hard accuracy</div><span style="font-size:10px;color:var(--hint)">per student</span></div>
        <div class="ch180"><canvas id="easyHardScatter"></canvas></div>
    </div>

    <!-- Unit accuracy bars (right) -->
    <div class="card col-right" style="animation-delay:170ms">
        <div class="card-title"><div class="card-title-l">📚 Unit avg accuracy</div></div>
        <div id="unitAvgBars"><div class="loading"><div class="spinner"></div></div></div>
    </div>

    <!-- Attempts per student bar -->
    <div class="card" style="animation-delay:180ms">
        <div class="card-title"><div class="card-title-l">🗺 Attempts per student</div></div>
        <div class="ch180"><canvas id="attemptsPerStudent"></canvas></div>
    </div>

    <!-- Score spread horizontal -->
    <div class="card" style="animation-delay:190ms">
        <div class="card-title"><div class="card-title-l">📉 Score spread (sorted)</div></div>
        <div class="ch180"><canvas id="scoreSpread"></canvas></div>
    </div>

    <!-- Engagement heatmap (by day of week) -->
    <div class="card" style="animation-delay:200ms">
        <div class="card-title"><div class="card-title-l">📅 Attempt activity heatmap</div><span style="font-size:10px;color:var(--hint)">by weekday</span></div>
        <div id="activityHeatmap"><div class="loading"><div class="spinner"></div></div></div>
    </div>

    <!-- Unit coverage donut right -->
    <div class="card col-right" style="animation-delay:210ms">
        <div class="card-title"><div class="card-title-l">🗂 Unit attempt split</div></div>
        <div class="ch160"><canvas id="unitSplitDonut"></canvas></div>
    </div>

    <!-- ████  SECTION C: STUDENT INSIGHTS  ████ -->
    <div class="sec-head">👨‍🎓 Student Insights</div>

    <!-- Top performers -->
    <div class="card" style="animation-delay:220ms">
        <div class="card-title"><div class="card-title-l">🏆 Top performers</div><span class="badge bs" id="topCount">—</span></div>
        <div id="topList"><div class="loading"><div class="spinner"></div></div></div>
    </div>

    <!-- At risk -->
    <div class="card" style="animation-delay:230ms">
        <div class="card-title"><div class="card-title-l">⚠️ Needs attention</div><span class="badge bw" id="riskCount">—</span></div>
        <div id="riskList"><div class="loading"><div class="spinner"></div></div></div>
    </div>

    <!-- Most active -->
    <div class="card" style="animation-delay:240ms">
        <div class="card-title"><div class="card-title-l">🔥 Most active students</div><span style="font-size:10px;color:var(--hint)">by attempts</span></div>
        <div id="activeList"><div class="loading"><div class="spinner"></div></div></div>
    </div>

    <!-- Improving / declining -->
    <div class="card col-right" style="animation-delay:245ms">
        <div class="card-title"><div class="card-title-l">📊 Key class insights</div></div>
        <div id="classInsights"><div class="loading"><div class="spinner"></div></div></div>
    </div>

    <!-- ████  SECTION D: FULL ROSTER  ████ -->
    <div class="sec-head">📋 Full Student Roster</div>

    <div class="card col-span4" style="animation-delay:260ms">
        <div class="card-title">
            <div class="card-title-l">👨‍🎓 All students</div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                <div class="search-wrap">
                    <input type="text" class="search-inp" id="stuSearch" placeholder="Search name…" oninput="filterStudents()" style="width:140px">
                </div>
                <select class="filters" style="margin:0" id="stuStatusF" onchange="filterStudents()">
                    <option value="ALL">All Status</option>
                    <option value="strong">Strong ≥70%</option>
                    <option value="avg">Average 40–70%</option>
                    <option value="weak">Weak &lt;40%</option>
                    <option value="none">No Attempts</option>
                </select>
                <select class="filters" style="margin:0" id="stuSortF" onchange="filterStudents()">
                    <option value="name">Sort: Name</option>
                    <option value="acc_desc">Accuracy ↓</option>
                    <option value="acc_asc">Accuracy ↑</option>
                    <option value="att_desc">Attempts ↓</option>
                    <option value="improve">Improving first</option>
                </select>
                <span class="badge bm" id="stuFilterCount">—</span>
            </div>
        </div>
        <div class="stu-scroll">
            <table class="stu-table">
                <thead>
                    <tr>
                        <th style="width:32px"></th>
                        <th>Student</th>
                        <th>ID</th>
                        <th>Attempts</th>
                        <th>Score</th>
                        <th>Accuracy</th>
                        <th>U1</th><th>U2</th><th>U3</th><th>U4</th><th>U5</th><th>U6</th>
                        <th>Easy</th><th>Medium</th><th>Hard</th>
                        <th>Trend</th>
                        <th>Δ Recent</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="stuTbody">
                    <tr><td colspan="18"><div class="loading"><div class="spinner"></div><span>Loading…</span></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /ov-body -->
</div><!-- /ov -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
/* ══════════════════════════════════════════════════════════════
   CONFIG
══════════════════════════════════════════════════════════════ */
const COURSEID  = <?php echo (int)$courseid; ?>;
const AJAX      = M.cfg.wwwroot + '/local/automation/analytics_ajax.php';
const SECTIONS  = ['U1','U2','U3','U4','U5','U6'];
const UNIT_MAP  = {I:'U1',II:'U2',III:'U3',IV:'U4',V:'U5',VI:'U6'};
const DIFFS     = ['easy','medium','hard'];

Chart.defaults.font.family = "'DM Sans',system-ui,sans-serif";
Chart.defaults.color = '#6B6B6B';

/* ══════════════════════════════════════════════════════════════
   STATE
══════════════════════════════════════════════════════════════ */
let ALL_STUDENTS = [], QUIZ_BY_SID = {}, STATS = {}, CHARTS = {};

/* ══════════════════════════════════════════════════════════════
   HELPERS
══════════════════════════════════════════════════════════════ */
const post = (action, extra={}) => fetch(AJAX,{
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({action, courseid:COURSEID, sesskey:M.cfg.sesskey, ...extra})
}).then(r=>r.json());

const colorOf = p => p===null?'#A8A8A8':p>=70?'#639922':p>=40?'#EF9F27':'#E24B4A';
const bgOf    = p => p===null?'#F0EEE8':p>=70?'#EAF3DE':p>=40?'#FEF3E0':'#FCEBEB';
const txtOf   = p => p===null?'#A8A8A8':p>=70?'#3B6D11':p>=40?'#854F0B':'#A32D2D';
const pillCls = p => p===null?'bm':p>=70?'bs':p>=40?'ba':'bw';
const statusOf= p => p===null?{l:'No Attempts',c:'bm'}:p>=70?{l:'Strong',c:'bs'}:p>=40?{l:'Average',c:'ba'}:{l:'Weak',c:'bw'};
const hBg = p => p===null?'#F0EEE8':p<20?'#F7C1C1':p<40?'#F09595':p<60?'#FAC775':p<80?'#C0DD97':'#85C440';
const hTx = p => p===null?'#A8A8A8':p<40?'#A32D2D':p<60?'#854F0B':'#3B6D11';
const mkI = (f,l) => ((f?.[0]||'')+(l?.[0]||'')).toUpperCase()||'ST';
const avg = arr => arr.length ? Math.round(arr.reduce((a,b)=>a+b,0)/arr.length) : null;

function resolveUnits(q){
    const raw = ((q.topic||'')+' '+(q.unit||'')).toUpperCase();
    const found = new Set();
    let m;
    const re=/UNIT\s+(VI|IV|V|III|II|I)/g;
    while((m=re.exec(raw))!==null){const k=UNIT_MAP[m[1]];if(k)found.add(k);}
    const sr=/\b([4-6])\.\d+/g;
    while((m=sr.exec(raw))!==null){const mp={'4':'U4','5':'U5','6':'U6'};if(mp[m[1]])found.add(mp[m[1]]);}
    return [...found];
}

/* ══════════════════════════════════════════════════════════════
   COMPUTE STATS PER STUDENT
══════════════════════════════════════════════════════════════ */
function computeStats(sid){
    const quiz = QUIZ_BY_SID[sid]||[];
    const units = {};
    SECTIONS.forEach(u=>{units[u]={s:0,t:0};});
    const diffs = {easy:{s:0,t:0},medium:{s:0,t:0},hard:{s:0,t:0}};
    let tS=0,tT=0;
    const trend=[];

    quiz.forEach(q=>{
        const sc=parseInt(q.score)||0,tot=parseInt(q.total)||0;
        const d=(q.difficulty||'').toLowerCase();
        tS+=sc; tT+=tot;
        if(diffs[d]){diffs[d].s+=sc;diffs[d].t+=tot;}
        resolveUnits(q).forEach(u=>{units[u].s+=sc;units[u].t+=tot;});
        if(q.timecreated&&tot>0) trend.push({ts:parseInt(q.timecreated),pct:Math.round(sc/tot*100)});
    });

    trend.sort((a,b)=>a.ts-b.ts);
    const acc = tT>0 ? Math.round(tS/tT*100) : null;
    const unitPcts = SECTIONS.map(u=>units[u].t>0?Math.round(units[u].s/units[u].t*100):null);
    const diffAcc  = {
        easy:   diffs.easy.t>0  ? Math.round(diffs.easy.s/diffs.easy.t*100)    : null,
        medium: diffs.medium.t>0? Math.round(diffs.medium.s/diffs.medium.t*100): null,
        hard:   diffs.hard.t>0  ? Math.round(diffs.hard.s/diffs.hard.t*100)    : null,
    };
    const rec5 = trend.slice(-5).map(d=>d.pct);
    const trendDelta = rec5.length>=2 ? rec5[rec5.length-1]-rec5[0] : 0;

    return {acc, attempts:quiz.length, tS, tT, unitPcts, diffAcc, trend, trendDelta};
}

/* ══════════════════════════════════════════════════════════════
   HERO STRIP
══════════════════════════════════════════════════════════════ */
function renderHero(){
    const sids = ALL_STUDENTS.map(s=>s.id);
    const accs = sids.map(id=>STATS[id]?.acc).filter(a=>a!==null);
    const clsAvg = avg(accs)||0;
    const totalAtt = sids.reduce((a,id)=>a+(STATS[id]?.attempts||0),0);
    const strong = accs.filter(a=>a>=70).length;
    const atRisk = accs.filter(a=>a<40).length;
    const hardAs = sids.map(id=>STATS[id]?.diffAcc.hard).filter(a=>a!==null);
    const hardAvg= avg(hardAs)||0;
    const unitAvgs = SECTIONS.map((_,i)=>avg(sids.map(id=>STATS[id]?.unitPcts[i]).filter(p=>p!==null))||0);
    const bestI  = unitAvgs.indexOf(Math.max(...unitAvgs));

    el('h-students').textContent  = ALL_STUDENTS.length;
    el('h-avg').textContent        = clsAvg+'%';
    el('h-avg-sub').textContent    = 'class average';
    el('h-attempts').textContent   = totalAtt;
    el('h-strong').textContent     = strong;
    el('h-strong-sub').textContent = `${Math.round(strong/ALL_STUDENTS.length*100)||0}% of class`;
    el('h-atrisk').textContent     = atRisk;
    el('h-hard').textContent       = hardAvg+'%';
    el('h-bestunit').textContent   = SECTIONS[bestI]||'—';
    el('h-bestunit-sub').textContent = (unitAvgs[bestI]||0)+'% avg';
    el('navStudentCount').textContent = ALL_STUDENTS.length+' students';
    el('navAttempts').textContent  = totalAtt+' attempts';
    el('navTs').textContent        = 'Updated '+new Date().toLocaleTimeString();
}

/* ══════════════════════════════════════════════════════════════
   CLASS METRIC CARDS
══════════════════════════════════════════════════════════════ */
function renderMetrics(){
    const sids = ALL_STUDENTS.map(s=>s.id);
    const accs = sids.map(id=>STATS[id]?.acc).filter(a=>a!==null);
    const clsAvg = avg(accs)||0;
    const totalAtt = sids.reduce((a,id)=>a+(STATS[id]?.attempts||0),0);
    const avgAtt = sids.length ? Math.round(totalAtt/sids.length) : 0;
    const improving = sids.filter(id=>(STATS[id]?.trendDelta||0)>5).length;
    const declining = sids.filter(id=>(STATS[id]?.trendDelta||0)<-5).length;
    const totalSec  = Object.values({}).length; // placeholder
    const hardAs = sids.map(id=>STATS[id]?.diffAcc.hard).filter(a=>a!==null);
    const hardAvg = avg(hardAs)||0;
    const easyAs  = sids.map(id=>STATS[id]?.diffAcc.easy).filter(a=>a!==null);
    const easyAvg = avg(easyAs)||0;

    const kpis=[
        {icon:'🎯',label:'Class Avg Accuracy',val:clsAvg+'%',sub:'overall',accent:'#378ADD'},
        {icon:'📚',label:'Avg Attempts / Student',val:avgAtt,sub:'per student',accent:'#7F77DD'},
        {icon:'📈',label:'Improving Students',val:improving,sub:'recent upward trend',accent:'#639922'},
        {icon:'📉',label:'Declining Students',val:declining,sub:'recent downward trend',accent:'#E24B4A'},
        {icon:'🟢',label:'Easy Class Avg',val:easyAvg+'%',sub:`${easyAs.length} students`,accent:'#639922'},
        {icon:'💪',label:'Hard Class Avg',val:hardAvg+'%',sub:`${hardAs.length} students`,accent:'#EF9F27'},
    ];
    el('classMetrics').innerHTML = kpis.map((k,i)=>`
        <div class="metric fi" style="--accent:${k.accent};animation-delay:${80+i*25}ms">
            <span class="metric-icon">${k.icon}</span>
            <div class="metric-label">${k.label}</div>
            <div class="metric-val" style="color:${k.accent}">${k.val}</div>
            <div class="metric-sub">${k.sub}</div>
        </div>`).join('');
}

/* ══════════════════════════════════════════════════════════════
   GRADE DONUT + DISTRIBUTION STRIP
══════════════════════════════════════════════════════════════ */
function renderGradeDonut(){
    const sids=ALL_STUDENTS.map(s=>s.id);
    const accs=sids.map(id=>STATS[id]?.acc).filter(a=>a!==null);
    const buckets=[
        {l:'A+ ≥90',c:'#639922',v:accs.filter(a=>a>=90).length},
        {l:'A 80–89',c:'#7DB83A',v:accs.filter(a=>a>=80&&a<90).length},
        {l:'B 70–79',c:'#378ADD',v:accs.filter(a=>a>=70&&a<80).length},
        {l:'C 60–69',c:'#EF9F27',v:accs.filter(a=>a>=60&&a<70).length},
        {l:'D 50–59',c:'#E27427',v:accs.filter(a=>a>=50&&a<60).length},
        {l:'F <50',  c:'#E24B4A',v:accs.filter(a=>a<50).length},
    ];
    if(CHARTS.gradeDonut)CHARTS.gradeDonut.destroy();
    CHARTS.gradeDonut=new Chart(el('gradeDonut'),{
        type:'doughnut',
        data:{labels:buckets.map(b=>b.l),datasets:[{data:buckets.map(b=>b.v),backgroundColor:buckets.map(b=>b.c),borderColor:'#fff',borderWidth:2,hoverOffset:6}]},
        options:{responsive:true,maintainAspectRatio:false,cutout:'62%',plugins:{legend:{position:'bottom',labels:{boxWidth:8,padding:7,font:{size:9}}},tooltip:{callbacks:{label:c=>`${c.label}: ${c.raw} students`}}}}
    });
    const tot=accs.length||1;
    el('distStrip').innerHTML=buckets.map(b=>`<div class="dist-seg" style="width:${(b.v/tot*100).toFixed(1)}%;background:${b.c}"></div>`).join('');
    el('distLegend').innerHTML=buckets.map(b=>`<div class="dist-item"><div class="dist-dot" style="background:${b.c}"></div>${b.l}: <b>${b.v}</b></div>`).join('');

    const topG=buckets[0].v+buckets[1].v+buckets[2].v;
    const botG=buckets[4].v+buckets[5].v;
    el('distInsights').innerHTML=`
        <div class="ins-row"><div class="ins-val" style="color:#639922">${topG}</div><div><div class="ins-label">Grade B or above</div><div class="ins-sub">performing well overall</div></div></div>
        <div class="ins-row"><div class="ins-val" style="color:#EF9F27">${buckets[3].v}</div><div><div class="ins-label">Grade C (60–69%)</div><div class="ins-sub">borderline — needs push</div></div></div>
        <div class="ins-row"><div class="ins-val" style="color:#E24B4A">${botG}</div><div><div class="ins-label">Grade D / F</div><div class="ins-sub">critical intervention needed</div></div></div>
        <div class="ins-row"><div class="ins-val" style="color:var(--hint)">${ALL_STUDENTS.length-accs.length}</div><div><div class="ins-label">No attempts yet</div><div class="ins-sub">not started</div></div></div>`;
}

/* ══════════════════════════════════════════════════════════════
   CLASS TREND
══════════════════════════════════════════════════════════════ */
function renderClassTrend(){
    const dayMap={};
    ALL_STUDENTS.forEach(s=>{
        (QUIZ_BY_SID[s.id]||[]).forEach(q=>{
            if(!q.timecreated||!parseInt(q.total))return;
            const d=new Date(parseInt(q.timecreated)*1000).toISOString().slice(0,10);
            if(!dayMap[d])dayMap[d]={s:0,t:0};
            dayMap[d].s+=parseInt(q.score)||0;
            dayMap[d].t+=parseInt(q.total)||0;
        });
    });
    const days=Object.keys(dayMap).sort();
    const vals=days.map(d=>dayMap[d].t>0?Math.round(dayMap[d].s/dayMap[d].t*100):0);
    const labels=days.map(d=>new Date(d).toLocaleDateString('en-GB',{day:'2-digit',month:'short'}));
    el('trendDayCount').textContent=days.length+' days';
    if(CHARTS.classTrend)CHARTS.classTrend.destroy();
    CHARTS.classTrend=new Chart(el('classTrend'),{
        type:'line',
        data:{labels:labels.length?labels:['No data'],datasets:[{label:'Class Avg %',data:vals.length?vals:[0],borderColor:'#378ADD',backgroundColor:'rgba(55,138,221,0.08)',borderWidth:2,pointRadius:3,pointBackgroundColor:'#378ADD',tension:.38,fill:true}]},
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>c.raw+'%'}}},scales:{x:{grid:{display:false},ticks:{maxRotation:35,maxTicksLimit:8,font:{size:10}}},y:{min:0,max:100,ticks:{callback:v=>v+'%',font:{size:10}},grid:{color:'rgba(0,0,0,0.04)'}}}}
    });
}

/* ══════════════════════════════════════════════════════════════
   CLASS RADAR
══════════════════════════════════════════════════════════════ */
function renderClassRadar(){
    const sids=ALL_STUDENTS.map(s=>s.id);
    const unitAvgs=SECTIONS.map((_,i)=>avg(sids.map(id=>STATS[id]?.unitPcts[i]).filter(p=>p!==null))||0);
    if(CHARTS.classRadar)CHARTS.classRadar.destroy();
    CHARTS.classRadar=new Chart(el('classRadar'),{
        type:'radar',
        data:{labels:SECTIONS,datasets:[{label:'Class Avg %',data:unitAvgs,borderColor:'#7F77DD',backgroundColor:'rgba(127,119,221,0.12)',borderWidth:2,pointBackgroundColor:'#7F77DD',pointRadius:3}]},
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{r:{beginAtZero:true,max:100,ticks:{stepSize:25,font:{size:9},backdropColor:'transparent'},grid:{color:'rgba(0,0,0,0.06)'},angleLines:{color:'rgba(0,0,0,0.06)'},pointLabels:{font:{size:10},color:'#6B6B6B'}}}}
    });
}

/* ══════════════════════════════════════════════════════════════
   DIFFICULTY BAR
══════════════════════════════════════════════════════════════ */
function renderDiffBar(){
    const sids=ALL_STUDENTS.map(s=>s.id);
    const labels=ALL_STUDENTS.map(s=>s.firstname);
    const eD=ALL_STUDENTS.map(s=>STATS[s.id]?.diffAcc.easy??0);
    const mD=ALL_STUDENTS.map(s=>STATS[s.id]?.diffAcc.medium??0);
    const hD=ALL_STUDENTS.map(s=>STATS[s.id]?.diffAcc.hard??0);
    if(CHARTS.diffBar)CHARTS.diffBar.destroy();
    CHARTS.diffBar=new Chart(el('diffBar'),{
        type:'bar',
        data:{labels,datasets:[
            {label:'Easy',  data:eD,backgroundColor:'#C0DD97',borderColor:'#639922',borderWidth:1,borderRadius:3},
            {label:'Medium',data:mD,backgroundColor:'#FAC775',borderColor:'#EF9F27',borderWidth:1,borderRadius:3},
            {label:'Hard',  data:hD,backgroundColor:'#F09595',borderColor:'#E24B4A',borderWidth:1,borderRadius:3},
        ]},
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:true,position:'top',labels:{boxWidth:8,padding:8,font:{size:10}}},tooltip:{callbacks:{label:c=>c.dataset.label+': '+c.raw+'%'}}},scales:{x:{grid:{display:false},ticks:{font:{size:10},maxRotation:40}},y:{beginAtZero:true,max:100,ticks:{callback:v=>v+'%',font:{size:10}},grid:{color:'rgba(0,0,0,0.04)'}}}}
    });
}

/* ══════════════════════════════════════════════════════════════
   CLASS HEATMAP
══════════════════════════════════════════════════════════════ */
function renderClassHeatmap(){
    const sids=ALL_STUDENTS.map(s=>s.id);
    let html=`<table class="heatmap-table"><thead><tr><th style="text-align:left">Unit</th><th>Easy</th><th>Medium</th><th>Hard</th><th>All</th></tr></thead><tbody>`;
    SECTIONS.forEach((u,ui)=>{
        html+=`<tr><td class="rl">${u}</td>`;
        [...DIFFS,'all'].forEach(diff=>{
            const vals=[];
            sids.forEach(id=>{
                const quiz=QUIZ_BY_SID[id]||[];
                let sc=0,tot=0;
                quiz.forEach(q=>{
                    if(diff!=='all'&&(q.difficulty||'').toLowerCase()!==diff)return;
                    if(!resolveUnits(q).includes(u))return;
                    sc+=parseInt(q.score)||0; tot+=parseInt(q.total)||0;
                });
                if(tot>0)vals.push(Math.round(sc/tot*100));
            });
            const av=vals.length?Math.round(vals.reduce((a,b)=>a+b,0)/vals.length):null;
            html+=`<td style="background:${hBg(av)};color:${hTx(av)}" title="${vals.length} students">${av!==null?av+'%':'—'}</td>`;
        });
        html+=`</tr>`;
    });
    html+=`</tbody></table>
    <div style="display:flex;gap:5px;margin-top:9px;flex-wrap:wrap;align-items:center">
    <span style="font-size:9px;color:var(--hint);font-family:var(--mono)">AVG</span>
    ${[['#F09595','#A32D2D','<40%'],['#FAC775','#854F0B','40–60%'],['#C0DD97','#3B6D11','60–80%'],['#85C440','#3B6D11','80%+'],['#F0EEE8','#A8A8A8','—']]
        .map(([bg,c,l])=>`<span class="badge" style="background:${bg};color:${c};font-size:9px">${l}</span>`).join('')}
    </div>`;
    el('classHeatmap').innerHTML=html;
}

/* ══════════════════════════════════════════════════════════════
   ATTEMPTS BY UNIT
══════════════════════════════════════════════════════════════ */
function renderAttemptsUnit(){
    const sids=ALL_STUDENTS.map(s=>s.id);
    const unitAttempts=SECTIONS.map(u=>{
        let count=0;
        sids.forEach(id=>{(QUIZ_BY_SID[id]||[]).forEach(q=>{if(resolveUnits(q).includes(u))count++;});});
        return count;
    });
    const unitAttE=SECTIONS.map(u=>{let c=0;sids.forEach(id=>{(QUIZ_BY_SID[id]||[]).forEach(q=>{if(resolveUnits(q).includes(u)&&(q.difficulty||'').toLowerCase()==='easy')c++;});});return c;});
    const unitAttM=SECTIONS.map(u=>{let c=0;sids.forEach(id=>{(QUIZ_BY_SID[id]||[]).forEach(q=>{if(resolveUnits(q).includes(u)&&(q.difficulty||'').toLowerCase()==='medium')c++;});});return c;});
    const unitAttH=SECTIONS.map(u=>{let c=0;sids.forEach(id=>{(QUIZ_BY_SID[id]||[]).forEach(q=>{if(resolveUnits(q).includes(u)&&(q.difficulty||'').toLowerCase()==='hard')c++;});});return c;});
    if(CHARTS.attUnit)CHARTS.attUnit.destroy();
    CHARTS.attUnit=new Chart(el('attemptsUnit'),{
        type:'bar',
        data:{labels:SECTIONS,datasets:[
            {label:'Easy',  data:unitAttE,backgroundColor:'#C0DD97',stack:'s',borderRadius:3},
            {label:'Medium',data:unitAttM,backgroundColor:'#FAC775',stack:'s',borderRadius:3},
            {label:'Hard',  data:unitAttH,backgroundColor:'#F09595',stack:'s',borderRadius:3},
        ]},
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:true,position:'top',labels:{boxWidth:8,padding:8,font:{size:10}}}},scales:{x:{stacked:true,grid:{display:false},ticks:{font:{size:10}}},y:{stacked:true,beginAtZero:true,ticks:{stepSize:1,precision:0,font:{size:10}},grid:{color:'rgba(0,0,0,0.04)'}}}}
    });
}

/* ══════════════════════════════════════════════════════════════
   SCORE HISTOGRAM
══════════════════════════════════════════════════════════════ */
function renderHistogram(){
    const sids=ALL_STUDENTS.map(s=>s.id);
    const accs=sids.map(id=>STATS[id]?.acc).filter(a=>a!==null);
    const bins=[0,10,20,30,40,50,60,70,80,90,100];
    const counts=bins.slice(0,-1).map((_,i)=>accs.filter(a=>a>=bins[i]&&a<bins[i+1]).length);
    counts[counts.length-1]+=accs.filter(a=>a===100).length; // include 100
    const binColors=bins.slice(0,-1).map(b=>b<40?'rgba(226,75,74,0.7)':b<70?'rgba(239,159,39,0.7)':'rgba(99,153,34,0.7)');
    if(CHARTS.histogram)CHARTS.histogram.destroy();
    CHARTS.histogram=new Chart(el('scoreHistogram'),{
        type:'bar',
        data:{labels:bins.slice(0,-1).map((b,i)=>`${b}–${bins[i+1]}`),datasets:[{label:'Students',data:counts,backgroundColor:binColors,borderRadius:4,borderSkipped:false}]},
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>c.raw+' students'}}},scales:{x:{grid:{display:false},ticks:{font:{size:9}}},y:{beginAtZero:true,ticks:{stepSize:1,precision:0,font:{size:10}},grid:{color:'rgba(0,0,0,0.04)'}}}}
    });
}

/* ══════════════════════════════════════════════════════════════
   EASY vs HARD SCATTER
══════════════════════════════════════════════════════════════ */
function renderEasyHardScatter(){
    const pts=ALL_STUDENTS.map(s=>({
        x:STATS[s.id]?.diffAcc.easy??0,
        y:STATS[s.id]?.diffAcc.hard??0,
        label:(s.firstname+' '+s.lastname).trim(),
        acc:STATS[s.id]?.acc
    })).filter(p=>p.x>0||p.y>0);
    if(CHARTS.scatter)CHARTS.scatter.destroy();
    CHARTS.scatter=new Chart(el('easyHardScatter'),{
        type:'scatter',
        data:{datasets:[{label:'Students',data:pts,backgroundColor:pts.map(p=>colorOf(p.acc)+'BB'),pointRadius:6,pointHoverRadius:8}]},
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>`${c.raw.label}: Easy ${c.raw.x}%, Hard ${c.raw.y}%`}}},scales:{x:{min:0,max:100,title:{display:true,text:'Easy %',font:{size:10}},ticks:{callback:v=>v+'%',font:{size:10}},grid:{color:'rgba(0,0,0,0.04)'}},y:{min:0,max:100,title:{display:true,text:'Hard %',font:{size:10}},ticks:{callback:v=>v+'%',font:{size:10}},grid:{color:'rgba(0,0,0,0.04)'}}}}
    });
}

/* ══════════════════════════════════════════════════════════════
   UNIT AVG BARS (right)
══════════════════════════════════════════════════════════════ */
function renderUnitBars(){
    const sids=ALL_STUDENTS.map(s=>s.id);
    const unitColors=['#378ADD','#7F77DD','#1B9E9E','#639922','#EF9F27','#E24B4A'];
    el('unitAvgBars').innerHTML=SECTIONS.map((u,i)=>{
        const ps=sids.map(id=>STATS[id]?.unitPcts[i]).filter(p=>p!==null);
        const av=avg(ps);
        const c=colorOf(av);
        return `<div class="bar-row">
            <div class="bar-meta">
                <span class="bar-name" style="display:flex;align-items:center;gap:6px">
                    <span class="badge" style="background:${unitColors[i]}22;color:${unitColors[i]};padding:1px 6px">${u}</span>
                    <span style="font-size:10px;color:var(--muted)">${ps.length} students</span>
                </span>
                <span class="bar-pct" style="color:${c}">${av!==null?av+'%':'—'}</span>
            </div>
            <div class="bar-track"><div class="bar-fill" style="width:${av||0}%;background:${c}"></div></div>
        </div>`;
    }).join('');
}

/* ══════════════════════════════════════════════════════════════
   ATTEMPTS PER STUDENT
══════════════════════════════════════════════════════════════ */
function renderAttemptsPerStudent(){
    const labels=ALL_STUDENTS.map(s=>s.firstname);
    const data  =ALL_STUDENTS.map(s=>STATS[s.id]?.attempts||0);
    const maxA  =Math.max(...data,1);
    if(CHARTS.attPS)CHARTS.attPS.destroy();
    CHARTS.attPS=new Chart(el('attemptsPerStudent'),{
        type:'bar',
        data:{labels,datasets:[{label:'Attempts',data,backgroundColor:data.map(v=>`rgba(55,138,221,${0.25+v/maxA*0.7})`),borderRadius:4}]},
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>c.raw+' attempts'}}},scales:{x:{grid:{display:false},ticks:{font:{size:10},maxRotation:40}},y:{beginAtZero:true,ticks:{stepSize:1,precision:0,font:{size:10}},grid:{color:'rgba(0,0,0,0.04)'}}}}
    });
}

/* ══════════════════════════════════════════════════════════════
   SCORE SPREAD (horizontal)
══════════════════════════════════════════════════════════════ */
function renderScoreSpread(){
    const sorted=[...ALL_STUDENTS].sort((a,b)=>(STATS[a.id]?.acc||0)-(STATS[b.id]?.acc||0));
    const labels=sorted.map(s=>s.firstname);
    const data  =sorted.map(s=>STATS[s.id]?.acc??0);
    if(CHARTS.spread)CHARTS.spread.destroy();
    CHARTS.spread=new Chart(el('scoreSpread'),{
        type:'bar',
        data:{labels,datasets:[{label:'Accuracy %',data,backgroundColor:data.map(v=>colorOf(v)+'CC'),borderRadius:3}]},
        options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>c.raw+'%'}}},scales:{x:{min:0,max:100,ticks:{callback:v=>v+'%',font:{size:10}},grid:{color:'rgba(0,0,0,0.04)'}},y:{ticks:{font:{size:9}},grid:{display:false}}}}
    });
}

/* ══════════════════════════════════════════════════════════════
   ACTIVITY HEATMAP (by weekday × unit)
══════════════════════════════════════════════════════════════ */
function renderActivityHeatmap(){
    const days=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    const dayCounts=Array(7).fill(0);
    ALL_STUDENTS.forEach(s=>{
        (QUIZ_BY_SID[s.id]||[]).forEach(q=>{
            if(!q.timecreated)return;
            const d=new Date(parseInt(q.timecreated)*1000).getDay();
            dayCounts[d]++;
        });
    });
    const maxC=Math.max(...dayCounts,1);
    const colorsDay=dayCounts.map(c=>{
        if(!c)return{bg:'#F0EEE8',tx:'#A8A8A8'};
        const t=c/maxC;
        if(t<0.33)return{bg:'#E0F5F5',tx:'#0D5C5C'};
        if(t<0.66)return{bg:'#B8E8E8',tx:'#0D5C5C'};
        return{bg:'#1B9E9E',tx:'#fff'};
    });
    el('activityHeatmap').innerHTML=`
    <div style="font-size:9px;color:var(--muted);margin-bottom:8px;font-family:var(--mono);text-transform:uppercase;letter-spacing:.07em">Attempts by day of week</div>
    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px">
        ${days.map((d,i)=>`
        <div style="text-align:center">
            <div style="background:${colorsDay[i].bg};color:${colorsDay[i].tx};border-radius:6px;padding:10px 4px;font-family:var(--mono);font-size:13px;font-weight:600;margin-bottom:4px">${dayCounts[i]}</div>
            <div style="font-size:9px;color:var(--muted);font-family:var(--mono)">${d}</div>
        </div>`).join('')}
    </div>
    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-top:14px">
        ${SECTIONS.map((u,ui)=>{
            const unitColors=['#E8F2FC','#EEEDFE','#E0F5F5','#EAF3DE','#FEF3E0','#FCEBEB'];
            const unitTxts =['#185FA5','#3C3489','#0D5C5C','#3B6D11','#854F0B','#A32D2D'];
            let count=0;
            ALL_STUDENTS.forEach(s=>{(QUIZ_BY_SID[s.id]||[]).forEach(q=>{if(resolveUnits(q).includes(u))count++;});});
            return `<div style="text-align:center">
                <div style="background:${unitColors[ui]};color:${unitTxts[ui]};border-radius:6px;padding:8px 4px;font-family:var(--mono);font-size:12px;font-weight:600;margin-bottom:4px">${count}</div>
                <div style="font-size:9px;color:var(--muted);font-family:var(--mono)">${u}</div>
            </div>`;
        }).join('')}
    </div>
    <div style="font-size:9px;color:var(--muted);margin-top:8px;font-family:var(--mono);text-transform:uppercase;letter-spacing:.07em">Attempts by unit</div>`;
}

/* ══════════════════════════════════════════════════════════════
   UNIT SPLIT DONUT (right)
══════════════════════════════════════════════════════════════ */
function renderUnitDonut(){
    const sids=ALL_STUDENTS.map(s=>s.id);
    const unitCounts=SECTIONS.map(u=>{
        let c=0;sids.forEach(id=>{(QUIZ_BY_SID[id]||[]).forEach(q=>{if(resolveUnits(q).includes(u))c++;});});return c;
    });
    if(CHARTS.unitDonut)CHARTS.unitDonut.destroy();
    CHARTS.unitDonut=new Chart(el('unitSplitDonut'),{
        type:'doughnut',
        data:{labels:SECTIONS,datasets:[{data:unitCounts,backgroundColor:['#378ADD','#7F77DD','#1B9E9E','#639922','#EF9F27','#E24B4A'],borderColor:'#fff',borderWidth:2,hoverOffset:4}]},
        options:{responsive:true,maintainAspectRatio:false,cutout:'55%',plugins:{legend:{position:'bottom',labels:{boxWidth:8,padding:7,font:{size:9}}},tooltip:{callbacks:{label:c=>`${c.label}: ${c.raw} attempts`}}}}
    });
}

/* ══════════════════════════════════════════════════════════════
   TOP / AT RISK / ACTIVE LISTS
══════════════════════════════════════════════════════════════ */
function rankList(containerId, sorted, maxN, badgeId) {
    const items = sorted.slice(0, maxN);
    if(badgeId) el(badgeId).textContent = sorted.length;
    if(!items.length){ el(containerId).innerHTML=`<p style="font-size:11px;color:var(--hint);padding:4px 0">No data yet.</p>`; return; }
    el(containerId).innerHTML = items.map((s,i)=>{
        const st = STATS[s.id];
        const p  = st?.acc??null;
        const c  = colorOf(p);
        const bg = bgOf(p);
        const tx = txtOf(p);
        const nm = (s.firstname+' '+s.lastname).trim();
        const sparkH = (st?.trend||[]).slice(-5).map(d=>d.pct);
        const maxSp  = Math.max(...sparkH,1);
        const sparkEl= sparkH.length>=2?`<span class="spark">${sparkH.map(v=>`<span class="spark-b" style="height:${Math.round(v/maxSp*14)+2}px;background:${colorOf(v)}"></span>`).join('')}</span>`:'' ;
        return `<div class="rank-row" onclick="goToStudent(${s.id},'${nm}')">
            <span class="rank-num">${i+1}</span>
            <div class="rank-av" style="background:${bg};color:${tx}">${mkI(s.firstname,s.lastname)}</div>
            <div class="rank-info">
                <div class="rank-name">${nm}</div>
                <div class="rank-detail">${st?.attempts||0} attempts ${sparkEl}</div>
            </div>
            <span class="rank-score" style="color:${c}">${p!==null?p+'%':'—'}</span>
        </div>`;
    }).join('');
}

function renderRankLists(){
    const sorted=[...ALL_STUDENTS].filter(s=>STATS[s.id]?.acc!==null).sort((a,b)=>(STATS[b.id]?.acc||0)-(STATS[a.id]?.acc||0));
    const atRisk=[...ALL_STUDENTS].filter(s=>STATS[s.id]?.acc!==null&&STATS[s.id]?.acc<40).sort((a,b)=>(STATS[a.id]?.acc||0)-(STATS[b.id]?.acc||0));
    const active=[...ALL_STUDENTS].sort((a,b)=>(STATS[b.id]?.attempts||0)-(STATS[a.id]?.attempts||0));
    rankList('topList', sorted, 8, 'topCount');
    rankList('riskList', atRisk, 8, 'riskCount');
    rankList('activeList', active, 8, null);
}

/* ══════════════════════════════════════════════════════════════
   CLASS INSIGHTS (right)
══════════════════════════════════════════════════════════════ */
function renderClassInsights(){
    const sids=ALL_STUDENTS.map(s=>s.id);
    const accs=sids.map(id=>STATS[id]?.acc).filter(a=>a!==null);
    const improving=sids.filter(id=>(STATS[id]?.trendDelta||0)>5).length;
    const declining=sids.filter(id=>(STATS[id]?.trendDelta||0)<-5).length;
    const unitAvgs=SECTIONS.map((_,i)=>avg(sids.map(id=>STATS[id]?.unitPcts[i]).filter(p=>p!==null))||0);
    const bestI=unitAvgs.indexOf(Math.max(...unitAvgs));
    const worstI=unitAvgs.indexOf(Math.min(...unitAvgs.filter(v=>v>0)));
    const hardAs=sids.map(id=>STATS[id]?.diffAcc.hard).filter(a=>a!==null);
    const hardAvg=avg(hardAs)||0;
    const totalAtt=sids.reduce((a,id)=>a+(STATS[id]?.attempts||0),0);
    const stdDev=accs.length?Math.round(Math.sqrt(accs.reduce((a,v)=>a+Math.pow(v-(avg(accs)||0),2),0)/accs.length)):0;
    el('classInsights').innerHTML=`
        <div class="ins-row"><div class="ins-val" style="color:#639922">${improving}</div><div><div class="ins-label">Improving trend</div><div class="ins-sub">last 5 quizzes up</div></div></div>
        <div class="ins-row"><div class="ins-val" style="color:#E24B4A">${declining}</div><div><div class="ins-label">Declining trend</div><div class="ins-sub">last 5 quizzes down</div></div></div>
        <div class="ins-row"><div class="ins-val" style="color:#639922">${SECTIONS[bestI]||'—'}</div><div><div class="ins-label">Strongest unit</div><div class="ins-sub">${unitAvgs[bestI]||0}% class avg</div></div></div>
        <div class="ins-row"><div class="ins-val" style="color:#E24B4A">${SECTIONS[worstI]||'—'}</div><div><div class="ins-label">Weakest unit</div><div class="ins-sub">${unitAvgs[worstI]||0}% class avg</div></div></div>
        <div class="ins-row"><div class="ins-val" style="color:#7F77DD">±${stdDev}</div><div><div class="ins-label">Score std deviation</div><div class="ins-sub">spread of accuracy</div></div></div>
        <div class="ins-row"><div class="ins-val" style="color:#1B9E9E">${totalAtt}</div><div><div class="ins-label">Total quiz attempts</div><div class="ins-sub">all students combined</div></div></div>`;
}

/* ══════════════════════════════════════════════════════════════
   MAIN STACKED BAR
══════════════════════════════════════════════════════════════ */
function rebuildOverallChart(){
    const unitF=el('chartUnitF').value, diffF=el('chartDiffF').value;
    const units=unitF==='ALL'?SECTIONS:[unitF];
    const diffs=diffF==='ALL'?DIFFS:[diffF];
    const labels=ALL_STUDENTS.map(s=>(s.firstname+' '+s.lastname).trim());
    const datasets=[];
    const diffColors={easy:'#6DB33F',medium:'#F5C842',hard:'#E55C5C'};
    units.forEach(u=>{
        diffs.forEach(diff=>{
            const data=ALL_STUDENTS.map(s=>{
                let sc=0,tot=0;
                (QUIZ_BY_SID[s.id]||[]).forEach(q=>{
                    if((q.difficulty||'').toLowerCase()!==diff)return;
                    if(!resolveUnits(q).includes(u))return;
                    sc+=parseInt(q.score)||0; tot+=parseInt(q.total)||0;
                });
                return tot>0?Number((sc/tot*100).toFixed(1)):0;
            });
            datasets.push({label:u+' '+diff,data,stack:'all',backgroundColor:diffColors[diff],borderColor:'#fff',borderWidth:0.5,borderRadius:2});
        });
    });
    if(CHARTS.overall)CHARTS.overall.destroy();
    CHARTS.overall=new Chart(el('overallChart'),{
        type:'bar',
        data:{labels,datasets},
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>c.dataset.label+': '+c.raw+'%'}}},scales:{x:{stacked:true,grid:{display:false},ticks:{autoSkip:false,font:{size:10},maxRotation:40}},y:{stacked:true,beginAtZero:true,title:{display:true,text:'Stacked Performance (%)',font:{size:10}},ticks:{font:{size:10}},grid:{color:'rgba(0,0,0,0.04)'}}}}
    });
}

/* ══════════════════════════════════════════════════════════════
   STUDENT TABLE
══════════════════════════════════════════════════════════════ */
let FILTERED_STUDENTS=[];
function filterStudents(){
    const q=el('stuSearch').value.toLowerCase();
    const sf=el('stuStatusF').value;
    const so=el('stuSortF').value;
    let list=[...ALL_STUDENTS].filter(s=>{
        const nm=(s.firstname+' '+s.lastname).toLowerCase();
        if(q&&!nm.includes(q))return false;
        const acc=STATS[s.id]?.acc;
        if(sf==='strong'&&!(acc>=70))return false;
        if(sf==='avg'&&!(acc>=40&&acc<70))return false;
        if(sf==='weak'&&!(acc!==null&&acc<40))return false;
        if(sf==='none'&&acc!==null)return false;
        return true;
    });
    if(so==='acc_desc') list.sort((a,b)=>(STATS[b.id]?.acc||0)-(STATS[a.id]?.acc||0));
    else if(so==='acc_asc') list.sort((a,b)=>(STATS[a.id]?.acc||0)-(STATS[b.id]?.acc||0));
    else if(so==='att_desc') list.sort((a,b)=>(STATS[b.id]?.attempts||0)-(STATS[a.id]?.attempts||0));
    else if(so==='improve') list.sort((a,b)=>(STATS[b.id]?.trendDelta||0)-(STATS[a.id]?.trendDelta||0));
    else list.sort((a,b)=>a.lastname.localeCompare(b.lastname));
    FILTERED_STUDENTS=list;
    el('stuFilterCount').textContent=list.length+' shown';
    renderStudentTable();
}
function renderStudentTable(){
    if(!FILTERED_STUDENTS.length){
        el('stuTbody').innerHTML=`<tr><td colspan="18" style="text-align:center;padding:20px;font-size:11px;color:var(--muted)">No students match.</td></tr>`;
        return;
    }
    el('stuTbody').innerHTML=FILTERED_STUDENTS.map(s=>{
        const st=STATS[s.id]||{};
        const acc=st.acc??null;
        const c=colorOf(acc), bg=bgOf(acc), tx=txtOf(acc);
        const stat=statusOf(acc);
        const nm=(s.firstname+' '+s.lastname).trim();
        const av=mkI(s.firstname,s.lastname);
        const unitCells=(st.unitPcts||SECTIONS.map(()=>null)).map(p=>`<td><span style="font-family:var(--mono);font-size:10px;color:${colorOf(p)}">${p!==null?p+'%':'—'}</span></td>`).join('');
        const dE=st.diffAcc?.easy??null, dM=st.diffAcc?.medium??null, dH=st.diffAcc?.hard??null;
        const sparkH=(st.trend||[]).slice(-6).map(d=>d.pct);
        const maxSp=Math.max(...sparkH,1);
        const sparkEl=sparkH.length>=2?`<span class="spark">${sparkH.map(v=>`<span class="spark-b" style="height:${Math.round(v/maxSp*14)+2}px;background:${colorOf(v)}"></span>`).join('')}</span>`:'—';
        const delta=st.trendDelta||0;
        const deltaStr=delta>0?`<span style="color:#639922;font-family:var(--mono);font-size:10px">↑${delta}%</span>`:delta<0?`<span style="color:#E24B4A;font-family:var(--mono);font-size:10px">↓${Math.abs(delta)}%</span>`:`<span style="color:var(--hint);font-size:10px">→</span>`;
        return `<tr onclick="goToStudent(${s.id},'${nm}')">
            <td><div class="stu-av" style="background:${bg};color:${tx}">${av}</div></td>
            <td style="font-weight:500;font-size:12px">${nm}</td>
            <td style="font-family:var(--mono);font-size:10px;color:var(--muted)">${s.id}</td>
            <td style="font-family:var(--mono);font-size:11px">${st.attempts||0}</td>
            <td style="font-family:var(--mono);font-size:11px">${st.tS||0}/${st.tT||0}</td>
            <td><span style="font-family:var(--mono);font-size:12px;font-weight:600;color:${c}">${acc!==null?acc+'%':'—'}</span></td>
            ${unitCells}
            <td><span style="font-size:10px;color:${colorOf(dE)}">${dE!==null?dE+'%':'—'}</span></td>
            <td><span style="font-size:10px;color:${colorOf(dM)}">${dM!==null?dM+'%':'—'}</span></td>
            <td><span style="font-size:10px;color:${colorOf(dH)}">${dH!==null?dH+'%':'—'}</span></td>
            <td>${sparkEl}</td>
            <td>${deltaStr}</td>
            <td><span class="badge ${stat.c}">${stat.l}</span></td>
        </tr>`;
    }).join('');
}

/* ══════════════════════════════════════════════════════════════
   UTILS
══════════════════════════════════════════════════════════════ */
const el = id => document.getElementById(id);
function goToStudent(id, name){ window.location.href = M.cfg.wwwroot+'/local/automation/analytics_student.php?studentid='+id+'&name='+encodeURIComponent(name); }

/* ══════════════════════════════════════════════════════════════
   BOOT
══════════════════════════════════════════════════════════════ */
Promise.all([post('get_students'), post('get_all_students_quiz')])
.then(([students, quizData])=>{
    ALL_STUDENTS = students||[];
    (quizData||[]).forEach(q=>{
        const sid=q.studentid; if(!sid)return;
        if(!QUIZ_BY_SID[sid])QUIZ_BY_SID[sid]=[];
        QUIZ_BY_SID[sid].push(q);
    });
    ALL_STUDENTS.forEach(s=>{ STATS[s.id]=computeStats(s.id); });

    renderHero();
    renderMetrics();
    renderGradeDonut();
    rebuildOverallChart();
    renderClassTrend();
    renderClassRadar();
    renderDiffBar();
    renderClassHeatmap();
    renderAttemptsUnit();
    renderHistogram();
    renderEasyHardScatter();
    renderUnitBars();
    renderAttemptsPerStudent();
    renderScoreSpread();
    renderActivityHeatmap();
    renderUnitDonut();
    renderRankLists();
    renderClassInsights();

    FILTERED_STUDENTS=[...ALL_STUDENTS];
    filterStudents();
}).catch(err=>console.error('Analytics error:', err));
</script>

<?php echo $OUTPUT->footer(); ?>