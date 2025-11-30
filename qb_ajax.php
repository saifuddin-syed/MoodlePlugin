<?php
// local/automation/qb_ajax.php
// Single endpoint for Question Bank (QB) features:
// - fetch_courses
// - fetch_files (sections + folders + files)
// - generate QB via Groq + PDF, with file content snippets.

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

// Composer autoloader (installed at Moodle root via composer)
require_once($CFG->dirroot . '/vendor/autoload.php');

use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpPresentation\IOFactory as PptIOFactory;

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/filelib.php');

require_login();

@error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
@ini_set('display_errors', 0);

$PAGE->set_context(null);
header('Content-Type: application/json; charset=utf-8');

global $DB, $USER, $CFG;

/**
 * Ensure string is safe UTF-8.
 */
function local_automation_qb_safe_utf8(string $text): string {
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

/**
 * Shorten text to a safe length, trying to cut at word boundary.
 */
function local_automation_qb_shorten(string $text, int $max = 4000): string {
    $text = trim($text);
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

/**
 * Extracts text content from a Moodle stored_file using PDF / Word / PPT parsers.
 * Falls back to raw bytes if parsing fails.
 */
function local_automation_qb_extract_text_from_file(\stored_file $file): string {
    global $CFG;

    $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
    $content = '';

    // Temp file for parsers.
    $tmpdir  = make_temp_directory('local_automation_qb');
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
        // If parsing fails, we log and fall back.
        error_log('local_automation_qb: parse error for file ' .
            $file->get_id() . ' (' . $file->get_filename() . '): ' . $e->getMessage());
    }

    if ($content === '') {
        try {
            $content = $file->get_content();
        } catch (\Throwable $e) {
            $content = '';
        }
    }

    $content = local_automation_qb_safe_utf8($content);
    return local_automation_qb_shorten($content, 4000);
}

// ---------- Read raw JSON request ----------

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No payload provided']);
    exit;
}

$payload = json_decode($raw, true);
if ($payload === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Sesskey check (inside JSON)
$sesskey = $payload['sesskey'] ?? null;
if (!$sesskey || !confirm_sesskey($sesskey)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid session key']);
    exit;
}

// Optional "action"
$action = $payload['action'] ?? null;

/* ============================================================
   ACTION: fetch_courses
   ============================================================ */
if ($action === 'fetch_courses') {
    require_once($CFG->libdir . '/enrollib.php');

    $courses = enrol_get_users_courses($USER->id, true, 'id, fullname, shortname, visible');
    $out = [];

    foreach ($courses as $c) {
        $coursecontext = context_course::instance($c->id);
        if (!$c->visible && !has_capability('moodle/course:viewhiddencourses', $coursecontext)) {
            continue;
        }
        $out[] = [
            'id'        => (int)$c->id,
            'fullname'  => format_string($c->fullname),
            'shortname' => format_string($c->shortname),
        ];
    }

    echo json_encode(['success' => true, 'courses' => $out]);
    exit;
}

/* ============================================================
   ACTION: fetch_files
   ============================================================ */
if ($action === 'fetch_files') {
    $courseid = isset($payload['courseid']) ? (int)$payload['courseid'] : 0;
    if (!$courseid) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing courseid']);
        exit;
    }

    require_once($CFG->dirroot . '/course/lib.php');
    $course = get_course($courseid);
    $coursecontext = context_course::instance($courseid);
    require_capability('moodle/course:view', $coursecontext);

    $modinfo      = get_fast_modinfo($course);
    $sectionsinfo = $modinfo->get_section_info_all();
    $fs           = get_file_storage();

    $resultsections = [];
    $allowedext = ['pdf', 'ppt', 'pptx', 'doc', 'docx'];

    foreach ($sectionsinfo as $sectionnum => $sectioninfo) {
        if (empty($modinfo->sections[$sectionnum])) {
            continue;
        }
        if (!$sectioninfo->uservisible) {
            continue;
        }

        $sectionname = $sectioninfo->name ?: get_section_name($courseid, $sectioninfo);
        $filesforsection = [];

        foreach ($modinfo->sections[$sectionnum] as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if (!$cm->uservisible) {
                continue;
            }

            // Single file resources
            if ($cm->modname === 'resource') {
                $contextmod = context_module::instance($cm->id);
                $files = $fs->get_area_files(
                    $contextmod->id,
                    'mod_resource',
                    'content',
                    0,
                    'filepath, filename',
                    false
                );
                foreach ($files as $file) {
                    if ($file->is_directory()) {
                        continue;
                    }
                    $filename = $file->get_filename();
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedext, true)) {
                        continue;
                    }
                    $filepath = trim($file->get_filepath(), '/');
                    $path = $filepath ? $filepath . '/' . $filename : $filename;

                    $filesforsection[] = [
                        'fileid'   => $file->get_id(),
                        'cmid'     => $cm->id,
                        'name'     => $filename,
                        'path'     => $path,
                        'courseid' => $courseid,
                    ];
                }
            }

            // Folders
            if ($cm->modname === 'folder') {
                $contextmod = context_module::instance($cm->id);
                $folderfiles = $fs->get_area_files(
                    $contextmod->id,
                    'mod_folder',
                    'content',
                    0,
                    'filepath, filename',
                    false
                );
                foreach ($folderfiles as $file) {
                    if ($file->is_directory()) {
                        continue;
                    }
                    $filename = $file->get_filename();
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedext, true)) {
                        continue;
                    }
                    $filepath = trim($file->get_filepath(), '/');
                    $path = $filepath ? $filepath . '/' . $filename : $filename;

                    $filesforsection[] = [
                        'fileid'   => $file->get_id(),
                        'cmid'     => $cm->id,
                        'name'     => $filename,
                        'path'     => $path,
                        'courseid' => $courseid,
                    ];
                }
            }
        }

        if (!empty($filesforsection)) {
            $resultsections[] = [
                'id'    => $sectioninfo->id,
                'name'  => format_string($sectionname),
                'files' => $filesforsection,
            ];
        }
    }

    echo json_encode(['success' => true, 'sections' => $resultsections]);
    exit;
}

