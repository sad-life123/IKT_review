<?php
// This file is part of Moodle - http://moodle.org/

namespace local_ikt_review\task;

defined('MOODLE_INTERNAL') || die();

class do_everything extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('task_do_everything', 'local_ikt_review');
    }

    public function execute(): void {
        // Task logic goes here.
    }
}
