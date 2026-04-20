<?php

require_once(__DIR__.'/../../config.php');
require_login();

$courseid  = get_config('local_automation', 'student_ai_courseid');
$studentid = required_param('studentid', PARAM_INT);
$name      = optional_param('name', 'Student', PARAM_TEXT);

if (!$courseid) {
    print_error('Student AI course not configured.');
}

$context = context_course::instance($courseid);
require_capability('moodle/course:view', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/automation/analytics_student.php');
$PAGE->set_pagelayout('embedded');
$PAGE->set_title('Student Analytics — ' . $name);
$PAGE->set_heading('Student Analytics');

$parts    = explode(' ', trim($name));
$initials = strtoupper(
    (isset($parts[0]) ? $parts[0][0] : '') .
    (isset($parts[1]) ? $parts[1][0] : '')
);

echo $OUTPUT->header();
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:#F5F4F0; --surface:#FFFFFF; --surface-alt:#F9F8F5;
    --border:rgba(0,0,0,0.07); --border-md:rgba(0,0,0,0.11);
    --text:#1A1A1A; --muted:#6B6B6B; --hint:#A8A8A8;
    --weak:#E24B4A; --weak-bg:#FCEBEB; --weak-txt:#A32D2D;
    --avg:#EF9F27;  --avg-bg:#FEF3E0;  --avg-txt:#854F0B;
    --strong:#639922; --strong-bg:#EAF3DE; --strong-txt:#3B6D11;
    --blue:#378ADD;   --blue-bg:#E8F2FC;   --blue-txt:#185FA5;
    --purple:#7F77DD; --purple-bg:#EEEDFE; --purple-txt:#3C3489;
    --cyan:#1B9E9E;   --cyan-bg:#E0F5F5;   --cyan-txt:#0D5C5C;
    --r:12px; --rsm:8px;
    --font:'DM Sans',system-ui,sans-serif; --mono:'DM Mono',monospace;
    --sh:0 1px 3px rgba(0,0,0,0.06),0 1px 2px rgba(0,0,0,0.04);
    --shm:0 4px 14px rgba(0,0,0,0.08),0 2px 4px rgba(0,0,0,0.05);
}

html,body{background:var(--bg)!important;}
body{padding:0!important;margin:0!important;}
#page,#page-wrapper,#page-content,#page-content .row,
#region-main-box,#region-main,.region-content,[role="main"],
.main-inner,#maincontent,.course-content,
div[data-region="blocks-column"],.drawers,.drawers .main-inner
{max-width:none!important;width:100%!important;padding:0!important;margin:0!important;float:none!important;flex:unset!important;}
#nav-drawer,.drawer,[data-region="fixed-drawer"],[data-region="right-hand-drawer"],
#block-region-side-pre,#block-region-side-post,.block-region,aside.block-region{display:none!important;}

.sa{width:100vw;min-height:100vh;background:var(--bg);font-family:var(--font);color:var(--text);font-size:14px;line-height:1.5;}

