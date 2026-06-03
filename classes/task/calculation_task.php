<?php
// This file is part of Moodle - http://moodle.org/

namespace local_ikt_review\task;

defined('MOODLE_INTERNAL') || die();

class calculation_task extends \core\task\adhoc_task {
    public function execute(): void {
        $data = $this->get_custom_data();
        $runid = (int)($data->runid ?? 0);
        $courseids = isset($data->courseids) ? (array)$data->courseids : [];

        if ($runid <= 0) {
            throw new \coding_exception('Missing IKT review run id');
        }

        (new \local_ikt_review\manager())->execute_queued_run($runid, $courseids);
    }
}
