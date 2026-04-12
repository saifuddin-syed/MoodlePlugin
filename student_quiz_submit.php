<?php
require_once('../../config.php');

require_login();
require_sesskey();

global $DB, $USER;

// inputs
$courseid   = required_param('courseid', PARAM_INT);
$total      = required_param('totalquestions', PARAM_INT);
$difficulty = required_param('difficulty', PARAM_ALPHA);
$topics     = optional_param_array('topics', [], PARAM_TEXT); // units
$sections   = optional_param_array('sections', [], PARAM_RAW); // we expect "UNIT|SECTION" strings

// Quiz data: prefer session, but if not present, also support POSTed quizjson as fallback
$quizData = null;
if (isset($_SESSION['ai_quiz_data'])) {
    $quizData = $_SESSION['ai_quiz_data'];
} else {
    // fallback: if quizjson posted (when not using session)
    $quizjson = optional_param('quizjson', '', PARAM_RAW);
    if ($quizjson) {
        $quizData = json_decode($quizjson, true);
    }
}

if (!$quizData) {
    redirect(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        "Quiz session expired or quiz data missing."
    );
}

// Determine wrong questions
$score = 0;
$wrong_questions = [];

for ($i = 0; $i < $total; $i++) {

    $studentAnswer = optional_param("q$i", '', PARAM_INT);

    if (!isset($quizData[$i])) {
        continue;
    }

    $correctAnswer = isset($quizData[$i]['answer_index']) ? (int)$quizData[$i]['answer_index'] : null;

    if ($correctAnswer === null) {
        continue;
    }

    if ((int)$studentAnswer === (int)$correctAnswer) {
        $score++;
    } else {
        // collect the question text so we can request tailored advice
        $qtext = isset($quizData[$i]['question']) ? $quizData[$i]['question'] : '';
        // optionally include the correct option text and student's selection for better context
        $correct_option = '';
        if (isset($quizData[$i]['options']) && isset($quizData[$i]['options'][$correctAnswer])) {
            $correct_option = $quizData[$i]['options'][$correctAnswer];
        }
        $wrong_questions[] = [
            'question' => $qtext,
            'correct_answer_text' => $correct_option
        ];
    }
}

// Clear session quiz (if used)
if (isset($_SESSION['ai_quiz_data'])) {
    unset($_SESSION['ai_quiz_data']);
}

// Build a tidy topic string: "UNIT 1: 1.1,1.2; UNIT 3: 3.1"
$topic_map = []; // unit => [section, section, ...]
foreach ($sections as $s) {
    // expected "UNIT 1|1.2" (from student_quiz.js hidden inputs)
    $parts = explode('|', $s);
    if (count($parts) == 2) {
        $unit = trim($parts[0]);
        $section = trim($parts[1]);
        if (!isset($topic_map[$unit])) $topic_map[$unit] = [];
        if (!in_array($section, $topic_map[$unit])) $topic_map[$unit][] = $section;
    }
}

// fallback if no sections passed but topics (units) are present, mark unit only
if (empty($topic_map) && !empty($topics)) {
    foreach ($topics as $u) {
        if (!isset($topic_map[$u])) $topic_map[$u] = [];
    }
}

// Format topicstring
$parts = [];
foreach ($topic_map as $unit => $secs) {
    if (!empty($secs)) {
        $parts[] = $unit . ': ' . implode(',', $secs);
    } else {
        $parts[] = $unit;
    }
}
$topicstring = !empty($parts) ? implode(' ; ', $parts) : 'General';

// ---------- Request a short recommendation from FastAPI (Groq) ----------
$recommendation = '';

// Only call if there are wrong questions
if (!empty($wrong_questions)) {

    // Prepare payload (only include question texts to keep payload small)
    $wrong_texts = [];
    foreach ($wrong_questions as $w) {
        $wrong_texts[] = $w['question'] . ($w['correct_answer_text'] ? " (correct: " . $w['correct_answer_text'] . ")" : "");
    }

    $payload = json_encode([
        'wrong_questions' => $wrong_texts,
        'selected_topics' => $topic_map,
        'score' => $score,
        'total' => $total
    ]);

    // POST to FastAPI recommend endpoint
    $url = 'http://127.0.0.1:8000/recommend-quiz';
    $opts = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => $payload,
            'timeout' => 10
        ]
    ];
    $context = stream_context_create($opts);
    $result = @file_get_contents($url, false, $context);

    if ($result !== FALSE) {
        $res = json_decode($result, true);
        if ($res && isset($res['ok']) && $res['ok'] && isset($res['recommendation'])) {
            $recommendation = trim($res['recommendation']);
        }
    }
}

// Fallback recommendation if no result from API
if (empty($recommendation)) {
    if ($score == $total) {
        $recommendation = 'Excellent performance!';
    } elseif ($score >= ($total / 2)) {
        $recommendation = 'Good attempt. Revise the weak sections marked above and practice similar problems.';
    } else {
        $recommendation = 'Needs improvement. Review the key concepts from the selected units and try example exercises.';
    }
}

// Save to DB
$record = new stdClass();
$record->studentid = $USER->id;
$record->courseid = $courseid;
$record->topic = $topicstring;
$record->score = $score;
$record->total = $total;
$record->difficulty = $difficulty;
$record->recommendation = $recommendation;
$record->timecreated = time();

$quizid = $DB->insert_record('local_automation_student_quiz', $record);


/* ================= SAVE QUESTION LEVEL DATA ================= */

foreach ($quizData as $index => $q) {

    if (!isset($quizData[$index])) continue;

    $studentAnswer = optional_param("q$index", '', PARAM_INT);
    $correctAnswer = isset($q['answer_index']) ? (int)$q['answer_index'] : null;

    if ($correctAnswer === null) continue;

    // Score per question
    $isCorrect = ((int)$studentAnswer === (int)$correctAnswer) ? 1 : 0;

    // Question text
    $questiontext = isset($q['question']) ? $q['question'] : '';

    // Default values
    $unit = isset($q['unit']) ? $q['unit'] : 'General';
    $topic = isset($q['topic']) ? $q['topic'] : 'General';


    // Prepare record
    $qrecord = new stdClass();
    $qrecord->quizattemptid = $quizid;
    $qrecord->studentid = $USER->id;
    $qrecord->courseid = $courseid;

    $qrecord->questionid = $index;
    $qrecord->questiontext = $questiontext;
    $qrecord->topic = $topic;
    $qrecord->unit = $unit;

    $qrecord->score = $isCorrect;
    $qrecord->maxscore = 1;

    $qrecord->timecreated = time();

    // Insert into new table
    $DB->insert_record('local_automation_quiz_questions', $qrecord);
}

// Build chat message summary
$chatMessage = 
    "📘 <strong>Quiz ID:</strong> $quizid<br>" .
    "<strong>Score:</strong> $score / $total<br>" .
    "<strong>Recommendation:</strong> $recommendation";

$chatRecord = new stdClass();
$chatRecord->studentid = $USER->id;
$chatRecord->courseid = $courseid;
$chatRecord->message = $chatMessage;
$chatRecord->sender = 'bot'; // so it appears as tutor/system
$chatRecord->timecreated = time();

$DB->insert_record('local_automation_student_chat', $chatRecord);

// redirect back to course with message
redirect(
    new moodle_url('/course/view.php', ['id' => $courseid]),
    "Quiz submitted! Score: $score / $total"
);