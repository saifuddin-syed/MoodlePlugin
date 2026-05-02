<?php
require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

global $DB, $USER;

$action   = required_param('action', PARAM_ALPHANUMEXT);
$courseid = required_param('courseid', PARAM_INT);

header('Content-Type: application/json');

// ================= RAG CONFIG =================
define('RAG_BASE', 'http://127.0.0.1:8000');

function rag_post_student(string $endpoint, array $payload): ?array {
    $ch = curl_init(RAG_BASE . $endpoint);

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
    ]);

    $raw = curl_exec($ch);
    curl_close($ch);

    if (!$raw) return null;

    return json_decode($raw, true);
}

// ================= SWITCH =================
switch ($action) {

    // ===================================================
    // GENERATE QUIZ
    // ===================================================
    case 'generate_quiz':

        $difficulty = required_param('difficulty', PARAM_TEXT);
        $count      = required_param('count', PARAM_INT);

        $lock = $DB->get_record('local_automation_quiz_lock', [
            'studentid'  => $USER->id,
            'courseid'   => $courseid,
            'difficulty' => $difficulty
        ]);

        if ($lock && $lock->locked == 1) {
            echo json_encode([
                'error' => true,
                'message' => 'This difficulty is locked by your teacher.'
            ]);
            break;
        }

        $html = '';
        for ($i = 1; $i <= $count; $i++) {
            $html .= "<div style='padding:8px;border:1px solid #ddd;margin-bottom:5px;'>
                        Question $i ($difficulty)
                      </div>";
        }

        echo json_encode(['error' => false, 'html' => $html]);
        break;


    // ===================================================
    // SAVE MESSAGE (AI CHAT)
    // ===================================================
    case 'save_message':

        $message = required_param('message', PARAM_RAW);
        $sender  = required_param('sender', PARAM_ALPHA); // student / ai

        $record = new stdClass();
        $record->studentid   = $USER->id;
        $record->courseid    = $courseid;
        $record->message     = $message;
        $record->sender      = $sender;
        $record->timecreated = time();

        $DB->insert_record('local_automation_student_chat', $record);

        echo json_encode([
            'status' => 'success',
            'timecreated' => $record->timecreated
        ]);
        break;


    // ===================================================
    // FETCH CHAT HISTORY (AI CHAT)
    // ===================================================
    case 'fetch_history':

        $records = $DB->get_records(
            'local_automation_student_chat',
            ['studentid' => $USER->id, 'courseid' => $courseid],
            'timecreated ASC'
        );

        echo json_encode(array_values($records));
        break;


    // ===================================================
    // ASK RAG (AI RESPONSE)
    // ===================================================
    case 'ask_rag':

        $question = required_param('question', PARAM_RAW);

        $historyrecords = $DB->get_records(
            'local_automation_student_chat',
            ['studentid' => $USER->id, 'courseid' => $courseid],
            'timecreated DESC',
            'id, sender, message',
            0,
            6
        );

        $history = [];

        foreach (array_reverse($historyrecords) as $rec) {
            $history[] = [
                'role'    => ($rec->sender === 'student') ? 'user' : 'assistant',
                'content' => $rec->message
            ];
        }

        $response = rag_post_student('/ask', [
            'question' => $question,
            'history'  => $history
        ]);

        if (!$response) {
            echo json_encode([
                'ok' => false,
                'error' => 'RAG server not responding'
            ]);
            break;
        }

        echo json_encode($response);
        break;


    // ===================================================
    // INSERT DUMMY QUIZ
    // ===================================================
    case 'insert_dummy_quiz':

        $record = new stdClass();
        $record->studentid   = $USER->id;
        $record->courseid    = $courseid;
        $record->topic       = 'Demo Topic';
        $record->score       = rand(2, 5);
        $record->total       = 5;
        $record->difficulty  = 'medium';
        $record->recommendation = 'Revise core concepts.';
        $record->timecreated = time();

        $DB->insert_record('local_automation_student_quiz', $record);

        echo json_encode(['status' => 'quiz_inserted']);
        break;


    // ===================================================
    // SEND STUDENT MESSAGE (TEACHER CHAT)
    // ===================================================
    case 'send_student_msg':

        $message = trim(required_param('message', PARAM_TEXT));

        if ($message === '') {
            echo json_encode(['ok' => false, 'error' => 'Message cannot be empty.']);
            break;
        }

        // RAG relevance check
        $flag = rag_post_student('/flag-message', [
            'message'   => $message
        ]);
        if (!$flag || empty($flag['ok'])) {
            echo json_encode([
                'ok' => false,
                'error' => 'Could not verify message relevance.'
            ]);
            break;
        }

        if (!$flag['relevant']) {
            echo json_encode([
                'ok' => false,
                'relevant' => false,
                'score' => $flag['score'] ?? null,
                'error' => 'Message not course-related.'
            ]);
            break;
        }

        $record = new stdClass();
        $record->studentid   = $USER->id;
        $record->teacherid   = 0;
        $record->courseid    = $courseid;
        $record->advice      = $message;
        $record->sender      = 'student';
        $record->timecreated = time();

        $id = $DB->insert_record('local_automation_advice', $record);

        echo json_encode([
            'ok' => true,
            'id' => $id,
            'score' => $flag['score'] ?? null
        ]);
        break;


    // ===================================================
    // GET TEACHER CHAT HISTORY
    // ===================================================
    case 'get_chat_history':

        $rows = $DB->get_records(
            'local_automation_advice',
            ['studentid' => $USER->id, 'courseid' => $courseid],
            'timecreated ASC'
        );

        $messages = [];

        foreach ($rows as $r) {
            $messages[] = [
                'sender'      => $r->sender,
                'message'     => $r->advice,
                'teacherid'   => (int)$r->teacherid,
                'timecreated' => (int)$r->timecreated,
            ];
        }

        echo json_encode($messages);
        break;


    // ===================================================
    default:
        echo json_encode(['error' => 'Invalid action']);
}