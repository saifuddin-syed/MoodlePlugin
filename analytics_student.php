<?php

require_once(__DIR__.'/../../config.php');
require_login();

$courseid = get_config('local_automation', 'student_ai_courseid');
$studentid = required_param('studentid', PARAM_INT);
$name = optional_param('name', 'Student', PARAM_TEXT);

if (!$courseid) {
    print_error('Student AI course not configured.');
}

$context = context_course::instance($courseid);
require_capability('moodle/course:view', $context);

$PAGE->set_url('/local/automation/analytics_student.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Student Analytics');
$PAGE->set_heading('Student Analytics');

echo $OUTPUT->header();
?>

<button onclick="goBack()" style="margin-bottom:10px;">← Back</button>

<div id="student-content"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

const courseid = <?php echo (int)$courseid; ?>;
const studentid = <?php echo (int)$studentid; ?>;
const name = "<?php echo $name; ?>";

/* ===================== SAVE ADVICE ===================== */

function saveAdvice(){

    let text = document.getElementById("adviceText").value.trim();

    if(!text){
        alert("Enter advice");
        return;
    }

    fetch(M.cfg.wwwroot + "/local/automation/analytics_ajax.php",{
        method:"POST",
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({
            action:'save_advice',
            studentid:studentid,
            courseid:courseid,
            advice:text,
            sesskey:M.cfg.sesskey
        })
    })
    .then(r=>r.json())
    .then(()=>{
        document.getElementById("adviceText").value="";
        loadAdvice(); // refresh advice list
    });
}

/* ===================== LOAD ADVICE ===================== */

function loadAdvice(){

    fetch(M.cfg.wwwroot + "/local/automation/analytics_ajax.php",{
        method:"POST",
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({
            action:'get_advice',
            studentid:studentid,
            courseid:courseid,
            sesskey:M.cfg.sesskey
        })
    })
    .then(r=>r.json())
    .then(data=>{

        let html="";

        if(data.length===0){
            html="<div>No advice yet</div>";
        }else{
            data.forEach(a=>{
                html+=`
                <div style="
                    border-left:4px solid #2196F3;
                    background:#f9f9f9;
                    padding:8px;
                    margin-bottom:6px;
                ">
                    ${a.advice}<br>
                    <small>${new Date(a.timecreated*1000).toLocaleString()}</small>
                </div>`;
            });
        }

        document.getElementById("adviceList").innerHTML=html;
    });
}

