<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

function local_ikt_review_ensure_required_indexes(): void {
    foreach (local_ikt_review_required_postgres_indexes() as $index) {
        local_ikt_review_ensure_postgres_index($index['table'], $index['suffix'], $index['definition']);
    }
}

function local_ikt_review_ensure_logstore_index(): void {
    $indexes = local_ikt_review_required_postgres_indexes();
    $index = $indexes['logstore'];
    local_ikt_review_ensure_postgres_index($index['table'], $index['suffix'], $index['definition']);
}

function local_ikt_review_required_postgres_indexes(): array {
    return [
        'logstore' => [
            'table' => 'logstore_standard_log',
            'suffix' => 'lssl_ikt_time_course_v2_ix',
            'definition' => "(timecreated, courseid)
                INCLUDE (userid, contextinstanceid, contextlevel, crud, action)
                WHERE contextlevel = 70 OR action = 'submitted'",
        ],
        'bbb_join' => [
            'table' => 'bigbluebuttonbn_logs',
            'suffix' => 'bbbjoin_time_user_ix',
            'definition' => "(bigbluebuttonbnid, timecreated, userid)
                WHERE log = 'Join'",
        ],
        'assign_submission' => [
            'table' => 'assign_submission',
            'suffix' => 'assub_sub_time_user_ix',
            'definition' => "(assignment, timemodified, userid)
                WHERE status = 'submitted'",
        ],
        'assign_grades' => [
            'table' => 'assign_grades',
            'suffix' => 'asgr_valid_ass_user_ix',
            'definition' => "(assignment, userid)
                INCLUDE (id)
                WHERE grade IS NOT NULL AND grade >= 0",
        ],
        'quiz_attempts' => [
            'table' => 'quiz_attempts',
            'suffix' => 'quiza_fin_time_user_ix',
            'definition' => "(quiz, timefinish, userid)
                WHERE state = 'finished' AND preview = 0",
        ],
        'grade_grades' => [
            'table' => 'grade_grades',
            'suffix' => 'gragr_final_item_ix',
            'definition' => "(itemid)
                INCLUDE (finalgrade)
                WHERE finalgrade IS NOT NULL",
        ],
    ];
}

function local_ikt_review_ensure_postgres_index(string $logicaltable, string $suffix, string $definition): void {
    global $DB;

    if ($DB->get_dbfamily() !== 'postgres') {
        return;
    }

    $dbman = $DB->get_manager();
    if (!$dbman->table_exists($logicaltable)) {
        return;
    }

    $tablename = $DB->get_prefix() . $logicaltable;
    $indexname = $DB->get_prefix() . $suffix;
    if (local_ikt_review_pg_index_exists($tablename, $indexname)) {
        return;
    }

    $DB->execute("CREATE INDEX IF NOT EXISTS {$indexname} ON {$tablename} {$definition}");
}

function local_ikt_review_logstore_primary_index_name(): string {
    global $DB;

    return $DB->get_prefix() . 'lssl_ikt_time_course_v2_ix';
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
