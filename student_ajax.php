<?php
require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

global $DB, $USER;

$action = required_param('action', PARAM_ALPHANUMEXT);
$courseid = required_param('courseid', PARAM_INT);

header('Content-Type: application/json');

error_log("STUDENT AJAX CALLED");

switch ($action) {

    // ===== Save chat message =====
    case 'save_message':

        $message = required_param('message', PARAM_RAW);
        $sender  = required_param('sender', PARAM_ALPHA);

        $record = new stdClass();
        $record->studentid = $USER->id;
        $record->courseid = $courseid;
        $record->message = $message;
        $record->sender = $sender;
        $record->timecreated = time();

        $DB->insert_record('local_automation_student_chat', $record);

        echo json_encode([
            'status' => 'success',
            'timecreated' => $record->timecreated
        ]);
        break;


    // ===== Fetch chat history =====
    case 'fetch_history':

        $records = $DB->get_records(
            'local_automation_student_chat',
            ['studentid' => $USER->id, 'courseid' => $courseid],
            'timecreated ASC'
        );

        echo json_encode(array_values($records));
        break;


    // ===== Insert dummy mini quiz =====
    case 'insert_dummy_quiz':

        $record = new stdClass();
        $record->studentid = $USER->id;
        $record->courseid = $courseid;
        $record->topic = 'Demo Topic';
        $record->score = rand(2, 5);
        $record->total = 5;
        $record->difficulty = 'medium';
        $record->recommendation = 'Revise core concepts.';
        $record->timecreated = time();

        $DB->insert_record('local_automation_student_quiz', $record);

        echo json_encode(['status' => 'quiz_inserted']);
        break;

    case 'ask_rag':

        $question = required_param('question', PARAM_RAW);

        // Fetch last 6 messages
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
            $role = ($rec->sender === 'user') ? 'user' : 'assistant';
            $history[] = [
                "role" => $role,
                "content" => $rec->message
            ];
        }

        $payload = json_encode([
            "question" => $question,
            "history" => $history
        ]);

        $ch = curl_init('http://127.0.0.1:8000/ask');

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        $result = curl_exec($ch);

        if ($result === false) {
            echo json_encode([
                "ok" => false,
                "error" => curl_error($ch)
            ]);
            curl_close($ch);
            break;
        }

        curl_close($ch);

        echo $result;
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
}