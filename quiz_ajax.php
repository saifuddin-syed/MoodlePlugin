<?php
// local/automation/quiz_ajax.php
// Endpoint for Quiz Generator:
// - action = "generate" → call Groq with file context and return MCQs as JSON.

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

// Composer autoloader for PDF/Word/PPT parsing (like qb_ajax.php)
require_once($CFG->dirroot . '/vendor/autoload.php');

use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpPresentation\IOFactory as PptIOFactory;

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

require_login();

@error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
@ini_set('display_errors', 0);

$PAGE->set_context(null);
header('Content-Type: application/json; charset=utf-8');

global $CFG, $DB, $USER;

/* ===================== Helpers ===================== */

function local_automation_quiz_safe_utf8(string $text): string {
    if ($text === '') {
        return '';
    }

    if (function_exists('mb_detect_encoding') &&
        mb_detect_encoding($text, 'UTF-8', true) !== false) {
        return $text;
    }

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        if ($converted !== false) {
            return $converted;
        }
    }

    // Fallback: keep only basic printable ASCII + whitespace.
    return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $text);
}

function local_automation_quiz_shorten(string $text, int $max = 4000): string {
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    if (!function_exists('mb_strlen')) {
        if (strlen($text) <= $max) {
            return $text;
        }
        $cut = substr($text, 0, $max);
        $pos = strrpos($cut, ' ');
        if ($pos !== false && $pos > ($max * 0.6)) {
            $cut = substr($cut, 0, $pos);
        }
        return $cut . ' …';
    }

    if (mb_strlen($text, 'UTF-8') <= $max) {
        return $text;
    }

    $cut = mb_substr($text, 0, $max, 'UTF-8');
    $lastspace = mb_strrpos($cut, ' ', 0, 'UTF-8');
    if ($lastspace !== false && $lastspace > ($max * 0.6)) {
        $cut = mb_substr($cut, 0, $lastspace, 'UTF-8');
    }

    return $cut . ' …';
}

function local_automation_quiz_error(string $message, array $extra = []): void {
    echo json_encode(array_merge([
        'success' => false,
        'message' => $message,
    ], $extra));
    exit;
}

/**
 * Extract text content from a Moodle stored_file using PDF / Word / PPT parsers.
 * Falls back to raw bytes if parsing fails (like qb_ajax).
 */
function local_automation_quiz_extract_text_from_file(\stored_file $file): string {
    $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
    $content = '';

    // Temp file for parsers.
    $tmpdir  = make_temp_directory('local_automation_quiz');
    $tmpfile = $tmpdir . '/' . $file->get_contenthash() . '.' . $ext;

    if (!file_exists($tmpfile)) {
        $file->copy_content_to($tmpfile);
    }

    try {
        // PDF
        if ($ext === 'pdf' && class_exists(PdfParser::class)) {
            $parser = new PdfParser();
            $pdf    = $parser->parseFile($tmpfile);
            $content = $pdf->getText();

        // Word (docx/doc)
        } else if (in_array($ext, ['docx', 'doc'], true) && class_exists(WordIOFactory::class)) {
            $phpWord = WordIOFactory::load($tmpfile);
            $text    = '';
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText() . "\n";
                    }
                }
            }
            $content = $text;

        // PowerPoint (ppt/pptx)
        } else if (in_array($ext, ['ppt', 'pptx'], true) && class_exists(PptIOFactory::class)) {
            $presentation = PptIOFactory::load($tmpfile);
            $text         = '';
            foreach ($presentation->getAllSlides() as $slide) {
                foreach ($slide->getShapeCollection() as $shape) {
                    if (method_exists($shape, 'getText')) {
                        $text .= $shape->getText() . "\n";
                    }
                }
            }
            $content = $text;
        }
    } catch (\Throwable $e) {
        error_log('local_automation_quiz: parse error for file ' .
            $file->get_id() . ' (' . $file->get_filename() . '): ' . $e->getMessage());
    }

    if ($content === '') {
        try {
            $content = $file->get_content();
        } catch (\Throwable $e) {
            $content = '';
        }
    }

    $content = local_automation_quiz_safe_utf8($content);
    return local_automation_quiz_shorten($content, 4000);
}

