<?php
// This file is part of Moodle - http://moodle.org/

namespace local_ikt_review\metric;

defined('MOODLE_INTERNAL') || die();

class content extends base_metric {
    private const LECTURES_HOURS = 36;
    private const PZ_HOURS = 18;
    private const LR_HOURS = 18;

    private const WEIGHT_T = 0.3;
    private const WEIGHT_GR = 0.7;

    public function get_metric_key(): string {
        return 'content';
    }

    public function get_name(): string {
        return 'Content';
    }

    public function calculate(?int $runid = null): array {
        $records = $this->get_snap_records($runid, 'courseid, fullname, t_count, gr_count');
        $results = [];
        $lectures = self::LECTURES_HOURS / 2;
        $practice = self::PZ_HOURS / 2 + self::LR_HOURS / 4;

        foreach ($records as $record) {
            $t = (int)$record->t_count;
            $gr = (int)$record->gr_count;
            $value = self::WEIGHT_T * $t / $lectures + self::WEIGHT_GR * $gr / $practice;

            $results[$record->courseid] = [
                'courseid' => $record->courseid,
                'fullname' => $record->fullname,
                't' => $t,
                'gr' => $gr,
                'content' => $value,
            ];
        }

        return $results;
    }

    protected function get_value_payload(array $data): array {
        return $this->single_value((float)$data['content']);
    }
}