/* ============================================================
   ACTION: upload_qb
   Upload the generated QB PDF as a Resource into the course.
   It creates (or reuses) a section named "IA Question Bank"
   or "ESE Question Bank" depending on question type.
   ============================================================ */
if ($action === 'upload_qb') {
    $courseid     = isset($payload['courseid']) ? (int)$payload['courseid'] : 0;
    $fileid       = isset($payload['fileid']) ? (int)$payload['fileid'] : 0;
    $questiontype = $payload['questiontype'] ?? 'IA';

    if ($courseid <= 0 || $fileid <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing courseid or fileid']);
        exit;
    }

    $course        = get_course($courseid);
    $coursecontext = context_course::instance($courseid);
    require_capability('moodle/course:update', $coursecontext);

    $fs   = get_file_storage();
    $file = $fs->get_file_by_id($fileid);
    if (!$file) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'QB file not found']);
        exit;
    }

    // Decide section name
    $sectionname = ($questiontype === 'ESE') ? 'ESE Question Bank' : 'IA Question Bank';

    // Find or create that section
    $modinfo      = get_fast_modinfo($course);
    $sectionsinfo = $modinfo->get_section_info_all();

    $targetsectionnum = null;
    foreach ($sectionsinfo as $sectionnum => $sectioninfo) {
        $existingname = trim($sectioninfo->name ?? '');
        if ($existingname !== '' && $existingname === $sectionname) {
            $targetsectionnum = (int)$sectionnum;
            break;
        }
    }

    if ($targetsectionnum === null) {
        // Create new section at the end
        $format  = course_get_format($course);
        $last    = $format->get_last_section_number();
        $targetsectionnum = $last + 1;

        course_create_sections_if_missing($courseid, [$targetsectionnum]);

        // Set the section name
        if ($sectionrecord = $DB->get_record('course_sections',
                       ['course' => $courseid, 'section' => $targetsectionnum])) {
            $sectionrecord->name = $sectionname;
            $DB->update_record('course_sections', $sectionrecord);
        }
    }

    // Put file into user's draft area for the resource module
    $usercontext = context_user::instance($USER->id);
    $draftitemid = file_get_unused_draft_itemid();
    file_prepare_draft_area(
        $draftitemid,
        $usercontext->id,
        'user',
        'draft',
        $draftitemid,
        ['subdirs' => 0, 'maxfiles' => 1]
    );

    // Copy QB file into draft area
    $filerec = [
        'contextid' => $usercontext->id,
        'component' => 'user',
        'filearea'  => 'draft',
        'itemid'    => $draftitemid,
        'filepath'  => '/',
        'filename'  => $file->get_filename(),
    ];
    $fs->create_file_from_storedfile($filerec, $file);

     // Build module info for a Resource
    $resource = new stdClass();
    $resource->modulename   = 'resource';
    $resource->course       = $courseid;
    $resource->section      = $targetsectionnum;
    $resource->visible      = 1;
    $resource->visibleoncoursepage = 1;
    $resource->name         = $file->get_filename();

    // Intro fields
    $resource->intro        = '';
    $resource->introformat  = FORMAT_HTML;
    $resource->introeditor  = [
        'text'   => '',
        'format' => FORMAT_HTML,
        'itemid' => 0,
    ];
    $resource->files        = $draftitemid; // this is what mod_resource_form uses

    // Create the module
    $newcm = create_module($resource);

    // Rebuild caches
    rebuild_course_cache($courseid, true);

    // Try to get cmid/id from return value
    $cmid = null;
    if (is_object($newcm)) {
        if (!empty($newcm->coursemodule)) {
            $cmid = (int)$newcm->coursemodule;
        } else if (!empty($newcm->id)) {
            $cmid = (int)$newcm->id;
        }
    } else if (is_int($newcm)) {
        $cmid = $newcm;
    }

    echo json_encode([
        'success'      => true,
        'message'      => 'Question Bank uploaded to course.',
        'courseid'     => $courseid,
        'sectionnum'   => $targetsectionnum,
        'sectionname'  => $sectionname,
        'cmid'         => $cmid,
    ]);
    exit;
}