/**
 * Create a multichoice question in the course question bank
 * and add it to the given quiz.
 *
 * @param stdClass $quiz      quiz record from mdl_quiz
 * @param context_course $context course context
 * @param array $q            one element from $decoded['questions']
 * @param float $marksperquestion max mark for the slot
 * @return int question id
 */
function local_automation_quiz_create_mcq_and_add_to_quiz(stdClass $quiz, context_course $context, array $q, float $marksperquestion): int {
    global $USER;

    // 1. Default question category for this course.
    $cat = question_get_default_category($context->id);
    $categoryid = $cat->id;

    $correctindex = (int)($q['correct_index'] ?? 0);

    // 2. Build $fromform – this mimics what the question edit form would send.
    $fromform = new stdClass();

    // Core question fields.
    $fromform->id                    = 0;  // new question
    $fromform->category              = $categoryid;
    $fromform->qtype                 = 'multichoice';
    $fromform->name                  = mb_substr($q['questiontext'], 0, 250);
    $fromform->questiontext          = $q['questiontext'];
    $fromform->questiontextformat    = FORMAT_HTML;
    $fromform->generalfeedback       = $q['feedback'] ?? '';
    $fromform->generalfeedbackformat = FORMAT_HTML;
    $fromform->defaultmark           = $marksperquestion;
    $fromform->penalty               = 0.3333333;
    $fromform->hidden                = 0;

    // Flags normally set by forms.
    $fromform->createdby             = $USER->id;
    $fromform->modifiedby            = $USER->id;

    // Multichoice-specific fields.
    $fromform->single                              = 1;
    $fromform->shuffleanswers                      = 1;
    $fromform->answernumbering                     = 'abc';
    $fromform->shownumcorrect                      = 0;
    $fromform->correctfeedback                     = '';
    $fromform->correctfeedbackformat               = FORMAT_HTML;
    $fromform->partiallycorrectfeedback            = '';
    $fromform->partiallycorrectfeedbackformat      = FORMAT_HTML;
    $fromform->incorrectfeedback                   = '';
    $fromform->incorrectfeedbackformat             = FORMAT_HTML;

    // Answers – note: this is ARRAY-style, as the qtype code expects.
    $fromform->answer         = [];
    $fromform->fraction       = [];
    $fromform->feedback       = [];
    $fromform->feedbackformat = [];

    foreach ($q['options'] as $idx => $opttext) {
        $fromform->answer[$idx]         = $opttext;
        $fromform->fraction[$idx]       = ($idx === $correctindex) ? 1.0 : 0.0;
        $fromform->feedback[$idx]       = '';
        $fromform->feedbackformat[$idx] = FORMAT_HTML;
    }

    // 3. Call the official API – $question is the "existing" record (new one here).
    $qtype    = question_bank::get_qtype('multichoice');

    $question = new stdClass();
    $question->id = 0;          // new question
    $question->category = $categoryid;

    // This will create records in:
    // - question
    // - question_answers
    // - qtype_multichoice_options
    // - question_bank_entries
    // - question_versions
    $savedquestion = $qtype->save_question($question, $fromform);

    // 4. Attach to the quiz.
    $questionid = $savedquestion->id;
    quiz_add_quiz_question($questionid, $quiz, 0, $marksperquestion);

    return $questionid;
}



/**
 * Call Groq using curl (same style as chatbot_endpoint.php).
 */
