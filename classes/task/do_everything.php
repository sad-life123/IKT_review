<?php
// This file is part of Moodle - http://moodle.org/
// connected to the scheduled task defined in db/tasks.php
// can set period. FUTURE: API timing.

namespace local_ikt_review\task;

defined('MOODLE_INTERNAL') || die();

class do_everything extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('task_do_everything', 'local_ikt_review');
    }

    public function execute(): void {
        $periodto = usergetmidnight(time()) + DAYSECS - 1; // end of today
        $periodfrom = usergetmidnight(time() - 180 * DAYSECS); // 180 days ago

        (new \local_ikt_review\manager())->queue_run($periodfrom, $periodto);
    }
}
