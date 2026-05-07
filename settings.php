<?php
// local/automation/settings.php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) { // needs admin access
    $settings = new admin_settingpage(
        'local_automation_settings',
        get_string('pluginname', 'local_automation')
    );

    // Groq API key — use passwordunmask so it's hidden in UI but editable
    $name = 'local_automation/groq_api_key';
    $title = get_string('groqapikey', 'local_automation');
    $description = get_string('groqapikey_desc', 'local_automation');
    $default = '';

    $settings->add(new admin_setting_configpasswordunmask($name, $title, $description, $default));

    // Optional: Groq model name (default = free tier)
    $settings->add(new admin_setting_configtext(
        'local_automation/groq_model',
        get_string('groqmodel', 'local_automation'),
        get_string('groqmodel_desc', 'local_automation'),
        'llama-3.3-70b-versatile',
        PARAM_TEXT
    ));

    // Demo Course ID for Student AI
    $settings->add(new admin_setting_configtext(
        'local_automation/student_ai_courseid',
        'Student AI Demo Course ID',
        'Enter the course ID where the Student AI Tutor should be active.',
        '',
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