function local_automation_quiz_call_groq(string $systemPrompt, string $userPrompt): array {
    $apiKey = get_config('local_automation', 'groq_api_key');
    if (!$apiKey) {
        return ['error' => 'Groq API key not configured in admin settings.'];
    }

    $model = get_config('local_automation', 'groq_model');
    if (empty($model)) {
        $model = 'llama-3.1-8b-instant';
    }

    $payload = [
        'model'    => $model,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userPrompt],
        ],
    ];

    $url = 'https://api.groq.com/openai/v1/chat/completions';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$apiKey}",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 40);

    $response = curl_exec($ch);
    $curlerr  = curl_error($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $curlerr) {
        return ['error' => 'Failed to reach Groq API: ' . $curlerr . ' (HTTP ' . $httpcode . ')'];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return ['error' => 'Invalid JSON from Groq API.'];
    }

    if (!empty($data['error']['message'])) {
        return ['error' => 'Groq error: ' . $data['error']['message']];
    }

    $assistantText = $data['choices'][0]['message']['content'] ?? null;
    if ($assistantText === null) {
        return ['error' => 'Missing content in Groq response.'];
    }

    return ['reply' => $assistantText];
}

/* ===================== Read & validate input ===================== */

// sesskey comes from URL (?sesskey=...)
if (!confirm_sesskey(optional_param('sesskey', null, PARAM_RAW_TRIMMED))) {
    local_automation_quiz_error('Invalid sesskey, please reload the page.');
}

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    local_automation_quiz_error('No payload provided.');
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    local_automation_quiz_error('Invalid JSON payload.');
}

$action           = $payload['action']           ?? '';
$courseid         = (int)($payload['courseid']   ?? 0);
$sectionid        = (int)($payload['sectionid']  ?? 0);
$fileids          = $payload['fileids']          ?? [];
$quizname         = trim($payload['quizname']    ?? '');
$numquestions     = (int)($payload['numquestions'] ?? 0);
$marksperquestion = (float)($payload['marksperquestion'] ?? 1);
$timelimitminutes = (int)($payload['timelimitminutes'] ?? 0);
$instructions     = trim($payload['instructions'] ?? '');

// $questionspayload = $payload['questions'] ?? [];

