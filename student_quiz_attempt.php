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
$quizjson = required_param('quizjson', PARAM_RAW);
$topics = optional_param_array('topics', [], PARAM_TEXT);

$quizData = json_decode($quizjson, true);

if (!$quizData) {
    die("Invalid quiz data.");
}

// Store answer key in session
$_SESSION['ai_quiz_data'] = $quizData;

$context = context_course::instance($courseid);

$PAGE->set_url('/local/automation/student_quiz_attempt.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Quiz Attempt');
$PAGE->set_heading('Quiz Attempt');
$PAGE->set_pagelayout('popup'); // cleaner layout

echo $OUTPUT->header();
?>

<h3>Quiz Attempt</h3>

<div id="timer" style="position:fixed; top:20px; right:20px; font-size:18px; font-weight:bold;"></div>

<form id="quizForm" method="POST" action="student_quiz_submit.php">

<input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
<input type="hidden" name="courseid" value="<?php echo $courseid; ?>">
<input type="hidden" name="difficulty" value="<?php echo s($difficulty); ?>">
<input type="hidden" name="totalquestions" value="<?php echo $total; ?>">

<?php foreach ($topics as $t): ?>
    <input type="hidden" name="topics[]" value="<?php echo s($t); ?>">
<?php endforeach; ?>

<?php foreach ($quizData as $index => $q): ?>

    <div style="margin-bottom:20px;">
        <p><strong>Q<?php echo $index + 1; ?>:</strong>
        <?php echo s($q['question']); ?></p>

        <?php foreach ($q['options'] as $optIndex => $option): ?>
            <label>
                <input type="radio" name="q<?php echo $index; ?>" value="<?php echo $optIndex; ?>">
                <?php echo s($option); ?>
            </label><br>
        <?php endforeach; ?>
    </div>

<?php endforeach; ?>

<button type="submit">Submit Quiz</button>
</form>

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
</script>

<?php
echo $OUTPUT->footer();