/* ===================== LOAD STUDENT DATA ===================== */

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

    const sections=["U1","U2","U3","U4","U5","U6"];

    let topics={};

    sections.forEach(s=>{
        topics[s]={
            easy:{attempts:0,score:0,total:0},
            medium:{attempts:0,score:0,total:0},
            hard:{attempts:0,score:0,total:0}
        };
    });

    data.forEach(q=>{

        let diff=q.difficulty.toLowerCase();
        let topicText=q.topic.toUpperCase();

        let matches = topicText.match(/UNIT\s+(VI|IV|V|III|II|I)/g);

        if(matches){

            matches.forEach(unit=>{

                let roman = unit.replace("UNIT","").trim();

                let map={
                    "I":"U1","II":"U2","III":"U3",
                    "IV":"U4","V":"U5","VI":"U6"
                };

                let u = map[roman];

                if(u){
                    topics[u][diff].attempts++;
                    topics[u][diff].score += parseInt(q.score);
                    topics[u][diff].total += parseInt(q.total);
                }

            });

        }

    });

    let labels=sections;
    let easy=[], medium=[], hard=[];
    let tooltipData={easy:[], medium:[], hard:[]};

    labels.forEach(t=>{
        easy.push(topics[t].easy.attempts);
        medium.push(topics[t].medium.attempts);
        hard.push(topics[t].hard.attempts);

        tooltipData.easy.push(topics[t].easy.score+"/"+topics[t].easy.total);
        tooltipData.medium.push(topics[t].medium.score+"/"+topics[t].medium.total);
        tooltipData.hard.push(topics[t].hard.score+"/"+topics[t].hard.total);
    });

    let html=`<h3>${name}</h3>

    <div style="width:100%;max-width:800px;margin-bottom:20px;">
        <canvas id="quizChart"></canvas>
    </div>

    <h4>Teacher Advice</h4>

    <!-- ✅ SHOW OLD ADVICE FIRST -->
    <div id="adviceList" style="margin-bottom:15px;"></div>

    <!-- ✅ INPUT AFTER -->
    <textarea id="adviceText" style="width:100%;height:100px;"></textarea>
    <br>
    <button onclick="saveAdvice()">Save Advice</button>

    <h4>Difficulty Lock</h4>

    <div id="lockControls">
        ${["easy","medium","hard"].map(d => `
            <div style="margin-bottom:10px;">
                <strong>${d.toUpperCase()}</strong>
                <label class="switch">
                    <input type="checkbox" onchange="toggleLock('${d}', this.checked)">
                    <span class="slider"></span>
                </label>
            </div>
        `).join("")}
    </div>

    <h4>Quiz Attempts</h4>`;

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
    html += `
    <h4>Student Chats</h4>
    <div id="chatList" style="margin-top:20px;"></div>
    `;

    document.getElementById("student-content").innerHTML=html;

    /* ===================== CHART ===================== */

    new Chart(document.getElementById('quizChart'),{
        type:'bar',
        data:{
            labels:labels,
            datasets:[
                {label:'Easy', data:easy, backgroundColor:'#4CAF50'},
                {label:'Medium', data:medium, backgroundColor:'#FFC107'},
                {label:'Hard', data:hard, backgroundColor:'#F44336'}
            ]
        },
        options:{
            plugins:{
                tooltip:{
                    callbacks:{
                        label:function(context){
                            let dataset=context.dataset.label.toLowerCase();
                            let index=context.dataIndex;
                            return dataset+" attempts: "+context.raw +
                            " | score: "+tooltipData[dataset][index];
                        }
                    }
                }
            },
            scales:{
                y:{
                    beginAtZero:true,
                    ticks:{
                        stepSize:1,
                        precision:0
                    },
                    title:{
                        display:true,
                        text:'Attempts'
                    }
                },
                x:{
                    title:{
                        display:true,
                        text:'Units'
                    }
                }
            }
        }
    });

    // ✅ IMPORTANT
    loadAdvice();
    loadLocks();
    loadChats();
});

/* ===================== NAV ===================== */
function toggleLock(difficulty, isLocked){

    fetch(M.cfg.wwwroot + "/local/automation/analytics_ajax.php",{
        method:"POST",
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({
            action:'toggle_lock',
            studentid:studentid,
            courseid:courseid,
            difficulty:difficulty,
            locked:isLocked ? 1 : 0,
            sesskey:M.cfg.sesskey
        })
    })
    .then(r=>r.json())
    .then(res=>{
        console.log("Lock updated", res);
    });

}
function loadLocks(){

    fetch(M.cfg.wwwroot + "/local/automation/analytics_ajax.php",{
        method:"POST",
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({
            action:'get_locks',
            studentid:studentid,
            courseid:courseid,
            sesskey:M.cfg.sesskey
        })
    })
    .then(r=>r.json())
    .then(data=>{

        data.forEach(l=>{
            let checkbox = document.querySelector(`input[onchange*="${l.difficulty}"]`);
            if(checkbox){
                checkbox.checked = l.locked == 1;
            }
        });

    });
}

function goBack(){
    window.location.href = M.cfg.wwwroot + "/local/automation/analytics_overview.php";
}
function loadChats(){

    fetch(M.cfg.wwwroot + "/local/automation/analytics_ajax.php",{
        method:"POST",
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({
            action:'get_student_chat',   // ✅ FIXED
            studentid:studentid,
            courseid:courseid,
            sesskey:M.cfg.sesskey
        })
    })
    .then(r=>r.json())
    .then(data=>{

        console.log("Chats:", data); // 🔥 check this

        let html="";

        if(data.length===0){
            html="<div>No chats found</div>";
        }else{
            data.forEach(c=>{
                html+=`
                    <div style="border-bottom:1px solid #ddd;padding:6px;">
                        <strong>Q:</strong> ${c.question || c.message}<br>
                        <small>${new Date(c.timecreated*1000).toLocaleString()}</small>
                    </div>
                `;
            });
        }

        document.getElementById("chatList").innerHTML = html;
    });
}
</script>

<?php
echo $OUTPUT->footer();
?>