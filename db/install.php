<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/upgradelib.php';

function xmldb_local_ikt_review_install(): bool {
    local_ikt_review_ensure_logstore_index();

    return true;
}
