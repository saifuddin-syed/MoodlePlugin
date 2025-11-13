<?php
// local/automation/chatbot_endpoint.php
require_once(__DIR__ . '/../../config.php');
require_login();
require_sesskey();

header('Content-Type: application/json; charset=utf-8');

// Get parameters
$prompt = required_param('prompt', PARAM_RAW);
$mode = optional_param('mode', 'assistant', PARAM_ALPHA);

// Retrieve API key from plugin settings
$apiKey = get_config('local_automation', 'groq_api_key');
if (!$apiKey) {
    echo json_encode(['error' => 'Groq API key not configured in admin settings.']);
    exit;
}

// Define system prompts based on mode
$systemPrompts = [
    'assistant' => 'You are a helpful teaching assistant for Moodle. Respond concisely and help with teaching tasks.',
    'qb' => 'You are a question bank generator. Given a topic, create relevant questions, answers, and explanations.',
    'quiz' => 'You generate multiple-choice quiz questions for classroom assessments. 
Return questions in this exact format:
Q1. <question text>
a) <option A>
b) <option B>
c) <option C>
d) <option D>
Correct answer: <letter>

Do not include explanations or extra text.
Ensure each question and option are on separate lines.'
];


$systemPrompt = $systemPrompts[$mode] ?? $systemPrompts['assistant'];

// Prepare API payload
$payload = [
    'model' => 'llama-3.1-8b-instant',
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $prompt]
    ]
];

// Make request to Groq API
$url = 'https://api.groq.com/openai/v1/chat/completions';
$context = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n" .
                     "Authorization: Bearer {$apiKey}\r\n",
        'content' => json_encode($payload),
        'ignore_errors' => true
    ]
]);

$response = file_get_contents($url, false, $context);
if ($response === false) {
    echo json_encode(['error' => 'Failed to reach Groq API endpoint.']);
    exit;
}

$data = json_decode($response, true);
if (isset($data['choices'][0]['message']['content'])) {
    echo json_encode(['reply' => $data['choices'][0]['message']['content']]);
} else {
    echo json_encode(['error' => 'Invalid response from Groq API.', 'details' => $data]);
}