/* ============================================================
   NO ACTION → GENERATE QUESTION BANK (Groq + PDF)
   Called when you click Generate in QB mode.
   ============================================================ */

$courseid      = isset($payload['courseid']) ? (int)$payload['courseid'] : 0;
$questiontype  = $payload['questiontype'] ?? null; // 'IA' or 'ESE'
$counts        = $payload['counts'] ?? [];
$instructions  = trim($payload['instructions'] ?? '');
$selectedfiles = $payload['selectedfiles'] ?? [];
$filemeta      = $payload['filemeta'] ?? [];
$mode          = $payload['mode'] ?? 'initial';
$previous      = $payload['previous'] ?? null;

if ($courseid <= 0 || empty($questiontype)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing courseid or question type']);
    exit;
}

require_once($CFG->dirroot . '/course/lib.php');
$course = get_course($courseid);
$coursecontext = context_course::instance($courseid);
require_capability('moodle/course:update', $coursecontext);

// --------------------------------------------------------
// 1) Build just a summary of selected files (NO CONTENT)
// --------------------------------------------------------
$filesummary  = [];
$fs = get_file_storage();

foreach ($selectedfiles as $fid) {
    $fid  = (int)$fid;
    $file = $fs->get_file_by_id($fid);
    if (!$file) {
        continue;
    }

    $key         = (string)$fid;
    $meta        = $filemeta[$key] ?? ($filemeta[$fid] ?? []);
    $name        = $meta['name']        ?? $file->get_filename();
    $sectionname = $meta['sectionname'] ?? 'Unknown';
    $path        = $meta['path']        ?? '';

    $filesummary[] = sprintf(
        '- %s (Section: %s, Path: %s)',
        $name,
        $sectionname,
        $path
    );
}

$allowedtopics = [];

// Use section names
foreach ($filemeta as $meta) {
    if (!empty($meta['sectionname'])) {
        $allowedtopics[] = $meta['sectionname'];
    }
    if (!empty($meta['name'])) {
        // strip extension from filename as topic hint
        $basename = preg_replace('/\.[A-Za-z0-9]+$/', '', $meta['name']);
        $allowedtopics[] = $basename;
    }
}

// Deduplicate + clean
$allowedtopics = array_values(array_unique(array_map(function($t) {
    $t = trim($t);
    return $t;
}, $allowedtopics)));

$allowedtopicsline = empty($allowedtopics)
    ? '(no explicit topic list; infer from content)'
    : implode(', ', $allowedtopics);


$filesummarytext = implode("\n", $filesummary);

// --------------------------------------------------------
// 2) Question counts + detail text
// --------------------------------------------------------
$detail = '';

$ia2   = (int)($counts['ia2marks']   ?? 0);
$ia5   = (int)($counts['ia5marks']   ?? 0);
$ese5  = (int)($counts['ese5marks']  ?? 0);
$ese10 = (int)($counts['ese10marks'] ?? 0);

