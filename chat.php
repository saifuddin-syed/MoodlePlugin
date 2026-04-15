<?php

require_once(__DIR__.'/../../config.php');
require_login();

$courseid = required_param('courseid', PARAM_INT);
$studentid = required_param('studentid', PARAM_INT);

$context = context_course::instance($courseid);

$PAGE->set_context($context);

$PAGE->set_url('/local/automation/chat.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Teacher Advice');
$PAGE->set_heading('Teacher Advice');

echo $OUTPUT->header();
?>

<h3>Teacher Advice</h3>

<button onclick="goBack()">← Back</button>

<div id="adviceBox" style="
    border:1px solid #ccc;
    min-height:200px;
    padding:10px;
    margin-top:10px;
    background:#fafafa;
"></div>

<script>

const courseid = <?php echo $courseid; ?>;
const studentid = <?php echo $studentid; ?>;

/* ================= LOAD ADVICE ================= */

function loadAdvice(){

    fetch(M.cfg.wwwroot + "/local/automation/analytics_ajax.php",{
        method:"POST",
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({
            action:'get_advice',
            courseid:courseid,
            studentid:studentid,
            sesskey:M.cfg.sesskey
        })
    })
    .then(r=>r.json())
    .then(data=>{

        let html = "";

        if(data.length === 0){
            html = "<div>No advice yet</div>";
        }else{
            data.forEach(a=>{
                html += `
                <div style="
                    padding:10px;
                    margin-bottom:8px;
                    border-left:4px solid #2196F3;
                    background:#fff;
                ">
                    ${a.advice}<br>
                    <small>${new Date(a.timecreated*1000).toLocaleString()}</small>
                </div>
                `;
            });
        }

        document.getElementById("adviceBox").innerHTML = html;
    });
}

/* ================= BACK ================= */

function goBack(){
    window.history.back();
}

/* ================= INIT ================= */

loadAdvice();

// optional auto refresh
setInterval(loadAdvice, 5000);

</script>

<?php
echo $OUTPUT->footer();
?>