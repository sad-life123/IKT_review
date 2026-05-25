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
        $records = $this->get_snap_records($runid, 'courseid, view_count, unique_view_count, gr_count, t_count, student_count');
        $results = [];

        foreach ($records as $record) {
            $viewcount = (int)$record->view_count;
            $uniqueviewcount = (int)$record->unique_view_count;
            $elementscount = (int)$record->gr_count + (int)$record->t_count;
            $studentcount = (int)$record->student_count;
            $denominator = $studentcount * $elementscount;
            $value = $denominator > 0 ? $viewcount / $denominator : 0;
            $uniquevalue = $denominator > 0 ? $uniqueviewcount / $denominator : 0;

            $results[$record->courseid] = [
                'courseid' => $record->courseid,
                'view_count' => $viewcount,
                'unique_view_count' => $uniqueviewcount,
                'elements_count' => $elementscount,
                'student_count' => $studentcount,
                'at' => $value,
                'unique_at' => $uniquevalue,
            ];
        }

        return $results;
    }

    protected function get_value_payload(array $data): array {
        return [
            'value' => (float)$data['at'],
            'unique_value' => (float)$data['unique_at'],
        ];
    }
}
