<?php
// This file is part of Moodle - http://moodle.org/

namespace local_ikt_review\metric;

defined('MOODLE_INTERNAL') || die();

class done extends base_metric {
    public function get_metric_key(): string {
        return 'done';
    }

    public function get_name(): string {
        return 'Done';
    }

    public function calculate(?int $runid = null): array {
        $records = $this->get_snap_records($runid, 'courseid, answer_count, gr_count, student_count');
        $results = [];

        foreach ($records as $record) {
            $answercount = (int)$record->answer_count;
            $grcount = (int)$record->gr_count;
            $studentcount = (int)$record->student_count;
            $denominator = $grcount * $studentcount;
            $value = $denominator > 0 ? $answercount / $denominator : 0;

            $results[$record->courseid] = [
                'courseid' => $record->courseid,
                'total_ans' => $answercount,
                'gr_count' => $grcount,
                'student_count' => $studentcount,
                'done' => $value,
            ];
        }

        return $results;
    }

    protected function get_value_payload(array $data): array {
        return ['value' => (float)$data['done']];
    }
}
