<?php
// local/automation/qb_download.php
// Serves a stored Question Bank PDF by fileid.

require_once(__DIR__ . '/../../config.php');
require_login();

$fileid = required_param('fileid', PARAM_INT);

$fs = get_file_storage();
$file = $fs->get_file_by_id($fileid);

if (!$file) {
    print_error('filenotfound', 'error');
}

// Security: only users who can view the course should access.
$contextid = $file->get_contextid();
$context = context::instance_by_id($contextid, MUST_EXIST);

// Simple check: require login is already done; if context is course, enforce view.
if ($context instanceof context_course) {
    require_capability('moodle/course:view', $context);
}

// Send file to browser
send_stored_file($file, 0, 0, true);
