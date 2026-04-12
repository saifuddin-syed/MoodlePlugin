<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_automation_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    /* ================= STUDENT CHAT ================= */
    if ($oldversion < 2026022300) {

        $table = new xmldb_table('local_automation_student_chat');

        if (!$dbman->table_exists($table)) {

            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('message', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('sender', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('studentid_idx', XMLDB_INDEX_NOTUNIQUE, ['studentid']);
            $table->add_index('courseid_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026022300, 'local', 'automation');
    }


    /* ================= STUDENT QUIZ ================= */
    if ($oldversion < 2026022301) {

        $table2 = new xmldb_table('local_automation_student_quiz');

        if (!$dbman->table_exists($table2)) {

            $table2->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table2->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table2->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table2->add_field('topic', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
            $table2->add_field('score', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL);
            $table2->add_field('total', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL);
            $table2->add_field('difficulty', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL);
            $table2->add_field('recommendation', XMLDB_TYPE_TEXT, null, null);
            $table2->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

            $table2->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table2->add_index('studentid_idx2', XMLDB_INDEX_NOTUNIQUE, ['studentid']);
            $table2->add_index('courseid_idx2', XMLDB_INDEX_NOTUNIQUE, ['courseid']);

            $dbman->create_table($table2);
        }

        upgrade_plugin_savepoint(true, 2026022301, 'local', 'automation');
    }


    /* ================= ADVICE TABLE (FIXED) ================= */
    if ($oldversion < 2026032403) {

        $table3 = new xmldb_table('local_automation_advice');

        if (!$dbman->table_exists($table3)) {

            $table3->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table3->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table3->add_field('teacherid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table3->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table3->add_field('advice', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table3->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

            $table3->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $dbman->create_table($table3);
        }

        upgrade_plugin_savepoint(true, 2026032403, 'local', 'automation');
    }


    // 🔹 Add quiz lock table
    if ($oldversion < 2026032600) {

        $table = new xmldb_table('local_automation_quiz_lock');

        // Fields
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('difficulty', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL);
        $table->add_field('locked', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

        // Keys
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Index
        $table->add_index('uniq_lock', XMLDB_INDEX_UNIQUE, ['studentid','courseid','difficulty']);

        // Create table
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Savepoint
        upgrade_plugin_savepoint(true, 2026032600, 'local', 'automation');
    }

    /* ================= QUIZ QUESTION TABLE ================= */
    if ($oldversion < 2026041000) {

        $table = new xmldb_table('local_automation_quiz_questions');

        if (!$dbman->table_exists($table)) {

            // Fields
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);

            $table->add_field('quizattemptid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

            $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

            $table->add_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('questiontext', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('topic', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
            $table->add_field('unit', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);

            $table->add_field('score', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL);
            $table->add_field('maxscore', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL);

            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

            // Keys
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            // Indexes
            $table->add_index('quiz_idx', XMLDB_INDEX_NOTUNIQUE, ['quizattemptid']);
            $table->add_index('student_idx', XMLDB_INDEX_NOTUNIQUE, ['studentid']);
            $table->add_index('unit_idx', XMLDB_INDEX_NOTUNIQUE, ['unit']);

            // Create table
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026041000, 'local', 'automation');
    }

    return true;
}