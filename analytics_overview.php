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

<h2>📊 Class Performance (Stacked Slice View)</h2>

<div style="width:100%;max-width:1100px;margin-bottom:30px;height:400px;">
    <canvas id="overallChart"></canvas>
</div>

<h3>👨‍🎓 Students</h3>
<div id="student-list"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

const courseid = <?php echo (int)$courseid; ?>;

/* ===================== LOAD BOTH DATA ===================== */

Promise.all([

    // Quiz Data
    fetch(M.cfg.wwwroot + "/local/automation/analytics_ajax.php",{
        method:"POST",
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({
            action:'get_all_students_quiz',
            courseid:courseid,
            sesskey:M.cfg.sesskey
        })
    }).then(r=>r.json()),

    // Student List (for names)
    fetch(M.cfg.wwwroot + "/local/automation/analytics_ajax.php",{
        method:"POST",
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({
            action:'get_students',
            courseid:courseid,
            sesskey:M.cfg.sesskey
        })
    }).then(r=>r.json())

])
.then(([quizData, students])=>{

const sections=["U1","U2","U3","U4","U5","U6"];
const difficulties=["easy","medium","hard"];

/* ===== MAP STUDENT ID → NAME ===== */

let studentNames = {};

students.forEach(s=>{
    studentNames[s.id] = (s.firstname + " " + s.lastname).trim();
});

/* ===== GROUP DATA ===== */

let studentsMap = {};

quizData.forEach(q=>{

    let sid = q.studentid;
    if(!sid) return;

    if(!studentsMap[sid]){
        studentsMap[sid]={
            name: studentNames[sid] || "Student",
            topics:{}
        };

        sections.forEach(s=>{
            studentsMap[sid].topics[s]={
                easy:{score:0,total:0},
                medium:{score:0,total:0},
                hard:{score:0,total:0}
            };
        });
    }

    let diff = (q.difficulty || "").toLowerCase();
    if(!["easy","medium","hard"].includes(diff)) return;

    if(!q.topic) return;

    let topicText = q.topic.toUpperCase();

    let matches = topicText.match(/UNIT\s*(VI|IV|V|III|II|I)/g);

    if(matches){

        matches.forEach(unit=>{

            let roman = unit.replace("UNIT","").trim();

            let map={
                "I":"U1","II":"U2","III":"U3",
                "IV":"U4","V":"U5","VI":"U6"
            };

            let u = map[roman];

            if(u){
                studentsMap[sid].topics[u][diff].score += parseInt(q.score) || 0;
                studentsMap[sid].topics[u][diff].total += parseInt(q.total) || 0;
            }

        });

    }

});

/* ===== PREPARE ===== */

let studentIds = Object.keys(studentsMap);

if(studentIds.length === 0){
    console.error("No data available");
    return;
}

let labels = studentIds.map(id => studentsMap[id].name);

/* ===== DATASETS ===== */

let datasets = [];

sections.forEach(unit=>{
    difficulties.forEach(diff=>{

        let dataArr = [];

        studentIds.forEach(id=>{

            let obj = studentsMap[id].topics[unit][diff];
            let percent = obj.total ? (obj.score/obj.total)*100 : 0;

            dataArr.push(Number(percent.toFixed(1)));
        });

        datasets.push({
            label: unit + " " + diff,
            data: dataArr,
            stack: 'all',
            backgroundColor:
                diff === 'easy' ? '#4CAF50' :
                diff === 'medium' ? '#FFC107' :
                '#F44336',
            borderColor:'#000',
            borderWidth:1
        });

    });
});

/* ===== LABEL PLUGIN ===== */

const labelPlugin = {
    id: 'labelPlugin',
    afterDatasetsDraw(chart) {

        const {ctx} = chart;

        chart.data.datasets.forEach((dataset, datasetIndex) => {

            const meta = chart.getDatasetMeta(datasetIndex);

            meta.data.forEach((bar, index) => {

                const value = dataset.data[index];

                if(value <= 5) return;

                ctx.save();
                ctx.fillStyle = '#000';
                ctx.font = '10px Arial';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';

                const x = bar.x;
                const y = (bar.y + bar.base) / 2;

                ctx.fillText(dataset.label, x, y);

                ctx.restore();

            });

        });

    }
};

/* ===== CREATE CHART ===== */

new Chart(document.getElementById('overallChart'),{
    type:'bar',
    data:{
        labels:labels,
        datasets:datasets
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{
            legend:{display:false},
            tooltip:{
                callbacks:{
                    label:function(context){
                        return context.dataset.label + ": " + context.raw + "%";
                    }
                }
            }
        },
        scales:{
            x:{
                stacked:true,
                ticks:{
                    autoSkip:false,
                },
                title:{
                    display:true,
                    text:'Students'
                }
            },
            y:{
                stacked:true,
                beginAtZero:true,
                title:{
                    display:true,
                    text:'Performance (%)'
                }
            }
        }
    },
    plugins:[labelPlugin]
});

/* ===================== STUDENT LIST ===================== */

let html="";

students.forEach(s=>{
    html+=`
    <div onclick="goToStudent(${s.id}, '${s.firstname} ${s.lastname}')"
    style="cursor:pointer;padding:10px;border-bottom:1px solid #ddd;">
    ${s.firstname} ${s.lastname}
    </div>`;
});

document.getElementById("student-list").innerHTML=html;

});

/* ===== NAVIGATION ===== */

function goToStudent(id, name){
    window.location.href = M.cfg.wwwroot +
    "/local/automation/analytics_student.php?studentid=" + id +
    "&name=" + encodeURIComponent(name);
}

</script>

<?php
echo $OUTPUT->footer();
?>