$detail .= "QUESTION TYPE: {$questiontype}\n";
$detail .= "COURSE SHORTNAME: {$course->shortname}\n";
$detail .= "COURSE FULLNAME: {$course->fullname}\n";

if ($questiontype === 'IA') {
    $detail .= "EXACT 2-mark questions: {$ia2}\n";
    $detail .= "EXACT 5-mark questions: {$ia5}\n";
} else if ($questiontype === 'ESE') {
    $detail .= "EXACT 5-mark questions: {$ese5}\n";
    $detail .= "EXACT 10-mark questions: {$ese10}\n";
}

if ($instructions !== '') {
    $detail .= "\nTeacher preferences / instructions:\n" . $instructions . "\n";
}

// --------------------------------------------------------
// 3) Groq config
// --------------------------------------------------------
$apiKey = get_config('local_automation', 'groq_api_key');
$model  = get_config('local_automation', 'groq_model');
if (empty($model)) {
    $model = 'llama-3.1-8b-instant';
}

if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Groq API key not configured']);
    exit;
}

// --------------------------------------------------------
// 4) Previous QB text for edit mode (if any)
// --------------------------------------------------------
$previousqbtext = '';
if ($mode === 'edit' && !empty($previous)) {
    if (is_array($previous) && !empty($previous['qbtext'])) {
        $previousqbtext = $previous['qbtext'];
    } else {
        $previousqbtext = json_encode($previous, JSON_PRETTY_PRINT);
    }
}

// --------------------------------------------------------
// 5) System prompt (no reasoning, no thinking dump)
// --------------------------------------------------------
$systemPrompt = "You are a Question Bank generator for a university course on '{$course->fullname}'.

HARD RULES (MUST OBEY EXACTLY):
- ALWAYS add a TITLE at the top in the following strict format:
  \"{$course->shortname} - {$questiontype} QUESTION BANK\"
- Leave one empty line after the title.
- You are told how many questions of each mark to generate (for example, 4 x 2-mark, 6 x 5-mark).
- You MUST generate exactly that many questions for each mark category.
- Do NOT add extra questions, do NOT omit questions.
- Each question MUST be clearly labelled like: Q1 (2 marks): ...
- Group questions by marks (all 2-mark together, then all 5-mark, then all 10-mark if any).
- Do NOT include answers or explanations, only questions.

TOPIC / FILE RULES:
- From section names and file titles, infer topic groups (e.g. Trees, Strings, Greedy).
- Use these topics as the main themes of the questions.
- If there is more than one topic and the total number of questions in a marks group allows it,
  include questions from multiple topics in that group instead of only one.
- You are given SECTION NAMES and FILE TITLES (from PDFs, PPTs, DOCs).
- Treat these names as the PRIMARY source of topic information.
- You are also given an ALLOWED TOPIC LIST:
  {$allowedtopicsline}
- You MUST restrict all questions to these topics and closely related sub-ideas.
- You MUST NOT introduce new big topics that are clearly outside this list.
- If the extracted content or topic list is very small, it is acceptable to reuse and slightly rephrase ideas,
  but still stay within these topics.

TEACHER PREFERENCES:
- Phrases like \"focus on trees\" mean:
  - Give that topic a larger share of questions than others,
  - BUT still include questions from other topics unless the teacher clearly says ONLY that topic.
- If the teacher later says \"Don't just focus on trees\" or similar, rebalance so other topics also appear.

EDIT MODE:
- MODE may be 'initial' or 'edit'.
- In 'edit' mode you may receive the previous Question Bank text plus new instructions.
- In edit mode:
  - KEEP the same number of questions in each marks category unless the teacher changes the counts.
  - Apply only the requested changes (e.g. add 2 questions on bit manipulation, replace a tree question with a greedy one).
  - Preserve as many existing questions as possible and only modify what is necessary.

OUTPUT FORMAT (VERY IMPORTANT):
- Do NOT show your reasoning, steps, analysis, distributions, or bullet lists of how you think.
- Do NOT wrap the output in markdown fences like ``` or ```text.
- ONLY output the final Question Bank text:
  - First line: the TITLE exactly in the required format.
  - Then a blank line.
  - Then the questions, one by one.
  - There should be a blank line between consecutive questions.";

