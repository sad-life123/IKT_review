<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/upgradelib.php';

function xmldb_local_ikt_review_upgrade($oldversion): bool {
    if ($oldversion < 2026052501) {
        local_ikt_review_ensure_logstore_index();

        upgrade_plugin_savepoint(true, 2026052501, 'local', 'ikt_review');
    }

    return true;
}
