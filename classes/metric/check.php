<?php
// This file is part of Moodle - http://moodle.org/

namespace local_ikt_review\metric;

defined('MOODLE_INTERNAL') || die();

class check extends base_metric {
    public function get_metric_key(): string {
        return 'check';
    }

    public function get_name(): string {
        return 'Check';
    }

    public function calculate(?int $runid = null): array {
        $records = $this->get_snap_records($runid, 'courseid, assign_count, submit_count, assign_graded_count, student_count');
        $results = [];

        foreach ($records as $record) {
            $assigncount = (int)$record->assign_count;
            $submitcount = (int)$record->submit_count;
            $gradedcount = (int)$record->assign_graded_count;
            $studentcount = (int)$record->student_count;
            $possibledenominator = $assigncount * $studentcount;
            $value = $submitcount > 0 ? $gradedcount / $submitcount : 0;
            $possiblevalue = $possibledenominator > 0 ? $gradedcount / $possibledenominator : 0;

            $results[$record->courseid] = [
                'courseid' => $record->courseid,
                'assign_count' => $assigncount,
                'submit_count' => $submitcount,
                'assign_graded_count' => $gradedcount,
                'student_count' => $studentcount,
                'check' => $value,
                'possible_check' => $possiblevalue,
            ];
        }

        return $results;
    }

    protected function get_value_payload(array $data): array {
        return [
            'value' => (float)$data['check'],
            'possible_value' => (float)$data['possible_check'],
        ];
    }
}
