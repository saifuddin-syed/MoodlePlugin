<?php
require_once('../../config.php');

$courseid = required_param('courseid', PARAM_INT);
require_login($courseid);

$context = context_course::instance($courseid);
require_sesskey();

global $PAGE, $OUTPUT;

$courseid = required_param('courseid', PARAM_INT);
$total = required_param('totalquestions', PARAM_INT);
$difficulty = required_param('difficulty', PARAM_ALPHA);
$quizjson = optional_param('quizjson', '', PARAM_RAW);
$topics = optional_param_array('topics', [], PARAM_TEXT);

$quizData = null;

if (isset($_SESSION['ai_quiz_data'])) {
    $quizData = $_SESSION['ai_quiz_data'];
} elseif (!empty($quizjson)) {
    $quizData = json_decode($quizjson, true);
}

if (!$quizData) {
    die("Invalid quiz data.");
}

// Store answer key in session
$_SESSION['ai_quiz_data'] = $quizData;

$context = context_course::instance($courseid);

$PAGE->set_context($context);

$PAGE->set_url('/local/automation/student_quiz_attempt.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Quiz Attempt');
$PAGE->set_heading('Quiz Attempt');
$PAGE->set_pagelayout('popup'); // cleaner layout
$PAGE->requires->css('/local/automation/style/chatbot.css');

echo $OUTPUT->header();
?>

<div class="exam-wrapper">

    <div class="exam-header">
        <h2>Quiz Attempt</h2>
        <div id="timer" class="exam-timer"></div>
    </div>

    <form id="quizForm" method="POST" action="student_quiz_submit.php">

        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
        <input type="hidden" name="difficulty" value="<?php echo s($difficulty); ?>">
        <input type="hidden" name="totalquestions" value="<?php echo $total; ?>">

        <?php foreach ($topics as $t): ?>
            <input type="hidden" name="topics[]" value="<?php echo s($t); ?>">
        <?php endforeach; ?>

        <?php
        // If sections are POSTed to this page then include them as hidden fields for final submit.
        $sections_posted = optional_param_array('sections', [], PARAM_RAW);
        if (!empty($sections_posted)) {
            foreach ($sections_posted as $sec) {
                // sec is expected in format UNIT|SECTION (as produced by student_quiz.js)
                echo '<input type="hidden" name="sections[]" value="' . s($sec) . '">';
            }
        }
        ?>

        <?php foreach ($quizData as $index => $q): ?>

            <div class="question-card">
                <div class="question-title">
                    Q<?php echo $index + 1; ?>.
                </div>

                <div class="question-text">
                    <?php echo s($q['question']); ?>
                </div>

                <div class="options-group">
                    <?php foreach ($q['options'] as $optIndex => $option): ?>
                        <label class="option-item">
                            <input type="radio"
                                   name="q<?php echo $index; ?>"
                                   value="<?php echo $optIndex; ?>">
                            <span><?php echo s($option); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php endforeach; ?>

        <div class="submit-wrapper">
            <button type="submit" class="submit-btn">
                Submit Quiz
            </button>
        </div>

    </form>
</div>

<script>
let totalTime = <?php
    if ($difficulty === 'easy') echo $total * 60;
    elseif ($difficulty === 'medium') echo $total * 120;
    else echo $total * 150;
?>;

const timerElement = document.getElementById('timer');

function updateTimer() {

    let minutes = Math.floor(totalTime / 60);
    let seconds = totalTime % 60;

    timerElement.textContent =
        String(minutes).padStart(2, '0') + ':' +
        String(seconds).padStart(2, '0');

    if (totalTime <= 0) {
        alert('Time is up!');
        document.getElementById('quizForm').submit();
    } else {
        totalTime--;
        setTimeout(updateTimer, 1000);
    }
}

updateTimer();

// Disable copy
document.addEventListener('copy', e => e.preventDefault());
document.addEventListener('contextmenu', e => e.preventDefault());

// Prevent back
history.pushState(null, null, location.href);
window.onpopstate = function () {
    history.go(1);
};

document.getElementById('quizForm').addEventListener('submit', function() {
    sessionStorage.setItem('from_quiz_attempt', '1');
});
</script>

<?php
echo $OUTPUT->footer();