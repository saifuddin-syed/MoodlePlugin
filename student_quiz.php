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

// Load AMD JS
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

<?php
echo $OUTPUT->footer();