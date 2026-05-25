<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/upgradelib.php';

function xmldb_local_ikt_review_upgrade($oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026052501) {
        local_ikt_review_ensure_logstore_index();

        upgrade_plugin_savepoint(true, 2026052501, 'local', 'ikt_review');
    }

    if ($oldversion < 2028052502) {
        local_ikt_review_ensure_logstore_index();

        $table = new xmldb_table('local_ikt_review_snap');

        $field = new xmldb_field('live_bbb_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'bbb_count');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('unique_view_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'view_count');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('assign_graded_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'submit_count');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2028052502, 'local', 'ikt_review');
    }

    return true;
}
