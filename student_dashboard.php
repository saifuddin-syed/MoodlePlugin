<?php
require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $USER, $PAGE, $OUTPUT;

$studentid = $USER->id;
$courseid = required_param('courseid', PARAM_INT);

$PAGE->set_url('/local/automation/student_dashboard.php', ['courseid' => $courseid]);
$PAGE->set_title('Student Dashboard');
$PAGE->set_heading('Student Dashboard');

echo $OUTPUT->header();

/* ============================
   FETCH DATA
============================ */

$quizdata = $DB->get_records('local_automation_student_quiz', [
    'studentid' => $studentid,
    'courseid' => $courseid
]);

$questions = $DB->get_records('local_automation_quiz_questions', [
    'studentid' => $studentid,
    'courseid' => $courseid
]);

$locks = $DB->get_records('local_automation_quiz_lock', [
    'studentid' => $studentid,
    'courseid' => $courseid
]);

/* ============================
   BASIC METRICS
============================ */

$total_score = 0;
$total_marks = 0;

foreach ($quizdata as $q) {
    $total_score += $q->score;
    $total_marks += $q->total;
}

$percentage = $total_marks > 0 ? round(($total_score / $total_marks) * 100, 2) : 0;

/* ============================
   TOPIC + UNIT ANALYSIS
============================ */

$topics = [];
$units = [];

foreach ($questions as $q) {

    if (!isset($topics[$q->topic])) {
        $topics[$q->topic] = ['score'=>0,'total'=>0];
    }
    $topics[$q->topic]['score'] += $q->score;
    $topics[$q->topic]['total'] += $q->maxscore;

    if (!isset($units[$q->unit])) {
        $units[$q->unit] = ['score'=>0,'total'=>0];
    }
    $units[$q->unit]['score'] += $q->score;
    $units[$q->unit]['total'] += $q->maxscore;
}

/* ============================
   PERFORMANCE INDEX
============================ */

$accuracy = $percentage;
$attempts = count($quizdata);
$consistency = min(100, $attempts * 10);

$total_topics = count($topics);
$covered_topics = count(array_filter($topics, fn($t)=>$t['total']>0));
$coverage = $total_topics ? ($covered_topics/$total_topics)*100 : 0;

$performance_index = round(
    (0.6*$accuracy)+(0.2*$consistency)+(0.2*$coverage),2
);

/* ============================
   TOPIC STATUS
============================ */

function get_status($p){
    if($p<40) return ["Weak","#e74c3c"];
    if($p<70) return ["Average","#f39c12"];
    return ["Strong","#2ecc71"];
}

/* ============================
   WEAK / STRONG TOPICS
============================ */

$topic_percent=[];

foreach($topics as $t=>$d){
    $topic_percent[$t]=($d['total']>0)?($d['score']/$d['total'])*100:0;
}

asort($topic_percent);
$weak_topics=array_slice($topic_percent,0,5,true);

arsort($topic_percent);
$strong_topics=array_slice($topic_percent,0,5,true);

/* ============================
   INSIGHTS
============================ */

$weak_count=0;$strong_count=0;

foreach($topic_percent as $p){
    if($p<40) $weak_count++;
    if($p>70) $strong_count++;
}

/* ============================
   RECOMMENDATION
============================ */

$recommendation="";

if($weak_topics){
    $recommendation.="Focus on: ".implode(", ",array_keys($weak_topics)).". ";
}
if($strong_topics){
    $recommendation.="Strong in: ".implode(", ",array_keys($strong_topics)).". Try higher difficulty.";
}
if(!$recommendation){
    $recommendation="Keep practicing consistently.";
}

/* ============================
   FETCH API TOPICS
============================ */

$api_topics=[];
$res=@file_get_contents("http://127.0.0.1:8000/topics");
if($res) $api_topics=json_decode($res,true);

/* ============================
   UI
============================ */

echo "
<style>
.dashboard{max-width:1200px;margin:auto;font-family:Segoe UI;}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:15px;}
.card{background:#fff;padding:20px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.08);}
.big{font-size:28px;font-weight:bold;}
.progress{height:8px;background:#eee;border-radius:5px;margin-top:5px;}
.bar{height:8px;border-radius:5px;}
.row{display:flex;justify-content:space-between;margin:6px 0;}
.weak{color:red;}
</style>

<div class='dashboard'>
";

/* ============================
   TOP CARDS
============================ */

echo "<div class='grid'>
<div class='card'><h4>Performance Index</h4><div class='big'>$performance_index</div></div>
<div class='card'><h4>Accuracy</h4><div class='big'>$percentage%</div></div>
<div class='card'><h4>Attempts</h4><div class='big'>$attempts</div></div>
<div class='card'><h4>Coverage</h4><div class='big'>".round($coverage,2)."%</div></div>
</div>";

/* ============================
   TOPIC STATUS
============================ */

echo "<div class='card'><h3>Topic Status</h3>";

foreach($topics as $t=>$d){
    $p=($d['total']>0)?($d['score']/$d['total'])*100:0;
    list($s,$c)=get_status($p);
    $p=round($p,2);

    echo "<div class='row'><span>$t</span><span style='color:$c'>$s ($p%)</span></div>
    <div class='progress'><div class='bar' style='width:$p%;background:$c'></div></div>";
}
echo "</div>";

/* ============================
   WEAK UNITS
============================ */

echo "<div class='card'><h3>Weak Units</h3>";
foreach($units as $u=>$d){
    $p=($d['total']>0)?($d['score']/$d['total'])*100:0;
    if($p<50){
        echo "<p class='weak'>$u → ".round($p,2)."%</p>";
    }
}
echo "</div>";

/* ============================
   TOP 5 WEAK
============================ */

echo "<div class='card'><h3>Top 5 Weakest Topics</h3>";
foreach($weak_topics as $t=>$p){
    $p=round($p,2);
    echo "<p class='weak'>$t → $p%</p>
    <div class='progress'><div class='bar' style='width:$p%;background:red'></div></div>";
}
echo "</div>";

/* ============================
   TOP 5 STRONG
============================ */

echo "<div class='card'><h3>Top 5 Strongest Topics</h3>";
foreach($strong_topics as $t=>$p){
    $p=round($p,2);
    echo "<p>$t → $p%</p>
    <div class='progress'><div class='bar' style='width:$p%;background:green'></div></div>";
}
echo "</div>";

/* ============================
   INSIGHTS
============================ */

echo "<div class='card'>
<h3>Insights</h3>
<p>Weak Topics: $weak_count</p>
<p>Strong Topics: $strong_count</p>
<p>Performance Index: $performance_index</p>
</div>";

/* ============================
   LOCK STATUS
============================ */

echo "<div class='card'><h3>Difficulty Status</h3>";
foreach($locks as $l){
    echo "<p>{$l->difficulty} → ".($l->locked?"🔒 Locked":"✅ Unlocked")."</p>";
}
echo "</div>";

/* ============================
   RECOMMENDATION
============================ */

echo "<div class='card'><h3>AI Recommendation</h3><p>$recommendation</p></div>";

echo "</div>";

echo $OUTPUT->footer();