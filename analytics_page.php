<?php

require_once(__DIR__.'/../../config.php');
require_login();

$courseid = get_config('local_automation', 'student_ai_courseid');

if (!$courseid) {
    print_error('Student AI course not configured in plugin settings.');
}

$context = context_course::instance($courseid);
require_capability('moodle/course:view', $context);

$PAGE->set_url('/local/automation/analytics_page.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Course Analytics');
$PAGE->set_heading('Course Analytics');

echo $OUTPUT->header();
?>


<div id="analytics-container">

    <!-- Student List -->
    <div id="student-list">
        <h3>Students</h3>
    </div>

    <!-- Student Details (hidden initially) -->
    <div id="student-details" style="display:none;">
        <button onclick="loadStudents()">Back</button>
        <div id="student-content"></div>
    </div>

</div>

<script>

const courseid = <?php echo (int)$courseid; ?>;

function loadStudents(){

fetch(M.cfg.wwwroot + "/local/automation/analytics_ajax.php",{
method:"POST",
headers:{'Content-Type':'application/x-www-form-urlencoded'},
body:new URLSearchParams({
action:'get_students',
courseid:courseid,
sesskey:M.cfg.sesskey
})
})
.then(r=>r.json())
.then(students=>{

let html="<h3>Students</h3>";

students.forEach(s=>{
html+=`
<div onclick="loadStudentDetails(${s.id},'${s.firstname} ${s.lastname}')"
style="cursor:pointer;padding:8px;border-bottom:1px solid #ddd;">
${s.firstname} ${s.lastname}
</div>`;
});

document.getElementById("student-list").innerHTML=html;
document.getElementById("student-list").style.display="block";
document.getElementById("student-details").style.display="none";

});

}

function loadStudentDetails(studentid,name){

document.getElementById("student-list").style.display="none";
document.getElementById("student-details").style.display="block";

fetch(M.cfg.wwwroot + "/local/automation/analytics_ajax.php",{
method:"POST",
headers:{'Content-Type':'application/x-www-form-urlencoded'},
body:new URLSearchParams({
action:'get_student_quiz',
courseid:courseid,
studentid:studentid,
sesskey:M.cfg.sesskey
})
})
.then(r=>r.json())
.then(data=>{

let html=`<h3>${name}</h3><h4>Quiz Attempts</h4>`;

if(data.length===0){
html+="<div>No quiz attempts</div>";
}else{

data.forEach(q=>{
html+=`
<div style="padding:6px;border-bottom:1px solid #eee;">
Score: ${q.score}/${q.total}<br>
Topic: ${q.topic}<br>
Difficulty: ${q.difficulty}
</div>`;
});

}

document.getElementById("student-content").innerHTML=html;

loadChat(studentid);

});

}

function loadChat(studentid){

fetch(M.cfg.wwwroot + "/local/automation/analytics_ajax.php",{
method:"POST",
headers:{'Content-Type':'application/x-www-form-urlencoded'},
body:new URLSearchParams({
action:'get_student_chat',
courseid:courseid,
studentid:studentid,
sesskey:M.cfg.sesskey
})
})
.then(r=>r.json())
.then(data=>{

let html="<h4>Chat History</h4>";

if(data.length===0){
html+="<div>No chat history</div>";
}else{

data.forEach(c=>{
html+=`<div><b>${c.sender}</b>: ${c.message}</div>`;
});

}

document.getElementById("student-content").innerHTML+=html;

});

}

loadStudents();

</script>

<?php
echo $OUTPUT->footer();