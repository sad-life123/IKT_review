<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

function local_ikt_review_ensure_logstore_index(): void {
    global $DB;

    if ($DB->get_dbfamily() !== 'postgres') {
        return;
    }

    $tablename = $DB->get_prefix() . 'logstore_standard_log';
    $indexname = local_ikt_review_logstore_primary_index_name();

    if (local_ikt_review_pg_index_exists($tablename, $indexname)) {
        return;
    }

    $DB->execute("
        CREATE INDEX IF NOT EXISTS {$indexname}
            ON {$tablename} (timecreated, courseid)
            INCLUDE (userid, contextinstanceid, contextlevel, crud, action)
            WHERE contextlevel = 70 OR action = 'submitted'
    ");
}

function local_ikt_review_logstore_primary_index_name(): string {
    global $DB;

    return $DB->get_prefix() . 'lssl_ikt_time_course_v2_ix';
}

function local_ikt_review_logstore_alternative_index_name(): string {
    global $DB;

    return $DB->get_prefix() . 'lssl_ikt_course_time_partial_ix';
}

function local_ikt_review_pg_index_exists(string $tablename, string $indexname): bool {
    global $DB;

    return $DB->record_exists_sql(
        "SELECT 1
           FROM pg_indexes
          WHERE tablename = :tablename
            AND indexname = :indexname",
        [
            'tablename' => $tablename,
            'indexname' => $indexname,
        ]
    );
}
