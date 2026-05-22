<?php
// This file is part of Moodle - http://moodle.org/

namespace local_ikt_review\metric;

defined('MOODLE_INTERNAL') || die();

class attendance extends base_metric {
    public function get_metric_key(): string {
        return 'attendance';
    }

    public function get_name(): string {
        return 'Attendance';
    }

    public function calculate(?int $runid = null): array {
        $records = $this->get_snap_records($runid, 'courseid, view_count, gr_count, t_count');
        $results = [];

        foreach ($records as $record) {
            $viewcount = (int)$record->view_count;
            $elementscount = (int)$record->gr_count + (int)$record->t_count;
            $value = $elementscount > 0 ? $viewcount / $elementscount : 0;

            $results[$record->courseid] = [
                'courseid' => $record->courseid,
                'view_count' => $viewcount,
                'elements_count' => $elementscount,
                'at' => $value,
            ];
        }

        return $results;
    }

    protected function get_value_payload(array $data): array {
        return $this->single_value((float)$data['at']);
    }
}
