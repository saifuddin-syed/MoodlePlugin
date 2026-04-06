<?php
require_once('../../config.php');

$courseid = required_param('courseid', PARAM_INT);

// MUST be first
require_login($courseid);

$context = context_course::instance($courseid);

$PAGE->set_url('/local/automation/student_quiz.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Mini Quiz');
$PAGE->set_heading('Mini Quiz');
$PAGE->set_pagelayout('standard');

$PAGE->requires->js_call_amd(
    'local_automation/student_quiz',
    'init',
    [
        'courseid' => $courseid
    ]
);

// Only now start output
echo $OUTPUT->header();
?>

<div class="quiz-page-wrapper">
    <div class="quiz-card">

        <h2>Generate Practice Quiz</h2>

        <div class="quiz-controls">

            <label><strong>Select Units & Sections</strong></label>
            <div id="unitContainer"></div>

            <div class="quiz-settings">
                <div class="setting-block">
                    <label>Number of Questions</label><br>
                    <input type="number" id="questionCount" min="1" placeholder="Enter number">
                </div>

                <div class="setting-block">
                    <label>Difficulty</label><br>
                    <select id="difficulty">
                        <option value="easy">Easy</option>
                        <option value="medium">Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
            </div>

            <button id="generateQuizBtn">Generate Quiz</button>

        </div>

        <div id="quizContainer"></div>

    </div>
</div>

<script>

const courseid = <?php echo (int)$courseid; ?>;
const userid = <?php echo (int)$USER->id; ?>;

/* ================= LOAD LOCKS ================= */

function loadLocks(){

    fetch(M.cfg.wwwroot + "/local/automation/analytics_ajax.php",{
        method:"POST",
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({
            action:'get_locks',
            studentid:userid,
            courseid:courseid,
            sesskey:M.cfg.sesskey
        })
    })
    .then(r=>r.json())
    .then(data=>{

        let select = document.getElementById("difficulty");

        data.forEach(l=>{

            if(l.locked == 1){

                let option = select.querySelector(`option[value="${l.difficulty}"]`);

                if(option){
                    option.disabled = true;
                    option.text = option.text + " 🔒";
                }
            }

        });

    });

}

/* ================= GENERATE QUIZ ================= */

document.getElementById("generateQuizBtn").addEventListener("click", function(){

    let difficulty = document.getElementById("difficulty").value;
    let count = document.getElementById("questionCount").value;

    if(!count){
        alert("Enter number of questions");
        return;
    }

    fetch(M.cfg.wwwroot + "/local/automation/student_ajax.php",{
        method:"POST",
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({
            action:'generate_quiz',
            courseid:courseid,
            difficulty:difficulty,
            count:count,
            sesskey:M.cfg.sesskey
        })
    })
    .then(r=>r.json())
    .then(res=>{

        if(res.error){
            alert(res.message); // 🔒 backend protection
            return;
        }

        document.getElementById("quizContainer").innerHTML = res.html;

    });

});

/* ================= INIT ================= */

loadLocks();

</script>

<?php
echo $OUTPUT->footer();