// --------------------------------------------------------
// 6) User prompt (NO raw file contents, just titles/sections)
// --------------------------------------------------------
$userPrompt =
    "MODE: {$mode}\n" .
    "Course: {$course->fullname} ({$course->shortname})\n\n" .
    "Question Bank Settings:\n" .
    $detail .
    "\nALLOWED TOPICS (you must stay within these):\n" .
    $allowedtopicsline . "\n\n" .
    "Selected notes/slides/files (titles as topic hints):\n" .
    ($filesummarytext !== '' ? $filesummarytext : "- (no file titles provided)") . 
    "\nSelected notes/slides/files (titles as topic hints):\n" .
    ($filesummarytext !== '' ? $filesummarytext : "- (no file titles provided)") .
    "\n\nPrevious Question Bank (if any):\n" .
    ($previousqbtext !== '' ? $previousqbtext : "(no previous QB)") .
    "\n\nGenerate the complete Question Bank text now, obeying ALL rules.";

// (We assume Groq returns valid UTF-8; DO NOT over-sanitize the response.)
$groqUrl = 'https://api.groq.com/openai/v1/chat/completions';

$groqPayload = [
    'model'    => $model,
    'temperature' => 0.1, // keep it low for consistency
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user',   'content' => $userPrompt],
    ]
];

$jsonBody = json_encode($groqPayload, JSON_UNESCAPED_UNICODE);
if ($jsonBody === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Internal encoding error before calling Groq.'
    ]);
    exit;
}

$context = stream_context_create([
    'http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\n" .
                           "Authorization: Bearer {$apiKey}\r\n",
        'content'       => $jsonBody,
        'ignore_errors' => true,
        'timeout'       => 60,
    ]
]);

$groqResponse = @file_get_contents($groqUrl, false, $context);
if ($groqResponse === false) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'Failed to reach Groq API']);
    exit;
}

$groqData = json_decode($groqResponse, true);
if (!isset($groqData['choices'][0]['message']['content'])) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Invalid response from Groq',
        'details' => $groqData
    ]);
    exit;
}

$qbtext = $groqData['choices'][0]['message']['content'];

// --------------------------------------------------------
// 7) Light post-processing: strip fences, ensure spacing
// --------------------------------------------------------

// kill ``` fences if any
$qbtext = preg_replace('/```[a-zA-Z0-9]*\s*/', '', $qbtext);
$qbtext = str_replace('```', '', $qbtext);

// keep only from title onward if model added extra stuff before
$titleline = $course->shortname . ' - ' . $questiontype . ' QUESTION BANK';
$pos = stripos($qbtext, $titleline);
if ($pos !== false) {
    $qbtext = substr($qbtext, $pos);
}

// ensure a blank line before each new question label
$qbtext = preg_replace(
    "/(?<!\n)\n(Q\\d+\\s*\\([0-9]+\\s*marks\\))/i",
    "\n\n$1",
    $qbtext
);

$qbtext = trim($qbtext);

// --------------------------------------------------------
// 8) Create PDF from qbtext
// --------------------------------------------------------
require_once($CFG->libdir . '/pdflib.php');

$pdf = new pdf();
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetTitle('Question Bank - ' . $course->shortname);
$pdf->SetAuthor(fullname($USER));
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 11);

// convert to basic HTML
$html = nl2br(htmlspecialchars($qbtext, ENT_QUOTES, 'UTF-8'));
$pdf->writeHTML($html, true, false, true, false, '');

$pdfcontent = $pdf->Output('', 'S');

$contextid = $coursecontext->id;
$filename = 'QuestionBank_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $course->shortname) . '_' . date('Ymd_His') . '.pdf';

$filerecord = (object)[
    'contextid' => $contextid,
    'component' => 'local_automation',
    'filearea'  => 'qb',
    'itemid'    => time(),
    'filepath'  => '/',
    'filename'  => $filename,
];

$file = $fs->create_file_from_string($filerecord, $pdfcontent);
$fileid = $file->get_id();

$downloadurl = (new moodle_url('/local/automation/qb_download.php', ['fileid' => $fileid]))->out(false);

echo json_encode([
    'success'     => true,
    'message'     => 'Question Bank generated successfully.',
    'fileid'      => $fileid,
    'courseid'    => $courseid,
    'downloadurl' => $downloadurl,
    'data'        => [
        'qbtext'       => $qbtext,
        'questiontype' => $questiontype,
        'counts'       => $counts,
        'instructions' => $instructions
    ]
]);
exit;