/* NAV */
.sa-nav{position:sticky;top:0;z-index:100;background:rgba(245,244,240,0.94);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border-bottom:0.5px solid var(--border-md);display:flex;align-items:center;justify-content:space-between;padding:0 28px;height:52px;gap:12px;}
.sa-nav-left{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.sa-nav-right{display:flex;align-items:center;gap:10px;}
.back-btn{display:inline-flex;align-items:center;gap:6px;font-family:var(--font);font-size:12px;font-weight:500;color:var(--muted);background:var(--surface);border:0.5px solid var(--border-md);border-radius:var(--rsm);padding:5px 12px;cursor:pointer;text-decoration:none;transition:color .15s,border-color .15s,box-shadow .15s;box-shadow:var(--sh);}
.back-btn:hover{color:var(--text);border-color:rgba(0,0,0,0.2);box-shadow:var(--shm);}
.back-btn svg{width:14px;height:14px;flex-shrink:0;}
.sa-avatar{width:32px;height:32px;border-radius:50%;background:var(--blue-bg);color:var(--blue-txt);font-size:11px;font-weight:600;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.page-title{font-size:17px;font-weight:600;letter-spacing:-.01em;}
.page-sub{font-size:11px;color:var(--muted);}
.grade-pill{font-size:12px;font-weight:600;padding:3px 13px;border-radius:20px;}
.nav-ts{font-family:var(--mono);font-size:10px;color:var(--hint);}

/* BODY */
.sa-body{padding:18px 24px 60px;display:grid;grid-template-columns:248px 1fr 268px;gap:12px;align-items:start;}
.col-left{grid-column:1;display:flex;flex-direction:column;gap:12px;}
.col-mid{grid-column:2;display:flex;flex-direction:column;gap:12px;}
.col-right{grid-column:3;display:flex;flex-direction:column;gap:12px;}

/* CARD */
.card{background:var(--surface);border:0.5px solid var(--border);border-radius:var(--r);padding:16px 18px;box-shadow:var(--sh);}
.card-title{font-size:10px;font-weight:500;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:13px;padding-bottom:9px;border-bottom:0.5px solid var(--border);display:flex;align-items:center;gap:6px;}

/* PI */
.pi-card{background:var(--surface);border:0.5px solid var(--border);border-radius:var(--r);padding:18px 18px 14px;box-shadow:var(--sh);position:relative;overflow:hidden;}
.pi-card::before{content:'';position:absolute;inset:0;border-radius:var(--r);padding:1.5px;background:var(--pi-glow,#D3D1C7);-webkit-mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0);-webkit-mask-composite:xor;mask-composite:exclude;pointer-events:none;}
.pi-top{display:flex;align-items:center;gap:16px;margin-bottom:14px;}
.pi-ring-wrap{position:relative;width:72px;height:72px;flex-shrink:0;}
.pi-ring-wrap svg{transform:rotate(-90deg);}
.pi-ring-bg{fill:none;stroke:#EDECE8;stroke-width:6;}
.pi-ring-fill{fill:none;stroke-width:6;stroke-linecap:round;transition:stroke-dashoffset 1.1s cubic-bezier(.16,1,.3,1);}
.pi-ring-label{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:var(--mono);font-weight:600;line-height:1;}
.pi-score-num{font-size:18px;} .pi-score-denom{font-size:9px;color:var(--hint);margin-top:1px;}
.pi-right{flex:1;min-width:0;}
.pi-label{font-size:9px;font-weight:500;text-transform:uppercase;letter-spacing:.09em;color:var(--muted);margin-bottom:3px;}
.pi-rank{font-size:15px;font-weight:600;letter-spacing:-.01em;}
.pi-tagline{font-size:11px;color:var(--muted);margin-top:3px;line-height:1.4;}
.pi-breakdown{border-top:0.5px solid var(--border);padding-top:10px;display:flex;flex-direction:column;gap:6px;}
.pi-factor{display:grid;grid-template-columns:1fr auto 54px;align-items:center;gap:8px;}
.pi-fname{font-size:10px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.pi-fval{font-family:var(--mono);font-size:10px;font-weight:500;text-align:right;white-space:nowrap;}
.pi-fbar{height:3px;background:#EDECE8;border-radius:3px;overflow:hidden;}
.pi-fbar-fill{height:3px;border-radius:3px;transition:width .9s cubic-bezier(.16,1,.3,1);}

/* METRICS */
.metrics-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.metric-full{grid-column:1/-1;}
.metric{background:var(--surface);border:0.5px solid var(--border);border-radius:var(--r);padding:12px 14px;position:relative;overflow:hidden;box-shadow:var(--sh);transition:box-shadow .15s,transform .15s;}
.metric:hover{box-shadow:var(--shm);transform:translateY(-1px);}
.metric::before{content:'';position:absolute;top:0;left:0;bottom:0;width:3px;background:var(--accent,#D3D1C7);border-radius:var(--r) 0 0 var(--r);}
.metric-icon{font-size:13px;margin-bottom:5px;display:block;}
.metric-label{font-size:9px;font-weight:500;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:2px;}
.metric-val{font-size:20px;font-weight:600;font-family:var(--mono);line-height:1;letter-spacing:-.02em;}
.metric-sub{font-size:10px;color:var(--hint);margin-top:3px;}

/* CHARTS */
.charts-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.ch180{position:relative;height:175px;width:100%;}

/* BAR ROWS */
.bar-row{margin-bottom:8px;} .bar-row:last-child{margin-bottom:0;}
.bar-meta{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:3px;}
.bar-name{font-size:11px;flex:1;margin-right:8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.bar-pct{font-family:var(--mono);font-size:11px;white-space:nowrap;}
.bar-track{height:4px;background:#EDECE8;border-radius:4px;overflow:hidden;}
.bar-fill{height:4px;border-radius:4px;transition:width .7s cubic-bezier(.16,1,.3,1);}

/* BADGES */
.badge{display:inline-block;font-size:10px;font-weight:500;font-family:var(--mono);padding:2px 7px;border-radius:20px;white-space:nowrap;}
.bw{background:var(--weak-bg);color:var(--weak-txt);} .ba{background:var(--avg-bg);color:var(--avg-txt);}
.bs{background:var(--strong-bg);color:var(--strong-txt);} .bb{background:var(--blue-bg);color:var(--blue-txt);}
.bp{background:var(--purple-bg);color:var(--purple-txt);} .bc{background:var(--cyan-bg);color:var(--cyan-txt);}
.bm{background:#F0EEE8;color:var(--muted);}

/* HEATMAP */
.heatmap-table{width:100%;border-collapse:separate;border-spacing:3px;font-size:11px;}
.heatmap-table th{font-size:9px;font-weight:500;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);padding:3px 4px;text-align:center;}
.heatmap-table td{padding:7px;text-align:center;border-radius:5px;font-family:var(--mono);font-size:11px;font-weight:500;cursor:default;transition:filter .12s,transform .12s;}
.heatmap-table td:hover:not(.rl){filter:brightness(.92);transform:scale(1.06);}
.heatmap-table .rl{text-align:left;font-family:var(--font);color:var(--text);font-size:11px;padding-right:6px;white-space:nowrap;}

/* SECTION TABLE */
.sec-table{width:100%;border-collapse:collapse;font-size:11px;}
.sec-table th{text-align:left;font-size:9px;font-weight:500;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);padding:5px 7px;border-bottom:.5px solid var(--border-md);}
.sec-table td{padding:7px 7px;border-bottom:.5px solid var(--border);vertical-align:middle;}
.sec-table tr:last-child td{border-bottom:none;} .sec-table tr:hover td{background:var(--surface-alt);}
.mbar{height:3px;background:#EDECE8;border-radius:3px;width:48px;overflow:hidden;display:inline-block;vertical-align:middle;margin-left:5px;}
.mfill{height:3px;border-radius:3px;display:block;}

/* ATTEMPT LOG */
.ag{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1.1fr) 76px 76px 60px;gap:6px;align-items:center;padding:7px 7px;}
.ag-head{font-size:9px;font-weight:500;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);border-bottom:.5px solid var(--border-md);padding-bottom:7px;}
.ag-row{border-bottom:.5px solid var(--border);transition:background .1s;border-radius:4px;}
.ag-row:last-child{border-bottom:none;} .ag-row:hover{background:var(--surface-alt);}

/* INSIGHTS */
.ins-row{display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:.5px solid var(--border);}
.ins-row:last-child{border-bottom:none;}
.ins-val{font-size:17px;font-weight:600;font-family:var(--mono);min-width:42px;letter-spacing:-.02em;}
.ins-label{font-size:11px;font-weight:500;} .ins-sub{font-size:10px;color:var(--hint);}

/* ADVICE */
.advice-item{border-left:3px solid var(--blue);background:var(--blue-bg);padding:9px 12px;border-radius:0 6px 6px 0;margin-bottom:8px;font-size:12px;line-height:1.5;}
.advice-item:last-child{margin-bottom:0;}
.advice-ts{color:var(--hint);font-size:10px;font-family:var(--mono);margin-top:3px;}
textarea.sa-textarea{width:100%;min-height:74px;resize:vertical;border:0.5px solid var(--border-md);border-radius:var(--rsm);background:var(--surface-alt);color:var(--text);font-family:var(--font);font-size:12px;padding:9px 11px;outline:none;transition:border-color .15s;line-height:1.55;margin-top:10px;}
textarea.sa-textarea:focus{border-color:var(--blue);}
.sa-save-btn{margin-top:7px;padding:7px 16px;font-family:var(--font);font-size:12px;font-weight:500;cursor:pointer;background:var(--blue);color:#fff;border:none;border-radius:var(--rsm);transition:opacity .15s;}
.sa-save-btn:hover{opacity:.88;}

/* LOCKS */
.lock-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:0.5px solid var(--border);}
.lock-row:last-of-type{border-bottom:none;}
.lock-label{font-size:12px;font-weight:500;} .lock-desc{font-size:10px;color:var(--hint);margin-top:1px;}
.sa-toggle{position:relative;width:34px;height:19px;display:inline-block;flex-shrink:0;}
.sa-toggle input{opacity:0;width:0;height:0;position:absolute;}
.sa-toggle-bg{position:absolute;inset:0;border-radius:10px;background:var(--border-md);transition:background .2s;cursor:pointer;}
.sa-toggle input:checked+.sa-toggle-bg{background:var(--blue);}
.sa-toggle-knob{position:absolute;top:2.5px;left:2.5px;width:14px;height:14px;border-radius:50%;background:#fff;transition:transform .2s;pointer-events:none;}
.sa-toggle input:checked~.sa-toggle-knob{transform:translateX(15px);}

/* MISC */
.filters{display:flex;gap:6px;margin-bottom:11px;flex-wrap:wrap;}
.filters select{font-family:var(--font);font-size:11px;padding:4px 9px;border:.5px solid var(--border-md);border-radius:var(--rsm);background:var(--surface);color:var(--text);cursor:pointer;outline:none;box-shadow:var(--sh);}
.filters select:focus{border-color:var(--blue);}
.sh{font-size:10px;font-weight:500;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);margin-bottom:8px;display:flex;align-items:center;gap:8px;}
.sh::after{content:'';flex:1;height:.5px;background:var(--border-md);}
.score-pill{font-family:var(--mono);font-size:11px;font-weight:500;padding:2px 7px;border-radius:5px;display:inline-block;}
.score-high{background:var(--strong-bg);color:var(--strong-txt);} .score-mid{background:var(--avg-bg);color:var(--avg-txt);} .score-low{background:var(--weak-bg);color:var(--weak-txt);}
.loading{display:flex;align-items:center;gap:7px;font-size:11px;color:var(--muted);padding:14px 0;}
.spinner{width:12px;height:12px;border:1.5px solid #D8D6D0;border-top-color:var(--blue);border-radius:50%;animation:spin .6s linear infinite;}
@keyframes spin{to{transform:rotate(360deg);}}
.fi{animation:fadeUp .3s ease both;}
@keyframes fadeUp{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}

/* ══════════════════════════════════════
   STUDENT CHAT VIEWER
══════════════════════════════════════ */
.chv {
    background: var(--surface);
    border: 0.5px solid var(--border);
    border-radius: var(--r);
    box-shadow: var(--sh);
    overflow: hidden;
}
/* header */
.chv-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 16px 11px;
    border-bottom: 0.5px solid var(--border);
}
.chv-title {
    font-size: 10px; font-weight: 500; text-transform: uppercase;
    letter-spacing: .08em; color: var(--muted);
    display: flex; align-items: center; gap: 7px;
}
.chv-pill {
    font-size: 10px; font-weight: 500; font-family: var(--mono);
    background: var(--blue-bg); color: var(--blue-txt);
    padding: 2px 8px; border-radius: 20px;
}
/* filter chips */
.chv-filters {
    display: flex; gap: 5px; padding: 8px 14px 7px;
    background: var(--surface-alt);
    border-bottom: 0.5px solid var(--border);
    flex-wrap: wrap;
}
.chv-chip {
    font-size: 9px; font-weight: 500; font-family: var(--mono);
    padding: 3px 9px; border-radius: 12px;
    border: 0.5px solid var(--border-md);
    background: var(--surface); color: var(--muted);
    cursor: pointer; transition: all .12s; white-space: nowrap;
}
.chv-chip:hover  { color: var(--blue-txt); border-color: rgba(55,138,221,.35); }
.chv-chip.active { background: var(--blue-bg); color: var(--blue-txt); border-color: rgba(55,138,221,.3); }
/* scrollable thread */
.chv-thread {
    max-height: 420px; overflow-y: auto;
    padding: 10px 14px 12px;
    display: flex; flex-direction: column; gap: 2px;
    scroll-behavior: smooth;
}
.chv-thread::-webkit-scrollbar { width: 3px; }
.chv-thread::-webkit-scrollbar-track { background: transparent; }
.chv-thread::-webkit-scrollbar-thumb { background: var(--border-md); border-radius: 3px; }
/* day label */
.chv-day {
    display: flex; align-items: center; gap: 8px;
    margin: 10px 0 6px;
}
.chv-day:first-child { margin-top: 2px; }
.chv-day span { font-size: 9px; font-family: var(--mono); color: var(--hint); text-transform: uppercase; letter-spacing: .07em; white-space: nowrap; }
.chv-day::before, .chv-day::after { content: ''; flex: 1; height: 0.5px; background: var(--border-md); }
/* row */
.chv-row {
    display: grid; grid-template-columns: 30px 1fr auto;
    gap: 10px; align-items: start;
    padding: 8px 8px; border-radius: var(--rsm);
    transition: background .1s;
}
.chv-row:hover { background: var(--surface-alt); }
/* avatar */
.chv-av {
    width: 30px; height: 30px; border-radius: 50%;
    background: var(--blue-bg); color: var(--blue-txt);
    font-size: 10px; font-weight: 600; font-family: var(--mono);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; margin-top: 1px;
}
/* body */
.chv-who {
    font-size: 11px; font-weight: 500; color: var(--text);
    margin-bottom: 4px;
    display: flex; align-items: center; gap: 5px; flex-wrap: wrap;
}
.chv-topic {
    font-size: 9px; font-family: var(--mono);
    background: var(--cyan-bg); color: var(--cyan-txt);
    padding: 1px 6px; border-radius: 10px;
}
.chv-msg { font-size: 12px; color: var(--text); line-height: 1.55; word-break: break-word; }
/* timestamp */
.chv-ts { font-size: 9px; font-family: var(--mono); color: var(--hint); white-space: nowrap; padding-top: 3px; flex-shrink: 0; }
/* empty */
.chv-empty { padding: 32px 0; text-align: center; }
.chv-empty-icon { font-size: 26px; opacity: .28; margin-bottom: 8px; }
.chv-empty p { font-size: 11px; color: var(--hint); }

/* RESPONSIVE */
@media(max-width:1260px){.sa-body{grid-template-columns:220px 1fr 245px;padding:14px 16px 50px;}}
@media(max-width:1020px){
    .sa-body{grid-template-columns:1fr 1fr;}
    .col-left{grid-column:1/-1;flex-direction:row;flex-wrap:wrap;}
    .col-left>*{flex:1 1 260px;min-width:0;}
    .col-mid{grid-column:1/-1;} .col-right{grid-column:1/-1;}
    .charts-grid{grid-template-columns:1fr 1fr;}
}
@media(max-width:640px){
    .sa-body{grid-template-columns:1fr;padding:10px 10px 50px;}
    .col-left{flex-direction:column;} .charts-grid{grid-template-columns:1fr;}
    .sa-nav{padding:0 12px;} .page-title{font-size:15px;}
    .ag{grid-template-columns:minmax(0,2fr) 65px 52px;}
    .ag span:nth-child(3),.ag span:nth-child(4){display:none;}
    .chv-thread{max-height:280px;}
}
.bubble-wrap{display:flex;align-items:flex-start;gap:10px;margin-bottom:10px;}
.bubble-wrap.bubble-bot{flex-direction:row-reverse;}
.bbl-body{flex:1;max-width:88%;}
.bbl-who{font-size:10px;font-weight:500;color:var(--muted);margin-bottom:4px;}
.bbl-ts{font-size:9px;font-family:var(--mono);color:var(--hint);margin-top:3px;}
.bubble{padding:9px 12px;border-radius:10px;font-size:12px;line-height:1.6;word-break:break-word;}
.bubble-student{background:var(--surface-alt);border:0.5px solid var(--border-md);}
.bubble-ai{background:var(--blue-bg);border:0.5px solid rgba(55,138,221,.18);}
</style>

<div class="sa">

<div class="sa-nav">
    <div class="sa-nav-left">
        <a class="back-btn" href="javascript:void(0)" onclick="goBack()">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 3L5 8l5 5"/></svg>
            Overview
        </a>
        <div class="sa-avatar"><?php echo $initials ?: 'ST'; ?></div>
        <div>
            <div class="page-title"><?php echo s($name); ?></div>
            <div class="page-sub">Student ID <?php echo (int)$studentid; ?> · Teacher View</div>
        </div>
        <span class="grade-pill" id="gradePill"></span>
    </div>
    <div class="sa-nav-right">
        <span class="badge bb">Course <?php echo (int)$courseid; ?></span>
        <span class="nav-ts" id="updatedAt"></span>
    </div>
</div>

<div class="sa-body">

    <!-- LEFT -->
    <div class="col-left">
        <div class="pi-card" id="piCard"><div class="loading"><div class="spinner"></div><span>Computing index…</span></div></div>
        <div class="metrics-grid" id="metricsList"><div class="metric metric-full"><div class="loading"><div class="spinner"></div></div></div></div>
        <div class="card">
            <div class="card-title">🎯 Sections to focus on</div>
            <div id="weakSections"><div class="loading"><div class="spinner"></div></div></div>
        </div>
        <div class="card">
            <div class="card-title">💡 Key insights</div>
            <div id="insightsPanel"><div class="loading"><div class="spinner"></div></div></div>
        </div>
    </div>

    <!-- CENTRE -->
    <div class="col-mid">

        <div>
            <div class="sh">Performance overview</div>
            <div class="charts-grid">
                <div class="card"><div class="card-title">📈 Accuracy over time</div><div class="ch180"><canvas id="trendChart"></canvas></div></div>
                <div class="card"><div class="card-title">🕸 Unit mastery radar</div><div class="ch180"><canvas id="radarChart"></canvas></div></div>
                <div class="card"><div class="card-title">📊 Attempts — unit × difficulty</div><div class="ch180"><canvas id="attemptsChart"></canvas></div></div>
                <div class="card"><div class="card-title">🍩 Section distribution</div><div class="ch180"><canvas id="donutChart"></canvas></div></div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">🌡 Score heatmap — unit × difficulty</div>
            <div id="heatmapWrap"><div class="loading"><div class="spinner"></div></div></div>
        </div>

        <div class="card">
            <div class="card-title">🔍 Topic drill-down</div>
            <div class="filters">
                <select id="unitFilter" onchange="filterSections()">
                    <option value="ALL">All Units</option>
                    <option value="UNIT IV">Unit IV — Trees</option>
                    <option value="UNIT V">Unit V — Graphs</option>
                    <option value="UNIT VI">Unit VI — Python &amp; Testing</option>
                </select>
                <select id="statusFilter" onchange="filterSections()">
                    <option value="ALL">All Status</option>
                    <option value="Weak">Weak (&lt;40%)</option>
                    <option value="Average">Average (40–70%)</option>
                    <option value="Strong">Strong (&gt;70%)</option>
                    <option value="Not Attempted">Not Attempted</option>
                </select>
                <select id="sortFilter" onchange="filterSections()">
                    <option value="default">Default order</option>
                    <option value="asc">Score: Low → High</option>
                    <option value="desc">Score: High → Low</option>
                    <option value="attempts">Most attempted</option>
                </select>
            </div>
            <div id="sectionTableWrap"><div class="loading"><div class="spinner"></div></div></div>
        </div>

        <div class="card">
            <div class="card-title">📋 Quiz attempt log</div>
            <div class="ag ag-head"><span>Section / Topic</span><span>Unit</span><span>Difficulty</span><span>Score</span><span>Accuracy</span></div>
            <div id="attemptLog"><div class="loading"><div class="spinner"></div></div></div>
        </div>

        <!-- ══ STUDENT CHAT VIEWER ══ -->
        <div class="chv">

            <div class="chv-header">
                <div class="chv-title">
                    
                    💬 Student AI chat history
                </div>
                <span class="chv-pill" id="chvPill">
                    <span class="spinner" style="width:9px;height:9px;display:inline-block;vertical-align:middle"></span>
                </span>
            </div>

            

            <div class="chv-thread" id="chvThread">
                <div class="loading"><div class="spinner"></div><span>Loading chat history…</span></div>
            </div>

        </div><!-- /.chv -->

    </div><!-- /col-mid -->

    <!-- RIGHT -->
    <div class="col-right">
        <div class="card">
            <div class="card-title">⚡ Strongest areas</div>
            <div id="strongSections"><div class="loading"><div class="spinner"></div></div></div>
        </div>
        <div class="card">
            <div class="card-title">📝 Teacher advice</div>
            <div id="adviceList"><div class="loading"><div class="spinner"></div></div></div>
            <textarea class="sa-textarea" id="adviceText" placeholder="Write advice for this student…"></textarea>
            <button class="sa-save-btn" onclick="saveAdvice()">Save Advice</button>
        </div>
        <div class="card">
            <div class="card-title">🔒 Difficulty lock</div>
            <div class="lock-row">
                <div><div class="lock-label" style="color:var(--strong-txt)">Easy</div><div class="lock-desc">Restrict easy-level questions</div></div>
                <label class="sa-toggle"><input type="checkbox" id="lock-easy" onchange="toggleLock('easy',this.checked)"><div class="sa-toggle-bg"></div><div class="sa-toggle-knob"></div></label>
            </div>
            <div class="lock-row">
                <div><div class="lock-label" style="color:var(--avg-txt)">Medium</div><div class="lock-desc">Restrict medium-level questions</div></div>
                <label class="sa-toggle"><input type="checkbox" id="lock-medium" onchange="toggleLock('medium',this.checked)"><div class="sa-toggle-bg"></div><div class="sa-toggle-knob"></div></label>
            </div>
            <div class="lock-row">
                <div><div class="lock-label" style="color:var(--weak-txt)">Hard</div><div class="lock-desc">Restrict hard-level questions</div></div>
                <label class="sa-toggle"><input type="checkbox" id="lock-hard" onchange="toggleLock('hard',this.checked)"><div class="sa-toggle-bg"></div><div class="sa-toggle-knob"></div></label>
            </div>
        </div>
    </div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const COURSEID  = <?php echo (int)$courseid; ?>;
const STUDENTID = <?php echo (int)$studentid; ?>;
const AJAX_URL  = M.cfg.wwwroot + '/local/automation/analytics_ajax.php';

const DEMO_TOPICS = {
  "UNIT I": {
    "1.1": "Introduction to Algorithms",
    "1.2": "Reduction Technique",
    "1.3": "Recursion Concept",
    "1.4": "Recursive Algorithms",
    "1.5": "Tower of Hanoi",
    "1.6": "Divide and Conquer",
    "1.7": "Merge Sort",
    "1.8": "Quick Sort"
  },

  "UNIT II": {
    "2.1": "Introduction to Stack",
    "2.2": "Stack Operations",
    "2.3": "Stack Overflow and Underflow",
    "2.4": "Stack Implementation (Array)",
    "2.5": "Stack Implementation (Linked List)",
    "2.6": "Applications of Stack",
    "2.7": "Expressions and Notations",
    "2.8": "Infix to Postfix Conversion",
    "2.9": "Introduction to Queue",
    "2.10": "Queue Operations",
    "2.11": "Types of Queue",
    "2.12": "Applications of Queue"
  },

  "UNIT III": {
    "3.1": "Introduction to Linked List",
    "3.2": "Advantages and Disadvantages",
    "3.3": "Types of Linked Lists",
    "3.4": "Node Structure",
    "3.5": "Creation of Linked List",
    "3.6": "Traversal of Linked List",
    "3.7": "Insertion in Linked List",
    "3.8": "Deletion in Linked List",
    "3.9": "Applications of Linked List"
  },

  "UNIT IV": {
    "4.1": "Introduction to Trees",
    "4.2": "Tree Terminology",
    "4.3": "Tree Properties",
    "4.4": "Tree Representations",
    "4.5": "Binary Trees",
    "4.6": "Types of Binary Trees",
    "4.7": "Binary Tree ADT",
    "4.8": "Properties of Binary Trees",
    "4.9": "Binary Tree Representation",
    "4.10": "Binary Tree Traversals",
    "4.11": "Level Order Traversal",
    "4.12": "Binary Search Trees",
    "4.13": "BST Searching",
    "4.14": "BST Insertion",
    "4.15": "BST Deletion",
    "4.16": "Height of BST"
  },

  "UNIT V": {
    "5.1": "Introduction to Graphs",
    "5.2": "Graph Terminology",
    "5.3": "Types of Graphs",
    "5.4": "Subgraphs and Paths",
    "5.5": "Degree of a Vertex",
    "5.6": "Graph ADT",
    "5.7": "Graph Representations",
    "5.8": "Adjacency Matrix",
    "5.9": "Adjacency List",
    "5.10": "Adjacency Multilist",
    "5.11": "Graph Traversal",
    "5.12": "Depth First Search",
    "5.13": "Breadth First Search",
    "5.14": "Spanning Trees",
    "5.15": "Minimum Spanning Tree",
    "5.16": "Kruskal Algorithm",
    "5.17": "Prim Algorithm",
    "5.18": "Single Source Shortest Path",
    "5.19": "All Pairs Shortest Path",
    "5.20": "Transitive Closure"
  },

  "UNIT VI": {
    "6.1": "Python Standard Library",
    "6.2": "Operating System Interface",
    "6.3": "OS Module Functions",
    "6.4": "String Pattern Matching",
    "6.5": "Regex Functions",
    "6.6": "Regex Patterns",
    "6.7": "Mathematical Functions",
    "6.8": "Internet Access",
    "6.9": "Date and Time",
    "6.10": "Calendar Module",
    "6.11": "Data Compression",
    "6.12": "Multithreading",
    "6.13": "Thread Module",
    "6.14": "Threading Module",
    "6.15": "Thread Synchronization",
    "6.16": "GUI Programming",
    "6.17": "Tkinter Widgets",
    "6.18": "Menu and Event Handling",
    "6.19": "Turtle Graphics",
    "6.20": "Turtle Operations",
    "6.21": "Software Testing",
    "6.22": "Unit Testing",
    "6.23": "Writing Test Cases"
  }
};

const UNIT_MAP={"I":"U1","II":"U2","III":"U3","IV":"U4","V":"U5","VI":"U6"};
const SECTIONS=["U1","U2","U3","U4","U5","U6"];

Chart.defaults.font.family="'DM Sans',system-ui,sans-serif";
Chart.defaults.color='#6B6B6B';

let ALL_QUIZ=[],TOPIC_DATA={},SECTION_DATA={},TREND_DATA=[],FLAT=[];

function post(action,extra={}){
    return fetch(AJAX_URL,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({action,studentid:STUDENTID,courseid:COURSEID,sesskey:M.cfg.sesskey,...extra})
    }).then(r=>r.json());
}

function fmtDate(ts){return new Date(ts*1000).toLocaleString('en-IN',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});}
function fmtTime(ts){return new Date(ts*1000).toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'});}
function fmtDay(ts){
    const d=new Date(ts*1000),t=new Date();
    if(d.toDateString()===t.toDateString())return'Today';
    const y=new Date(t);y.setDate(t.getDate()-1);
    if(d.toDateString()===y.toDateString())return'Yesterday';
    return d.toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'});
}
function mkInitials(name){const p=(name||'').trim().split(' ');return((p[0]?.[0]||'')+(p[1]?.[0]||'')).toUpperCase()||'ST';}
function escHtml(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function statusOf(p){if(p===null)return{label:"Not Attempted",cls:"bm",color:"#A8A8A8"};if(p<40)return{label:"Weak",cls:"bw",color:"#E24B4A"};if(p<70)return{label:"Average",cls:"ba",color:"#EF9F27"};return{label:"Strong",cls:"bs",color:"#639922"};}
function gradeOf(a){if(a>=90)return{l:"A+",bg:"#EAF3DE",c:"#3B6D11"};if(a>=80)return{l:"A",bg:"#EAF3DE",c:"#3B6D11"};if(a>=70)return{l:"B",bg:"#E8F2FC",c:"#185FA5"};if(a>=60)return{l:"C",bg:"#FEF3E0",c:"#854F0B"};if(a>=50)return{l:"D",bg:"#FEF3E0",c:"#854F0B"};return{l:"F",bg:"#FCEBEB",c:"#A32D2D"};}
function unitPct(u){const d=TOPIC_DATA[u],s=d.easy.s+d.medium.s+d.hard.s,t=d.easy.t+d.medium.t+d.hard.t;return t>0?Math.round(s/t*100):null;}
function scorePillCls(s,t){if(!t)return'score-mid';const p=s/t;return p>=0.7?'score-high':p>=0.4?'score-mid':'score-low';}
function diffBadge(d){const dl=(d||'').toLowerCase();return dl==='easy'?`<span class="badge bs">Easy</span>`:dl==='medium'?`<span class="badge ba">Medium</span>`:dl==='hard'?`<span class="badge bw">Hard</span>`:`<span class="badge bm">${d||'—'}</span>`;}

/* ─────────────────────────────────────────────────────────────────────────────
   resolveUnits(quiz)
   Returns an array of short unit keys (e.g. ["U4","U5","U6"]) that a quiz
   attempt should be credited to.

   Detection order:
   1. Explicit multi-unit tag in topic/unit string  → e.g. "UNIT IV, V"
   2. Multiple UNIT mentions anywhere in topic text → credit all found
   3. Section-number prefix match (4.x → U4, 5.x → U5 …)
   4. Keyword mapping per unit's topic titles
   5. Fallback: single UNIT roman numeral match (original behaviour)
───────────────────────────────────────────────────────────────────────────── */
function resolveUnits(q) {
    const text   = ((q.topic || '') + ' ' + (q.unit || '')).toLowerCase();
    const rawStr = ((q.topic || '') + ' ' + (q.unit || '')).toUpperCase();

    const found = new Set();

    // ── 1 & 2: scan for every "UNIT <roman>" occurrence in the combined string
    const unitRegex = /UNIT\s+(VI|IV|V|III|II|I)/g;
    let m;
    while ((m = unitRegex.exec(rawStr)) !== null) {
        const key = UNIT_MAP[m[1]];
        if (key) found.add(key);
    }

    // ── 3: section-number prefix  e.g. "4.1", "5.12", "6.3"
    const secRegex = /\b([4-6])\.\d+/g;
    while ((m = secRegex.exec(text)) !== null) {
        const map = {'4':'U4','5':'U5','6':'U6'};
        if (map[m[1]]) found.add(map[m[1]]);
    }

    // ── 4: keyword match against each unit's topic titles
    //       Only fires when the topic/section string itself has no unit tags
    if (found.size === 0) {
        Object.entries(DEMO_TOPICS).forEach(([unitName, secs]) => {
            const shortKey = UNIT_MAP[unitName.replace('UNIT ','').trim()] || null;
            if (!shortKey) return;
            Object.values(secs).forEach(title => {
                const fw = title.toLowerCase().split(' ')[0];
                if (fw.length > 4 && text.includes(fw)) found.add(shortKey);
                if (text.includes(title.toLowerCase()))  found.add(shortKey);
            });
        });
    }

    // ── 5: nothing matched → return empty (attempt is unit-agnostic / unknown)
    return [...found];
}

/* ─────────────────────────────────────────────────────────────────────────────
   buildState(data)
   For every quiz attempt we credit its score to EVERY resolved unit so that
   a cross-unit quiz shows up in all relevant unit panels, heatmaps, etc.
───────────────────────────────────────────────────────────────────────────── */
function buildState(data){
    ALL_QUIZ = data;

    // Initialise per-unit difficulty buckets
    SECTIONS.forEach(s => {
        TOPIC_DATA[s] = { easy:{a:0,s:0,t:0}, medium:{a:0,s:0,t:0}, hard:{a:0,s:0,t:0} };
    });

    // Initialise per-section accumulators
    Object.keys(DEMO_TOPICS).forEach(u => {
        SECTION_DATA[u] = {};
        Object.keys(DEMO_TOPICS[u]).forEach(sec => {
            SECTION_DATA[u][sec] = { attempts:0, score:0, total:0 };
        });
    });

    data.forEach(q => {
        const diff  = (q.difficulty || '').toLowerCase();
        const score = parseInt(q.score)  || 0;
        const total = parseInt(q.total)  || 0;

        // ── Credit TOPIC_DATA for every resolved unit ──────────────────────
        const units = resolveUnits(q);
        units.forEach(u => {
            if (TOPIC_DATA[u]?.[diff]) {
                TOPIC_DATA[u][diff].a++;
                TOPIC_DATA[u][diff].s += score;
                TOPIC_DATA[u][diff].t += total;
            }
        });

        // ── Credit SECTION_DATA: match against every topic title ───────────
        // This already checks all units/sections independently, so a quiz
        // whose topic text matches titles in multiple units will naturally
        // land in all of them — no change needed here, but kept explicit.
        Object.entries(DEMO_TOPICS).forEach(([unit, secs]) => {
            Object.entries(secs).forEach(([sec, title]) => {
                const tl  = (q.topic || '').toLowerCase();
                const ttl = title.toLowerCase();
                const fw  = ttl.split(' ')[0];
                if (tl.includes(ttl) || tl.includes(sec) || (fw.length > 4 && tl.includes(fw))) {
                    SECTION_DATA[unit][sec].attempts++;
                    SECTION_DATA[unit][sec].score += score;
                    SECTION_DATA[unit][sec].total += total;
                }
            });
        });

        // ── Trend data (unchanged) ─────────────────────────────────────────
        if (q.timecreated && total > 0) {
            TREND_DATA.push({
                ts:  parseInt(q.timecreated),
                pct: Math.round(score / total * 100)
            });
        }
    });

    TREND_DATA.sort((a, b) => a.ts - b.ts);
}

function buildFlat(){
    FLAT=[];
    Object.entries(DEMO_TOPICS).forEach(([unit,secs])=>{
        Object.entries(secs).forEach(([sec,title])=>{
            const d=SECTION_DATA[unit][sec],p=d.total>0?Math.round(d.score/d.total*100):null,st=statusOf(p);
            FLAT.push({unit,sec,title,...d,pct:p,status:st.label,cls:st.cls,color:st.color});
        });
    });
}

function computePI(){
    const tS=ALL_QUIZ.reduce((a,q)=>a+(parseInt(q.score)||0),0),tM=ALL_QUIZ.reduce((a,q)=>a+(parseInt(q.total)||0),0);
    const rawAcc=tM>0?tS/tM*100:0;
    const totalSec=Object.values(DEMO_TOPICS).reduce((a,u)=>a+Object.keys(u).length,0);
    const pcts=[];Object.values(SECTION_DATA).forEach(secs=>Object.values(secs).forEach(d=>{if(d.total>0)pcts.push(Math.round(d.score/d.total*100));}));
    const coverage=pcts.length/totalSec*100,mastery=pcts.length?pcts.filter(p=>p>=70).length/pcts.length*100:0;
    const r10=TREND_DATA.slice(-10).map(d=>d.pct);let consistency=100;
    if(r10.length>1){const avg=r10.reduce((a,b)=>a+b,0)/r10.length;consistency=Math.max(0,100-Math.sqrt(r10.reduce((a,v)=>a+Math.pow(v-avg,2),0)/r10.length));}
    const hT=SECTIONS.reduce((a,u)=>a+TOPIC_DATA[u].hard.t,0),hS=SECTIONS.reduce((a,u)=>a+TOPIC_DATA[u].hard.s,0);
    const hardAcc=hT>0?hS/hT*100:rawAcc*0.5;
    const first5=TREND_DATA.slice(-10,-5).map(d=>d.pct),last5=TREND_DATA.slice(-5).map(d=>d.pct);
    const avg5=a=>a.length?a.reduce((x,y)=>x+y,0)/a.length:null;
    const f5=avg5(first5),l5=avg5(last5);
    let trend=50;
    if(f5!==null&&l5!==null)trend=Math.min(100,Math.max(0,50+(l5-f5)));else if(l5!==null)trend=l5;
    const pi=rawAcc*0.30+coverage*0.20+mastery*0.20+consistency*0.15+hardAcc*0.10+trend*0.05;
    return{score:Math.round(Math.min(100,Math.max(0,pi))),factors:[
        {name:"Raw accuracy",val:Math.round(rawAcc),weight:30},
        {name:"Coverage",val:Math.round(coverage),weight:20},
        {name:"Mastery quality",val:Math.round(mastery),weight:20},
        {name:"Consistency",val:Math.round(consistency),weight:15},
        {name:"Hard accuracy",val:Math.round(hardAcc),weight:10},
        {name:"Recent trend",val:Math.round(trend),weight:5},
    ]};
}

function renderPI(){
    if(!ALL_QUIZ.length){document.getElementById('piCard').innerHTML=`<div class="card-title">🏅 Performance Index</div><p style="font-size:11px;color:var(--hint)">No quiz attempts yet.</p>`;return;}
    const{score,factors}=computePI();
    const piColor=score>=80?"#639922":score>=60?"#378ADD":score>=40?"#EF9F27":"#E24B4A";
    const piGlow=score>=80?"#C0DD97":score>=60?"#85B7EB":score>=40?"#FAC775":"#F09595";
    const piRank=score>=85?"Elite":score>=70?"Proficient":score>=55?"Developing":score>=40?"Foundational":"Needs Work";
    const piTag=score>=85?"Outstanding across all dimensions":score>=70?"Strong foundation with clear strengths":score>=55?"Good progress — focus on coverage":score>=40?"Building momentum — encourage more practice":"Student needs more quiz attempts";
    const R=28,C=2*Math.PI*R,dash=Math.round(C*score/100);
    document.getElementById('piCard').style.setProperty('--pi-glow',piGlow);
    document.getElementById('piCard').innerHTML=`
        <div class="card-title">🏅 Performance Index</div>
        <div class="pi-top">
            <div class="pi-ring-wrap">
                <svg width="72" height="72" viewBox="0 0 72 72">
                    <circle class="pi-ring-bg" cx="36" cy="36" r="${R}"/>
                    <circle class="pi-ring-fill" cx="36" cy="36" r="${R}" stroke="${piColor}" stroke-dasharray="${C}" stroke-dashoffset="${C}" id="piRingFill"/>
                </svg>
                <div class="pi-ring-label">
                    <span class="pi-score-num" style="color:${piColor}">${score}</span>
                    <span class="pi-score-denom">/ 100</span>
                </div>
            </div>
            <div class="pi-right">
                <div class="pi-label">Performance Index</div>
                <div class="pi-rank" style="color:${piColor}">${piRank}</div>
                <div class="pi-tagline">${piTag}</div>
            </div>
        </div>
        <div class="pi-breakdown">
            ${factors.map(f=>`
            <div class="pi-factor">
                <span class="pi-fname">${f.name} <span style="color:var(--hint);font-size:9px">(×${f.weight}%)</span></span>
                <span class="pi-fval" style="color:${f.val>=70?'#639922':f.val>=40?'#EF9F27':'#E24B4A'}">${f.val}%</span>
                <div class="pi-fbar"><div class="pi-fbar-fill" style="width:0%;background:${piColor}" data-w="${f.val}"></div></div>
            </div>`).join('')}
        </div>`;
    requestAnimationFrame(()=>requestAnimationFrame(()=>{
        const ring=document.getElementById('piRingFill');
        if(ring)ring.style.strokeDashoffset=C-dash;
        document.querySelectorAll('.pi-fbar-fill').forEach(el=>{el.style.width=el.dataset.w+'%';});
    }));
}

function renderMetrics(){
    const tS=ALL_QUIZ.reduce((a,q)=>a+(parseInt(q.score)||0),0),tM=ALL_QUIZ.reduce((a,q)=>a+(parseInt(q.total)||0),0);
    const acc=tM>0?Math.round(tS/tM*100):0;
    const totalSec=Object.values(DEMO_TOPICS).reduce((a,u)=>a+Object.keys(u).length,0);
    const pcts=[];Object.values(SECTION_DATA).forEach(secs=>Object.values(secs).forEach(d=>{if(d.total>0)pcts.push(Math.round(d.score/d.total*100));}));
    const wk=pcts.filter(p=>p<40).length,sk=pcts.filter(p=>p>=70).length;
    const ds=new Set(TREND_DATA.map(d=>new Date(d.ts*1000).toDateString()));
    let streak=0,day=new Date();
    for(let i=0;i<90;i++){if(ds.has(day.toDateString())){streak++;day.setDate(day.getDate()-1);}else if(i===0)day.setDate(day.getDate()-1);else break;}
    const hT=SECTIONS.reduce((a,u)=>a+TOPIC_DATA[u].hard.t,0),hS=SECTIONS.reduce((a,u)=>a+TOPIC_DATA[u].hard.s,0);
    const hAcc=hT>0?Math.round(hS/hT*100):null;
    const g=gradeOf(acc);
    document.getElementById('gradePill').textContent=`Grade ${g.l}`;
    document.getElementById('gradePill').style.cssText=`background:${g.bg};color:${g.c};border:.5px solid ${g.c}44;font-size:12px;font-weight:600;padding:3px 13px;border-radius:20px;`;
    document.getElementById('updatedAt').textContent='Updated '+new Date().toLocaleTimeString();
    const acOf=a=>a>=70?"#639922":a>=40?"#EF9F27":"#E24B4A";
    const kpis=[
        {icon:"🎯",label:"Overall Accuracy",val:acc+"%",sub:tS+"/"+tM+" marks",accent:acOf(acc),full:true},
        {icon:"📚",label:"Total Attempts",val:ALL_QUIZ.length,sub:"quizzes taken",accent:"#7F77DD"},
        {icon:"🗺",label:"Coverage",val:pcts.length+"/"+totalSec,sub:"sections attempted",accent:"#378ADD"},
        {icon:"🔥",label:"Study Streak",val:streak+"d",sub:"consecutive days",accent:"#EF9F27"},
        {icon:"📉",label:"Weak Sections",val:wk,sub:"below 40%",accent:"#E24B4A"},
        {icon:"⭐",label:"Strong Sections",val:sk,sub:"above 70%",accent:"#639922"},
        {icon:"💪",label:"Hard Accuracy",val:hAcc!==null?hAcc+"%":"—",sub:hT>0?hS+"/"+hT+" marks":"none attempted",accent:"#EF9F27"},
    ];
    document.getElementById('metricsList').innerHTML=kpis.map((k,i)=>`
        <div class="metric fi${k.full?' metric-full':''}" style="--accent:${k.accent};animation-delay:${i*40}ms">
            <span class="metric-icon">${k.icon}</span>
            <div class="metric-label">${k.label}</div>
            <div class="metric-val" style="color:${k.accent}">${k.val}</div>
            <div class="metric-sub">${k.sub}</div>
        </div>`).join('');
}

function renderCharts(){
    const e=SECTIONS.map(s=>TOPIC_DATA[s].easy.a),m=SECTIONS.map(s=>TOPIC_DATA[s].medium.a),h=SECTIONS.map(s=>TOPIC_DATA[s].hard.a);
    new Chart(document.getElementById('attemptsChart'),{type:'bar',data:{labels:SECTIONS,datasets:[{label:"Easy",data:e,backgroundColor:"#C0DD97",borderColor:"#639922",borderWidth:1,borderRadius:4},{label:"Medium",data:m,backgroundColor:"#FAC775",borderColor:"#EF9F27",borderWidth:1,borderRadius:4},{label:"Hard",data:h,backgroundColor:"#F09595",borderColor:"#E24B4A",borderWidth:1,borderRadius:4}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:true,position:"top",labels:{boxWidth:8,padding:8,font:{size:10}}},tooltip:{callbacks:{label:ctx=>`${ctx.dataset.label}: ${ctx.raw}`}}},scales:{x:{grid:{display:false},ticks:{font:{size:10}}},y:{beginAtZero:true,ticks:{stepSize:1,precision:0,font:{size:10}},grid:{color:"rgba(0,0,0,0.04)"}}}}});
    const tL=TREND_DATA.map(d=>new Date(d.ts*1000).toLocaleDateString("en-GB",{day:"2-digit",month:"short"})),tV=TREND_DATA.map(d=>d.pct);
    new Chart(document.getElementById('trendChart'),{type:'line',data:{labels:tL.length?tL:["No data"],datasets:[{label:"Score %",data:tV.length?tV:[0],borderColor:"#378ADD",backgroundColor:"rgba(55,138,221,0.07)",borderWidth:2,pointBackgroundColor:"#378ADD",pointRadius:3,tension:.38,fill:true}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>ctx.raw+"%"}}},scales:{x:{grid:{display:false},ticks:{maxRotation:30,maxTicksLimit:7,font:{size:10}}},y:{min:0,max:100,ticks:{callback:v=>v+"%",font:{size:10}},grid:{color:"rgba(0,0,0,0.04)"}}}}});
    const rs=SECTIONS.map(u=>unitPct(u)||0);
    new Chart(document.getElementById('radarChart'),{type:'radar',data:{labels:SECTIONS,datasets:[{label:"Mastery %",data:rs,borderColor:"#7F77DD",backgroundColor:"rgba(127,119,221,0.12)",borderWidth:2,pointBackgroundColor:"#7F77DD",pointRadius:3}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{r:{beginAtZero:true,max:100,ticks:{stepSize:25,font:{size:9},backdropColor:"transparent"},grid:{color:"rgba(0,0,0,0.07)"},angleLines:{color:"rgba(0,0,0,0.07)"},pointLabels:{font:{size:10},color:"#6B6B6B"}}}}});
    const totalSec=Object.values(DEMO_TOPICS).reduce((a,u)=>a+Object.keys(u).length,0);
    const pcts=[];Object.values(SECTION_DATA).forEach(secs=>Object.values(secs).forEach(d=>{if(d.total>0)pcts.push(Math.round(d.score/d.total*100));}));
    const d1=pcts.filter(p=>p>=70).length,d2=pcts.filter(p=>p>=40&&p<70).length,d3=pcts.filter(p=>p<40).length,d4=totalSec-pcts.length;
    new Chart(document.getElementById('donutChart'),{type:'doughnut',data:{labels:["Strong ≥70%","Average 40–70%","Weak <40%","Not attempted"],datasets:[{data:[d1,d2,d3,d4],backgroundColor:["#C0DD97","#FAC775","#F09595","#E8E6DF"],borderColor:["#639922","#EF9F27","#E24B4A","#C8C6C0"],borderWidth:1.5,hoverOffset:5}]},options:{responsive:true,maintainAspectRatio:false,cutout:"62%",plugins:{legend:{position:"bottom",labels:{boxWidth:9,padding:8,font:{size:10}}},tooltip:{callbacks:{label:ctx=>`${ctx.label}: ${ctx.raw}`}}}}});
}

function renderHeatmap(){
    const hBg=p=>p===null?"#F0EEE8":p<20?"#F7C1C1":p<40?"#F09595":p<60?"#FAC775":p<80?"#C0DD97":"#85C440";
    const hTx=p=>p===null?"#A8A8A8":p<40?"#A32D2D":p<60?"#854F0B":"#3B6D11";
    let html=`<table class="heatmap-table"><thead><tr><th style="text-align:left">Unit</th><th>Easy</th><th>Medium</th><th>Hard</th></tr></thead><tbody>`;
    SECTIONS.forEach(u=>{html+=`<tr><td class="rl">${u}</td>`;['easy','medium','hard'].forEach(d=>{const dd=TOPIC_DATA[u][d],p=dd.t>0?Math.round(dd.s/dd.t*100):null;html+=`<td style="background:${hBg(p)};color:${hTx(p)}" title="${dd.t>0?dd.s+'/'+dd.t+' marks':'Not attempted'}">${p!==null?p+'%':'—'}</td>`;});html+=`</tr>`;});
    html+=`</tbody></table><div style="display:flex;gap:5px;margin-top:9px;align-items:center;flex-wrap:wrap"><span style="font-size:9px;color:var(--hint);font-family:var(--mono);text-transform:uppercase;letter-spacing:.06em">Score</span>${[["#F09595","#A32D2D","<40%"],["#FAC775","#854F0B","40–60%"],["#C0DD97","#3B6D11","60–80%"],["#85C440","#3B6D11","80%+"],["#F0EEE8","#A8A8A8","—"]].map(([bg,c,l])=>`<span class="badge" style="background:${bg};color:${c};border:none;font-size:9px">${l}</span>`).join('')}</div>`;
    document.getElementById('heatmapWrap').innerHTML=html;
}

function renderWeakStrong(){
    const att=FLAT.filter(r=>r.pct!==null);
    const bars=arr=>!arr.length?`<p style="font-size:11px;color:var(--hint)">No data yet.</p>`:arr.map(r=>`<div class="bar-row"><div class="bar-meta"><span class="bar-name" title="${r.title}">${r.title}</span><span class="bar-pct" style="color:${r.color}">${r.pct}%</span></div><div class="bar-track"><div class="bar-fill" style="width:${r.pct}%;background:${r.color}"></div></div></div>`).join('');
    document.getElementById('weakSections').innerHTML=bars([...att].sort((a,b)=>a.pct-b.pct).slice(0,8));
    document.getElementById('strongSections').innerHTML=bars([...att].sort((a,b)=>b.pct-a.pct).slice(0,8));
}

function renderInsights(){
    const att=FLAT.filter(r=>r.pct!==null);
    const avgSec=att.length?Math.round(att.reduce((a,r)=>a+r.pct,0)/att.length):0;
    const us=SECTIONS.map(u=>({unit:u,pct:unitPct(u)})).filter(u=>u.pct!==null).sort((a,b)=>b.pct-a.pct);
    const best=us[0]||{unit:"—",pct:"—"},worst=us[us.length-1]||{unit:"—",pct:"—"};
    const hT=SECTIONS.reduce((a,u)=>a+TOPIC_DATA[u].hard.t,0),hS=SECTIONS.reduce((a,u)=>a+TOPIC_DATA[u].hard.s,0);
    const hAcc=hT>0?Math.round(hS/hT*100):null;
    const r5=TREND_DATA.slice(-5).map(d=>d.pct),tr=r5.length>=2?r5[r5.length-1]-r5[0]:0;
    const rows=[
        {val:avgSec+"%",label:"Avg section score",sub:"across attempted",color:"#7F77DD"},
        {val:best.unit,label:"Strongest unit",sub:`${best.pct}% accuracy`,color:"#639922"},
        {val:worst.unit,label:"Needs most work",sub:`${worst.pct}% accuracy`,color:"#E24B4A"},
        {val:hAcc!==null?hAcc+"%":"—",label:"Hard accuracy",sub:`${hS}/${hT} marks`,color:"#EF9F27"},
        {val:(tr>0?"↑ ":tr<0?"↓ ":"→ ")+Math.abs(Math.round(tr))+"%",label:"Recent trend",sub:"last 5 attempts",color:tr>5?"#639922":tr<-5?"#E24B4A":"#6B6B6B"},
        {val:FLAT.filter(r=>r.pct===null).length,label:"Not attempted yet",sub:"of "+FLAT.length+" total",color:"#378ADD"},
    ];
    document.getElementById('insightsPanel').innerHTML=rows.map(r=>`<div class="ins-row"><div class="ins-val" style="color:${r.color}">${r.val}</div><div><div class="ins-label">${r.label}</div><div class="ins-sub">${r.sub}</div></div></div>`).join('');
}

function filterSections(){
    const u=document.getElementById('unitFilter').value,s=document.getElementById('statusFilter').value,o=document.getElementById('sortFilter').value;
    let rows=FLAT.slice();
    if(u!=='ALL')rows=rows.filter(r=>r.unit===u);
    if(s!=='ALL')rows=rows.filter(r=>r.status===s);
    if(o==='asc')rows.sort((a,b)=>(a.pct??-1)-(b.pct??-1));
    else if(o==='desc')rows.sort((a,b)=>(b.pct??-1)-(a.pct??-1));
    else if(o==='attempts')rows.sort((a,b)=>b.attempts-a.attempts);
    if(!rows.length){document.getElementById('sectionTableWrap').innerHTML=`<p style="font-size:11px;color:var(--hint);padding:8px 0">No sections match.</p>`;return;}
    let html=`<table class="sec-table"><thead><tr><th>Sec</th><th>Title</th><th>Unit</th><th>Attempts</th><th>Score</th><th>Accuracy</th><th>Status</th></tr></thead><tbody>`;
    rows.forEach(r=>{html+=`<tr><td style="font-family:var(--mono);font-size:10px;color:var(--muted)">${r.sec}</td><td>${r.title}</td><td><span class="badge bb">${r.unit}</span></td><td style="font-family:var(--mono);font-size:11px">${r.attempts}</td><td style="font-family:var(--mono);font-size:11px">${r.score}/${r.total}</td><td><span style="font-family:var(--mono);font-size:11px;color:${r.color}">${r.pct!==null?r.pct+'%':'—'}</span><span class="mbar"><span class="mfill" style="width:${r.pct||0}%;background:${r.color}"></span></span></td><td><span class="badge ${r.cls}">${r.status}</span></td></tr>`;});
    document.getElementById('sectionTableWrap').innerHTML=html+`</tbody></table>`;
}

function renderAttemptLog(){
    if(!ALL_QUIZ.length){document.getElementById('attemptLog').innerHTML=`<p style="font-size:11px;color:var(--hint);padding:10px 0">No attempts yet.</p>`;return;}
    document.getElementById('attemptLog').innerHTML=[...ALL_QUIZ].sort((a,b)=>(parseInt(b.timecreated)||0)-(parseInt(a.timecreated)||0)).map(q=>{
        const sc=parseInt(q.score)||0,tot=parseInt(q.total)||0,pct=tot>0?Math.round(sc/tot*100):0;
        // Show all resolved units for cross-unit quizzes
        const resolvedUnits = resolveUnits(q);
        const unitDisplay = resolvedUnits.length > 0 ? resolvedUnits.join(', ') : (q.unit || '—');
        return `<div class="ag ag-row"><span style="font-size:11px">${q.topic||'—'}</span><span style="font-size:10px;color:var(--muted);font-family:var(--mono)">${unitDisplay}</span><span>${diffBadge(q.difficulty)}</span><span class="score-pill ${scorePillCls(sc,tot)}">${sc}/${tot}</span><span style="font-family:var(--mono);font-size:11px;color:${statusOf(pct).color}">${pct}%</span></div>`;
    }).join('');
}

function loadAdvice(){
    post('get_advice').then(data=>{
        const list=document.getElementById('adviceList');
        if(!data||!data.length){list.innerHTML=`<p style="font-size:11px;color:var(--hint)">No advice added yet.</p>`;return;}
        list.innerHTML=data.map(a=>`<div class="advice-item">${a.advice}<div class="advice-ts">${fmtDate(a.timecreated)}</div></div>`).join('');
    }).catch(()=>{document.getElementById('adviceList').innerHTML=`<p style="font-size:11px;color:var(--hint)">No advice yet.</p>`;});
}
function saveAdvice(){
    const ta=document.getElementById('adviceText'),text=ta.value.trim();
    if(!text){ta.focus();return;}
    post('save_advice',{advice:text}).then(()=>{ta.value='';loadAdvice();});
}

function loadLocks(){
    post('get_locks').then(data=>{
        (data||[]).forEach(l=>{const cb=document.getElementById(`lock-${l.difficulty}`);if(cb)cb.checked=parseInt(l.locked)===1;});
    }).catch();
}
function toggleLock(difficulty,isLocked){post('toggle_lock',{difficulty,locked:isLocked?1:0}).then();}

/* ══════════════════════════════════════
   CHAT VIEWER
══════════════════════════════════════ */
let ALL_CHATS=[], CHV_FILTER='ALL';

function setChvFilter(f,el){
    CHV_FILTER=f;
    document.querySelectorAll('.chv-chip').forEach(c=>c.classList.remove('active'));
    el.classList.add('active');
    renderChvThread();
}

function renderChvThread(){
    const thread = document.getElementById('chvThread');
    const pill   = document.getElementById('chvPill');
    const n      = ALL_CHATS.length;
    pill.textContent = n + (n === 1 ? ' chat' : ' chats');

    if (!n) {
        thread.innerHTML = `<div class="chv-empty"><div class="chv-empty-icon">💬</div><p>No chats yet.</p></div>`;
        return;
    }

    const seenDays = [];
    let html = '';

    ALL_CHATS.forEach(c => {
        const ts      = parseInt(c.timecreated) || 0;
        const day     = ts ? fmtDay(ts) : '';
        const time    = ts ? fmtTime(ts) : '';
        const msg     = escHtml(c.question || c.message || '—');
        const sender  = (c.sender || '').toLowerCase();
        const isBot   = sender === 'bot' || sender === 'chatbot' || sender === 'assistant' || sender === 'ai';
        const label   = isBot ? 'Chatbot' : 'Student';
        const av      = isBot ? 'AI' : mkInitials(c.sender_name || c.username || '');

        if (day && !seenDays.includes(day)) {
            seenDays.push(day);
            html += `<div class="chv-day"><span>${day}</span></div>`;
        }

        html += `
        <div class="bubble-wrap${isBot ? ' bubble-bot' : ''}">
            <div class="chv-av" style="${isBot
                ? 'background:var(--strong-bg);color:var(--strong-txt)'
                : 'background:var(--blue-bg);color:var(--blue-txt)'
            }">${av}</div>
            <div class="bbl-body">
                <div class="bbl-who" style="${isBot ? 'text-align:right' : ''}">${label}</div>
                <div class="bubble ${isBot ? 'bubble-ai' : 'bubble-student'}">${msg}</div>
                <div class="bbl-ts" style="${isBot ? 'text-align:right' : ''}">${time}</div>
            </div>
        </div>`;
    });

    thread.innerHTML = html;
    thread.scrollTop = thread.scrollHeight;
}

function loadChats(){
    post('get_student_chat').then(data=>{
        ALL_CHATS=(data||[]).slice().sort((a,b)=>(parseInt(a.timecreated)||0)-(parseInt(b.timecreated)||0));
        renderChvThread();
    }).catch(()=>{
        document.getElementById('chvThread').innerHTML=`<p style="font-size:11px;color:var(--hint);padding:14px 0">Could not load chat history.</p>`;
        document.getElementById('chvPill').textContent='—';
    });
}

function goBack(){window.location.href=M.cfg.wwwroot+'/local/automation/analytics_overview.php';}

/* BOOT */
post('get_student_quiz').then(data=>{
    buildState(data);
    buildFlat();
    renderMetrics();
    renderCharts();
    renderHeatmap();
    renderPI();
    renderWeakStrong();
    renderInsights();
    filterSections();
    renderAttemptLog();
    loadAdvice();
    loadLocks();
    loadChats();
});
</script>

<?php echo $OUTPUT->footer(); ?>