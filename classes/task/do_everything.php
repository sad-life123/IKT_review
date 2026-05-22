<?php
// This file is part of Moodle - http://moodle.org/

namespace local_ikt_review\task;

defined('MOODLE_INTERNAL') || die();

class review_task extends \core\task\scheduled_task {
    public function get_name(): string {
        return 'IKT review complex task';
    }

    public function execute(): void {
        // Task logic goes here.
    }
}