if ($action === 'upload') {
    $questions = $payload['questions'] ?? [];

    if ($courseid <= 0) {
        local_automation_quiz_error('Missing course for quiz upload.');
    }
    if ($sectionid <= 0) {
        local_automation_quiz_error('Missing section for quiz upload.');
    }
    if ($quizname === '') {
        local_automation_quiz_error('Quiz name is required for upload.');
    }
    if (empty($questions) || !is_array($questions)) {
        local_automation_quiz_error('No questions provided to upload.');
    }

    $course  = get_course($courseid);
    $context = context_course::instance($courseid);
    require_capability('moodle/course:manageactivities', $context);

    global $DB, $CFG;

    // Find section number from course_sections.id
    $sectionrec = $DB->get_record('course_sections', [
        'id'     => $sectionid,
        'course' => $courseid,
    ], '*', MUST_EXIST);

    $sectionnum = (int)$sectionrec->section;

    // Build moduleinfo for create_module().
    $moduleinfo = new stdClass();
    $moduleinfo->modulename          = 'quiz';
    $moduleinfo->course              = $courseid;
    $moduleinfo->section             = $sectionnum;
    $moduleinfo->visible             = 1;
    $moduleinfo->visibleoncoursepage = 1;
    $moduleinfo->name                = $quizname;
    $moduleinfo->intro               = '';
    $moduleinfo->introformat         = FORMAT_HTML;

    // ✅ REQUIRED by create_module / mod_form:
    $moduleinfo->introeditor = [
        'text'   => '',
        'format' => FORMAT_HTML,
        'itemid' => 0,
    ];

    // Basic quiz fields.
    $moduleinfo->timeopen        = 0;
    $moduleinfo->timeclose       = 0;
    $moduleinfo->timelimit       = $timelimitminutes * 60;
    $moduleinfo->overduehandling = 'autoabandon';
    $moduleinfo->graceperiod     = 0;
    $moduleinfo->preferredbehaviour = 'deferredfeedback';
    $moduleinfo->attempts        = 0;   // unlimited
    $moduleinfo->shuffleanswers  = 1;
    $moduleinfo->sumgrades       = $numquestions * $marksperquestion;
    $moduleinfo->grade           = $moduleinfo->sumgrades;
    $moduleinfo->password     = ''; // for direct DB field safety

    $now = time();
    $moduleinfo->timecreated     = $now;
    $moduleinfo->timemodified    = $now;

    // Review options – basic sensible default (during review after attempt close).
    $moduleinfo->reviewattempt          = 0x10000; // 65536
    $moduleinfo->reviewcorrectness      = 0;
    $moduleinfo->reviewmaxmarks         = 0;
    $moduleinfo->reviewmarks            = 0;
    $moduleinfo->reviewspecificfeedback = 0;
    $moduleinfo->reviewgeneralfeedback  = 0;
    $moduleinfo->reviewrightanswer      = 0;
    $moduleinfo->reviewoverallfeedback  = 0;

    // Actually create the quiz module.
    $cmid = create_module($moduleinfo);
    if (is_object($cmid)) {
        // Some Moodle versions return cm object.
        $cm = $cmid;
        $cmid = $cm->id;
    } else {
        $cm = get_coursemodule_from_id('quiz', $cmid, $courseid, false, MUST_EXIST);
    }

    $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

    // Now create each MCQ in the question bank and add to quiz.
    foreach ($questions as $q) {
        if (!is_array($q)) {
            continue;
        }
        if (empty($q['questiontext']) || empty($q['options']) || !is_array($q['options'])) {
            continue;
        }
        // Safety: ensure exactly 4 options.
        if (count($q['options']) !== 4) {
            continue;
        }

        local_automation_quiz_create_mcq_and_add_to_quiz(
            $quiz,
            $context,
            $q,
            $marksperquestion
        );
    }

    // Update quiz sumgrades just in case.
    quiz_update_sumgrades($quiz);

    $quizurl = (new moodle_url('/mod/quiz/view.php', ['id' => $cmid]))->out(false);

    echo json_encode([
        'success'   => true,
        'message'   => 'Quiz created and questions added.',
        'quizid'    => $quiz->id,
        'cmid'      => $cmid,
        'viewurl'   => $quizurl,
    ]);
    exit;
}
else if ($action !== 'generate') {
    local_automation_quiz_error('Unknown action: ' . $action);
}

if ($courseid <= 0) {
    local_automation_quiz_error('Please select a course.');
}
if ($sectionid <= 0) {
    local_automation_quiz_error('Please select a section.');
}
if (empty($fileids) || !is_array($fileids)) {
    local_automation_quiz_error('Please select at least one file.');
}
if ($quizname === '') {
    local_automation_quiz_error('Please enter a quiz name.');
}
if ($numquestions <= 0) {
    local_automation_quiz_error('Number of MCQs must be greater than zero.');
}

$course  = get_course($courseid);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

/* ===================== Build file context ===================== */

$fs = get_file_storage();
$filesummary  = [];
$filecontents = [];

foreach ($fileids as $fid) {
    $fid = (int)$fid;
    if ($fid <= 0) {
        continue;
    }

    $file = $fs->get_file_by_id($fid);
    if (!$file) {
        continue;
    }

    $name = $file->get_filename();
    $filesummary[] = "- {$name}";

    $text = local_automation_quiz_extract_text_from_file($file);
    if ($text !== '') {
        $filecontents[] = "FILE: {$name}\n{$text}";
    }
}

$filesummarytext  = local_automation_quiz_safe_utf8(implode("\n", $filesummary));
$filecontentstext = local_automation_quiz_safe_utf8(implode("\n\n-----\n\n", $filecontents));

