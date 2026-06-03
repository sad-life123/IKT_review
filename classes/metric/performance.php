<?php
// This file is part of Moodle - http://moodle.org/

namespace local_ikt_review\metric;

defined('MOODLE_INTERNAL') || die();

class performance extends base_metric {
    public function get_metric_key(): string {
        return 'performance';
    }

    public function get_name(): string {
        return 'Performance';
    }

    public function calculate(?int $runid = null): array {
        $records = $this->get_snap_records($runid, 'courseid, avg_grade');
        $results = [];

        foreach ($records as $record) {
            $results[$record->courseid] = [
                'courseid' => $record->courseid,
                'avg_grade' => $record->avg_grade === null ? 0 : (float)$record->avg_grade,
            ];
        }

        return $results;
    }

    protected function get_value_payload(array $data): array {
        return ['value' => (float)$data['avg_grade']];
    }
}