if (function_exists('mb_strlen') &&
    mb_strlen($filecontentstext, 'UTF-8') > 14000) {
    $filecontentstext = local_automation_quiz_shorten($filecontentstext, 14000);
}

/* ===================== Build prompts ===================== */

$systemPrompt = <<<EOT
You are an assistant that generates multiple-choice quiz questions (MCQs) for university-level courses.

HARD RULES (VERY IMPORTANT):
- You must respond with STRICT JSON only, no markdown, no explanations, no commentary.
- The top-level JSON must be an object with a single key "questions".
- "questions" must be an array.
- Each element of "questions" must be an object with:
  - "questiontext": string, the question stem (no numbering).
  - "options": array of exactly 4 short answer strings.
  - "correct_index": integer from 0 to 3 (index into the options array).
  - "feedback": string (optional), brief explanation of the correct answer.

Do NOT:
- Do NOT add any text before or after the JSON object.
- Do NOT wrap the JSON in code fences like ```json.
- Do NOT include question numbers in "questiontext".
EOT;

$details = [];
$details[] = "Course: {$course->fullname} ({$course->shortname})";
$details[] = "Quiz name: {$quizname}";
$details[] = "Number of questions: {$numquestions}";
$details[] = "Marks per question: {$marksperquestion}";
if ($timelimitminutes > 0) {
    $details[] = "Time limit (minutes): {$timelimitminutes}";
}
if ($instructions !== '') {
    $details[] = "Teacher instructions: {$instructions}";
}
if ($filesummarytext !== '') {
    $details[] = "";
    $details[] = "Selected files:";
    $details[] = $filesummarytext;
}

$detailblock  = implode("\n", $details);
$contextblock = $filecontentstext ?: '(No readable text found in selected files; use only file names and course name to guess topics.)';

$userPrompt = <<<EOT
Generate exactly {$numquestions} single-best-answer multiple-choice questions for a Moodle quiz.

Quiz and teacher details:
{$detailblock}

CONTENT FROM FILES (PRIMARY SOURCE FOR CONCEPTS):
{$contextblock}

You MUST output a SINGLE JSON object of this exact shape:

{
  "questions": [
    {
      "questiontext": "Question text here",
      "options": [
        "Option A",
        "Option B",
        "Option C",
        "Option D"
      ],
      "correct_index": 1,
      "feedback": "Short explanation for the correct answer (optional)"
    }
  ]
}

Rules:
- All questions must be answerable based on the above content/topics.
- "options" must always have exactly 4 elements.
- "correct_index" must be 0, 1, 2, or 3.
- Do NOT include any other keys at the top level besides "questions".
EOT;

/* ===================== Call Groq ===================== */

$result = local_automation_quiz_call_groq($systemPrompt, $userPrompt);

if (!empty($result['error'])) {
    local_automation_quiz_error('Groq error: ' . $result['error']);
}

$content = local_automation_quiz_safe_utf8($result['reply'] ?? '');

if ($content === '') {
    local_automation_quiz_error('Empty response from model.');
}

// Strip UTF-8 BOM if present.
$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

// If there is any junk before/after, extract the first {...} block.
if (preg_match('/\{[\s\S]*\}/', $content, $m)) {
    $jsonstr = $m[0];
} else {
    $jsonstr = $content;
}

// Remove control chars that JSON does not like (keep tab, CR, LF).
$jsonstr = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $jsonstr);

// Decode once, no fancy logic.
$decoded = json_decode($jsonstr, true);

if (!is_array($decoded) || !isset($decoded['questions']) || !is_array($decoded['questions'])) {
    local_automation_quiz_error(
        'Model did not return valid questions JSON.',
        ['raw' => local_automation_quiz_shorten($jsonstr, 1000)]
    );
}


/* ===================== Success ===================== */

echo json_encode([
    'success'   => true,
    'questions' => $decoded['questions'],
]);
exit;
